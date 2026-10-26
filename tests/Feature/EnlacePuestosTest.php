<?php

namespace Tests\Feature;

use App\Services\EnlacePuestos;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * S14 — Fase C: enlace de asignaciones, evaluaciones, delegaciones y tramos a
 * la plaza. Solo se enlaza lo inequívoco, lo demás queda como excepción, el
 * ensayo no escribe y una referencia ya puesta no se cambia.
 *
 * La prueba trabaja sobre la base configurada en el entorno y se ejecuta dentro
 * de una transacción que se revierte al terminar, de modo que no deja datos.
 */
class EnlacePuestosTest extends TestCase
{
    private int $idUsuario;
    private string $s;
    private int $idDependencia;
    private int $idCargo;
    private int $idPeriodo;
    private array $v = [];
    private array $plaza = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requiere una base MySQL con el esquema institucional aplicado.');
        }

        DB::beginTransaction();

        $this->s = 'E' . random_int(10000, 99999);
        $this->idUsuario = DB::table('usuario')->insertGetId(['username' => "tmp.enlace.{$this->s}", 'password' => 'x', 'rol' => 'ADMINISTRADOR', 'activo' => 1]);
        $this->idDependencia = DB::table('dependencia')->insertGetId(['nombre' => "OFICINA {$this->s}", 'activa' => 1]);
        DB::table('cargo')->insertOrIgnore(['nombre' => 'JEFE DE OFICINA', 'codigo_cargo' => 6, 'activo' => 1]);
        $this->idCargo = (int) DB::table('cargo')->where('nombre', 'JEFE DE OFICINA')->value('id_cargo');
        $this->idPeriodo = DB::table('periodo')->insertGetId([
            'id_usuario_apertura' => $this->idUsuario, 'sistema' => 'RENDIMIENTO_LABORAL', 'anio' => 2099, 'semestre' => 1,
            'fecha_inicio' => '2099-01-01', 'fecha_fin' => '2099-06-30', 'estado' => 'ABIERTO',
        ]);

        // Jefe con plaza desde antes del periodo, jefe nuevo (llegó después del periodo),
        // jefe sin plaza, delegado y un evaluado por cada caso.
        foreach (['jefe', 'nuevo', 'sinPlaza', 'delegado', 'e1', 'e2', 'e3'] as $clave) {
            $this->v[$clave] = $this->vinculacion($clave);
        }
        $this->plaza['jefe'] = $this->ocupar($this->v['jefe'], 1, '2098-01-01');
        $this->plaza['nuevo'] = $this->ocupar($this->v['nuevo'], 2, '2099-08-01');
        $this->plaza['delegado'] = $this->ocupar($this->v['delegado'], 3, '2098-01-01');

        foreach (['e1' => 'jefe', 'e2' => 'nuevo', 'e3' => 'sinPlaza'] as $evaluado => $evaluador) {
            DB::table('evaluador_asignacion')->insert(['id_vinc_evaluador' => $this->v[$evaluador], 'id_vinc_evaluado' => $this->v[$evaluado], 'fecha_asignacion' => now()]);
        }
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    private function vinculacion(string $clave): int
    {
        $idUsuario = DB::table('usuario')->insertGetId(['username' => "tmp.enlace.{$clave}.{$this->s}", 'password' => 'x', 'rol' => 'EVALUADO', 'activo' => 1]);
        $idFuncionario = DB::table('funcionario')->insertGetId([
            'id_usuario' => $idUsuario, 'tipo_documento' => 'CEDULA_CIUDADANIA', 'numero_doc' => (string) random_int(10 ** 9, 2 * 10 ** 9),
            'nombres' => strtoupper($clave), 'apellidos' => $this->s,
        ]);

        return DB::table('vinculacion')->insertGetId([
            'id_funcionario' => $idFuncionario, 'cargo' => 'JEFE DE OFICINA', 'codigo_cargo' => 6, 'grado_cargo' => 1,
            'nivel_jerarquico' => 'DIRECTIVO', 'area' => "OFICINA {$this->s}", 'tipo_vinculacion' => 'PROVISIONALIDAD',
            'sistema_evaluacion' => 'RENDIMIENTO_LABORAL', 'es_evaluador' => 1, 'fecha_ingreso' => '2098-01-01', 'activa' => 1,
        ]);
    }

    /** Crea una plaza y la ocupación vigente de la persona desde la fecha dada. */
    private function ocupar(int $idVinc, int $consecutivo, string $desde): int
    {
        $idPuesto = DB::table('puesto')->insertGetId([
            'codigo_plaza' => "T-{$this->s}-{$consecutivo}", 'id_dependencia' => $this->idDependencia, 'id_cargo' => $this->idCargo,
            'codigo_cargo' => 6, 'grado' => 1, 'nivel_jerarquico' => 'DIRECTIVO', 'sistema_evaluacion' => 'RENDIMIENTO_LABORAL',
            'estado' => 'BORRADOR', 'requiere_revision' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('puesto_ocupacion')->insert([
            'id_puesto' => $idPuesto, 'id_vinculacion' => $idVinc, 'tipo_ocupacion' => 'TITULAR', 'vigente_desde' => $desde,
            'estado' => 'VIGENTE', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $idPuesto;
    }

    private function evaluacion(string $evaluado, string $evaluador, string $estado, ?string $suplente = null): int
    {
        return DB::table('evaluacion')->insertGetId([
            'id_periodo' => $this->idPeriodo, 'id_vinc_evaluado' => $this->v[$evaluado], 'id_vinc_evaluador' => $this->v[$evaluador],
            'id_vinc_suplente' => $suplente ? $this->v[$suplente] : null, 'tipo_evaluacion' => 'SEMESTRE_1',
            'fase_actual' => 3, 'concertacion_firmada' => 1, 'estado' => $estado,
        ]);
    }

    private function excepcionesDe(array $r, string $tabla, int $id): array
    {
        return array_values(array_filter($r['excepciones'], fn ($e) => $e['tabla'] === $tabla && $e['id'] === $id));
    }

    public function test_el_ensayo_no_escribe_nada(): void
    {
        $r = app(EnlacePuestos::class)->ensayar();

        $this->assertGreaterThanOrEqual(2, $r['resumen']['evaluador_asignacion']['enlazados']);
        $this->assertNull(DB::table('evaluador_asignacion')->where('id_vinc_evaluado', $this->v['e1'])->value('id_puesto_evaluador'));
    }

    public function test_enlaza_asignaciones_y_deja_excepcion_si_el_evaluador_no_tiene_plaza(): void
    {
        $r = app(EnlacePuestos::class)->aplicar();

        $this->assertSame($this->plaza['jefe'], (int) DB::table('evaluador_asignacion')->where('id_vinc_evaluado', $this->v['e1'])->value('id_puesto_evaluador'));
        $this->assertSame($this->plaza['nuevo'], (int) DB::table('evaluador_asignacion')->where('id_vinc_evaluado', $this->v['e2'])->value('id_puesto_evaluador'));

        $sinPlaza = DB::table('evaluador_asignacion')->where('id_vinc_evaluado', $this->v['e3'])->first();
        $this->assertNull($sinPlaza->id_puesto_evaluador);
        $this->assertCount(1, $this->excepcionesDe($r, 'evaluador_asignacion', (int) $sinPlaza->id_asignacion));
    }

    public function test_evaluacion_abierta_con_suplente_va_a_la_plaza_del_titular(): void
    {
        $id = $this->evaluacion('e1', 'delegado', 'EN_PROCESO', 'jefe');

        app(EnlacePuestos::class)->aplicar();

        $e = DB::table('evaluacion')->where('id_evaluacion', $id)->first();
        $this->assertSame($this->plaza['jefe'], (int) $e->id_puesto_evaluador);
        $this->assertSame((int) DB::table('puesto_ocupacion')->where('id_puesto', $this->plaza['jefe'])->value('id_puesto_ocupacion'), (int) $e->id_puesto_ocupacion_responsable);
    }

    public function test_evaluacion_cerrada_solo_se_enlaza_si_el_evaluador_ocupaba_la_plaza_en_el_periodo(): void
    {
        $cubierta = $this->evaluacion('e1', 'jefe', 'CALIFICADA');
        // El jefe nuevo llegó a su plaza después de terminar el periodo: no se le atribuye.
        $sinHistoria = $this->evaluacion('e2', 'nuevo', 'CALIFICADA');

        $r = app(EnlacePuestos::class)->aplicar();

        $this->assertSame($this->plaza['jefe'], (int) DB::table('evaluacion')->where('id_evaluacion', $cubierta)->value('id_puesto_evaluador'));
        $this->assertNull(DB::table('evaluacion')->where('id_evaluacion', $sinHistoria)->value('id_puesto_evaluador'));
        $this->assertStringContainsString('no cubre', $this->excepcionesDe($r, 'evaluacion', $sinHistoria)[0]['motivo']);
    }

    public function test_delegacion_y_sus_tramos_van_a_la_plaza_del_delegante(): void
    {
        $idPp = DB::table('periodo_parcial')->insertGetId([
            'id_periodo' => $this->idPeriodo, 'id_vinc_funcionario' => $this->v['e1'], 'fecha_inicio' => '2099-02-01',
            'fecha_fin' => '2099-03-31', 'estado' => 'CERRADO', 'id_usuario_apertura' => $this->idUsuario,
        ]);
        $idDelegacion = DB::table('delegacion')->insertGetId([
            'id_vinc_delegante' => $this->v['jefe'], 'id_vinc_delegado' => $this->v['delegado'], 'motivo' => 'VACACIONES',
            'fecha_inicio' => '2099-02-01', 'fecha_fin' => '2099-03-31', 'estado' => 'FINALIZADA', 'id_usuario_registra' => $this->idUsuario,
            'detalle_transferencia' => json_encode(['periodo_parcial_ids' => [$idPp]]),
        ]);

        app(EnlacePuestos::class)->aplicar();

        $this->assertSame($this->plaza['jefe'], (int) DB::table('delegacion')->where('id_delegacion', $idDelegacion)->value('id_puesto'));
        $this->assertSame($this->plaza['jefe'], (int) DB::table('periodo_parcial')->where('id_periodo_parcial', $idPp)->value('id_puesto_responsable'));
    }

    public function test_no_cambia_un_enlace_existente_y_se_puede_repetir(): void
    {
        DB::table('evaluador_asignacion')->where('id_vinc_evaluado', $this->v['e1'])->update(['id_puesto_evaluador' => $this->plaza['delegado']]);

        app(EnlacePuestos::class)->aplicar();
        $segunda = app(EnlacePuestos::class)->aplicar();

        $this->assertSame($this->plaza['delegado'], (int) DB::table('evaluador_asignacion')->where('id_vinc_evaluado', $this->v['e1'])->value('id_puesto_evaluador'));
        $idE2 = (int) DB::table('evaluador_asignacion')->where('id_vinc_evaluado', $this->v['e2'])->value('id_asignacion');
        $this->assertSame($this->plaza['nuevo'], (int) DB::table('evaluador_asignacion')->where('id_asignacion', $idE2)->value('id_puesto_evaluador'));
        $this->assertSame([], $this->excepcionesDe($segunda, 'evaluador_asignacion', $idE2));
    }
}
