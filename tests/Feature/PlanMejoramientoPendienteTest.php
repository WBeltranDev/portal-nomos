<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Corrección VIII (plan de pruebas 29-10-2026): el aviso «planes de mejoramiento
 * pendientes» del evaluador solo debe listar evaluaciones cuya categoría aplica
 * plan según la escala 1.0-5.0. Una nota Buena o Sobresaliente no lo genera.
 */
class PlanMejoramientoPendienteTest extends TestCase
{
    private int $idUsuario;
    private int $idFuncionarioEvaluador;
    private int $idEvaluacion;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requiere una base MySQL con el esquema institucional aplicado.');
        }

        DB::beginTransaction();

        $sufijo = 'T' . random_int(10000, 99999);
        $this->idUsuario = $this->crearUsuario("tmp.plan.{$sufijo}");
        $idEvaluado = $this->crearFuncionario('Tmp', 'Evaluado', "{$sufijo}A");
        $this->idFuncionarioEvaluador = $this->crearFuncionario('Tmp', 'Evaluador', "{$sufijo}B");
        $vincEvaluado = $this->crearVinculacion($idEvaluado, 'PROFESIONAL', false);
        $vincEvaluador = $this->crearVinculacion($this->idFuncionarioEvaluador, 'DIRECTIVO', true);

        $periodo = DB::table('periodo')->insertGetId([
            'id_usuario_apertura' => $this->idUsuario,
            'sistema' => 'RENDIMIENTO_LABORAL',
            'anio' => 2026,
            'semestre' => 1,
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-06-30',
            'estado' => 'ABIERTO',
        ]);

        $this->idEvaluacion = DB::table('evaluacion')->insertGetId([
            'id_periodo' => $periodo,
            'id_vinc_evaluado' => $vincEvaluado,
            'id_vinc_evaluador' => $vincEvaluador,
            'tipo_evaluacion' => 'SEMESTRE_1',
            'fase_actual' => 5,
            'concertacion_firmada' => 1,
            'estado' => 'CALIFICADA',
            'calificacion_final' => 4.4,
            'categoria_final' => 'BUENO',
        ]);

        session(['usuario_autenticado' => [
            'id_usuario' => $this->idUsuario,
            'id_funcionario' => $this->idFuncionarioEvaluador,
            'rol_activo' => 'evaluador',
            'roles' => ['evaluador'],
        ]]);
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    private function crearUsuario(string $username): int
    {
        return DB::table('usuario')->insertGetId([
            'username' => $username,
            'password' => bcrypt('temporal'),
            'rol' => 'ADMINISTRADOR',
            'activo' => 1,
        ]);
    }

    private function crearFuncionario(string $nombres, string $apellidos, string $doc): int
    {
        return DB::table('funcionario')->insertGetId([
            'id_usuario' => $this->crearUsuario('tmp.plan.' . strtolower($doc)),
            'tipo_documento' => 'CEDULA_CIUDADANIA',
            'numero_doc' => $doc,
            'nombres' => $nombres,
            'apellidos' => $apellidos,
        ]);
    }

    private function crearVinculacion(int $idFuncionario, string $nivel, bool $esEvaluador): int
    {
        return DB::table('vinculacion')->insertGetId([
            'id_funcionario' => $idFuncionario,
            'cargo' => $nivel,
            'codigo_cargo' => 1,
            'grado_cargo' => 1,
            'nivel_jerarquico' => $nivel,
            'area' => 'PRUEBA',
            'tipo_vinculacion' => 'PROVISIONALIDAD',
            'sistema_evaluacion' => 'RENDIMIENTO_LABORAL',
            'es_evaluador' => $esEvaluador ? 1 : 0,
            'fecha_ingreso' => '2026-01-01',
            'activa' => 1,
        ]);
    }

    private function planesPendientes(): array
    {
        return $this->get('/dashboard')->viewData('planesPendientesEvaluador')->pluck('id_evaluacion')->all();
    }

    public function test_una_nota_buena_no_aparece_como_plan_pendiente(): void
    {
        $this->assertNotContains($this->idEvaluacion, $this->planesPendientes());
    }

    public function test_una_nota_sobresaliente_no_aparece_como_plan_pendiente(): void
    {
        DB::table('evaluacion')->where('id_evaluacion', $this->idEvaluacion)
            ->update(['calificacion_final' => 4.8, 'categoria_final' => 'SOBRESALIENTE']);

        $this->assertNotContains($this->idEvaluacion, $this->planesPendientes());
    }

    public function test_una_nota_susceptible_a_plan_si_aparece(): void
    {
        DB::table('evaluacion')->where('id_evaluacion', $this->idEvaluacion)
            ->update(['calificacion_final' => 3.8, 'categoria_final' => 'APROBADO_MEJORA']);

        $this->assertContains($this->idEvaluacion, $this->planesPendientes());
    }
}
