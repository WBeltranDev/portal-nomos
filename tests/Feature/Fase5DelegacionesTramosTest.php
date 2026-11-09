<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaDatosSerag;
use Tests\TestCase;

/**
 * Fase 5 (v1.4) — Delegaciones, tramos de 31 días y notas ponderadas.
 * Acta de Acuerdos del 29-10-2026 y PLAN-CAMBIOS-SERAG.
 *
 * 5.1 Evaluador del delegado (Regla de Oro: nunca se autoevalúa, decide si necesita nuevo evaluador).
 * 5.2 Delegar cargo vacante (permite es_vacante = 1, omite tramo pausa del titular).
 * 5.3 Delegación sin fecha de fin (arranca corta; comando diario la pasa a larga al día 31).
 * 5.4 Traslado que conserva la concertación (mismo cargo precarga compromisos).
 * 5.5 Tramos desde 31 días (<= 30 días no genera evaluación separada; acumula días).
 * 5.6 Vacante al trasladar y visualización de ponderación (% peso y fórmula explícita).
 */
class Fase5DelegacionesTramosTest extends TestCase
{
    use CreaDatosSerag;

    private int $idAdmin;
    private int $idPeriodo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarDatosSerag();

        $this->idAdmin = $this->crearUsuarioPrueba('ADMINISTRADOR');

        $hoy = Carbon::today();
        $this->idPeriodo = DB::table('periodo')->insertGetId([
            'id_usuario_apertura' => $this->idAdmin,
            'sistema' => 'RENDIMIENTO_LABORAL',
            'anio' => 2099,
            'semestre' => 2,
            'fecha_inicio' => $hoy->copy()->subDays(150)->toDateString(),
            'fecha_fin' => $hoy->copy()->addDays(90)->toDateString(),
            'estado' => 'ABIERTO',
        ]);

        session(['usuario_autenticado' => [
            'id_usuario' => $this->idAdmin,
            'rol_activo' => 'admin',
            'roles' => ['admin'],
        ]]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->finalizarDatosSerag();
        parent::tearDown();
    }

    private function calificarEvaluacion(int $idEvaluacion, float $nota = 4.0): void
    {
        DB::table('compromiso')->insert([
            'id_evaluacion' => $idEvaluacion,
            'numero_orden' => 1,
            'descripcion' => 'Compromiso de prueba',
            'porcentaje_peso' => 100,
            'calificacion_definitiva' => $nota,
        ]);
        DB::table('evaluacion')->where('id_evaluacion', $idEvaluacion)->update([
            'estado' => 'CALIFICADA',
            'fase_actual' => 5,
            'calificacion_final' => $nota,
        ]);
    }

    private function crearEvaluacionConConcertacion(int $idEvaluado, int $idEvaluador, string $tipo = 'SEMESTRE_2'): int
    {
        return DB::table('evaluacion')->insertGetId([
            'id_periodo' => $this->idPeriodo,
            'id_vinc_evaluado' => $idEvaluado,
            'id_vinc_evaluador' => $idEvaluador,
            'tipo_evaluacion' => $tipo,
            'fase_actual' => 4,
            'concertacion_firmada' => 1,
            'estado' => 'EN_PROCESO',
        ]);
    }

    // =========================================================================
    // 5.1 Evaluador del delegado (Regla de Oro)
    // =========================================================================

