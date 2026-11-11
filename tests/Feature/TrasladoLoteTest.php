<?php

namespace Tests\Feature;

use App\Services\LectorXlsx;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

/**
 * Traslados en bloque (rotación): un lote agrupa traslados normales que se
 * programan y se confirman todos juntos, con la plaza destino ocupada por
 * alguien que también sale en el lote.
 */
class TrasladoLoteTest extends TestCase
{
    private string $s;
    private int $idUsuario;
    private int $idPeriodo;
    private array $dep = [];
    private array $jefe = [];
    private array $aux = [];
    private array $plazaAux = [];
    private array $archivos = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requiere una base MySQL con el esquema institucional aplicado.');
        }

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
        DB::beginTransaction();

        $this->s = 'L' . random_int(10000, 99999);
        $this->idUsuario = DB::table('usuario')->insertGetId(['username' => "tmp.lote.{$this->s}", 'password' => 'x', 'rol' => 'ADMINISTRADOR', 'activo' => 1]);
        DB::table('cargo')->insertOrIgnore(['nombre' => 'AUXILIAR ADMINISTRATIVO', 'codigo_cargo' => 407, 'activo' => 1]);
        DB::table('cargo')->insertOrIgnore(['nombre' => 'JEFE DE OFICINA', 'codigo_cargo' => 6, 'activo' => 1]);
        $idCargoAux = (int) DB::table('cargo')->where('nombre', 'AUXILIAR ADMINISTRATIVO')->value('id_cargo');
        $idCargoJefe = (int) DB::table('cargo')->where('nombre', 'JEFE DE OFICINA')->value('id_cargo');

        $hoy = Carbon::today();
        $this->idPeriodo = DB::table('periodo')->insertGetId([
            'id_usuario_apertura' => $this->idUsuario, 'sistema' => 'RENDIMIENTO_LABORAL', 'anio' => 2099, 'semestre' => 1,
            'fecha_inicio' => $hoy->copy()->subDays(30)->toDateString(), 'fecha_fin' => $hoy->copy()->addDays(120)->toDateString(), 'estado' => 'ABIERTO',
        ]);

        // Tres dependencias, cada una con su jefe y un auxiliar; los auxiliares rotan entre las tres.
        foreach (['A', 'B', 'C'] as $k) {
            $this->dep[$k] = DB::table('dependencia')->insertGetId(['nombre' => "OFICINA {$k} {$this->s}", 'activa' => 1]);
            $plazaJefe = $this->plaza("J{$k}", $this->dep[$k], $idCargoJefe, 6, null);
            $this->jefe[$k] = $this->persona("J{$k}", true, "OFICINA {$k} {$this->s}", 'JEFE DE OFICINA', $plazaJefe);
            $this->plazaAux[$k] = $this->plaza("X{$k}", $this->dep[$k], $idCargoAux, 407, $plazaJefe);
            $this->aux[$k] = $this->persona("X{$k}", false, "OFICINA {$k} {$this->s}", 'AUXILIAR ADMINISTRATIVO', $this->plazaAux[$k]);
            DB::table('evaluador_asignacion')->insert(['id_vinc_evaluador' => $this->jefe[$k], 'id_vinc_evaluado' => $this->aux[$k], 'fecha_asignacion' => now()]);
        }

        session(['usuario_autenticado' => ['id_usuario' => $this->idUsuario, 'rol_activo' => 'admin', 'roles' => ['admin']]]);
    }

    protected function tearDown(): void
    {
        foreach ($this->archivos as $archivo) {
            @unlink($archivo);
        }
        DB::rollBack();
        parent::tearDown();
    }

    private function plaza(string $clave, int $idDependencia, int $idCargo, int $codigo, ?int $superior): int
    {
        return DB::table('puesto')->insertGetId([
            'codigo_plaza' => "T-{$this->s}-{$clave}", 'id_dependencia' => $idDependencia, 'id_cargo' => $idCargo, 'codigo_cargo' => $codigo, 'grado' => 1,
            'nivel_jerarquico' => 'ASISTENCIAL', 'sistema_evaluacion' => 'RENDIMIENTO_LABORAL', 'id_puesto_superior' => $superior,
            'estado' => 'VALIDADA', 'requiere_revision' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function persona(string $clave, bool $esEvaluador, string $area, string $cargo, int $idPuesto): int
    {
        $idUsuario = DB::table('usuario')->insertGetId(['username' => "tmp.lote.{$clave}.{$this->s}", 'password' => 'x', 'rol' => 'EVALUADO', 'activo' => 1]);
        $idFuncionario = DB::table('funcionario')->insertGetId([
            'id_usuario' => $idUsuario, 'tipo_documento' => 'CEDULA_CIUDADANIA', 'numero_doc' => $this->cedula($clave), 'nombres' => $clave, 'apellidos' => $this->s,
        ]);
        $idVinc = DB::table('vinculacion')->insertGetId([
            'id_funcionario' => $idFuncionario, 'cargo' => $cargo, 'codigo_cargo' => 1, 'grado_cargo' => 1, 'nivel_jerarquico' => 'ASISTENCIAL', 'area' => $area,
            'tipo_vinculacion' => 'PROVISIONALIDAD', 'sistema_evaluacion' => 'RENDIMIENTO_LABORAL', 'es_evaluador' => $esEvaluador ? 1 : 0,
            'fecha_ingreso' => '2090-01-01', 'activa' => 1,
        ]);
        DB::table('puesto_ocupacion')->insert([
            'id_puesto' => $idPuesto, 'id_vinculacion' => $idVinc, 'tipo_ocupacion' => 'PROVISIONALIDAD', 'vigente_desde' => '2090-01-01', 'estado' => 'VIGENTE',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $idVinc;
    }

    private function cedula(string $clave): string
    {
        return (string) (9 * 10 ** 9 + (crc32($clave . $this->s) % 10 ** 8));
    }

    private function codigo(string $clave): string
    {
        return "T-{$this->s}-{$clave}";
    }

    /** Excel de rotación con las filas indicadas: [cédula, plaza destino, cédula evaluador?]. */
    private function excel(array $filas): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), 'lote') . '.xlsx';
        $this->archivos[] = $ruta;
        $w = new Writer();
        $w->openToFile($ruta);
        $w->getCurrentSheet()->setName('INSTRUCCIONES');
        $w->addRow(Row::fromValues(['Instrucciones']));
        $w->addNewSheetAndMakeItCurrent()->setName('ROTACION');
        $w->addRow(Row::fromValues(['No IDENTIFICACION', 'NOMBRE FUNCIONARIO', 'DEPENDENCIA ACTUAL', 'PLAZA ACTUAL', 'PLAZA DESTINO (CODIGO)', 'CEDULA NUEVO EVALUADOR (OPCIONAL)', 'FECHA TRASLADO (OPCIONAL)', 'OBSERVACION']));
        foreach ($filas as $fila) {
            [$cedula, $destino, $evaluador] = $fila;
            $w->addRow(Row::fromValues([(int) $cedula, '', '', '', $destino, $evaluador !== null ? (int) $evaluador : '', $fila[3] ?? '', '']));
        }
        $w->close();

        return new UploadedFile($ruta, 'rotacion.xlsx', null, null, true);
    }

    /** Rotación en ciclo: A→B, B→C, C→A (todas las plazas destino están ocupadas por quien también sale). */
    private function cicloCompleto(): array
    {
        return [[$this->cedula('XA'), $this->codigo('XB'), null], [$this->cedula('XB'), $this->codigo('XC'), null], [$this->cedula('XC'), $this->codigo('XA'), null]];
    }

    private function analizar(array $filas)
    {
        return $this->post('/admin/traslados/lote/analizar', ['archivo' => $this->excel($filas)], ['Accept' => 'application/json']);
    }

    private function programar(array $filas, ?string $fecha = null, bool $sinFechaDeLote = false)
    {
        $token = $this->analizar($filas)->assertOk()->json('token');

        return $this->postJson('/admin/traslados/lote/programar', [
            'token' => $token, 'archivo' => 'rotacion.xlsx', 'fecha_traslado' => $sinFechaDeLote ? null : ($fecha ?? Carbon::today()->toDateString()), 'descripcion' => 'Rotación anual',
        ]);
    }

    private function plazaDe(int $idVinc): ?int
    {
        return DB::table('puesto_ocupacion')->where('id_vinculacion', $idVinc)->where('estado', 'VIGENTE')->value('id_puesto');
    }

    private function evaluadorDe(int $idVinc): int
    {
        return (int) DB::table('evaluador_asignacion')->where('id_vinc_evaluado', $idVinc)->value('id_vinc_evaluador');
    }

    public function test_el_formato_se_descarga_con_las_personas_del_cargo_y_las_plazas(): void
    {
        $respuesta = $this->get('/admin/traslados/lote/plantilla?cargo=' . urlencode('AUXILIAR ADMINISTRATIVO'));
        $respuesta->assertOk();
        $ruta = tempnam(sys_get_temp_dir(), 'plantilla') . '.xlsx';
        $this->archivos[] = $ruta;
        file_put_contents($ruta, $respuesta->baseResponse->getFile()->getContent());

        $hojas = (new LectorXlsx())->leer($ruta);
        $this->assertSame(['INSTRUCCIONES', 'ROTACION', 'PLAZAS'], array_keys($hojas));
        $this->assertSame('PLAZA DESTINO (CODIGO)', $hojas['ROTACION'][1][5]);
        $this->assertSame('FECHA TRASLADO (OPCIONAL)', $hojas['ROTACION'][1][7]);
        $cedulas = collect($hojas['ROTACION'])->skip(1)->map(fn ($f) => (string) (int) $f[1])->all();
        $this->assertContains($this->cedula('XA'), $cedulas);
        $plazas = collect($hojas['PLAZAS'])->skip(1)->keyBy(fn ($f) => $f[1]);
        $this->assertSame("JA {$this->s}", $plazas[$this->codigo('XA')][6], 'El evaluador por defecto es el ocupante de la plaza superior.');
    }

    public function test_la_revision_acepta_una_rotacion_en_ciclo_y_propone_el_evaluador_de_la_plaza_superior(): void
    {
        $analisis = $this->analizar($this->cicloCompleto())->assertOk()->json();

        $this->assertSame([3, 0, 3], [$analisis['resumen']['total'], $analisis['resumen']['con_error'], $analisis['resumen']['listas']]);
        $filaA = collect($analisis['filas'])->firstWhere('cedula', $this->cedula('XA'));
        $this->assertSame($this->jefe['B'], $filaA['id_vinc_evaluador_nuevo'], 'XA pasa a la oficina B: su evaluador es el jefe de B.');
        $this->assertSame('plaza superior', $filaA['origen_evaluador']);
        $this->assertSame("OFICINA B {$this->s}", $filaA['dependencia_destino']);
        // La revisión no escribe nada.
        $this->assertSame(0, DB::table('traslado_lote')->count());
    }

    public function test_la_revision_marca_cada_error_por_fila(): void
    {
        $filas = [
            ['1234', $this->codigo('XB'), null],                          // cédula inexistente
            [$this->cedula('XA'), 'NO-EXISTE', null],                      // plaza inexistente
            [$this->cedula('XB'), $this->codigo('JA'), null],              // la plaza la ocupa un jefe que no sale en el lote
            [$this->cedula('XB'), $this->codigo('XA'), null],              // persona repetida
            [$this->cedula('XC'), $this->codigo('XA'), $this->cedula('XC')], // evaluador que no es evaluador
        ];

        $analisis = $this->analizar($filas)->assertOk()->json();
        $errores = collect($analisis['filas'])->map(fn ($f) => implode(' | ', $f['errores']));

        $this->assertStringContainsString('No hay un funcionario activo con la cédula 1234', $errores[0]);
        $this->assertStringContainsString('NO-EXISTE', $errores[1]);
        $this->assertStringContainsString('que no sale en este lote', $errores[2]);
        $this->assertStringContainsString('repetida', $errores[3]);
        $this->assertStringContainsString('no está marcado como evaluador', $errores[4]);
        $this->assertSame(5, $analisis['resumen']['con_error']);
    }

    public function test_dos_personas_no_pueden_ir_a_la_misma_plaza(): void
    {
        $analisis = $this->analizar([[$this->cedula('XA'), $this->codigo('XC'), null], [$this->cedula('XB'), $this->codigo('XC'), null], [$this->cedula('XC'), $this->codigo('XB'), null]])->assertOk()->json();

        $this->assertStringContainsString('recibe a más de una persona', implode(' ', $analisis['filas'][1]['errores']));
    }

    public function test_sin_evaluador_en_la_plaza_superior_hay_que_indicarlo(): void
    {
        DB::table('puesto_ocupacion')->where('id_puesto', DB::table('puesto')->where('codigo_plaza', $this->codigo('JB'))->value('id_puesto'))->update(['estado' => 'CERRADA']);

        $analisis = $this->analizar($this->cicloCompleto())->assertOk()->json();
        $filaA = collect($analisis['filas'])->firstWhere('cedula', $this->cedula('XA'));
        $this->assertStringContainsString('no tiene un ocupante evaluador', implode(' ', $filaA['errores']));

        // Indicando la cédula del evaluador se resuelve.
        $filas = $this->cicloCompleto();
        $filas[0][2] = $this->cedula('JC');
        $analisis = $this->analizar($filas)->assertOk()->json();
        $this->assertSame([], collect($analisis['filas'])->firstWhere('cedula', $this->cedula('XA'))['errores']);
    }

    public function test_el_lote_se_programa_y_se_confirma_moviendo_a_todos_a_la_vez(): void
    {
        $respuesta = $this->programar($this->cicloCompleto())->assertOk();
        $idLote = $respuesta->json('id_lote');

        // Programar no mueve a nadie.
        $this->assertSame($this->plazaAux['A'], $this->plazaDe($this->aux['A']));
        $this->assertSame(3, DB::table('traslado')->where('id_lote', $idLote)->where('estado', 'PROGRAMADO')->count());
        $this->assertSame('PROGRAMADO', DB::table('traslado_lote')->where('id_lote', $idLote)->value('estado'));

        $this->postJson("/admin/traslados/lote/{$idLote}/confirmar")->assertOk();

        $this->assertSame($this->plazaAux['B'], $this->plazaDe($this->aux['A']));
        $this->assertSame($this->plazaAux['C'], $this->plazaDe($this->aux['B']));
        $this->assertSame($this->plazaAux['A'], $this->plazaDe($this->aux['C']));
        $this->assertSame($this->jefe['B'], $this->evaluadorDe($this->aux['A']));
        $this->assertSame($this->jefe['C'], $this->evaluadorDe($this->aux['B']));
        $this->assertSame($this->jefe['A'], $this->evaluadorDe($this->aux['C']));
        $this->assertSame("OFICINA B {$this->s}", DB::table('vinculacion')->where('id_vinculacion', $this->aux['A'])->value('area'));

        // Queda el registro: lote confirmado y un traslado confirmado por persona, ligado al lote.
        $this->assertSame('CONFIRMADO', DB::table('traslado_lote')->where('id_lote', $idLote)->value('estado'));
        $traslados = DB::table('traslado')->where('id_lote', $idLote)->get();
        $this->assertCount(3, $traslados);
        $this->assertTrue($traslados->every(fn ($t) => $t->estado === 'CONFIRMADO' && $t->tipo === 'CAMBIO'));
        $this->assertStringContainsString("lote #{$idLote}", $traslados[0]->motivo);
        // Cada persona abre su evaluación en el cargo nuevo, como en un traslado individual.
        $this->assertSame(3, DB::table('evaluacion')->whereIn('id_vinc_evaluado', array_values($this->aux))->where('tipo_evaluacion', 'PARCIAL')->count());
    }

    public function test_el_lote_no_se_confirma_mientras_un_tramo_siga_sin_calificar_y_no_mueve_a_nadie(): void
    {
        // XA tiene una evaluación abierta en el periodo con su jefe actual.
        $idEvaluacion = DB::table('evaluacion')->insertGetId([
            'id_periodo' => $this->idPeriodo, 'id_vinc_evaluado' => $this->aux['A'], 'id_vinc_evaluador' => $this->jefe['A'],
            'tipo_evaluacion' => 'SEMESTRE_1', 'fase_actual' => 4, 'concertacion_firmada' => 1, 'estado' => 'EN_PROCESO',
        ]);
        $idLote = $this->programar($this->cicloCompleto(), Carbon::today()->addDays(3)->toDateString())->assertOk()->json('id_lote');

        $respuesta = $this->postJson("/admin/traslados/lote/{$idLote}/confirmar")->assertStatus(422);
        $this->assertStringContainsString('Falta calificar el tramo de origen', $respuesta->json('message'));
        $this->assertStringContainsString('XA', $respuesta->json('message'));

        // Nadie se movió ni el lote cambió de estado.
        $this->assertSame($this->plazaAux['A'], $this->plazaDe($this->aux['A']));
        $this->assertSame($this->plazaAux['B'], $this->plazaDe($this->aux['B']));
        $this->assertSame('PROGRAMADO', DB::table('traslado_lote')->where('id_lote', $idLote)->value('estado'));

        // Calificado el tramo y vencido el plazo de recursos, el lote se confirma completo.
        DB::table('compromiso')->insert(['id_evaluacion' => $idEvaluacion, 'numero_orden' => 1, 'descripcion' => 'C', 'porcentaje_peso' => 100, 'calificacion_definitiva' => 4.0]);
        DB::table('evaluacion')->where('id_evaluacion', $idEvaluacion)->update(['estado' => 'CALIFICADA', 'fase_actual' => 5]);
        DB::table('firma')->insert(['id_evaluacion' => $idEvaluacion, 'tipo_firma' => 'NOTIFICACION_EVALUADO', 'id_vinc_firmante' => $this->aux['A'], 'fecha_firma' => Carbon::now()->subDays(12)]);

        $this->postJson("/admin/traslados/lote/{$idLote}/confirmar")->assertOk();
        $this->assertSame($this->plazaAux['B'], $this->plazaDe($this->aux['A']));
    }

    public function test_el_plazo_de_recursos_tambien_frena_el_lote(): void
    {
        $idEvaluacion = DB::table('evaluacion')->insertGetId([
            'id_periodo' => $this->idPeriodo, 'id_vinc_evaluado' => $this->aux['A'], 'id_vinc_evaluador' => $this->jefe['A'],
            'tipo_evaluacion' => 'SEMESTRE_1', 'fase_actual' => 5, 'concertacion_firmada' => 1, 'estado' => 'CALIFICADA',
        ]);
        DB::table('compromiso')->insert(['id_evaluacion' => $idEvaluacion, 'numero_orden' => 1, 'descripcion' => 'C', 'porcentaje_peso' => 100, 'calificacion_definitiva' => 4.0]);
        DB::table('firma')->insert(['id_evaluacion' => $idEvaluacion, 'tipo_firma' => 'NOTIFICACION_EVALUADO', 'id_vinc_firmante' => $this->aux['A'], 'fecha_firma' => Carbon::now()]);
        $idLote = $this->programar($this->cicloCompleto(), Carbon::today()->addDays(3)->toDateString())->assertOk()->json('id_lote');

        $this->postJson("/admin/traslados/lote/{$idLote}/confirmar")->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'plazo para recursos'));

        $lote = collect($this->getJson('/admin/traslados/lotes')->assertOk()->json())->firstWhere('id_lote', $idLote);
        $this->assertSame(2, $lote['listos']);
        $this->assertSame(3, $lote['pendientes']);
    }

    public function test_un_traslado_del_lote_no_se_confirma_ni_se_cancela_por_separado(): void
    {
        $idLote = $this->programar($this->cicloCompleto())->assertOk()->json('id_lote');
        $idTraslado = DB::table('traslado')->where('id_lote', $idLote)->value('id_traslado');

        $this->postJson("/admin/traslados/{$idTraslado}/confirmar")->assertStatus(422)
            ->assertJsonPath('message', "Este traslado hace parte del lote #{$idLote}; confírmalo desde Traslados en bloque.");
        $this->postJson("/admin/traslados/{$idTraslado}/cancelar")->assertStatus(422)
            ->assertJsonPath('message', "Este traslado hace parte del lote #{$idLote}; cancélalo desde Traslados en bloque.");
    }

    public function test_cancelar_el_lote_cancela_todos_sus_traslados_y_no_mueve_a_nadie(): void
    {
        $idLote = $this->programar($this->cicloCompleto())->assertOk()->json('id_lote');

        $this->postJson("/admin/traslados/lote/{$idLote}/cancelar")->assertOk();

        $this->assertSame('CANCELADO', DB::table('traslado_lote')->where('id_lote', $idLote)->value('estado'));
        $this->assertSame(3, DB::table('traslado')->where('id_lote', $idLote)->where('estado', 'CANCELADO')->count());
        $this->assertSame($this->plazaAux['A'], $this->plazaDe($this->aux['A']));
        // Los traslados individuales siguen funcionando: se puede volver a programar a la misma persona.
        $this->assertSame(0, DB::table('traslado')->whereIn('id_vinc_funcionario', array_values($this->aux))->where('estado', 'PROGRAMADO')->count());
    }

    public function test_programar_exige_el_archivo_sin_errores_y_una_fecha_vigente(): void
    {
        $token = $this->analizar([['1234', $this->codigo('XB'), null]])->assertOk()->json('token');
        $this->postJson('/admin/traslados/lote/programar', ['token' => $token, 'fecha_traslado' => Carbon::today()->toDateString()])
            ->assertStatus(422)->assertJsonPath('message', 'El archivo tiene filas con errores: corrígelas y vuelve a subirlo.');
        $this->assertSame(0, DB::table('traslado_lote')->count());

        $token = $this->analizar($this->cicloCompleto())->assertOk()->json('token');
        $this->postJson('/admin/traslados/lote/programar', ['token' => $token, 'fecha_traslado' => Carbon::yesterday()->toDateString()])->assertStatus(422);
    }

    public function test_el_traslado_individual_sigue_funcionando_igual(): void
    {
        $this->post('/admin/traslados', [
            'id_vinc_funcionario' => $this->aux['A'], 'id_vinc_evaluador_nuevo' => $this->jefe['B'], 'fecha_traslado' => Carbon::today()->toDateString(),
            'area_nuevo' => "OFICINA B {$this->s}", 'id_puesto_destino' => DB::table('puesto')->where('codigo_plaza', $this->codigo('XB'))->value('id_puesto'),
        ])->assertSessionHasErrors(['traslados' => 'La plaza destino ya tiene un ocupante vigente: elige una plaza vacante.']);
    }

    public function test_cada_persona_puede_tener_su_propia_fecha_dentro_del_lote(): void
    {
        $propia = Carbon::today()->addDays(5)->toDateString();
        $filas = $this->cicloCompleto();
        $filas[0][3] = $propia;                                   // XA con fecha propia
        $filas[1][3] = Carbon::today()->addDays(7)->format('d/m/Y'); // XB con fecha en formato DD/MM/AAAA

        $analisis = $this->analizar($filas)->assertOk()->json();
        $this->assertSame($propia, collect($analisis['filas'])->firstWhere('cedula', $this->cedula('XA'))['fecha']);
        $this->assertSame(Carbon::today()->addDays(7)->toDateString(), collect($analisis['filas'])->firstWhere('cedula', $this->cedula('XB'))['fecha']);
        $this->assertNull(collect($analisis['filas'])->firstWhere('cedula', $this->cedula('XC'))['fecha']);

        $idLote = $this->programar($filas)->assertOk()->json('id_lote');
        $fechas = DB::table('traslado')->where('id_lote', $idLote)->pluck('fecha_traslado', 'id_vinc_funcionario');
        $this->assertSame($propia, $fechas[$this->aux['A']]);
        $this->assertSame(Carbon::today()->addDays(7)->toDateString(), $fechas[$this->aux['B']]);
        $this->assertSame(Carbon::today()->toDateString(), $fechas[$this->aux['C']], 'Sin fecha propia usa la del lote.');
    }

    public function test_si_todas_las_filas_traen_fecha_no_hace_falta_la_del_lote(): void
    {
        $filas = $this->cicloCompleto();
        foreach ($filas as $i => $fila) {
            $filas[$i][3] = Carbon::today()->addDays(2 + $i)->toDateString();
        }

        $idLote = $this->programar($filas, null, true)->assertOk()->json('id_lote');
        $this->assertSame(Carbon::today()->addDays(2)->toDateString(), DB::table('traslado_lote')->where('id_lote', $idLote)->value('fecha_traslado'), 'La fecha del lote es la más próxima.');

        // Con filas sin fecha propia y sin fecha de lote se pide la fecha.
        $this->programar($this->cicloCompleto(), null, true)->assertStatus(422)
            ->assertJsonPath('message', 'Indica la fecha del traslado: hay filas sin fecha propia en el archivo.');
    }

    public function test_una_fecha_invalida_o_pasada_es_un_error_de_la_fila(): void
    {
        $filas = $this->cicloCompleto();
        $filas[0][3] = 'mañana';
        $filas[1][3] = Carbon::yesterday()->toDateString();

        $analisis = $this->analizar($filas)->assertOk()->json();

        $this->assertStringContainsString('no es válida', implode(' ', $analisis['filas'][0]['errores']));
        $this->assertStringContainsString('anterior a hoy', implode(' ', $analisis['filas'][1]['errores']));
        $this->assertSame([], $analisis['filas'][2]['errores']);
    }

    public function test_la_fecha_propia_se_conserva_al_confirmar_el_lote(): void
    {
        $filas = $this->cicloCompleto();
        $filas[0][3] = Carbon::today()->addDays(4)->toDateString();
        $idLote = $this->programar($filas)->assertOk()->json('id_lote');

        $this->postJson("/admin/traslados/lote/{$idLote}/confirmar")->assertOk();

        $ocupacion = DB::table('puesto_ocupacion')->where('id_vinculacion', $this->aux['A'])->where('id_puesto', $this->plazaAux['B'])->first();
        $this->assertSame(Carbon::today()->addDays(4)->toDateString(), $ocupacion->vigente_desde);
        $this->assertSame(Carbon::today()->toDateString(), DB::table('puesto_ocupacion')->where('id_vinculacion', $this->aux['B'])->where('id_puesto', $this->plazaAux['C'])->value('vigente_desde'));
    }

    public function test_la_revision_con_fecha_valida_lo_mismo_que_rechazaria_el_traslado_real_y_no_escribe_nada(): void
    {
        // XA ya tiene un traslado confirmado posterior en el periodo: una fecha anterior la rechazaría el traslado real.
        DB::table('traslado')->insert([
            'id_vinc_funcionario' => $this->aux['A'], 'estado' => 'CONFIRMADO', 'tipo' => 'CAMBIO', 'id_periodo' => $this->idPeriodo,
            'id_vinc_evaluador_origen' => $this->jefe['A'], 'id_vinc_evaluador_nuevo' => $this->jefe['A'], 'fecha_traslado' => Carbon::today()->addDays(10)->toDateString(),
        ]);
        $antes = DB::table('traslado')->count();

        // Sin fecha, la revisión no puede simular el traslado y lo advierte.
        $sinFecha = $this->analizar($this->cicloCompleto())->assertOk()->json();
        $this->assertFalse($sinFecha['resumen']['simulado']);
        $this->assertSame(0, $sinFecha['resumen']['con_error']);

        // Con fecha, la revisión ejecuta la validación real y detecta el problema de esa persona.
        $archivo = $this->excel($this->cicloCompleto());
        $conFecha = $this->post('/admin/traslados/lote/analizar', ['archivo' => $archivo, 'fecha_traslado' => Carbon::today()->addDays(2)->toDateString()], ['Accept' => 'application/json'])->assertOk()->json();

        $filaA = collect($conFecha['filas'])->firstWhere('cedula', $this->cedula('XA'));
        $this->assertStringContainsString('anterior a su último traslado', implode(' ', $filaA['errores']));
        $this->assertTrue($conFecha['resumen']['simulado']);
        $this->assertSame(1, $conFecha['resumen']['con_error']);

        // La simulación no dejó nada escrito.
        $this->assertSame($antes, DB::table('traslado')->count());
        $this->assertSame(0, DB::table('traslado_lote')->count());
    }

    public function test_programar_no_permite_un_lote_que_la_revision_completa_rechaza(): void
    {
        DB::table('traslado')->insert([
            'id_vinc_funcionario' => $this->aux['A'], 'estado' => 'CONFIRMADO', 'tipo' => 'CAMBIO', 'id_periodo' => $this->idPeriodo,
            'id_vinc_evaluador_origen' => $this->jefe['A'], 'id_vinc_evaluador_nuevo' => $this->jefe['A'], 'fecha_traslado' => Carbon::today()->addDays(10)->toDateString(),
        ]);

        $this->programar($this->cicloCompleto(), Carbon::today()->addDays(2)->toDateString())
            ->assertStatus(422)->assertJsonPath('message', 'El archivo tiene filas con errores: corrígelas y vuelve a subirlo.');
        $this->assertSame(0, DB::table('traslado_lote')->count());
    }

    public function test_la_pantalla_de_traslados_ofrece_el_formato_y_el_lote(): void
    {
        $this->get('/dashboard')->assertOk()
            ->assertSee('Traslados en bloque (rotación)')
            ->assertSee('Descargar formato (Excel)')
            ->assertSee('id="lote-archivo"', false)
            ->assertSee('id="lotes-admin-lista" class="grid gap-3 max-h-[60vh] overflow-y-auto', false)
            // El histórico tiene su propia barra de desplazamiento.
            ->assertSee('id="traslados-admin-lista" class="grid gap-3 max-h-[72vh] overflow-y-auto', false);

        // El panel de lotes va dentro de la columna del formulario, debajo de Traslados de Funcionarios y antes del histórico.
        $html = $this->get('/dashboard')->getContent();
        $seccion = strpos($html, 'id="section-traslados"');
        $this->assertLessThan(strpos($html, 'Traslados en bloque (rotación)', $seccion), strpos($html, 'Traslados de Funcionarios', $seccion));
        $this->assertLessThan(strpos($html, 'Histórico de traslados', $seccion), strpos($html, 'Traslados en bloque (rotación)', $seccion));
    }

    public function test_solo_talento_humano_usa_los_traslados_en_bloque(): void
    {
        session(['usuario_autenticado' => ['id_usuario' => $this->idUsuario, 'rol_activo' => 'evaluador', 'roles' => ['evaluador']]]);

        $this->get('/admin/traslados/lote/plantilla')->assertForbidden();
        $this->getJson('/admin/traslados/lotes')->assertForbidden();
        $this->postJson('/admin/traslados/lote/1/confirmar')->assertForbidden();
    }
}
