<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaDatosSerag;
use Tests\TestCase;

/**
 * Fase 3 (v1.2) — Recursos y firma de la notificación (Acta del 29-10-2026).
 *
 * 3.1 Enlaces de oficio y evidencia al decidir un recurso.
 * 3.2 Firma de notificación obligatoria para descargar informes en PDF.
 * 3.3 Confirmación de traslado y delegación espera recursos pendientes.
 * 3.4 Recurso favorable conserva la nota previa en historial y BD.
 */
class Fase3RecursosNotificacionTest extends TestCase
{
    use CreaDatosSerag;

    private int $idPeriodo;
    private int $idEvaluado;
    private int $idEvaluador;
    private int $vincEvaluado;
    private int $vincEvaluador;
    private int $idEvaluacion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarDatosSerag();

        [$this->idEvaluado] = $this->crearFuncionarioPrueba('Evaluado', 'Prueba');
        [$this->idEvaluador] = $this->crearFuncionarioPrueba('Evaluador', 'Prueba', 'EVALUADOR');
        $this->vincEvaluado = $this->crearVinculacionPrueba($this->idEvaluado);
        $this->vincEvaluador = $this->crearVinculacionPrueba($this->idEvaluador, true);
        $this->asignarEvaluadorPrueba($this->vincEvaluador, $this->vincEvaluado);

        $this->idPeriodo = $this->crearPeriodoPrueba('RENDIMIENTO_LABORAL', 2099, 1, '2099-02-01', '2099-07-31');

