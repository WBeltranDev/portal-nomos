<?php

namespace Tests\Feature;

use Tests\Concerns\CreaDatosSerag;
use Tests\TestCase;

/**
 * Las cuentas de administrador de Talento Humano (th1, th2, th3) no tienen
 * funcionario asociado. puedeDescargarInforme() revisaba el funcionario antes
 * que el rol, así que esas cuentas no podían descargar ningún PDF.
 */
class PermisoInformePdfTest extends TestCase
{
    use CreaDatosSerag;

    private int $idEvaluacion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarDatosSerag();

        [$idEvaluado] = $this->crearFuncionarioPrueba('Tmp', 'Evaluado');
        [$idEvaluador] = $this->crearFuncionarioPrueba('Tmp', 'Evaluador', 'EVALUADOR');
        $vincEvaluado = $this->crearVinculacionPrueba($idEvaluado);
        $vincEvaluador = $this->crearVinculacionPrueba($idEvaluador, true);
        $idPeriodo = $this->crearPeriodoPrueba('RENDIMIENTO_LABORAL', 2099, 1, '2099-02-01', '2099-07-31');

        $this->idEvaluacion = $this->crearEvaluacionPrueba($idPeriodo, $vincEvaluado, $vincEvaluador, [
            'fase_actual' => 5,
            'concertacion_firmada' => 1,
            'estado' => 'CALIFICADA',
            'calificacion_final' => 4.4,
            'categoria_final' => 'BUENO',
        ]);
    }

    protected function tearDown(): void
    {
        $this->finalizarDatosSerag();
        parent::tearDown();
    }

    public function test_un_administrador_sin_funcionario_descarga_el_informe(): void
    {
        $this->sesionComo('admin', null);

        $this->get("/evaluaciones/{$this->idEvaluacion}/informe")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_un_funcionario_ajeno_a_la_evaluacion_no_lo_descarga(): void
    {
        [$idOtro] = $this->crearFuncionarioPrueba('Tmp', 'Ajeno');
        $this->crearVinculacionPrueba($idOtro);
        $this->sesionComo('evaluado', $idOtro);

        $this->get("/evaluaciones/{$this->idEvaluacion}/informe")->assertForbidden();
    }
}