    public function test_delegacion_larga_sin_necesidad_de_nuevo_evaluador_mantiene_evaluador_habitual_sin_tramo_para_delegado(): void
    {
        [$idTitular] = $this->crearFuncionarioPrueba('Titular', 'Uno');
        [$idDelegado] = $this->crearFuncionarioPrueba('Delegado', 'Uno');
        [$idJefeDelegado] = $this->crearFuncionarioPrueba('Jefe', 'Delegado', 'EVALUADOR');
        [$idEvaluado] = $this->crearFuncionarioPrueba('Evaluado', 'Uno');

        $vincTitular = $this->crearVinculacionPrueba($idTitular, true);
        $vincDelegado = $this->crearVinculacionPrueba($idDelegado, false);
        $vincJefeDelegado = $this->crearVinculacionPrueba($idJefeDelegado, true);
        $vincEvaluado = $this->crearVinculacionPrueba($idEvaluado, false);

        // Titular evalúa a Evaluado
        $this->asignarEvaluadorPrueba($vincTitular, $vincEvaluado);
        $evalTitular = $this->crearEvaluacionConConcertacion($vincEvaluado, $vincTitular);

        // JefeDelegado evalúa a Delegado (el titular NO es su jefe)
        $this->asignarEvaluadorPrueba($vincJefeDelegado, $vincDelegado);
        $evalDelegado = $this->crearEvaluacionConConcertacion($vincDelegado, $vincJefeDelegado);

        // Se registra delegación de 60 días indicando que NO necesita nuevo evaluador
        $inicio = Carbon::today()->toDateString();
        $fin = Carbon::today()->addDays(59)->toDateString();

        $resp = $this->post('/admin/delegaciones', [
            'id_vinc_delegante' => $vincTitular,
            'id_vinc_delegado' => $vincDelegado,
            'fecha_inicio' => $inicio,
            'fecha_fin' => $fin,
            'necesita_nuevo_evaluador' => 0,
            'motivo' => 'Comisión de servicios',
        ]);

        $resp->assertSessionHasNoErrors();

        $delegacion = DB::table('delegacion')
            ->where('id_vinc_delegante', $vincTitular)
            ->where('id_vinc_delegado', $vincDelegado)
            ->orderByDesc('id_delegacion')
            ->first();

        $this->assertNotNull($delegacion);
        $this->assertSame(1, (int) $delegacion->es_larga);
        $this->assertSame(0, (int) $delegacion->necesita_nuevo_evaluador);
        $this->assertNull($delegacion->id_vinc_superior_delegado);

        // Se verifica que NO se programó traslado para el delegado
        $trasladoDelegado = DB::table('traslado')
            ->where('id_delegacion', $delegacion->id_delegacion)
            ->where('id_vinc_funcionario', $vincDelegado)
            ->first();
        $this->assertNull($trasladoDelegado, 'No se debe cortar tramo para el delegado si no necesita nuevo evaluador.');

        // Pero sí se programó traslado para el evaluado que pasa al delegado
        $trasladoEvaluado = DB::table('traslado')
            ->where('id_delegacion', $delegacion->id_delegacion)
            ->where('id_vinc_funcionario', $vincEvaluado)
            ->first();
        $this->assertNotNull($trasladoEvaluado);
        $this->assertSame($vincDelegado, (int) $trasladoEvaluado->id_vinc_evaluador_nuevo);
    }

    public function test_delegacion_larga_con_necesidad_de_nuevo_evaluador_programa_tramo_para_delegado(): void
    {
        [$idTitular] = $this->crearFuncionarioPrueba('Titular', 'Dos');
        [$idDelegado] = $this->crearFuncionarioPrueba('Delegado', 'Dos');
        [$idJefeDelegado] = $this->crearFuncionarioPrueba('Jefe', 'Habitual', 'EVALUADOR');
        [$idSuperior] = $this->crearFuncionarioPrueba('Superior', 'Nuevo', 'EVALUADOR');
        [$idEvaluado] = $this->crearFuncionarioPrueba('Evaluado', 'Dos');

        $vincTitular = $this->crearVinculacionPrueba($idTitular, true);
        $vincDelegado = $this->crearVinculacionPrueba($idDelegado, false);
        $vincJefeDelegado = $this->crearVinculacionPrueba($idJefeDelegado, true);
        $vincSuperior = $this->crearVinculacionPrueba($idSuperior, true);
        $vincEvaluado = $this->crearVinculacionPrueba($idEvaluado, false);

        $this->asignarEvaluadorPrueba($vincTitular, $vincEvaluado);
        $this->crearEvaluacionConConcertacion($vincEvaluado, $vincTitular);

        $this->asignarEvaluadorPrueba($vincJefeDelegado, $vincDelegado);
        $this->crearEvaluacionConConcertacion($vincDelegado, $vincJefeDelegado);

        $inicio = Carbon::today()->toDateString();
        $fin = Carbon::today()->addDays(59)->toDateString();

        $resp = $this->post('/admin/delegaciones', [
            'id_vinc_delegante' => $vincTitular,
            'id_vinc_delegado' => $vincDelegado,
            'fecha_inicio' => $inicio,
            'fecha_fin' => $fin,
            'necesita_nuevo_evaluador' => 1,
            'id_vinc_superior_delegado' => $vincSuperior,
            'motivo' => 'Licencia',
        ]);

        $resp->assertSessionHasNoErrors();

        $delegacion = DB::table('delegacion')
            ->where('id_vinc_delegante', $vincTitular)
            ->where('id_vinc_delegado', $vincDelegado)
            ->orderByDesc('id_delegacion')
            ->first();

        $this->assertNotNull($delegacion);
        $this->assertSame(1, (int) $delegacion->necesita_nuevo_evaluador);
        $this->assertSame($vincSuperior, (int) $delegacion->id_vinc_superior_delegado);

        // Se programó traslado para el delegado hacia el nuevo superior
        $trasladoDelegado = DB::table('traslado')
            ->where('id_delegacion', $delegacion->id_delegacion)
            ->where('id_vinc_funcionario', $vincDelegado)
            ->first();
        $this->assertNotNull($trasladoDelegado);
        $this->assertSame($vincSuperior, (int) $trasladoDelegado->id_vinc_evaluador_nuevo);
    }

