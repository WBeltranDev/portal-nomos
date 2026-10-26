<?php

namespace Tests\Feature;

use App\Services\LectorXlsx;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

/**
 * S14 — Carga de la planta oficial de Talento Humano al catálogo de plazas:
 * ensayo sin escribir, aplicación (el Excel manda, evaluador = jefe, el correo
 * del cargo identifica la plaza, vacantes como plazas sin ocupante,
 * ficticios inactivados), repetición sin duplicar y pendientes para el panel.
 *
 * La prueba trabaja sobre la base configurada en el entorno y se ejecuta dentro
 * de una transacción que se revierte al terminar, de modo que no deja datos.
 */
class CargaPlantaTest extends TestCase
{
    private int $idUsuario;
    private string $s;
    private string $n;
    private string $depA;
    private string $depB;
    private array $v = [];
    private ?string $archivo = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requiere una base MySQL con el esquema institucional aplicado.');
        }

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
        DB::beginTransaction();

        $this->n = (string) random_int(10000, 99999);
        $this->s = 'T' . $this->n;
        $this->idUsuario = DB::table('usuario')->insertGetId(['username' => "tmp.carga.{$this->s}", 'password' => 'x', 'rol' => 'ADMINISTRADOR', 'activo' => 1]);
        $this->depA = "OFICINA A {$this->s}";
        $this->depB = "OFICINA B {$this->s}";
        foreach ([$this->depA, $this->depB] as $dep) {
            DB::table('dependencia')->insert(['nombre' => $dep, 'activa' => 1]);
        }
        foreach (['JEFE DE OFICINA' => 6, 'AUXILIAR ADMINISTRATIVO' => 407] as $cargo => $codigo) {
            DB::table('cargo')->insertOrIgnore(['nombre' => $cargo, 'codigo_cargo' => $codigo, 'activo' => 1]);
        }

        // Jefe A, jefe B y dos auxiliares (hoy: aux1 en A con jefe A; aux2 en B con jefe B).
        $this->v['jefeA'] = $this->vinculacion("71{$this->n}", 'JEFE A', 'JEFE DE OFICINA', 6, 1, $this->depA, null, "jefea.{$this->s}@u.co");
        $this->v['jefeB'] = $this->vinculacion("72{$this->n}", 'JEFE B', 'JEFE DE OFICINA', 6, 1, $this->depB, null, "jefeb.{$this->s}@u.co");
        $this->v['aux1'] = $this->vinculacion("73{$this->n}", 'AUX UNO', 'AUXILIAR ADMINISTRATIVO', 407, 11, $this->depA, $this->v['jefeA'], "auxa.{$this->s}@u.co");
        $this->v['aux2'] = $this->vinculacion("74{$this->n}", 'AUX DOS', 'AUXILIAR ADMINISTRATIVO', 407, 11, $this->depB, $this->v['jefeB'], "auxb.{$this->s}@u.co");
        $this->v['ficticio'] = $this->vinculacion('00000', 'VACANTE', 'JEFE DE OFICINA', 6, 1, $this->depB, null, null);

        session(['usuario_autenticado' => ['id_usuario' => $this->idUsuario, 'rol_activo' => 'admin', 'roles' => ['admin']]]);
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        if ($this->archivo && is_file($this->archivo)) {
            unlink($this->archivo);
        }
        parent::tearDown();
    }

    private function vinculacion(string $doc, string $nombre, string $cargo, int $codigo, int $grado, string $area, ?int $jefe, ?string $correo): int
    {
        $idUsuario = DB::table('usuario')->insertGetId(['username' => "tmp.carga.{$doc}.{$this->s}", 'password' => 'x', 'rol' => 'EVALUADO', 'activo' => 1]);
        $idFuncionario = DB::table('funcionario')->insertGetId([
            'id_usuario' => $idUsuario, 'tipo_documento' => 'CEDULA_CIUDADANIA', 'numero_doc' => $doc,
            'nombres' => $nombre, 'apellidos' => $this->s, 'correo_cargo' => $correo,
        ]);
        $id = DB::table('vinculacion')->insertGetId([
            'id_funcionario' => $idFuncionario, 'cargo' => $cargo, 'codigo_cargo' => $codigo, 'grado_cargo' => $grado,
            'nivel_jerarquico' => $codigo === 6 ? 'DIRECTIVO' : 'ASISTENCIAL', 'area' => $area, 'tipo_vinculacion' => 'PROVISIONALIDAD',
            'sistema_evaluacion' => 'RENDIMIENTO_LABORAL', 'es_evaluador' => $codigo === 6 ? 1 : 0, 'id_vinc_jefe' => $jefe,
            'fecha_ingreso' => '2021-09-01', 'activa' => 1,
        ]);
        if ($jefe) {
            DB::table('evaluador_asignacion')->insert(['id_vinc_evaluador' => $jefe, 'id_vinc_evaluado' => $id, 'fecha_asignacion' => now()]);
        }

        return $id;
    }

    /** Excel de planta: los auxiliares rotan de oficina (y de correo) y hay vacantes. */
    private function excel(array $vacantesExtra = []): string
    {
        $fila = fn ($doc, $nombre, $cargo, $codigo, $grado, $area, $jefe, $correo, $rol) => [
            1, (int) $doc, "{$nombre} {$this->s}", $cargo, $codigo, $grado, $area, new \DateTimeImmutable('2021-09-01'), null, 'PROVISIONALIDAD',
            null, null, null, $correo, $codigo === 6 ? 'DIRECTIVO' : 'ASISTENCIAL', 'F', 'Cédula', $jefe, 'NO', 'NA', $rol, 'RENDIMIENTO LABORAL',
        ];

        $this->archivo = tempnam(sys_get_temp_dir(), 'planta') . '.xlsx';
        $w = new Writer();
        $w->openToFile($this->archivo);
        $w->getCurrentSheet()->setName('Listado ACTUALIZADO');
        $w->addRow(Row::fromValues(['ITEM', 'No IDENTIFICACIÓN', 'NOMBRE FUNCIONARIO', 'CARGO', 'CODIGO', 'GRADO', 'AREA', 'FECHA INGRESO', 'FECHA RETIRO',
            'TIPO DE VINCULACIÓN', 'FECHA DE NACIMIENTO', 'EDAD', 'TELEFONO', 'CORREO DEL CARGO', 'NIVEL', 'GENERO', 'TIPO DE DOCUMENTO', 'JEFE INMEDIATO',
            'LE PLICA EJE MISIONAL DE DOCENCIA?', 'DELEGACIÓN ', 'ROL', 'Tipo de evaluación']));
        $w->addRow(Row::fromValues($fila("71{$this->n}", 'JEFE A', 'JEFE DE OFICINA', 6, 1, $this->depA, 'CONSEJO SUPERIOR', "jefea.{$this->s}@u.co", 'EVALUADO & EVALUADOR')));
        $w->addRow(Row::fromValues($fila("72{$this->n}", 'JEFE B', 'JEFE DE OFICINA', 6, 1, $this->depB, 'CONSEJO SUPERIOR', "jefeb.{$this->s}@u.co", 'EVALUADO & EVALUADOR')));
        // Rotación: el aux1 pasa a la oficina B (con el correo de esa plaza) y el aux2 a la A.
        $w->addRow(Row::fromValues($fila("73{$this->n}", 'AUX UNO', 'AUXILIAR ADMINISTRATIVO', 407, 11, $this->depB, "JEFE B {$this->s}", "auxb.{$this->s}@u.co", 'EVALUADO')));
        $w->addRow(Row::fromValues($fila("74{$this->n}", 'AUX DOS', 'AUXILIAR ADMINISTRATIVO', 407, 11, $this->depA, "JEFE A {$this->s}", "auxa.{$this->s}@u.co", 'EVALUADO')));

        $w->addNewSheetAndMakeItCurrent()->setName('VACANTES');
        $w->addRow(Row::fromValues(['CARGOS VACANTES']));
        $w->addRow(Row::fromValues(['ITEM', 'CARGO', 'CODIGO ', 'GRADO', 'NIVEL', 'Tipo de Vinculación', 'SISTEMA DE EVALUACIÓN', 'AREA', 'CANTIDAD', 'TIPO DE VACANTE']));
        $w->addRow(Row::fromValues([1, 'Auxiliar Administrativo', 407, 11, 'Asistente', 'Provisionalidad', 'RL', $this->depA, 2, 'DEFINITIVA']));
        $w->addRow(Row::fromValues([2, "Decano {$this->s}", 8, 6, 'Directivo', 'Periodo Fijo', 'AG', 'Facultades', 1, 'DEFINITIVA']));
        foreach ($vacantesExtra as $vacante) {
            $w->addRow(Row::fromValues($vacante));
        }
        $w->addRow(Row::fromValues(['TOTAL VACANTES', null, null, null, null, null, null, null, 3]));
        $w->close();

        return $this->archivo;
    }

    private function subir(array $vacantesExtra = []): array
    {
        $archivo = new UploadedFile($this->excel($vacantesExtra), 'planta.xlsx', null, null, true);

        return $this->post('/admin/plazas/carga/analizar', ['archivo' => $archivo], ['Accept' => 'application/json'])->assertOk()->json();
    }

    private function aplicar(array $ensayo)
    {
        return $this->postJson('/admin/plazas/carga/aplicar', ['token' => $ensayo['token'], 'archivo' => 'planta.xlsx']);
    }

    private function plazaDe(int $vinc): ?object
    {
        return DB::table('puesto as p')->join('puesto_ocupacion as po', 'po.id_puesto', '=', 'p.id_puesto')
            ->where('po.id_vinculacion', $vinc)->where('po.estado', 'VIGENTE')->first(['p.*']);
    }

    public function test_el_ensayo_reporta_y_no_escribe_nada(): void
    {
        $antes = [DB::table('puesto')->count(), DB::table('cargo')->count(), DB::table('vinculacion')->where('id_vinculacion', $this->v['aux1'])->value('area')];

        $ensayo = $this->subir();

        $this->assertSame(4, $ensayo['resumen']['personas_reconocidas']);
        $this->assertSame(2, $ensayo['resumen']['reasignaciones_de_evaluador']);
        $this->assertEquals(['ficticios_a_inactivar' => 1, 'vacantes_a_crear' => 2, 'cargos_nuevos' => 1], array_intersect_key($ensayo['resumen'], array_flip(['ficticios_a_inactivar', 'vacantes_a_crear', 'cargos_nuevos'])));
        $this->assertSame('VACANTE_SIN_DEPENDENCIA', $ensayo['pendientes'][0]['tipo']);
        $this->assertSame($antes, [DB::table('puesto')->count(), DB::table('cargo')->count(), DB::table('vinculacion')->where('id_vinculacion', $this->v['aux1'])->value('area')]);
    }

    public function test_aplicar_actualiza_los_datos_de_cargo_y_crea_las_plazas(): void
    {
        $this->aplicar($this->subir())->assertOk();

        // El Excel manda: el aux1 queda en la oficina B con el jefe B como evaluador.
        $aux1 = DB::table('vinculacion')->where('id_vinculacion', $this->v['aux1'])->first();
        $this->assertSame([$this->depB, $this->v['jefeB']], [$aux1->area, (int) $aux1->id_vinc_jefe]);
        $this->assertSame($this->v['jefeB'], (int) DB::table('evaluador_asignacion')->where('id_vinc_evaluado', $this->v['aux1'])->value('id_vinc_evaluador'));

        // El correo del cargo es de la plaza: el aux1 ocupa la plaza "auxb".
        $plaza = $this->plazaDe($this->v['aux1']);
        $this->assertSame(mb_strtolower("auxb.{$this->s}@u.co"), $plaza->correo_cargo, 'El correo se guarda en minúsculas.');
        $this->assertSame((int) $this->plazaDe($this->v['jefeB'])->id_puesto, (int) $plaza->id_puesto_superior);
        $this->assertNull($this->plazaDe($this->v['jefeA'])->id_puesto_superior, 'Consejo Superior = sin plaza superior.');

        // Vacantes como plazas sin ocupante; el ficticio se inactiva; el cargo nuevo entra al catálogo.
        $idDepA = DB::table('dependencia')->where('nombre', $this->depA)->value('id_dependencia');
        $vacantes = collect(app(\App\Services\OcupacionPuestos::class)->vacantes())->where('dependencia', $this->depA)->count();
        $this->assertSame(2, $vacantes);
        $this->assertSame(0, (int) DB::table('vinculacion')->where('id_vinculacion', $this->v['ficticio'])->value('activa'));
        $this->assertTrue(DB::table('cargo')->where('nombre', "DECANO {$this->s}")->exists());

        $this->assertSame(1, DB::table('carga_planta_pendiente')->where('estado', 'PENDIENTE')->where('tipo', 'VACANTE_SIN_DEPENDENCIA')->count());
        $this->assertNotNull($idDepA);
    }

    public function test_repetir_la_carga_no_duplica_plazas_ni_vacantes(): void
    {
        $ensayo = $this->subir();
        $this->aplicar($ensayo)->assertOk();
        $plazas = DB::table('puesto')->count();

        $this->aplicar($ensayo)->assertOk();

        $this->assertSame($plazas, DB::table('puesto')->count());
    }

    public function test_cambio_de_jefe_con_evaluacion_abierta_queda_como_traslado_pendiente(): void
    {
        DB::table('evaluacion')->insert([
            'id_periodo' => DB::table('periodo')->insertGetId(['id_usuario_apertura' => $this->idUsuario, 'sistema' => 'RENDIMIENTO_LABORAL', 'anio' => 2099, 'semestre' => 1, 'fecha_inicio' => '2099-01-01', 'fecha_fin' => '2099-06-30', 'estado' => 'ABIERTO']),
            'id_vinc_evaluado' => $this->v['aux1'], 'id_vinc_evaluador' => $this->v['jefeA'], 'tipo_evaluacion' => 'SEMESTRE_1',
            'fase_actual' => 3, 'concertacion_firmada' => 1, 'estado' => 'EN_PROCESO',
        ]);

        $this->aplicar($this->subir())->assertOk();

        $this->assertSame($this->v['jefeA'], (int) DB::table('evaluador_asignacion')->where('id_vinc_evaluado', $this->v['aux1'])->value('id_vinc_evaluador'));
        $this->assertTrue(DB::table('carga_planta_pendiente')->where('tipo', 'REQUIERE_TRASLADO')->exists());
    }

    public function test_th_resuelve_la_vacante_pendiente_eligiendo_la_dependencia(): void
    {
        $this->aplicar($this->subir())->assertOk();
        $pendiente = DB::table('carga_planta_pendiente')->where('tipo', 'VACANTE_SIN_DEPENDENCIA')->first();
        $idDepB = DB::table('dependencia')->where('nombre', $this->depB)->value('id_dependencia');

        $this->postJson("/admin/plazas/pendientes/{$pendiente->id_pendiente}", ['accion' => 'crear_vacante', 'id_dependencia' => $idDepB])->assertOk();

        $this->assertSame('RESUELTO', DB::table('carga_planta_pendiente')->where('id_pendiente', $pendiente->id_pendiente)->value('estado'));
        $this->assertTrue(DB::table('puesto as p')->join('cargo as c', 'c.id_cargo', '=', 'p.id_cargo')->where('c.nombre', "DECANO {$this->s}")->where('p.id_dependencia', $idDepB)->exists());
    }

    public function test_las_decanaturas_por_facultad_se_crean_como_vacantes(): void
    {
        $decanaturas = [[3, "Decano {$this->s}", 8, 6, 'Directivo', 'Periodo Fijo', 'AG', 'Facultad de Artes', 1, 'DEFINITIVA'],
            [4, "Decano {$this->s}", 8, 6, 'Directivo', 'Periodo Fijo', 'AG', 'Facultad de Ingenierias', 1, 'DEFINITIVA']];
        $ensayo = $this->subir($decanaturas);
        $this->aplicar($ensayo)->assertOk();
        $this->aplicar($this->subir($decanaturas))->assertOk();

        $plazas = DB::table('puesto as p')->join('cargo as c', 'c.id_cargo', '=', 'p.id_cargo')->join('dependencia as d', 'd.id_dependencia', '=', 'p.id_dependencia')
            ->where('c.nombre', "DECANO {$this->s}")->pluck('d.nombre')->sort()->values()->all();
        $this->assertSame(['FACULTAD DE ARTES', 'FACULTAD DE INGENIERIAS'], $plazas, 'Una vacante por facultad, sin duplicar al recargar.');
        $this->assertSame(1, DB::table('carga_planta_pendiente')->where('estado', 'PENDIENTE')->where('tipo', 'VACANTE_SIN_DEPENDENCIA')->count(), 'Solo «Facultades» queda pendiente.');
    }

    public function test_el_catalogo_oficial_se_descarga_en_pdf_y_excel(): void
    {
        $this->aplicar($this->subir())->assertOk();

        $this->get('/admin/plazas/documento-oficial?formato=pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/admin/plazas/documento-oficial?formato=xlsx')->assertOk()->assertDownload();
    }

    public function test_la_planta_para_revision_se_vuelve_a_cargar_sin_cambios(): void
    {
        $this->aplicar($this->subir())->assertOk();

        $descarga = $this->get('/admin/plazas/planta-revision')->assertOk()->assertDownload();
        $revision = tempnam(sys_get_temp_dir(), 'revision') . '.xlsx';
        copy($descarga->baseResponse->getFile()->getPathname(), $revision);

        $carga = app(\App\Services\CargaPlanta::class);
        $planta = $carga->leer($revision);
        $ensayo = $carga->analizar($planta);
        $mias = ["71{$this->n}", "72{$this->n}", "73{$this->n}", "74{$this->n}"];

        // Lo mismo que hay en la plataforma: ni cambios ni vacantes nuevas para estas personas y oficinas.
        $this->assertSame([], array_values(array_filter($ensayo['cambios'], fn ($c) => in_array($c['cedula'], $mias, true))));
        $this->assertSame(4, count(array_filter($planta['personas'], fn ($p) => in_array($p['cedula'], $mias, true))));
        $vacanteA = collect($ensayo['_vacantes'])->first(fn ($v) => $v['area'] === $this->depA);
        $this->assertSame([2, 0], [$vacanteA['cantidad'], $vacanteA['faltan']]);
        $this->assertTrue(collect($ensayo['pendientes'])->contains(fn ($p) => $p['tipo'] === 'VACANTE_SIN_DEPENDENCIA' && $p['datos']['cargo'] === "DECANO {$this->s}"));

        // Al volver a cargarla, los pendientes de la carga anterior se reemplazan (no se duplican).
        $archivo = new UploadedFile($revision, 'revisada.xlsx', null, null, true);
        $nuevo = $this->post('/admin/plazas/carga/analizar', ['archivo' => $archivo], ['Accept' => 'application/json'])->assertOk()->json();
        $this->postJson('/admin/plazas/carga/aplicar', ['token' => $nuevo['token'], 'archivo' => 'revisada.xlsx'])->assertOk();
        unlink($revision);

        $decano = DB::table('carga_planta_pendiente')->where('tipo', 'VACANTE_SIN_DEPENDENCIA')->where('datos', 'like', "%DECANO {$this->s}%");
        $this->assertSame(1, (clone $decano)->where('estado', 'PENDIENTE')->count());
        $this->assertSame(1, (clone $decano)->where('estado', 'DESCARTADO')->count());
    }

    public function test_el_lector_entiende_cedulas_en_notacion_cientifica(): void
    {
        // Así viene la cédula en el Excel institucional real: <v>1.00774757E9</v>.
        $ruta = tempnam(sys_get_temp_dir(), 'xlsx') . '.xlsx';
        $zip = new \ZipArchive();
        $zip->open($ruta, \ZipArchive::CREATE);
        $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Hoja" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Target="worksheets/sheet1.xml" Type="worksheet"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1"><v>1.00774757E9</v></c><c r="B1"><v>44459.0</v></c><c r="C1" t="inlineStr"><is><t>texto</t></is></c></row></sheetData></worksheet>');
        $zip->close();

        $hojas = (new LectorXlsx())->leer($ruta);
        unlink($ruta);

        $this->assertSame(1007747570, $hojas['Hoja'][1][1]);
        $this->assertSame('2021-09-20', LectorXlsx::fechaExcel($hojas['Hoja'][1][2]));
        $this->assertSame('texto', $hojas['Hoja'][1][3]);
    }

    public function test_solo_talento_humano_carga_la_planta(): void
    {
        session(['usuario_autenticado' => ['id_usuario' => $this->idUsuario, 'rol_activo' => 'evaluador', 'roles' => ['evaluador']]]);

        $this->post('/admin/plazas/carga/analizar', [], ['Accept' => 'application/json'])->assertForbidden();
        $this->getJson('/admin/plazas/pendientes')->assertForbidden();
    }
}
