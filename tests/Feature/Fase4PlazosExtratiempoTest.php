<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaDatosSerag;
use Tests\TestCase;

/**
 * Fase 4 (v1.3) — Plazos del período y extratiempo (Acta del 29-10-2026, Hallazgo X).
 *
 * 4.1 Fechas límite del período (fecha_fin_concertacion y fecha_fin_calificacion).
 * 4.2 Extratiempo de calificación (tipo CONCERTACION vs CALIFICACION).
 */
class Fase4PlazosExtratiempoTest extends TestCase
{
    use CreaDatosSerag;

    private int $idEvaluado;
    private int $idEvaluador;
    private int $vincEvaluado;
    private int $vincEvaluador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarDatosSerag();

        [$this->idEvaluado] = $this->crearFuncionarioPrueba('Evaluado', 'Plazos');
        [$this->idEvaluador] = $this->crearFuncionarioPrueba('Evaluador', 'Plazos', 'EVALUADOR');
        $this->vincEvaluado = $this->crearVinculacionPrueba($this->idEvaluado);
        $this->vincEvaluador = $this->crearVinculacionPrueba($this->idEvaluador, true);
        $this->asignarEvaluadorPrueba($this->vincEvaluador, $this->vincEvaluado);
    }

    protected function tearDown(): void
    {
        $this->finalizarDatosSerag();
        parent::tearDown();
    }

    private function crearPeriodoConFechas(?string $finConcertacion, ?string $finCalificacion): int
    {
        return DB::table('periodo')->insertGetId([
            'id_usuario_apertura' => $this->crearUsuarioPrueba('ADMINISTRADOR'),
            'sistema' => 'RENDIMIENTO_LABORAL',
            'anio' => 2099,
            'semestre' => 1,
            'fecha_inicio' => '2099-01-01',
            'fecha_fin' => '2099-12-31',
            'fecha_fin_concertacion' => $finConcertacion,
            'fecha_fin_calificacion' => $finCalificacion,
            'estado' => 'ABIERTO',
        ]);
    }

    private function otorgarExtratiempo(int $idEvaluacion, string $tipo, string $fechaLimite, bool $activo = true): int
    {
        return DB::table('concertacion_extratiempo')->insertGetId([
            'id_evaluacion' => $idEvaluacion,
            'tipo' => $tipo,
            'justificacion' => "Extratiempo de prueba {$tipo}",
            'autorizado_por' => 'Talento Humano Pruebas',
            'fecha_limite' => $fechaLimite,
            'activo' => $activo ? 1 : 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // --- 4.1 Fechas límite del período ---

    public function test_periodo_sin_fechas_limite_no_bloquea_concertacion(): void
    {
        $idPeriodo = $this->crearPeriodoConFechas(null, null);
        $idEvaluacion = $this->crearEvaluacionPrueba($idPeriodo, $this->vincEvaluado, $this->vincEvaluador, [
            'fase_actual' => 1,
            'concertacion_firmada' => 0,
        ]);

        $this->sesionComo('evaluador', $this->idEvaluador);

        $response = $this->postJson("/evaluaciones/{$idEvaluacion}/compromisos", [
            'descripcion' => 'Compromiso ordinario sin fecha límite',
            'porcentaje_peso' => 10,
            'metas' => ['Meta 1'],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('compromiso', [
            'id_evaluacion' => $idEvaluacion,
            'descripcion' => 'Compromiso ordinario sin fecha límite',
        ]);
    }

    public function test_concertacion_dentro_del_plazo_es_permitida(): void
    {
        $manana = date('Y-m-d', strtotime('+1 day'));
        $idPeriodo = $this->crearPeriodoConFechas($manana, null);
        $idEvaluacion = $this->crearEvaluacionPrueba($idPeriodo, $this->vincEvaluado, $this->vincEvaluador, [
            'fase_actual' => 1,
            'concertacion_firmada' => 0,
        ]);

        $this->sesionComo('evaluador', $this->idEvaluador);

        $response = $this->postJson("/evaluaciones/{$idEvaluacion}/compromisos", [
            'descripcion' => 'Compromiso dentro del plazo',
            'porcentaje_peso' => 10,
            'metas' => ['Meta a tiempo'],
        ]);

        $response->assertStatus(200);
    }

    public function test_concertacion_fuera_del_plazo_se_bloquea_sin_extratiempo(): void
    {
        $ayer = date('Y-m-d', strtotime('-1 day'));
        $idPeriodo = $this->crearPeriodoConFechas($ayer, null);
        $idEvaluacion = $this->crearEvaluacionPrueba($idPeriodo, $this->vincEvaluado, $this->vincEvaluador, [
            'fase_actual' => 1,
            'concertacion_firmada' => 0,
        ]);

        $this->sesionComo('evaluador', $this->idEvaluador);

        $response = $this->postJson("/evaluaciones/{$idEvaluacion}/compromisos", [
            'descripcion' => 'Compromiso fuera de plazo',
            'porcentaje_peso' => 10,
            'metas' => ['Meta tardía'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'error' => "La fecha límite de concertación para este período ha finalizado ({$ayer}). Solicita a Talento Humano la habilitación de concertación extratiempo.",
        ]);
    }

    public function test_concertacion_fuera_del_plazo_se_habilita_con_extratiempo_de_concertacion(): void
    {
        $ayer = date('Y-m-d', strtotime('-1 day'));
        $futuro = date('Y-m-d', strtotime('+5 days'));
        $idPeriodo = $this->crearPeriodoConFechas($ayer, null);
        $idEvaluacion = $this->crearEvaluacionPrueba($idPeriodo, $this->vincEvaluado, $this->vincEvaluador, [
            'fase_actual' => 1,
            'concertacion_firmada' => 0,
        ]);

        $this->otorgarExtratiempo($idEvaluacion, 'CONCERTACION', $futuro);

        $this->sesionComo('evaluador', $this->idEvaluador);

        $response = $this->postJson("/evaluaciones/{$idEvaluacion}/compromisos", [
            'descripcion' => 'Compromiso con extratiempo concertación',
            'porcentaje_peso' => 10,
            'metas' => ['Meta autorizada'],
        ]);

        $response->assertStatus(200);
    }

    public function test_concertacion_fuera_del_plazo_no_se_habilita_con_extratiempo_de_calificacion(): void
    {
        $ayer = date('Y-m-d', strtotime('-1 day'));
        $futuro = date('Y-m-d', strtotime('+5 days'));
        $idPeriodo = $this->crearPeriodoConFechas($ayer, null);
        $idEvaluacion = $this->crearEvaluacionPrueba($idPeriodo, $this->vincEvaluado, $this->vincEvaluador, [
            'fase_actual' => 1,
            'concertacion_firmada' => 0,
        ]);

        // Otorgar extratiempo de tipo CALIFICACION (no debe habilitar concertación)
        $this->otorgarExtratiempo($idEvaluacion, 'CALIFICACION', $futuro);

        $this->sesionComo('evaluador', $this->idEvaluador);

        $response = $this->postJson("/evaluaciones/{$idEvaluacion}/compromisos", [
            'descripcion' => 'Compromiso con extratiempo equivocado',
            'porcentaje_peso' => 10,
            'metas' => ['Meta no permitida'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'error' => "La fecha límite de concertación para este período ha finalizado ({$ayer}). Solicita a Talento Humano la habilitación de concertación extratiempo.",
        ]);
    }

    // --- 4.2 Extratiempo de calificación ---

    public function test_calificacion_dentro_del_plazo_es_permitida(): void
    {
        $manana = date('Y-m-d', strtotime('+1 day'));
        $idPeriodo = $this->crearPeriodoConFechas(null, $manana);
        $idEvaluacion = $this->crearEvaluacionPrueba($idPeriodo, $this->vincEvaluado, $this->vincEvaluador, [
            'fase_actual' => 4,
            'concertacion_firmada' => 1,
        ]);

        $idComp = DB::table('compromiso')->insertGetId([
            'id_evaluacion' => $idEvaluacion,
            'descripcion' => 'Compromiso listo para calificar',
            'porcentaje_peso' => 80.00,
            'numero_orden' => 1,
        ]);

        $this->sesionComo('evaluador', $this->idEvaluador);

        $response = $this->postJson("/evaluaciones/{$idEvaluacion}/calificar-compromisos", [
            'compromisos' => [
                ['id_compromiso' => $idComp, 'calificacion_sem1' => 4.5],
            ],
        ]);

        $response->assertStatus(200);
    }

    public function test_calificacion_fuera_del_plazo_se_bloquea_sin_extratiempo(): void
    {
        $ayer = date('Y-m-d', strtotime('-1 day'));
        $idPeriodo = $this->crearPeriodoConFechas(null, $ayer);
        $idEvaluacion = $this->crearEvaluacionPrueba($idPeriodo, $this->vincEvaluado, $this->vincEvaluador, [
            'fase_actual' => 4,
            'concertacion_firmada' => 1,
        ]);

        $idComp = DB::table('compromiso')->insertGetId([
            'id_evaluacion' => $idEvaluacion,
            'descripcion' => 'Compromiso a calificar fuera de plazo',
            'porcentaje_peso' => 80.00,
            'numero_orden' => 1,
        ]);

        $this->sesionComo('evaluador', $this->idEvaluador);

        $response = $this->postJson("/evaluaciones/{$idEvaluacion}/calificar-compromisos", [
            'compromisos' => [
                ['id_compromiso' => $idComp, 'calificacion_sem1' => 4.5],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'error' => "La fecha límite de calificación para este período ha finalizado ({$ayer}). Solicita a Talento Humano la habilitación de calificación extratiempo.",
        ]);
    }

    public function test_calificacion_fuera_del_plazo_se_habilita_con_extratiempo_de_calificacion(): void
    {
        $ayer = date('Y-m-d', strtotime('-1 day'));
        $futuro = date('Y-m-d', strtotime('+3 days'));
        $idPeriodo = $this->crearPeriodoConFechas(null, $ayer);
        $idEvaluacion = $this->crearEvaluacionPrueba($idPeriodo, $this->vincEvaluado, $this->vincEvaluador, [
            'fase_actual' => 4,
            'concertacion_firmada' => 1,
        ]);

        $idComp = DB::table('compromiso')->insertGetId([
            'id_evaluacion' => $idEvaluacion,
            'descripcion' => 'Compromiso habilitado por extratiempo',
            'porcentaje_peso' => 80.00,
            'numero_orden' => 1,
        ]);

        $this->otorgarExtratiempo($idEvaluacion, 'CALIFICACION', $futuro);

        $this->sesionComo('evaluador', $this->idEvaluador);

        $response = $this->postJson("/evaluaciones/{$idEvaluacion}/calificar-compromisos", [
            'compromisos' => [
                ['id_compromiso' => $idComp, 'calificacion_sem1' => 4.5],
            ],
        ]);

        $response->assertStatus(200);
    }

    public function test_calificacion_fuera_del_plazo_no_se_habilita_con_extratiempo_de_concertacion(): void
    {
        $ayer = date('Y-m-d', strtotime('-1 day'));
        $futuro = date('Y-m-d', strtotime('+3 days'));
        $idPeriodo = $this->crearPeriodoConFechas(null, $ayer);
        $idEvaluacion = $this->crearEvaluacionPrueba($idPeriodo, $this->vincEvaluado, $this->vincEvaluador, [
            'fase_actual' => 4,
            'concertacion_firmada' => 1,
        ]);

        $idComp = DB::table('compromiso')->insertGetId([
            'id_evaluacion' => $idEvaluacion,
            'descripcion' => 'Compromiso con extratiempo incorrecto',
            'porcentaje_peso' => 80.00,
            'numero_orden' => 1,
        ]);

        // Otorgar extratiempo de tipo CONCERTACION (no debe habilitar calificación)
        $this->otorgarExtratiempo($idEvaluacion, 'CONCERTACION', $futuro);

        $this->sesionComo('evaluador', $this->idEvaluador);

        $response = $this->postJson("/evaluaciones/{$idEvaluacion}/calificar-compromisos", [
            'compromisos' => [
                ['id_compromiso' => $idComp, 'calificacion_sem1' => 4.5],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'error' => "La fecha límite de calificación para este período ha finalizado ({$ayer}). Solicita a Talento Humano la habilitación de calificación extratiempo.",
        ]);
    }

    public function test_crear_periodo_valida_orden_cronologico_de_fechas(): void
    {
        $this->sesionComo('admin', null);

        // Intento con fecha_fin_calificacion anterior a fecha_fin_concertacion
        $response = $this->post('/admin/periodos', [
            'sistema' => 'RENDIMIENTO_LABORAL',
            'anio' => 2098,
            'semestre' => 1,
            'fecha_inicio' => '2098-01-01',
            'fecha_fin' => '2098-06-30',
            'fecha_fin_concertacion' => '2098-03-31',
            'fecha_fin_calificacion' => '2098-02-28', // Anterior a concertación!
        ]);

        $response->assertSessionHasErrors('periodo');

        // Registro válido con ambas fechas
        $responseValida = $this->post('/admin/periodos', [
            'sistema' => 'RENDIMIENTO_LABORAL',
            'anio' => 2098,
            'semestre' => 1,
            'fecha_inicio' => '2098-01-01',
            'fecha_fin' => '2098-06-30',
            'fecha_fin_concertacion' => '2098-02-28',
            'fecha_fin_calificacion' => '2098-06-15',
        ]);

        $responseValida->assertSessionHasNoErrors();
        $this->assertDatabaseHas('periodo', [
            'sistema' => 'RENDIMIENTO_LABORAL',
            'anio' => 2098,
            'semestre' => 1,
            'fecha_fin_concertacion' => '2098-02-28',
            'fecha_fin_calificacion' => '2098-06-15',
        ]);
    }
}