    public function test_delegacion_fuerza_nuevo_evaluador_si_evaluador_del_delegado_es_el_titular(): void
    {
        [$idTitular] = $this->crearFuncionarioPrueba('Titular', 'Tres');
        [$idDelegado] = $this->crearFuncionarioPrueba('Delegado', 'Tres');

        $vincTitular = $this->crearVinculacionPrueba($idTitular, true);
        $vincDelegado = $this->crearVinculacionPrueba($idDelegado, false);

        // El titular es el evaluador habitual del delegado (Regla 1)
        $this->asignarEvaluadorPrueba($vincTitular, $vincDelegado);
        $this->crearEvaluacionConConcertacion($vincDelegado, $vincTitular);

        $inicio = Carbon::today()->toDateString();
        $fin = Carbon::today()->addDays(59)->toDateString();

        // Intenta indicar que NO necesita nuevo evaluador
        $resp = $this->post('/admin/delegaciones', [
            'id_vinc_delegante' => $vincTitular,
            'id_vinc_delegado' => $vincDelegado,
            'fecha_inicio' => $inicio,
            'fecha_fin' => $fin,
            'necesita_nuevo_evaluador' => 0,
            'motivo' => 'Licencia',
        ]);

        // Debe fallar porque no puede autoevaluarse
        $resp->assertSessionHasErrors('delegaciones');
    }

    // =========================================================================
    // 5.2 Delegar cargo vacante
    // =========================================================================

    public function test_delegacion_de_cargo_vacante_permite_registro_y_omite_pausa_del_titular(): void
    {
        [$idTitular] = $this->crearFuncionarioPrueba('Cargo', 'Vacante');
        [$idDelegado] = $this->crearFuncionarioPrueba('Delegado', 'Vacante');
        [$idJefeDelegado] = $this->crearFuncionarioPrueba('Jefe', 'HabitualVacante', 'EVALUADOR');
        [$idSuperior] = $this->crearFuncionarioPrueba('Superior', 'Vacante', 'EVALUADOR');
        [$idEvaluado] = $this->crearFuncionarioPrueba('Subordinado', 'Vacante');

        $vincVacante = $this->crearVinculacionPrueba($idTitular, true);
        $vincDelegado = $this->crearVinculacionPrueba($idDelegado, false);
        $vincJefeDelegado = $this->crearVinculacionPrueba($idJefeDelegado, true);
        $vincSuperior = $this->crearVinculacionPrueba($idSuperior, true);
        $vincEvaluado = $this->crearVinculacionPrueba($idEvaluado, false);

        // Marcar la vinculación del cargo como VACANTE
        DB::table('vinculacion')->where('id_vinculacion', $vincVacante)->update([
            'es_vacante' => 1,
            'activa' => 0,
        ]);

        // Personas asignadas al cargo vacante
        $this->asignarEvaluadorPrueba($vincVacante, $vincEvaluado);
        $this->crearEvaluacionConConcertacion($vincEvaluado, $vincVacante);

        // Delegado tiene su propio evaluador
        $this->asignarEvaluadorPrueba($vincJefeDelegado, $vincDelegado);
        $this->crearEvaluacionConConcertacion($vincDelegado, $vincJefeDelegado);

        $inicio = Carbon::today()->toDateString();
        $fin = Carbon::today()->addDays(59)->toDateString();

        $resp = $this->post('/admin/delegaciones', [
            'id_vinc_delegante' => $vincVacante,
            'id_vinc_delegado' => $vincDelegado,
            'fecha_inicio' => $inicio,
            'fecha_fin' => $fin,
            'necesita_nuevo_evaluador' => 1,
            'id_vinc_superior_delegado' => $vincSuperior,
            'motivo' => 'Suplencia de cargo vacante',
        ]);

        $resp->assertSessionHasNoErrors();

        $delegacion = DB::table('delegacion')->where('id_vinc_delegante', $vincVacante)->first();
        $this->assertNotNull($delegacion);

        // Verificar que NO existe traslado de tipo PAUSA para el cargo vacante
        $pausaTitular = DB::table('traslado')
            ->where('id_delegacion', $delegacion->id_delegacion)
            ->where('id_vinc_funcionario', $vincVacante)
            ->where('tipo', 'PAUSA')
            ->first();
        $this->assertNull($pausaTitular, 'Al delegar un cargo vacante no debe crearse tramo de pausa para el titular.');

        // Y el subordinado sí tiene su traslado programado al delegado
        $trasladoSubordinado = DB::table('traslado')
            ->where('id_delegacion', $delegacion->id_delegacion)
            ->where('id_vinc_funcionario', $vincEvaluado)
            ->first();
        $this->assertNotNull($trasladoSubordinado);
        $this->assertSame($vincDelegado, (int) $trasladoSubordinado->id_vinc_evaluador_nuevo);
    }

