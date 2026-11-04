<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaDatosSerag;
use Tests\TestCase;

/**
 * Acta 29-10-2026, hallazgo VII. La delegación Gledy -> Lizarazo se registró
 * antes de crear el período AG del segundo semestre. Como ningún período
 * abierto contenía la fecha, programarTraslado tomó el período anterior (aún
 * abierto) y ligó el cambio a la evaluación ya calificada del primer semestre,
 * cuya nota desapareció de la matriz mientras el cambio estuvo pendiente.
 */
class PeriodoDelTrasladoTest extends TestCase
{
    use CreaDatosSerag;

    private int $idPeriodoAnterior;
    private int $idVincEvaluado;
    private int $idVincEvaluadorOrigen;
    private int $idVincEvaluadorNuevo;
    private int $idEvaluacionAnterior;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarDatosSerag();

        $hoy = Carbon::today();
        // Semestre anterior, terminado pero todavía abierto (como 2026-A en producción).
        $this->idPeriodoAnterior = $this->crearPeriodoPrueba('ACUERDO_GESTION', 2099, 1,
            $hoy->copy()->subDays(200)->toDateString(), $hoy->copy()->subDays(20)->toDateString());

        [$idOrigen] = $this->crearFuncionarioPrueba('Tmp', 'Origen', 'EVALUADOR');
        [$idNuevo] = $this->crearFuncionarioPrueba('Tmp', 'Nuevo', 'EVALUADOR');
        [$idEvaluado] = $this->crearFuncionarioPrueba('Tmp', 'Evaluado');
        $this->idVincEvaluadorOrigen = $this->crearVinculacionPrueba($idOrigen, true, 'ACUERDO_GESTION');
        $this->idVincEvaluadorNuevo = $this->crearVinculacionPrueba($idNuevo, true, 'ACUERDO_GESTION');
        $this->idVincEvaluado = $this->crearVinculacionPrueba($idEvaluado, false, 'ACUERDO_GESTION');
        $this->asignarEvaluadorPrueba($this->idVincEvaluadorOrigen, $this->idVincEvaluado);

        $this->idEvaluacionAnterior = $this->crearEvaluacionPrueba($this->idPeriodoAnterior, $this->idVincEvaluado, $this->idVincEvaluadorOrigen, [
            'fase_actual' => 5,
            'concertacion_firmada' => 1,
            'estado' => 'CALIFICADA',
            'calificacion_final' => 5.0,
            'categoria_final' => 'SOBRESALIENTE',
        ]);
        DB::table('compromiso')->insert([
            'id_evaluacion' => $this->idEvaluacionAnterior,
            'numero_orden' => 1,
            'descripcion' => 'Compromiso de prueba',
            'porcentaje_peso' => 100,
            'calificacion_definitiva' => 5.0,
        ]);

        $this->sesionComo('admin', null);
    }

    protected function tearDown(): void
    {
        $this->finalizarDatosSerag();
        parent::tearDown();
    }

    private function registrarTraslado()
    {
        return $this->post('/admin/traslados', [
            'id_vinc_funcionario' => $this->idVincEvaluado,
            'id_vinc_evaluador_nuevo' => $this->idVincEvaluadorNuevo,
            'fecha_traslado' => Carbon::today()->toDateString(),
        ]);
    }

    private function notaSemestreAnterior(): ?float
    {
        $nota = obtenerNotaSemestreConsolidada($this->idVincEvaluado, 2099, 'ACUERDO_GESTION', 1);

        return isset($nota['nota']) ? (float) $nota['nota'] : null;
    }

    public function test_sin_periodo_abierto_para_la_fecha_se_rechaza_y_no_toca_el_semestre_anterior(): void
    {
        $antes = $this->notaSemestreAnterior();
        $this->assertNotNull($antes);

        $this->registrarTraslado()
            ->assertRedirect()
            ->assertSessionHasErrors('traslados');

        $this->assertStringContainsString('No hay un período abierto',
            session('errors')->first('traslados'));
        $this->assertSame(0, DB::table('traslado')->where('id_vinc_funcionario', $this->idVincEvaluado)->count(),
            'No debe quedar ningún traslado ligado al semestre anterior.');

        $this->assertSame($antes, $this->notaSemestreAnterior(), 'La nota del semestre anterior no cambia.');
    }

    public function test_una_delegacion_sin_periodo_abierto_para_su_fecha_se_rechaza_entera(): void
    {
        // Caso real del acta: delegación del titular (evaluador de origen) a otra persona.
        [$idSuperior] = $this->crearFuncionarioPrueba('Tmp', 'Superior', 'EVALUADOR');
        $vincSuperior = $this->crearVinculacionPrueba($idSuperior, true, 'ACUERDO_GESTION');
        $this->asignarEvaluadorPrueba($vincSuperior, $this->idVincEvaluadorNuevo);
        $antes = $this->notaSemestreAnterior();

        $this->post('/admin/delegaciones', [
            'id_vinc_delegante' => $this->idVincEvaluadorOrigen,
            'id_vinc_delegado' => $this->idVincEvaluadorNuevo,
            'fecha_inicio' => Carbon::today()->toDateString(),
            'id_vinc_superior_delegado' => $vincSuperior,
            'motivo' => 'Prueba',
        ])->assertRedirect()->assertSessionHasErrors('delegaciones');

        $this->assertStringContainsString('No hay un período abierto', session('errors')->first('delegaciones'));
        $this->assertSame(0, DB::table('delegacion')->where('id_vinc_delegante', $this->idVincEvaluadorOrigen)->count());
        $this->assertSame(0, DB::table('traslado')->where('id_vinc_funcionario', $this->idVincEvaluado)->count());
        $this->assertSame($antes, $this->notaSemestreAnterior());
    }

    public function test_con_el_periodo_creado_el_traslado_se_liga_al_semestre_de_su_fecha(): void
    {
        $hoy = Carbon::today();
        $idPeriodoActual = $this->crearPeriodoPrueba('ACUERDO_GESTION', 2099, 2,
            $hoy->copy()->subDays(19)->toDateString(), $hoy->copy()->addDays(150)->toDateString());

        $antes = $this->notaSemestreAnterior();
        $this->assertNotNull($antes);

        $this->registrarTraslado()->assertRedirect()->assertSessionHasNoErrors();

        $traslado = DB::table('traslado')->where('id_vinc_funcionario', $this->idVincEvaluado)->first();
        $this->assertSame($idPeriodoActual, (int) $traslado->id_periodo);
        $this->assertNotSame($this->idEvaluacionAnterior, (int) $traslado->id_evaluacion_origen);

        $this->assertSame($antes, $this->notaSemestreAnterior(), 'La nota del semestre anterior no cambia.');
    }
}
