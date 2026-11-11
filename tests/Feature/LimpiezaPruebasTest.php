<?php

namespace Tests\Feature;

use App\Services\LimpiezaPruebas;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Limpieza de los datos de prueba antes de operar: se vacían periodos,
 * evaluaciones, delegaciones, traslados y lo que cuelga de ellos; se conservan
 * los datos maestros y los evaluadores vuelven al jefe inmediato de la planta.
 */
class LimpiezaPruebasTest extends TestCase
{
    private string $s;
    private int $idJefe;
    private int $idPersona;
    private int $idOtroJefe;
    private int $idPeriodo;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requiere una base MySQL con el esquema institucional aplicado.');
        }

        DB::beginTransaction();
        $this->s = 'LP' . random_int(10000, 99999);

        $this->idJefe = $this->vinculacion('J', null);
        $this->idOtroJefe = $this->vinculacion('K', null);
        $this->idPersona = $this->vinculacion('P', $this->idJefe);

        // Deriva de las pruebas: la persona quedó con un evaluador distinto y con una delegación copiada.
        DB::table('evaluador_asignacion')->insert([
            ['id_vinc_evaluador' => $this->idOtroJefe, 'id_vinc_evaluado' => $this->idPersona, 'fecha_asignacion' => now()],
            ['id_vinc_evaluador' => $this->idJefe, 'id_vinc_evaluado' => $this->idPersona, 'fecha_asignacion' => now()],
        ]);

        $this->idPeriodo = DB::table('periodo')->insertGetId([
            'id_usuario_apertura' => (int) DB::table('funcionario')->value('id_usuario') ?: DB::table('usuario')->value('id_usuario'),
            'sistema' => 'RENDIMIENTO_LABORAL', 'anio' => 2099, 'semestre' => 1, 'fecha_inicio' => '2099-01-01', 'fecha_fin' => '2099-06-30', 'estado' => 'ABIERTO',
        ]);
        $idEvaluacion = DB::table('evaluacion')->insertGetId([
            'id_periodo' => $this->idPeriodo, 'id_vinc_evaluado' => $this->idPersona, 'id_vinc_evaluador' => $this->idOtroJefe,
            'tipo_evaluacion' => 'SEMESTRE_1', 'fase_actual' => 3, 'concertacion_firmada' => 1, 'estado' => 'EN_PROCESO',
        ]);
        DB::table('compromiso')->insert(['id_evaluacion' => $idEvaluacion, 'numero_orden' => 1, 'descripcion' => 'C', 'porcentaje_peso' => 80]);
        DB::table('evaluacion_evento')->insert(['id_evaluacion' => $idEvaluacion, 'tipo_evento' => 'EVALUACION_ABIERTA', 'created_at' => now()]);
        DB::table('traslado')->insert([
            'id_vinc_funcionario' => $this->idPersona, 'estado' => 'CONFIRMADO', 'tipo' => 'CAMBIO', 'id_periodo' => $this->idPeriodo,
            'id_vinc_evaluador_origen' => $this->idJefe, 'id_vinc_evaluador_nuevo' => $this->idOtroJefe, 'fecha_traslado' => '2099-03-01',
        ]);
        DB::table('delegacion')->insert([
            'id_vinc_delegante' => $this->idJefe, 'id_vinc_delegado' => $this->idOtroJefe, 'fecha_inicio' => '2099-02-01', 'estado' => 'ACTIVA',
        ]);
        DB::table('notificacion')->insert(['tipo' => 'PRUEBA', 'titulo' => 'x', 'mensaje' => 'y', 'seccion' => 'z', 'leida' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    private function vinculacion(string $clave, ?int $jefe): int
    {
        $idUsuario = DB::table('usuario')->insertGetId(['username' => "tmp.{$clave}.{$this->s}", 'password' => 'x', 'rol' => 'EVALUADO', 'activo' => 1]);
        $idFuncionario = DB::table('funcionario')->insertGetId([
            'id_usuario' => $idUsuario, 'tipo_documento' => 'CEDULA_CIUDADANIA', 'numero_doc' => (string) random_int(5 * 10 ** 9, 6 * 10 ** 9), 'nombres' => $clave, 'apellidos' => $this->s,
        ]);

        return DB::table('vinculacion')->insertGetId([
            'id_funcionario' => $idFuncionario, 'cargo' => 'PRUEBA', 'codigo_cargo' => 1, 'grado_cargo' => 1, 'nivel_jerarquico' => 'ASISTENCIAL', 'area' => "AREA {$clave} {$this->s}",
            'tipo_vinculacion' => 'PROVISIONALIDAD', 'sistema_evaluacion' => 'RENDIMIENTO_LABORAL', 'es_evaluador' => 1, 'fecha_ingreso' => '2090-01-01', 'activa' => 1,
            'id_vinc_jefe' => $jefe,
        ]);
    }

    public function test_la_simulacion_no_cambia_nada_y_cuenta_lo_que_se_borraria(): void
    {
        $plan = app(LimpiezaPruebas::class)->plan();

        $this->assertGreaterThanOrEqual(1, $plan['vaciar']['evaluacion']);
        $this->assertGreaterThanOrEqual(1, $plan['vaciar']['traslado']);
        $this->assertGreaterThan($plan['asignaciones_base'], $plan['asignaciones_hoy'], 'Hay asignaciones de más (deriva de las pruebas).');
        $this->assertSame(1, DB::table('evaluacion')->where('id_vinc_evaluado', $this->idPersona)->count());
    }

    public function test_ejecutar_vacia_lo_transaccional_y_conserva_los_datos_maestros(): void
    {
        $antes = [
            'usuario' => DB::table('usuario')->count(), 'funcionario' => DB::table('funcionario')->count(),
            'vinculacion' => DB::table('vinculacion')->count(), 'cargo' => DB::table('cargo')->count(),
            'dependencia' => DB::table('dependencia')->count(), 'puesto' => DB::table('puesto')->count(),
            'puesto_ocupacion' => DB::table('puesto_ocupacion')->count(), 'competencia_catalogo' => DB::table('competencia_catalogo')->count(),
            'configuracion_sistema' => DB::table('configuracion_sistema')->count(),
        ];

        app(LimpiezaPruebas::class)->ejecutar(true, false);

        foreach (LimpiezaPruebas::A_VACIAR as $tabla) {
            if (! \Illuminate\Support\Facades\Schema::hasTable($tabla)) {
                continue; // la limpieza también omite las tablas que un entorno no tenga
            }
            $this->assertSame(0, DB::table($tabla)->count(), "{$tabla} debe quedar vacía.");
        }
        foreach ($antes as $tabla => $n) {
            $this->assertSame($n, DB::table($tabla)->count(), "{$tabla} se conserva.");
        }
    }

    public function test_los_evaluadores_vuelven_al_jefe_inmediato_de_la_planta(): void
    {
        $resultado = app(LimpiezaPruebas::class)->ejecutar(true, false);

        $this->assertSame([$this->idJefe], DB::table('evaluador_asignacion')->where('id_vinc_evaluado', $this->idPersona)->pluck('id_vinc_evaluador')->map(fn ($v) => (int) $v)->all());
        // Sin jefe registrado no hay asignación.
        $this->assertSame(0, DB::table('evaluador_asignacion')->where('id_vinc_evaluado', $this->idJefe)->count());
        $this->assertSame(DB::table('evaluador_asignacion')->count(), $resultado['asignaciones']);
    }

    public function test_un_jefe_inactivo_o_una_persona_inactiva_no_reciben_asignacion(): void
    {
        DB::table('vinculacion')->where('id_vinculacion', $this->idJefe)->update(['activa' => 0]);

        app(LimpiezaPruebas::class)->ejecutar(true, false);
        $this->assertSame(0, DB::table('evaluador_asignacion')->where('id_vinc_evaluado', $this->idPersona)->count(), 'Su jefe está inactivo.');
    }

    public function test_sin_la_opcion_de_evaluadores_las_asignaciones_no_se_tocan(): void
    {
        $antes = DB::table('evaluador_asignacion')->count();

        app(LimpiezaPruebas::class)->ejecutar(false, false);

        $this->assertSame($antes, DB::table('evaluador_asignacion')->count());
        $this->assertSame(0, DB::table('evaluacion')->count());
    }

    public function test_el_comando_simula_por_defecto(): void
    {
        $this->artisan('serag:limpiar-pruebas')->expectsOutputToContain('Simulación: no se cambió nada')->assertExitCode(0);

        $this->assertSame(1, DB::table('evaluacion')->where('id_vinc_evaluado', $this->idPersona)->count());
    }

    public function test_el_comando_exige_confirmar_el_nombre_de_la_base(): void
    {
        $this->artisan('serag:limpiar-pruebas', ['--ejecutar' => true])->assertExitCode(1);
        $this->artisan('serag:limpiar-pruebas', ['--ejecutar' => true, '--base' => 'otra_base'])->assertExitCode(1);

        $this->assertSame(1, DB::table('evaluacion')->where('id_vinc_evaluado', $this->idPersona)->count());
    }

    public function test_el_comando_ejecuta_con_la_base_confirmada(): void
    {
        $base = DB::connection()->getDatabaseName();

        $this->artisan('serag:limpiar-pruebas', ['--ejecutar' => true, '--base' => $base, '--sin-reiniciar-ids' => true])
            ->expectsOutputToContain('Limpieza terminada')
            ->assertExitCode(0);

        $this->assertSame(0, DB::table('periodo')->count());
        $this->assertSame(0, DB::table('traslado')->count());
        $this->assertSame(1, DB::table('vinculacion')->where('id_vinculacion', $this->idPersona)->count());
    }
}