    // =========================================================================
    // 5.3 Delegación sin fecha de fin (Corrección caso Gledy)
    // =========================================================================

    public function test_delegacion_sin_fecha_fin_arranca_como_corta_y_comando_la_convierte_a_larga_al_dia_31(): void
    {
        [$idTitular] = $this->crearFuncionarioPrueba('Titular', 'Abierta');
        [$idDelegado] = $this->crearFuncionarioPrueba('Delegado', 'Abierta');
        [$idEvaluado] = $this->crearFuncionarioPrueba('Evaluado', 'Abierta');

        $vincTitular = $this->crearVinculacionPrueba($idTitular, true);
        $vincDelegado = $this->crearVinculacionPrueba($idDelegado, false);
        $vincEvaluado = $this->crearVinculacionPrueba($idEvaluado, false);

        $this->asignarEvaluadorPrueba($vincTitular, $vincEvaluado);
        $eval = $this->crearEvaluacionConConcertacion($vincEvaluado, $vincTitular);

        $fechaInicioReal = Carbon::today()->subDays(10)->toDateString();

        // Se registra delegación SIN fecha de fin
        $resp = $this->post('/admin/delegaciones', [
            'id_vinc_delegante' => $vincTitular,
            'id_vinc_delegado' => $vincDelegado,
            'fecha_inicio' => $fechaInicioReal,
            'fecha_fin' => null,
            'motivo' => 'Caso Gledy - Delegación abierta',
        ]);

        $resp->assertSessionHasNoErrors();

        $delegacion = DB::table('delegacion')->where('id_vinc_delegante', $vincTitular)->first();
        $this->assertNotNull($delegacion);
        // Debe arrancar como CORTA y ACTIVA
        $this->assertSame(0, (int) ($delegacion->es_larga ?? 0));
        $this->assertSame('ACTIVA', $delegacion->estado);

        // Sin tramos creados en traslado
        $this->assertSame(0, DB::table('traslado')->where('id_delegacion', $delegacion->id_delegacion)->count());

        // Al día 10 (hoy), ejecutar el comando diario no la cambia
        $this->artisan('delegaciones:revisar-abiertas')->assertExitCode(0);
        $delegacionActualizada = DB::table('delegacion')->where('id_delegacion', $delegacion->id_delegacion)->first();
        $this->assertSame(0, (int) ($delegacionActualizada->es_larga ?? 0));

        // Avanzar el tiempo hasta el día 31 desde la fecha de inicio
        Carbon::setTestNow(Carbon::parse($fechaInicioReal)->addDays(30)); // día 31 (inclusive)

        $this->artisan('delegaciones:revisar-abiertas')->assertExitCode(0);

        $delegacionActualizada = DB::table('delegacion')->where('id_delegacion', $delegacion->id_delegacion)->first();
        $this->assertSame(1, (int) $delegacionActualizada->es_larga, 'Debe convertirse a larga al llegar al día 31.');
        $this->assertSame('PROGRAMADA', $delegacionActualizada->estado);

        // Debe haber generado tramos con fecha = fecha_inicio real
        $tramos = DB::table('traslado')->where('id_delegacion', $delegacion->id_delegacion)->get();
        $this->assertNotEmpty($tramos);
        $this->assertSame($fechaInicioReal, $tramos->first()->fecha_traslado);
    }

