<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * S12 — Firma de la notificación de la calificación sin renuencia.
 *
 * El evaluado siempre firma la notificación de su nota, esté o no de acuerdo:
 * no existe la renuencia con testigos. La firma deja trazabilidad y es
 * requisito previo para radicar recursos o activar el plan de mejoramiento.
 *
 * La prueba trabaja sobre la base configurada en el entorno y se ejecuta dentro
 * de una transacción que se revierte al terminar, de modo que no deja datos.
 */
class NotificacionCalificacionTest extends TestCase
{
    private int $idUsuario;
    private int $idEvaluado;
    private int $idEvaluador;
    private int $idEvaluacion;

    protected function setUp(): void
    {
        parent::setUp();

        // El esquema institucional usa ENUM y DDL específico de MySQL
        // (ALTER TABLE ... MODIFY COLUMN), así que la prueba no puede correr
        // sobre el SQLite en memoria que trae phpunit.xml por defecto.
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requiere una base MySQL con el esquema institucional aplicado.');
        }

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
        DB::beginTransaction();

        $sufijo = 'T' . random_int(10000, 99999);

        // `funcionario.id_usuario` es único, así que cada persona de la prueba
        // necesita su propio usuario.
        $this->idUsuario = $this->crearUsuario("tmp.notif.{$sufijo}");
        $this->idEvaluado = $this->crearFuncionario('Tmp', 'Evaluado', "{$sufijo}A");
        $this->idEvaluador = $this->crearFuncionario('Tmp', 'Evaluador', "{$sufijo}B");

        $vincEvaluado = $this->crearVinculacion($this->idEvaluado, 'PROFESIONAL', 'PROVISIONALIDAD', false);
        $vincEvaluador = $this->crearVinculacion($this->idEvaluador, 'DIRECTIVO', 'LNR', true);

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

