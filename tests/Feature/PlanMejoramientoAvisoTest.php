<?php

namespace Tests\Feature;

use Tests\Concerns\CreaDatosSerag;
use Tests\TestCase;

/**
 * Acta 29-10-2026, hallazgo VIII: el aviso "planes de mejoramiento pendientes"
 * del evaluador listaba evaluaciones Buenas y Sobresalientes, porque la consulta
 * conservaba el umbral `<= 80` de la escala 0-100. Solo deben aparecer las
 * evaluaciones cuya banda de la escala institucional exige plan.
 */
class PlanMejoramientoAvisoTest extends TestCase
{
    use CreaDatosSerag;

    private int $idFuncionarioEvaluador;
    private int $idUsuarioEvaluador;
    private int $idVincEvaluador;
    private int $idPeriodo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarDatosSerag();

        [$this->idFuncionarioEvaluador, $this->idUsuarioEvaluador] = $this->crearFuncionarioPrueba('Tmp', 'Evaluador', 'EVALUADOR');
        $this->idVincEvaluador = $this->crearVinculacionPrueba($this->idFuncionarioEvaluador, true);
        $this->idPeriodo = $this->crearPeriodoPrueba('RENDIMIENTO_LABORAL', 2099, 1, '2099-02-01', '2099-07-31');
    }

    protected function tearDown(): void
    {
        $this->finalizarDatosSerag();
        parent::tearDown();
    }

    private function evaluacionCalificada(float $nota, ?string $categoria): int
    {
        [$idFuncionario] = $this->crearFuncionarioPrueba('Tmp', 'Evaluado ' . $nota);
        $idVinc = $this->crearVinculacionPrueba($idFuncionario);
        $this->asignarEvaluadorPrueba($this->idVincEvaluador, $idVinc);

        return $this->crearEvaluacionPrueba($this->idPeriodo, $idVinc, $this->idVincEvaluador, [
            'fase_actual' => 5,
            'concertacion_firmada' => 1,
            'estado' => 'CALIFICADA',
            'calificacion_final' => $nota,
            'categoria_final' => $categoria,
        ]);
    }

    private function idsEnElAviso(): array
    {
        $this->sesionComo('evaluador', $this->idFuncionarioEvaluador, $this->idUsuarioEvaluador);

        return $this->get('/dashboard')
            ->assertOk()
            ->viewData('planesPendientesEvaluador')
            ->pluck('id_evaluacion')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function test_solo_aparecen_las_bandas_que_exigen_plan(): void
    {
        $noSatisfactorio = $this->evaluacionCalificada(3.0, 'NO_SATISFACTORIO');
        $mejora = $this->evaluacionCalificada(3.8, 'APROBADO_MEJORA');
        $bueno = $this->evaluacionCalificada(4.3, 'BUENO');
        $sobresaliente = $this->evaluacionCalificada(4.8, 'SOBRESALIENTE');

        $ids = $this->idsEnElAviso();

        $this->assertContains($noSatisfactorio, $ids);
        $this->assertContains($mejora, $ids);
        $this->assertNotContains($bueno, $ids, 'Una evaluación Buena no debe pedir plan de mejoramiento.');
        $this->assertNotContains($sobresaliente, $ids, 'Una evaluación Sobresaliente no debe pedir plan de mejoramiento.');
    }

    public function test_sin_categoria_calculada_se_decide_por_la_nota_con_la_escala(): void
    {
        $baja = $this->evaluacionCalificada(3.9, null);
        $alta = $this->evaluacionCalificada(4.4, null);

        $ids = $this->idsEnElAviso();

        $this->assertContains($baja, $ids);
        $this->assertNotContains($alta, $ids);
    }
}