    public function test_delegacion_sin_fecha_fin_cerrada_antes_del_dia_31_se_finaliza_como_corta(): void
    {
        [$idTitular] = $this->crearFuncionarioPrueba('Titular', 'CierreCorta');
        [$idDelegado] = $this->crearFuncionarioPrueba('Delegado', 'CierreCorta');
        [$idEvaluado] = $this->crearFuncionarioPrueba('Evaluado', 'CierreCorta');

        $vincTitular = $this->crearVinculacionPrueba($idTitular, true);
        $vincDelegado = $this->crearVinculacionPrueba($idDelegado, false);
        $vincEvaluado = $this->crearVinculacionPrueba($idEvaluado, false);

        $this->asignarEvaluadorPrueba($vincTitular, $vincEvaluado);
        $this->crearEvaluacionConConcertacion($vincEvaluado, $vincTitular);

        $inicio = Carbon::today()->toDateString();
        $this->post('/admin/delegaciones', [
            'id_vinc_delegante' => $vincTitular,
            'id_vinc_delegado' => $vincDelegado,
            'fecha_inicio' => $inicio,
            'fecha_fin' => null,
            'motivo' => 'Corta a finalizar',
        ])->assertSessionHasNoErrors();

        $delegacion = DB::table('delegacion')->where('id_vinc_delegante', $vincTitular)->first();

        // Se finaliza al día 15
        Carbon::setTestNow(Carbon::parse($inicio)->addDays(15));
        $resp = $this->postJson("/admin/delegaciones/{$delegacion->id_delegacion}/finalizar");
        $resp->assertOk();

        $delFin = DB::table('delegacion')->where('id_delegacion', $delegacion->id_delegacion)->first();
        $this->assertSame('FINALIZADA', $delFin->estado);
        $this->assertSame(0, (int) ($delFin->es_larga ?? 0));
        $this->assertSame(0, DB::table('traslado')->where('id_delegacion', $delegacion->id_delegacion)->count());
    }

    // =========================================================================
    // 5.4 Traslado que conserva la concertación
    // =========================================================================

    public function test_traslado_sin_cambio_de_cargo_precarga_compromisos_en_nueva_evaluacion(): void
    {
        [$idEvaluado] = $this->crearFuncionarioPrueba('Evaluado', 'TrasladoComp');
        [$idEvaluador1] = $this->crearFuncionarioPrueba('Evaluador', 'UnoTraslado', 'EVALUADOR');
        [$idEvaluador2] = $this->crearFuncionarioPrueba('Evaluador', 'DosTraslado', 'EVALUADOR');

        $vincEvaluado = $this->crearVinculacionPrueba($idEvaluado, false);
        $vincEvaluador1 = $this->crearVinculacionPrueba($idEvaluador1, true);
        $vincEvaluador2 = $this->crearVinculacionPrueba($idEvaluador2, true);

        $cargoOriginal = DB::table('vinculacion')->where('id_vinculacion', $vincEvaluado)->value('cargo');

        $this->asignarEvaluadorPrueba($vincEvaluador1, $vincEvaluado);
        $evalOrigen = $this->crearEvaluacionConConcertacion($vincEvaluado, $vincEvaluador1);

        // Agregamos compromisos con metas
        $idComp = DB::table('compromiso')->insertGetId([
            'id_evaluacion' => $evalOrigen,
            'numero_orden' => 1,
            'descripcion' => 'Compromiso institucional de origen',
            'porcentaje_peso' => 100,
            'calificacion_definitiva' => 4.5,
        ]);
        DB::table('compromiso_meta')->insert([
            'id_compromiso' => $idComp,
            'meta' => 'Meta número 1 de prueba',
        ]);

        // Se programa traslado que mantiene el mismo cargo (o no cambia cargo)
        $respProg = $this->post('/admin/traslados', [
            'id_vinc_funcionario' => $vincEvaluado,
            'id_vinc_evaluador_nuevo' => $vincEvaluador2,
            'fecha_traslado' => Carbon::today()->toDateString(),
            'cargo_nuevo' => $cargoOriginal, // Mismo cargo
            'motivo' => 'Cambio de evaluador conservando cargo',
        ]);
        $respProg->assertSessionHasNoErrors();

        $traslado = DB::table('traslado')->where('id_vinc_funcionario', $vincEvaluado)->first();
        $this->assertNotNull($traslado);

        // Se califica la evaluación de origen para habilitar confirmación
        DB::table('evaluacion')->where('id_evaluacion', $evalOrigen)->update(['estado' => 'CALIFICADA']);

        // Se confirma el traslado
        $respConf = $this->postJson("/admin/traslados/{$traslado->id_traslado}/confirmar");
        if ($respConf->status() !== 200) {
            $this->fail('Error al confirmar: ' . json_encode($respConf->json()));
        }
        $respConf->assertOk();

        // La nueva evaluación parcial de destino debe tener los compromisos precargados
        $evalDestino = DB::table('evaluacion')
            ->where('id_periodo', $this->idPeriodo)
            ->where('id_vinc_evaluado', $vincEvaluado)
            ->where('id_vinc_evaluador', $vincEvaluador2)
            ->where('tipo_evaluacion', 'PARCIAL')
            ->first();

        $this->assertNotNull($evalDestino);
        $this->assertSame(0, (int) $evalDestino->concertacion_firmada, 'Debe quedar pendiente de firma.');

        $compCopiados = DB::table('compromiso')->where('id_evaluacion', $evalDestino->id_evaluacion)->get();
        $this->assertCount(1, $compCopiados);
        $this->assertSame('Compromiso institucional de origen', $compCopiados->first()->descripcion);
        $this->assertSame((int) $idComp, (int) $compCopiados->first()->id_compromiso_origen);
    }