        $this->idEvaluacion = $this->crearEvaluacionPrueba($this->idPeriodo, $this->vincEvaluado, $this->vincEvaluador, [
            'fase_actual' => 5,
            'concertacion_firmada' => 1,
            'estado' => 'CALIFICADA',
            'calificacion_final' => 4.20,
            'categoria_final' => 'BUENO',
            'nota_compromisos' => 4.20,
            'nota_competencias' => 4.20,
            'nota_ejes_misionales' => 0.00,
        ]);
    }

    protected function tearDown(): void
    {
        $this->finalizarDatosSerag();
        parent::tearDown();
    }

    private function firmarNotificacion(int $idEvaluacion, int $idFuncionario): void
    {
        DB::table('firma')->insert([
            'id_evaluacion' => $idEvaluacion,
            'tipo_firma' => 'NOTIFICACION_EVALUADO',
            'fecha_firma' => now(),
        ]);
    }

    private function radicarRecurso(int $idEvaluacion, string $tipo = 'REPOSICION'): int
    {
        return DB::table('recurso')->insertGetId([
            'id_evaluacion' => $idEvaluacion,
            'tipo_recurso' => $tipo,
            'id_vinc_receptor' => $this->vincEvaluador,
            'numero_radicado' => $tipo === 'REPOSICION' ? 'REP-2099-0001' : 'APL-2099-0001',
            'numero_folios' => 3,
            'fecha_recurso' => '2099-06-01',
            'decision' => 'PENDIENTE',
            'motivacion' => 'Solicito revisión de la calificación.',
        ]);
    }

    // --- 3.1 Enlaces al decidir un recurso ---

    public function test_decidir_recurso_exige_enlace_oficio_y_evidencia_validos(): void
    {
        $idRecurso = $this->radicarRecurso($this->idEvaluacion);
        $this->sesionComo('evaluador', $this->idEvaluador);

        // Sin enlace de oficio
        $this->postJson("/recursos/{$idRecurso}/decision", [
            'decision' => 'NEGADO',
            'motivacion' => 'Se mantiene la calificación.',
            'url_evidencia_decision' => 'https://drive.google.com/evidencia.pdf',
        ])->assertStatus(422)->assertJsonValidationErrors('url_oficio_decision');

        // Sin enlace de evidencia
        $this->postJson("/recursos/{$idRecurso}/decision", [
            'decision' => 'NEGADO',
            'motivacion' => 'Se mantiene la calificación.',
            'url_oficio_decision' => 'https://drive.google.com/oficio.pdf',
        ])->assertStatus(422)->assertJsonValidationErrors('url_evidencia_decision');

        // URLs inválidas
        $this->postJson("/recursos/{$idRecurso}/decision", [
            'decision' => 'NEGADO',
            'motivacion' => 'Se mantiene la calificación.',
            'url_oficio_decision' => 'no-es-una-url',
            'url_evidencia_decision' => 'tampoco-es-url',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['url_oficio_decision', 'url_evidencia_decision']);
    }

    public function test_decidir_recurso_guarda_enlaces_en_bd_y_en_payload(): void
    {
        $idRecurso = $this->radicarRecurso($this->idEvaluacion);
        $this->sesionComo('evaluador', $this->idEvaluador);

        $this->postJson("/recursos/{$idRecurso}/decision", [
            'decision' => 'NEGADO',
            'motivacion' => 'Se ratifica la calificación según soportes adjuntos.',
            'url_oficio_decision' => 'https://drive.google.com/oficio_respuesta_001.pdf',
            'url_evidencia_decision' => 'https://drive.google.com/evidencia_cedp_001.pdf',
        ])->assertOk()->assertJsonPath('recurso.decision', 'NEGADO');

        $recursoBd = DB::table('recurso')->where('id_recurso', $idRecurso)->first();
        $this->assertSame('https://drive.google.com/oficio_respuesta_001.pdf', $recursoBd->url_oficio_decision);
        $this->assertSame('https://drive.google.com/evidencia_cedp_001.pdf', $recursoBd->url_evidencia_decision);
        $this->assertStringContainsString('DECISIÓN (Desfavorable', $recursoBd->motivacion);

        // Visible en el listado de recursos de la evaluación
        $this->sesionComo('evaluado', $this->idEvaluado);
        $this->getJson("/evaluaciones/{$this->idEvaluacion}/recursos")
            ->assertOk()
            ->assertJsonFragment([
                'url_oficio_decision' => 'https://drive.google.com/oficio_respuesta_001.pdf',
                'url_evidencia_decision' => 'https://drive.google.com/evidencia_cedp_001.pdf',
            ]);
    }

    // --- 3.4 Recurso favorable sin perder la nota anterior ---

    public function test_recurso_aprobado_guarda_nota_previa_en_recurso_y_evento(): void
    {
        $idRecurso = $this->radicarRecurso($this->idEvaluacion);
        $this->sesionComo('evaluador', $this->idEvaluador);

        $this->postJson("/recursos/{$idRecurso}/decision", [
            'decision' => 'APROBADO',
            'motivacion' => 'Se acogen los argumentos para reabrir y recalificar.',
            'url_oficio_decision' => 'https://drive.google.com/oficio_aprobacion.pdf',
            'url_evidencia_decision' => 'https://drive.google.com/evidencia_acogida.pdf',
        ])->assertOk();

        // 1. La evaluación vuelve a fase 4 EN_PROCESO para recalificar
        $evaluacionBd = DB::table('evaluacion')->where('id_evaluacion', $this->idEvaluacion)->first();
        $this->assertSame('EN_PROCESO', $evaluacionBd->estado);
        $this->assertSame(4, (int) $evaluacionBd->fase_actual);

        // 2. El recurso conserva la nota previa congelada
        $recursoBd = DB::table('recurso')->where('id_recurso', $idRecurso)->first();
        $this->assertEquals(4.20, (float) $recursoBd->nota_antes_recurso);
        $this->assertSame('BUENO', $recursoBd->categoria_antes_recurso);

        // 3. El evento RECURSO_DECIDIDO guarda la foto completa en bitácora
        $evento = DB::table('evaluacion_evento')
            ->where('id_evaluacion', $this->idEvaluacion)
            ->where('tipo_evento', 'RECURSO_DECIDIDO')
            ->latest('id_evento')
            ->first();

        $this->assertNotNull($evento);
        $datos = json_decode($evento->datos, true);
        $this->assertEquals(4.20, (float) $datos['nota_antes_recurso']);
        $this->assertSame('BUENO', $datos['categoria_antes_recurso']);
        $this->assertSame('https://drive.google.com/oficio_aprobacion.pdf', $datos['url_oficio_decision']);
        $this->assertSame('https://drive.google.com/evidencia_acogida.pdf', $datos['url_evidencia_decision']);
    }

    // --- 3.2 Firma de la notificación obligatoria para PDF ---

    public function test_evaluado_sin_firma_de_notificacion_no_puede_descargar_informes(): void
    {
        $this->sesionComo('evaluado', $this->idEvaluado);

        // Intento semestral sin firma
        $resSem = $this->get("/evaluaciones/{$this->idEvaluacion}/informe");
        $resSem->assertStatus(403);
        $this->assertStringContainsString('Firma la notificación de tu calificación para descargar el informe', $resSem->getContent());

        // Intento anual sin firma
        $resAnual = $this->get("/evaluaciones/{$this->idEvaluacion}/informe-anual");
        $resAnual->assertStatus(403);
        $this->assertStringContainsString('Firma la notificación de tu calificación para descargar el informe', $resAnual->getContent());
    }

    public function test_evaluado_con_firma_de_notificacion_si_puede_descargar_informes(): void
    {
        $this->firmarNotificacion($this->idEvaluacion, $this->idEvaluado);
        $this->sesionComo('evaluado', $this->idEvaluado);

        $this->get("/evaluaciones/{$this->idEvaluacion}/informe")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_evaluador_y_administrador_pueden_descargar_sin_firma_del_evaluado(): void
    {
        // El evaluador puede descargar el informe de su evaluado
        $this->sesionComo('evaluador', $this->idEvaluador);
        $this->get("/evaluaciones/{$this->idEvaluacion}/informe")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        // El administrador descarga sin importar la firma
        $this->sesionComo('admin', null);
        $this->get("/evaluaciones/{$this->idEvaluacion}/informe")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    // --- 3.3 El traslado espera recursos pendientes ---

    public function test_traslado_no_se_confirma_si_tramo_de_origen_tiene_recurso_pendiente(): void
    {
        // Programar un traslado desde esta evaluación calificada
        $idTraslado = DB::table('traslado')->insertGetId([
            'id_vinc_funcionario' => $this->vincEvaluado,
            'id_vinc_evaluador_origen' => $this->vincEvaluador,
            'id_vinc_evaluador_nuevo' => $this->vincEvaluador,
            'id_periodo' => $this->idPeriodo,
            'id_evaluacion_origen' => $this->idEvaluacion,
            'fecha_traslado' => '2099-05-01',
            'dias_laborados' => 89,
            'tipo' => 'CAMBIO',
            'estado' => 'PROGRAMADO',
        ]);

        // Evaluado radica un recurso que queda PENDIENTE
        $this->radicarRecurso($this->idEvaluacion, 'REPOSICION');

        // Talento Humano intenta confirmar el traslado
        $this->sesionComo('admin', null);
        $res = $this->postJson("/admin/traslados/{$idTraslado}/confirmar");
        $res->assertStatus(422);
        $this->assertStringContainsString('tiene un recurso de reposición pendiente por decidir', $res->json('message'));

        // El traslado sigue PROGRAMADO
        $this->assertSame('PROGRAMADO', DB::table('traslado')->where('id_traslado', $idTraslado)->value('estado'));
    }

    public function test_traslado_se_confirma_una_vez_resuelto_el_recurso(): void
    {
        [$idNuevoEvaluador] = $this->crearFuncionarioPrueba('Nuevo', 'Jefe', 'EVALUADOR');
        $vincNuevoEvaluador = $this->crearVinculacionPrueba($idNuevoEvaluador, true);

        $idTraslado = DB::table('traslado')->insertGetId([
            'id_vinc_funcionario' => $this->vincEvaluado,
            'id_vinc_evaluador_origen' => $this->vincEvaluador,
            'id_vinc_evaluador_nuevo' => $vincNuevoEvaluador,
            'id_periodo' => $this->idPeriodo,
            'id_evaluacion_origen' => $this->idEvaluacion,
            'fecha_traslado' => '2099-05-01',
            'dias_laborados' => 89,
            'tipo' => 'CAMBIO',
            'estado' => 'PROGRAMADO',
        ]);

        $idRecurso = $this->radicarRecurso($this->idEvaluacion, 'REPOSICION');

        // Se decide el recurso
        $this->sesionComo('evaluador', $this->idEvaluador);
        $this->postJson("/recursos/{$idRecurso}/decision", [
            'decision' => 'NEGADO',
            'motivacion' => 'Resuelto.',
            'url_oficio_decision' => 'https://drive.google.com/oficio.pdf',
            'url_evidencia_decision' => 'https://drive.google.com/evidencia.pdf',
        ])->assertOk();

        // Ahora sí se confirma el traslado
        $this->sesionComo('admin', null);
        $this->postJson("/admin/traslados/{$idTraslado}/confirmar")->assertOk();
        $this->assertSame('CONFIRMADO', DB::table('traslado')->where('id_traslado', $idTraslado)->value('estado'));
    }

    public function test_delegacion_reporta_pendientes_si_hay_recursos_sin_resolver(): void
    {
        $idDelegacion = DB::table('delegacion')->insertGetId([
            'id_vinc_delegante' => $this->vincEvaluador,
            'id_vinc_delegado' => $this->vincEvaluado,
            'fecha_inicio' => '2099-05-01',
            'estado' => 'PROGRAMADA',
            'es_larga' => 1,
        ]);

        DB::table('traslado')->insert([
            'id_delegacion' => $idDelegacion,
            'id_vinc_funcionario' => $this->vincEvaluado,
            'id_vinc_evaluador_origen' => $this->vincEvaluador,
            'id_vinc_evaluador_nuevo' => $this->vincEvaluador,
            'id_periodo' => $this->idPeriodo,
            'id_evaluacion_origen' => $this->idEvaluacion,
            'fecha_traslado' => '2099-05-01',
            'dias_laborados' => 89,
            'tipo' => 'CAMBIO',
            'estado' => 'PROGRAMADO',
        ]);

        // Sin recurso pendiente, no hay pendientes
        $pendientes = pendientesDelegacion($idDelegacion);
        $this->assertEmpty($pendientes);

        // Al radicar recurso pendiente en el tramo de origen, aparece como pendiente
        $this->radicarRecurso($this->idEvaluacion, 'REPOSICION');
        $pendientesConRecurso = pendientesDelegacion($idDelegacion);
        $this->assertCount(1, $pendientesConRecurso);
    }
}