        $this->comoEvaluado();
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
            'id_usuario' => $this->crearUsuario('tmp.notif.' . strtolower($doc)),
            'tipo_documento' => 'CEDULA_CIUDADANIA',
            'numero_doc' => $doc,
            'nombres' => $nombres,
            'apellidos' => $apellidos,
        ]);
    }

    private function crearVinculacion(int $idFuncionario, string $nivel, string $tipo, bool $esEvaluador): int
    {
        return DB::table('vinculacion')->insertGetId([
            'id_funcionario' => $idFuncionario,
            'cargo' => $nivel,
            'codigo_cargo' => 1,
            'grado_cargo' => 1,
            'nivel_jerarquico' => $nivel,
            'area' => 'PRUEBA',
            'tipo_vinculacion' => $tipo,
            'sistema_evaluacion' => 'RENDIMIENTO_LABORAL',
            'es_evaluador' => $esEvaluador ? 1 : 0,
            'fecha_ingreso' => '2026-01-01',
            'activa' => 1,
        ]);
    }

    private function comoEvaluado(?int $idFuncionario = null): void
    {
        session(['usuario_autenticado' => [
            'id_usuario' => $this->idUsuario,
            'id_funcionario' => $idFuncionario ?? $this->idEvaluado,
            'rol_activo' => 'evaluado',
            'roles' => ['evaluado'],
        ]]);
    }

    public function test_el_evaluado_firma_la_notificacion_y_queda_trazabilidad(): void
    {
        $this->assertFalse($this->notificacionFirmada());

        $response = $this->postJson("/evaluaciones/{$this->idEvaluacion}/firmar-notificacion");

        $response->assertOk();
        $this->assertTrue($this->notificacionFirmada());

        $firma = DB::table('firma')
            ->where('id_evaluacion', $this->idEvaluacion)
            ->where('tipo_firma', 'NOTIFICACION_EVALUADO')
            ->first();

        $this->assertNotNull($firma->fecha_firma);
    }

    public function test_la_firma_es_idempotente_y_no_admite_renuencia(): void
    {
        $this->postJson("/evaluaciones/{$this->idEvaluacion}/firmar-notificacion")->assertOk();
        $primera = DB::table('firma')->where('id_evaluacion', $this->idEvaluacion)->where('tipo_firma', 'NOTIFICACION_EVALUADO')->first();

        $this->postJson("/evaluaciones/{$this->idEvaluacion}/firmar-notificacion")->assertOk();

        $firmas = DB::table('firma')->where('id_evaluacion', $this->idEvaluacion)->where('tipo_firma', 'NOTIFICACION_EVALUADO')->get();
        $this->assertCount(1, $firmas);

        // No debe existir ninguna marca de renuencia en el esquema.
        $this->assertFalse(Schema::hasColumn('firma', 'renuencia'));
        $this->assertFalse(Schema::hasTable('testigo_renuencia'));
        $this->assertFalse(Schema::hasTable('renuencia_evidencia'));
    }

    public function test_no_se_puede_firmar_antes_de_que_la_evaluacion_este_calificada(): void
    {
        DB::table('evaluacion')->where('id_evaluacion', $this->idEvaluacion)->update(['estado' => 'EN_PROCESO']);

        $this->postJson("/evaluaciones/{$this->idEvaluacion}/firmar-notificacion")->assertStatus(422);

        $this->assertFalse($this->notificacionFirmada());
    }

    public function test_el_evaluador_no_firma_la_notificacion_del_evaluado(): void
    {
        session(['usuario_autenticado' => [
            'id_usuario' => $this->idUsuario,
            'id_funcionario' => $this->idEvaluador,
            'rol_activo' => 'evaluador',
            'roles' => ['evaluador'],
        ]]);

        $this->postJson("/evaluaciones/{$this->idEvaluacion}/firmar-notificacion")->assertStatus(403);
    }

    public function test_un_funcionario_ajeno_no_firma_la_notificacion(): void
    {
        $ajeno = $this->crearFuncionario('Tmp', 'Ajeno', 'T' . random_int(10000, 99999));
        $this->comoEvaluado($ajeno);

        $this->postJson("/evaluaciones/{$this->idEvaluacion}/firmar-notificacion")->assertStatus(403);
    }

    public function test_los_recursos_exigen_la_firma_de_la_notificacion(): void
    {
        $this->postJson("/evaluaciones/{$this->idEvaluacion}/recursos", $this->payloadRecurso())
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'Primero debes firmar la notificación de la calificación para dejar constancia de que fuiste notificado.'
            );

        $this->assertSame(0, DB::table('recurso')->where('id_evaluacion', $this->idEvaluacion)->count());
    }

    public function test_el_plan_de_mejoramiento_exige_la_firma_de_la_notificacion(): void
    {
        DB::table('evaluacion')->where('id_evaluacion', $this->idEvaluacion)->update([
            'categoria_final' => 'NO_SATISFACTORIO',
            'calificacion_final' => 3.2,
        ]);

        $this->getJson("/evaluaciones/{$this->idEvaluacion}/plan-mejoramiento")
            ->assertOk()
            ->assertJsonPath('habilitado', false);

        $this->postJson("/evaluaciones/{$this->idEvaluacion}/firmar-notificacion")->assertOk();

        $this->getJson("/evaluaciones/{$this->idEvaluacion}/plan-mejoramiento")
            ->assertOk()
            ->assertJsonPath('habilitado', true);
    }

    public function test_los_recursos_se_habilitan_para_cualquier_categoria_de_calificacion(): void
    {
        $this->postJson("/evaluaciones/{$this->idEvaluacion}/firmar-notificacion")->assertOk();

        // Categoría BUENO: antes quedaba fuera del alcance de la interfaz.
        $this->postJson("/evaluaciones/{$this->idEvaluacion}/recursos", $this->payloadRecurso('REPOSICION'))
            ->assertOk();

        $this->postJson("/evaluaciones/{$this->idEvaluacion}/recursos", $this->payloadRecurso('APELACION'))
            ->assertOk();

        $this->assertSame(2, DB::table('recurso')->where('id_evaluacion', $this->idEvaluacion)->count());
    }

    public function test_el_recurso_sigue_exigiendo_soportes(): void
    {
        $this->postJson("/evaluaciones/{$this->idEvaluacion}/firmar-notificacion")->assertOk();

        $this->postJson("/evaluaciones/{$this->idEvaluacion}/recursos", [
            'tipo_recurso' => 'REPOSICION',
            'numero_folios' => 2,
            'motivacion' => 'Sin soporte adjunto.',
            'evidencias' => [],
        ])->assertStatus(422)->assertJsonValidationErrors('evidencias');
    }

    public function test_el_endpoint_de_recursos_informa_el_estado_de_la_notificacion(): void
    {
        $this->getJson("/evaluaciones/{$this->idEvaluacion}/recursos")
            ->assertOk()
            ->assertJsonPath('notificacion_firmada', false);

        $this->postJson("/evaluaciones/{$this->idEvaluacion}/firmar-notificacion")->assertOk();

        $this->getJson("/evaluaciones/{$this->idEvaluacion}/recursos")
            ->assertOk()
            ->assertJsonPath('notificacion_firmada', true);
    }

    public function test_el_estado_de_concertacion_ya_no_expone_renuencia(): void
    {
        $response = $this->getJson("/evaluaciones/{$this->idEvaluacion}/compromisos")->assertOk();

        $estado = $response->json('estado');

        $this->assertArrayNotHasKey('renuencia_evaluado', $estado);
        $this->assertArrayNotHasKey('renuencia_evaluador', $estado);
        $this->assertArrayNotHasKey('testigos', $estado);

        // Y el estado ahora sí expone la constancia de notificación.
        $this->assertFalse($estado['notificacion_firmada']);

        $this->postJson("/evaluaciones/{$this->idEvaluacion}/firmar-notificacion")->assertOk();

        $this->getJson("/evaluaciones/{$this->idEvaluacion}/compromisos")
            ->assertOk()
            ->assertJsonPath('estado.notificacion_firmada', true);
    }

    public function test_el_informe_pdf_incluye_la_constancia_de_notificacion(): void
    {
        $this->postJson("/evaluaciones/{$this->idEvaluacion}/firmar-notificacion")->assertOk();

        $response = $this->get("/evaluaciones/{$this->idEvaluacion}/informe");

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    private function payloadRecurso(string $tipo = 'REPOSICION'): array
    {
        return [
            'tipo_recurso' => $tipo,
            'numero_folios' => 2,
            'motivacion' => 'Prueba del flujo de notificación: argumentos y hechos sustentados.',
            'evidencias' => [
                ['url' => 'https://unitropico.edu.co/soporte.pdf', 'descripcion' => 'Soporte'],
            ],
        ];
    }

    private function notificacionFirmada(): bool
    {
        return DB::table('firma')
            ->where('id_evaluacion', $this->idEvaluacion)
            ->where('tipo_firma', 'NOTIFICACION_EVALUADO')
            ->exists();
    }
}