    // =========================================================================
    // 5.5 Tramos desde 31 días
    // =========================================================================

    public function test_tramo_de_30_dias_o_menos_no_exige_calificacion_y_acumula_sus_dias(): void
    {
        [$idEvaluado] = $this->crearFuncionarioPrueba('Evaluado', 'TramoCorto');
        [$idEvaluador1] = $this->crearFuncionarioPrueba('Evaluador', 'Corto1', 'EVALUADOR');
        [$idEvaluador2] = $this->crearFuncionarioPrueba('Evaluador', 'Corto2', 'EVALUADOR');

        $vincEvaluado = $this->crearVinculacionPrueba($idEvaluado, false);
        $vincEvaluador1 = $this->crearVinculacionPrueba($idEvaluador1, true);
        $vincEvaluador2 = $this->crearVinculacionPrueba($idEvaluador2, true);

        $this->asignarEvaluadorPrueba($vincEvaluador1, $vincEvaluado);
        $evalOrigen = $this->crearEvaluacionConConcertacion($vincEvaluado, $vincEvaluador1);
        // Ojo: evaluación de origen NO está calificada (sigue EN_PROCESO)

        // El período inició hace 150 días en el setUp.
        // Creamos un período específico para esta prueba donde hayan transcurrido 20 días:
        $hoy = Carbon::today();
        $periodoCorto = DB::table('periodo')->insertGetId([
            'id_usuario_apertura' => $this->idAdmin,
            'sistema' => 'ACUERDO_GESTION',
            'anio' => 2099,
            'semestre' => 1,
            'fecha_inicio' => $hoy->copy()->subDays(20)->toDateString(),
            'fecha_fin' => $hoy->copy()->addDays(80)->toDateString(),
            'estado' => 'ABIERTO',
        ]);
        DB::table('vinculacion')->where('id_vinculacion', $vincEvaluado)->update(['sistema_evaluacion' => 'ACUERDO_GESTION']);
        DB::table('evaluacion')->where('id_evaluacion', $evalOrigen)->update(['id_periodo' => $periodoCorto]);

        // Programar traslado hoy: origen duró 20 días (<= 30 días)
        $this->post('/admin/traslados', [
            'id_vinc_funcionario' => $vincEvaluado,
            'id_vinc_evaluador_nuevo' => $vincEvaluador2,
            'fecha_traslado' => $hoy->toDateString(),
            'motivo' => 'Traslado con tramo corto de origen',
        ])->assertSessionHasNoErrors();

        $traslado = DB::table('traslado')->where('id_vinc_funcionario', $vincEvaluado)->orderByDesc('id_traslado')->first();
        $this->assertSame(20, (int) $traslado->dias_laborados);

        // Al confirmar, NO debe exigir calificar el origen porque dura <= 30 días
        $respConf = $this->postJson("/admin/traslados/{$traslado->id_traslado}/confirmar");
        $respConf->assertOk();

        // Y los 20 días de origen se acumulan en el destino (destino normal eran 81 días, con 20 acumulados = 101)
        $evalDestino = DB::table('evaluacion')
            ->where('id_periodo', $periodoCorto)
            ->where('id_vinc_evaluado', $vincEvaluado)
            ->where('id_vinc_evaluador', $vincEvaluador2)
            ->first();

        $this->assertNotNull($evalDestino);
        $this->assertSame(101, (int) $evalDestino->dias_laborados, 'Los 20 días del tramo corto de origen deben acumularse al tramo destino.');
    }

