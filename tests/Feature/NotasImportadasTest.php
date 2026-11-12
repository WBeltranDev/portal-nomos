<?php

namespace Tests\Feature;

use App\Services\LectorXlsx;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

/**
 * Contingencia: notas finales de un semestre cargadas desde Excel. Alimentan la
 * consolidación (matriz e informe anual); la calificación del flujo normal prevalece.
 */
class NotasImportadasTest extends TestCase
{
    private string $s;
    private int $anio = 2098;
    private array $v = [];
    private array $archivos = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requiere una base MySQL con el esquema institucional aplicado.');
        }

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
        DB::beginTransaction();

        $this->s = 'N' . random_int(10000, 99999);
        $idUsuario = DB::table('usuario')->insertGetId(['username' => "tmp.notas.{$this->s}", 'password' => 'x', 'rol' => 'ADMINISTRADOR', 'activo' => 1]);
        $this->v['jefe'] = $this->persona('jefe', 'RENDIMIENTO_LABORAL');
        foreach (['ana', 'beto', 'cata'] as $p) {
            $this->v[$p] = $this->persona($p, 'RENDIMIENTO_LABORAL');
            DB::table('evaluador_asignacion')->insert(['id_vinc_evaluador' => $this->v['jefe'], 'id_vinc_evaluado' => $this->v[$p], 'fecha_asignacion' => now()]);
        }
        session(['usuario_autenticado' => ['id_usuario' => $idUsuario, 'rol_activo' => 'admin', 'roles' => ['admin']]]);
    }

    protected function tearDown(): void
    {
        foreach ($this->archivos as $archivo) {
            @unlink($archivo);
        }
        DB::rollBack();
        parent::tearDown();
    }

    private function persona(string $clave, string $sistema): int
    {
        $idUsuario = DB::table('usuario')->insertGetId(['username' => "tmp.notas.{$clave}.{$this->s}", 'password' => 'x', 'rol' => 'EVALUADO', 'activo' => 1]);
        $idFuncionario = DB::table('funcionario')->insertGetId([
            'id_usuario' => $idUsuario, 'tipo_documento' => 'CEDULA_CIUDADANIA', 'numero_doc' => $this->cedula($clave), 'nombres' => $clave, 'apellidos' => $this->s,
        ]);

        return DB::table('vinculacion')->insertGetId([
            'id_funcionario' => $idFuncionario, 'cargo' => 'PROFESIONAL', 'codigo_cargo' => 1, 'grado_cargo' => 1, 'nivel_jerarquico' => 'PROFESIONAL', 'area' => "AREA {$this->s}",
            'tipo_vinculacion' => 'PROVISIONALIDAD', 'sistema_evaluacion' => $sistema, 'es_evaluador' => $clave === 'jefe' ? 1 : 0,
            'fecha_ingreso' => '2090-01-01', 'activa' => 1,
        ]);
    }

    private function cedula(string $clave): string
    {
        return (string) (8 * 10 ** 9 + (crc32($clave . $this->s) % 10 ** 8));
    }

    /** Excel con filas [cédula, sistema, nota, fecha?]. */
    private function excel(array $filas): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), 'notas') . '.xlsx';
        $this->archivos[] = $ruta;
        $w = new Writer();
        $w->openToFile($ruta);
        $w->getCurrentSheet()->setName('INSTRUCCIONES');
        $w->addRow(Row::fromValues(['Instrucciones']));
        $w->addNewSheetAndMakeItCurrent()->setName('NOTAS');
        $w->addRow(Row::fromValues(['No IDENTIFICACION', 'NOMBRE FUNCIONARIO', 'CARGO', 'DEPENDENCIA', 'SISTEMA (RL / AG)', 'NOTA FINAL (1.0 a 5.0)', 'FECHA CALIFICACION (OPCIONAL)', 'OBSERVACION (OPCIONAL)']));
        foreach ($filas as $f) {
            $w->addRow(Row::fromValues([(int) $f[0], '', '', '', $f[1] ?? '', $f[2] ?? '', $f[3] ?? '', '']));
        }
        $w->close();

        return new UploadedFile($ruta, 'notas.xlsx', null, null, true);
    }

    private function analizar(array $filas)
    {
        return $this->post('/admin/notas-importadas/analizar', ['archivo' => $this->excel($filas), 'anio' => $this->anio, 'semestre' => 1]);
    }

    private function aplicar(array $filas)
    {
        $token = $this->analizar($filas)->json('token');

        return $this->postJson('/admin/notas-importadas/aplicar', ['token' => $token, 'anio' => $this->anio, 'semestre' => 1, 'archivo' => 'notas.xlsx']);
    }

    private function nota(string $p, int $semestre = 1): ?float
    {
        return obtenerNotaSemestreConsolidada($this->v[$p], $this->anio, 'RENDIMIENTO_LABORAL', $semestre)['nota'] ?? null;
    }

    public function test_el_formato_trae_la_planta_evaluable_con_la_nota_vacia(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'fmt') . '.xlsx';
        $this->archivos[] = $ruta;
        $this->get("/admin/notas-importadas/plantilla?anio={$this->anio}&semestre=1")->assertOk();

        app(\App\Services\NotasImportadas::class)->plantilla($ruta, $this->anio, 1);
        $hojas = (new LectorXlsx())->leer($ruta);
        $this->assertSame(['INSTRUCCIONES', 'NOTAS'], array_keys($hojas));
        $cedulas = collect($hojas['NOTAS'])->map(fn ($f) => (string) ($f[1] ?? ''))->all();
        $this->assertContains($this->cedula('ana'), $cedulas);
        $this->assertNotContains($this->cedula('jefe'), $cedulas, 'Quien no es evaluado no va en el formato.');
    }

    public function test_la_revision_marca_errores_y_no_guarda_nada(): void
    {
        $r = $this->analizar([
            [$this->cedula('ana'), 'RL', '4,5'],
            [$this->cedula('beto'), '', '6.2'],
            ['1', '', '4.0'],
            [$this->cedula('cata'), '', 'abc'],
            [$this->cedula('ana'), 'RL', '3.0'],
        ])->assertOk();

        $estados = collect($r->json('filas'))->pluck('estado')->all();
        $this->assertSame(['LISTA', 'ERROR', 'ERROR', 'ERROR', 'ERROR'], $estados);
        $this->assertSame(4.5, (float) $r->json('filas.0.nota'));
        $this->assertSame(0, DB::table('nota_semestre_importada')->count());
    }

    public function test_el_lote_se_guarda_y_alimenta_la_consolidacion_con_el_otro_semestre(): void
    {
        $this->aplicar([[$this->cedula('ana'), '', '4.0'], [$this->cedula('beto'), 'RL', '3,5', '2026-06-30']])->assertOk()->assertJsonPath('success', true);

        $this->assertSame(4.0, $this->nota('ana'));
        $this->assertSame(3.5, $this->nota('beto'));
        $this->assertNull($this->nota('cata'));
        $this->assertSame('IMPORTADA', obtenerNotaSemestreConsolidada($this->v['ana'], $this->anio, 'RENDIMIENTO_LABORAL', 1)['tipo']);
        $this->assertSame('2026-06-30', DB::table('nota_semestre_importada')->where('id_vinculacion', $this->v['beto'])->value('fecha_calificacion'));
    }

    public function test_la_nota_del_flujo_normal_prevalece_y_la_fila_se_omite(): void
    {
        $idPeriodo = DB::table('periodo')->insertGetId([
            'id_usuario_apertura' => session('usuario_autenticado.id_usuario'), 'sistema' => 'RENDIMIENTO_LABORAL', 'anio' => $this->anio, 'semestre' => 1,
            'fecha_inicio' => "{$this->anio}-01-01", 'fecha_fin' => "{$this->anio}-06-30", 'estado' => 'CERRADO',
        ]);
        DB::table('evaluacion')->insert([
            'id_periodo' => $idPeriodo, 'id_vinc_evaluado' => $this->v['ana'], 'id_vinc_evaluador' => $this->v['jefe'], 'tipo_evaluacion' => 'SEMESTRE_1',
            'fase_actual' => 5, 'concertacion_firmada' => 1, 'estado' => 'CALIFICADA', 'es_traslado' => 0, 'calificacion_final' => 4.2, 'categoria_final' => 'BUENO',
        ]);

        $r = $this->analizar([[$this->cedula('ana'), '', '2.0'], [$this->cedula('beto'), '', '3.0']])->assertOk();
        $this->assertSame('OMITIDA', $r->json('filas.0.estado'));
        $this->assertSame('LISTA', $r->json('filas.1.estado'));
        $this->assertSame(1, $r->json('resumen.omitidas'));
    }

    public function test_no_se_puede_recargar_una_nota_hasta_revertir_el_lote(): void
    {
        $this->aplicar([[$this->cedula('ana'), '', '4.0']])->assertOk();
        $idLote = (int) DB::table('nota_importada_lote')->value('id_lote');

        $this->assertSame('OMITIDA', $this->analizar([[$this->cedula('ana'), '', '3.0']])->json('filas.0.estado'));

        $this->postJson("/admin/notas-importadas/lote/{$idLote}/revertir")->assertOk();
        $this->assertNull($this->nota('ana'));
        $this->assertSame('LISTA', $this->analizar([[$this->cedula('ana'), '', '3.0']])->json('filas.0.estado'));
        $this->postJson("/admin/notas-importadas/lote/{$idLote}/revertir")->assertStatus(422);
    }

    public function test_solo_el_administrador_carga_notas(): void
    {
        session(['usuario_autenticado' => ['id_usuario' => 1, 'rol_activo' => 'evaluador', 'roles' => ['evaluador']]]);
        $this->get("/admin/notas-importadas/plantilla?anio={$this->anio}&semestre=1")->assertForbidden();
        $this->postJson('/admin/notas-importadas/aplicar', [])->assertForbidden();
    }
}
