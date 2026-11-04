<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaDatosSerag;
use Tests\TestCase;

/**
 * Acta 29-10-2026, P2/P5: no se podía trasladar la evaluación al superior
 * jerárquico porque el traslado exigía la casilla `es_evaluador`, y en la
 * prueba hubo que marcarla a mano en la base. Además, quien recibía una
 * evaluación (por traslado o impedimento) sin esa casilla no obtenía el rol
 * de evaluador al iniciar sesión.
 */
class EvaluadorSinCasillaTest extends TestCase
{
    use CreaDatosSerag;

    private int $idPeriodo;
    private int $idVincEvaluado;
    private int $idVincEvaluadorOrigen;
    private int $idVincSuperior;
    private int $idUsuarioSuperior;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarDatosSerag();

        $hoy = Carbon::today();
        $this->idPeriodo = $this->crearPeriodoPrueba('RENDIMIENTO_LABORAL', 2099, 2,
            $hoy->copy()->subDays(120)->toDateString(), $hoy->copy()->addDays(60)->toDateString());

        [$idSuperior, $this->idUsuarioSuperior] = $this->crearFuncionarioPrueba('Tmp', 'Superior');
        $this->idVincSuperior = $this->crearVinculacionPrueba($idSuperior, false);

        [$idOrigen] = $this->crearFuncionarioPrueba('Tmp', 'Origen', 'EVALUADOR');
        $this->idVincEvaluadorOrigen = $this->crearVinculacionPrueba($idOrigen, true, 'RENDIMIENTO_LABORAL', $this->idVincSuperior);

        [$idEvaluado] = $this->crearFuncionarioPrueba('Tmp', 'Evaluado');
        $this->idVincEvaluado = $this->crearVinculacionPrueba($idEvaluado, false, 'RENDIMIENTO_LABORAL', $this->idVincEvaluadorOrigen);
        $this->asignarEvaluadorPrueba($this->idVincEvaluadorOrigen, $this->idVincEvaluado);
    }

    protected function tearDown(): void
    {
        $this->finalizarDatosSerag();
        parent::tearDown();
    }

    private function evaluacionDeOrigenCalificada(): int
    {
        $id = $this->crearEvaluacionPrueba($this->idPeriodo, $this->idVincEvaluado, $this->idVincEvaluadorOrigen, [
            'tipo_evaluacion' => 'SEMESTRE_2',
            'fase_actual' => 5,
            'concertacion_firmada' => 1,
            'estado' => 'CALIFICADA',
        ]);
        DB::table('compromiso')->insert([
            'id_evaluacion' => $id,
            'numero_orden' => 1,
            'descripcion' => 'Compromiso de prueba',
            'porcentaje_peso' => 100,
            'calificacion_definitiva' => 4.4,
        ]);

        return $id;
    }

    public function test_se_traslada_al_superior_aunque_no_tenga_la_casilla_y_se_le_marca(): void
    {
        $this->evaluacionDeOrigenCalificada();
        $this->sesionComo('admin', null);

        $this->post('/admin/traslados', [
            'id_vinc_funcionario' => $this->idVincEvaluado,
            'id_vinc_evaluador_nuevo' => $this->idVincSuperior,
            'fecha_traslado' => Carbon::today()->toDateString(),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $traslado = DB::table('traslado')->where('id_vinc_funcionario', $this->idVincEvaluado)->orderByDesc('id_traslado')->first();
        $this->assertSame('CONFIRMADO', $traslado->estado);
        $this->assertSame(1, (int) DB::table('vinculacion')->where('id_vinculacion', $this->idVincSuperior)->value('es_evaluador'),
            'Al confirmar el traslado, el nuevo evaluador queda marcado como evaluador.');
    }

    public function test_quien_tiene_una_evaluacion_a_cargo_entra_con_rol_de_evaluador(): void
    {
        // El superior recibe la evaluación (p. ej. por un impedimento aprobado)
        // sin tener la casilla ni asignaciones.
        $this->crearEvaluacionPrueba($this->idPeriodo, $this->idVincEvaluado, $this->idVincSuperior, [
            'tipo_evaluacion' => 'SEMESTRE_2',
            'fase_actual' => 2,
            'concertacion_firmada' => 1,
        ]);
        $correo = DB::table('usuario')->where('id_usuario', $this->idUsuarioSuperior)->value('username');

        $this->post('/login', ['correo' => $correo, 'password' => 'temporal'])->assertRedirect();

        $this->assertContains('evaluador', session('usuario_autenticado.roles'));
    }

    public function test_sin_evaluaciones_a_cargo_no_obtiene_el_rol(): void
    {
        $correo = DB::table('usuario')->where('id_usuario', $this->idUsuarioSuperior)->value('username');

        $this->post('/login', ['correo' => $correo, 'password' => 'temporal'])->assertRedirect();

        $this->assertNotContains('evaluador', session('usuario_autenticado.roles'));
    }
}