    public function test_tramo_de_31_dias_o_mas_exige_evaluacion_independiente(): void
    {
        [$idEvaluado] = $this->crearFuncionarioPrueba('Evaluado', 'TramoLargo');
        [$idEvaluador1] = $this->crearFuncionarioPrueba('Evaluador', 'Largo1', 'EVALUADOR');
        [$idEvaluador2] = $this->crearFuncionarioPrueba('Evaluador', 'Largo2', 'EVALUADOR');

        $vincEvaluado = $this->crearVinculacionPrueba($idEvaluado, false);
        $vincEvaluador1 = $this->crearVinculacionPrueba($idEvaluador1, true);
        $vincEvaluador2 = $this->crearVinculacionPrueba($idEvaluador2, true);

        $this->asignarEvaluadorPrueba($vincEvaluador1, $vincEvaluado);
        $evalOrigen = $this->crearEvaluacionConConcertacion($vincEvaluado, $vincEvaluador1);

        $hoy = Carbon::today();
        $periodoLargo = DB::table('periodo')->insertGetId([
            'id_usuario_apertura' => $this->idAdmin,
            'sistema' => 'ACUERDO_GESTION',
            'anio' => 2099,
            'semestre' => 2,
            'fecha_inicio' => $hoy->copy()->subDays(40)->toDateString(),
            'fecha_fin' => $hoy->copy()->addDays(80)->toDateString(),
            'estado' => 'ABIERTO',
        ]);
        DB::table('vinculacion')->where('id_vinculacion', $vincEvaluado)->update(['sistema_evaluacion' => 'ACUERDO_GESTION']);
        DB::table('evaluacion')->where('id_evaluacion', $evalOrigen)->update(['id_periodo' => $periodoLargo]);

        // Programar traslado hoy: origen duró 40 días (>= 31 días)
        $this->post('/admin/traslados', [
            'id_vinc_funcionario' => $vincEvaluado,
            'id_vinc_evaluador_nuevo' => $vincEvaluador2,
            'fecha_traslado' => $hoy->toDateString(),
            'motivo' => 'Traslado con tramo largo',
        ])->assertSessionHasNoErrors();

        $traslado = DB::table('traslado')->where('id_vinc_funcionario', $vincEvaluado)->orderByDesc('id_traslado')->first();
        $this->assertSame(40, (int) $traslado->dias_laborados);

        // Sin calificar, debe ser rechazado
        $respConf = $this->postJson("/admin/traslados/{$traslado->id_traslado}/confirmar");
        $respConf->assertStatus(422);

        // Una vez calificado, se confirma y cada uno tiene su propia evaluación
        $this->calificarEvaluacion($evalOrigen, 4.2);
        $respConf2 = $this->postJson("/admin/traslados/{$traslado->id_traslado}/confirmar");
        $respConf2->assertOk();
    }

    // =========================================================================
    // 5.6 Vacante al trasladar & Visualización de ponderación
    // =========================================================================

