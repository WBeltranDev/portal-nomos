<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaDatosSerag;
use Tests\TestCase;

/**
 * Acta 29-10-2026, II y IV: avisar al evaluado que revise y descargue el
 * documento de calificación antes de interponer un recurso, y que el recurso
 * debe estar debidamente sustentado.
 */
class AvisoAntesDelRecursoTest extends TestCase
{
    use CreaDatosSerag;

    private int $idEvaluacion;
    private int $idUsuarioEvaluado;
    private int $idFuncionarioEvaluado;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarDatosSerag();

        [$this->idFuncionarioEvaluado, $this->idUsuarioEvaluado] = $this->crearFuncionarioPrueba('Tmp', 'Evaluado');
        [$idEvaluador] = $this->crearFuncionarioPrueba('Tmp', 'Evaluador', 'EVALUADOR');
        $vincEvaluado = $this->crearVinculacionPrueba($this->idFuncionarioEvaluado);
        $vincEvaluador = $this->crearVinculacionPrueba($idEvaluador, true);
        $idPeriodo = $this->crearPeriodoPrueba('RENDIMIENTO_LABORAL', 2099, 1, '2099-02-01', '2099-07-31');

        $this->idEvaluacion = $this->crearEvaluacionPrueba($idPeriodo, $vincEvaluado, $vincEvaluador, [
            'fase_actual' => 4,
            'concertacion_firmada' => 1,
        ]);
        DB::table('compromiso')->insert([
            'id_evaluacion' => $this->idEvaluacion,
            'numero_orden' => 1,
            'descripcion' => 'Compromiso de prueba',
            'porcentaje_peso' => 100,
            'calificacion_definitiva' => 4.5,
        ]);
    }

    protected function tearDown(): void
    {
        $this->finalizarDatosSerag();
        parent::tearDown();
    }

    private function avisosDelEvaluado()
    {
        return DB::table('notificacion')
            ->where('id_usuario_destino', $this->idUsuarioEvaluado)
            ->where('tipo', 'EVALUACION_CALIFICADA')
            ->get();
    }

    public function test_al_calificar_se_avisa_al_evaluado_que_revise_el_documento(): void
    {
        $this->sesionComo('admin', null);

        $this->postJson("/evaluaciones/{$this->idEvaluacion}/calcular-final")->assertOk();

        $avisos = $this->avisosDelEvaluado();
        $this->assertCount(1, $avisos);
        $this->assertStringContainsString('Revisa y descarga el documento de calificación', $avisos->first()->mensaje);
        $this->assertStringContainsString('debidamente sustentado', $avisos->first()->mensaje);
        $this->assertSame('evaluaciones', $avisos->first()->seccion);
    }

    public function test_un_recalculo_no_repite_el_aviso(): void
    {
        $this->sesionComo('admin', null);

        $this->postJson("/evaluaciones/{$this->idEvaluacion}/calcular-final")->assertOk();
        $this->postJson("/evaluaciones/{$this->idEvaluacion}/calcular-final")->assertOk();

        $this->assertCount(1, $this->avisosDelEvaluado());
    }

    public function test_el_panel_del_evaluado_incluye_el_aviso_junto_a_los_recursos(): void
    {
        $this->sesionComo('evaluado', $this->idFuncionarioEvaluado, $this->idUsuarioEvaluado);

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('Antes de interponer un recurso:', false)
            ->assertSee('Descargar calificación');
    }
}