    public function test_tramos_evaluado_calcula_pesos_porcentuales_y_formula_explicita(): void
    {
        [$idEvaluado] = $this->crearFuncionarioPrueba('Evaluado', 'FormulaPond');
        [$idEvaluador1] = $this->crearFuncionarioPrueba('Evaluador', 'Pond1', 'EVALUADOR');
        [$idEvaluador2] = $this->crearFuncionarioPrueba('Evaluador', 'Pond2', 'EVALUADOR');

        $vincEvaluado = $this->crearVinculacionPrueba($idEvaluado, false);
        $vincEvaluador1 = $this->crearVinculacionPrueba($idEvaluador1, true);
        $vincEvaluador2 = $this->crearVinculacionPrueba($idEvaluador2, true);

        $eval1 = $this->crearEvaluacionConConcertacion($vincEvaluado, $vincEvaluador1, 'PARCIAL');
        DB::table('evaluacion')->where('id_evaluacion', $eval1)->update(['dias_laborados' => 60]);
        $this->calificarEvaluacion($eval1, 4.0);

        $eval2 = $this->crearEvaluacionConConcertacion($vincEvaluado, $vincEvaluador2, 'PARCIAL');
        DB::table('evaluacion')->where('id_evaluacion', $eval2)->update(['dias_laborados' => 90]);
        $this->calificarEvaluacion($eval2, 5.0);

        // Simular el registro en traslado para que tramosEvaluadoPorPeriodo lo identifique
        DB::table('traslado')->insert([
            'id_vinc_funcionario' => $vincEvaluado,
            'estado' => 'CONFIRMADO',
            'tipo' => 'CAMBIO',
            'id_periodo' => $this->idPeriodo,
            'id_evaluacion_origen' => $eval1,
            'id_evaluacion_destino' => $eval2,
            'id_vinc_evaluador_origen' => $vincEvaluador1,
            'id_vinc_evaluador_nuevo' => $vincEvaluador2,
            'fecha_traslado' => Carbon::today()->toDateString(),
            'dias_laborados' => 60,
        ]);

        $tramosInfo = tramosEvaluadoPorPeriodo($idEvaluado);
        $this->assertNotEmpty($tramosInfo);

        $periodoInfo = $tramosInfo[0];
        $this->assertArrayHasKey('formula_explicita', $periodoInfo);
        $this->assertStringContainsString('60', $periodoInfo['formula_explicita']);
        $this->assertStringContainsString('90', $periodoInfo['formula_explicita']);
        $this->assertStringContainsString('150', $periodoInfo['formula_explicita']);

        // Verificar porcentajes en cada tramo
        $this->assertEquals(40.0, $periodoInfo['tramos'][0]['peso_porcentaje']);
        $this->assertEquals(60.0, $periodoInfo['tramos'][1]['peso_porcentaje']);
    }

    public function test_aviso_declarar_vacante_al_trasladar_con_cambio_de_cargo(): void
    {
        [$idEvaluador] = $this->crearFuncionarioPrueba('Jefe', 'ConSubordinados', 'EVALUADOR');
        [$idSubordinado] = $this->crearFuncionarioPrueba('Subordinado', 'QuedaSinJefe');
        [$idEvaluadorDelJefe] = $this->crearFuncionarioPrueba('Jefe', 'DelJefe', 'EVALUADOR');
        [$idNuevoJefe] = $this->crearFuncionarioPrueba('Nuevo', 'Superior', 'EVALUADOR');

        $vincJefe = $this->crearVinculacionPrueba($idEvaluador, true);
        $vincSub = $this->crearVinculacionPrueba($idSubordinado, false);
        $vincEvaluadorDelJefe = $this->crearVinculacionPrueba($idEvaluadorDelJefe, true);
        $vincNuevoJefe = $this->crearVinculacionPrueba($idNuevoJefe, true);

        // VincJefe tiene personas a cargo
        $this->asignarEvaluadorPrueba($vincJefe, $vincSub);

        // VincJefe tiene su evaluador asignado y evaluación en proceso
        $this->asignarEvaluadorPrueba($vincEvaluadorDelJefe, $vincJefe);
        $evalJefe = $this->crearEvaluacionConConcertacion($vincJefe, $vincEvaluadorDelJefe);

        // Se programa traslado de VincJefe cambiando de cargo
        $this->post('/admin/traslados', [
            'id_vinc_funcionario' => $vincJefe,
            'id_vinc_evaluador_nuevo' => $vincNuevoJefe,
            'fecha_traslado' => Carbon::today()->toDateString(),
            'cargo_nuevo' => 'DIRECTOR TECNICO',
            'area_nuevo' => 'DIRECCION',
            'motivo' => 'Ascenso',
        ])->assertSessionHasNoErrors();

        $traslado = DB::table('traslado')->where('id_vinc_funcionario', $vincJefe)->first();

        // Se califica la evaluación para permitir confirmación
        $this->calificarEvaluacion($evalJefe, 4.8);

        // Al confirmar, debe registrarse aviso a TH para declarar la vacante y listar a los subordinados
        $resp = $this->postJson("/admin/traslados/{$traslado->id_traslado}/confirmar");
        $resp->assertOk();

        $json = $resp->json();
        $this->assertTrue(isset($json['aviso_vacante']) || isset($json['message']));

        // Debe haber notificación institucional para TH
        $notif = DB::table('notificacion')
            ->where('tipo', 'VACANCIA_SUGERIDA')
            ->orWhere('mensaje', 'LIKE', '%vacante%')
            ->first();
        $this->assertNotNull($notif);
        $this->assertStringContainsString('vacante', strtolower($notif->mensaje));
    }
}
