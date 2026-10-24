<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Impedimento / recusación: solo una solicitud PENDIENTE bloquea la
 * evaluación. Aprobarla exige elegir el nuevo evaluador, así que una solicitud
 * APROBADA ya quedó reasignada y no deja bloqueo residual.
 *
 * La prueba trabaja sobre la base configurada en el entorno y se ejecuta dentro
 * de una transacción que se revierte al terminar, de modo que no deja datos.
 */
class ImpedimentoRecusacionTest extends TestCase
{
    private int $idUsuario;
    private int $idEvaluacion;
    private int $idVincEvaluador;
    private int $idVincNuevoEvaluador;
    private int $idImpedimento;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requiere una base MySQL con el esquema institucional aplicado.');
        }

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
        DB::beginTransaction();

        $sufijo = 'T' . random_int(10000, 99999);
        $this->idUsuario = $this->crearUsuario("tmp.imped.{$sufijo}");

        $idVincEvaluado = $this->crearVinculacion($this->crearFuncionario("{$sufijo}A"), false);
        $this->idVincEvaluador = $this->crearVinculacion($this->crearFuncionario("{$sufijo}B"), true);
        $this->idVincNuevoEvaluador = $this->crearVinculacion($this->crearFuncionario("{$sufijo}C"), true);

        $periodo = DB::table('periodo')->insertGetId([
            'id_usuario_apertura' => $this->idUsuario,
            'sistema' => 'RENDIMIENTO_LABORAL',
            'anio' => 2099,
            'semestre' => 1,
            'fecha_inicio' => '2099-01-01',
            'fecha_fin' => '2099-06-30',
            'estado' => 'ABIERTO',
        ]);

        $this->idEvaluacion = DB::table('evaluacion')->insertGetId([
            'id_periodo' => $periodo,
            'id_vinc_evaluado' => $idVincEvaluado,
            'id_vinc_evaluador' => $this->idVincEvaluador,
            'tipo_evaluacion' => 'SEMESTRE_1',
            'fase_actual' => 3,
            'concertacion_firmada' => 1,
            'estado' => 'EN_PROCESO',
        ]);

        $this->idImpedimento = DB::table('impedimento_recusacion')->insertGetId([
            'id_evaluacion' => $this->idEvaluacion,
            'id_vinc_solicitante' => $this->idVincEvaluador,
            'tipo' => 'IMPEDIMENTO',
            'motivo' => 'Prueba',
            'estado' => 'PENDIENTE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        session(['usuario_autenticado' => [
            'id_usuario' => $this->idUsuario,
            'rol_activo' => 'admin',
            'roles' => ['admin'],
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

    private function crearFuncionario(string $doc): int
    {
        return DB::table('funcionario')->insertGetId([
            'id_usuario' => $this->crearUsuario('tmp.imped.' . strtolower($doc)),
            'tipo_documento' => 'CEDULA_CIUDADANIA',
            'numero_doc' => $doc,
            'nombres' => 'Tmp',
            'apellidos' => $doc,
        ]);
    }

    private function crearVinculacion(int $idFuncionario, bool $esEvaluador): int
    {
        return DB::table('vinculacion')->insertGetId([
            'id_funcionario' => $idFuncionario,
            'cargo' => 'PROFESIONAL',
            'codigo_cargo' => 1,
            'grado_cargo' => 1,
            'nivel_jerarquico' => 'PROFESIONAL',
            'area' => 'PRUEBA',
            'tipo_vinculacion' => 'PROVISIONALIDAD',
            'sistema_evaluacion' => 'RENDIMIENTO_LABORAL',
            'es_evaluador' => $esEvaluador ? 1 : 0,
            'fecha_ingreso' => '2026-01-01',
            'activa' => 1,
        ]);
    }

    private function resolver(array $payload)
    {
        return $this->postJson("/admin/impedimentos/{$this->idImpedimento}/resolver", $payload + ['respuesta' => 'Decisión de prueba']);
    }

    public function test_la_solicitud_pendiente_bloquea_la_evaluacion(): void
    {
        $this->assertTrue(evaluacionBloqueadaPorImpedimento($this->idEvaluacion));
    }

    public function test_aprobada_con_nuevo_evaluador_no_deja_bloqueo_residual(): void
    {
        $this->resolver(['estado' => 'APROBADO', 'id_vinc_nuevo_evaluador' => $this->idVincNuevoEvaluador])->assertOk();

        $this->assertSame($this->idVincNuevoEvaluador, (int) DB::table('evaluacion')->where('id_evaluacion', $this->idEvaluacion)->value('id_vinc_evaluador'));
        $this->assertFalse(evaluacionBloqueadaPorImpedimento($this->idEvaluacion));
    }

    public function test_no_se_aprueba_sin_elegir_nuevo_evaluador(): void
    {
        $this->resolver(['estado' => 'APROBADO'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id_vinc_nuevo_evaluador' => 'Debe seleccionar el nuevo evaluador para aprobar la solicitud.']);

        $this->assertSame('PENDIENTE', DB::table('impedimento_recusacion')->where('id_impedimento', $this->idImpedimento)->value('estado'));
        $this->assertTrue(evaluacionBloqueadaPorImpedimento($this->idEvaluacion));
    }

    public function test_aprobada_avisa_al_nuevo_evaluador_y_al_evaluado_no_a_talento_humano(): void
    {
        $avisosGenerales = DB::table('notificacion')->whereNull('id_usuario_destino')->count();

        $this->resolver(['estado' => 'APROBADO', 'id_vinc_nuevo_evaluador' => $this->idVincNuevoEvaluador])->assertOk();

        $idVincEvaluado = (int) DB::table('evaluacion')->where('id_evaluacion', $this->idEvaluacion)->value('id_vinc_evaluado');
        $tipos = fn (int $idVinc) => DB::table('notificacion as n')
            ->join('funcionario as f', 'f.id_usuario', '=', 'n.id_usuario_destino')
            ->join('vinculacion as v', 'v.id_funcionario', '=', 'f.id_funcionario')
            ->where('v.id_vinculacion', $idVinc)
            ->pluck('n.tipo')
            ->all();

        $this->assertSame(['IMPEDIMENTO_APROBADO'], $tipos($this->idVincNuevoEvaluador));
        $this->assertSame(['CAMBIO_EVALUADOR'], $tipos($idVincEvaluado));
        $this->assertSame($avisosGenerales, DB::table('notificacion')->whereNull('id_usuario_destino')->count());
    }

    public function test_la_bitacora_registra_la_resolucion_con_el_cambio_de_evaluador(): void
    {
        $this->resolver(['estado' => 'APROBADO', 'id_vinc_nuevo_evaluador' => $this->idVincNuevoEvaluador])->assertOk();

        $evento = DB::table('evaluacion_evento')->where('id_evaluacion', $this->idEvaluacion)->first();
        $this->assertSame('IMPEDIMENTO_RESUELTO', $evento->tipo_evento);

        $datos = json_decode($evento->datos, true);
        $this->assertSame('APROBADO', $datos['decision']);
        $this->assertSame($this->idVincEvaluador, $datos['id_vinc_evaluador_anterior']);
        $this->assertSame($this->idVincNuevoEvaluador, $datos['id_vinc_evaluador_nuevo']);
        $this->assertArrayNotHasKey('respuesta', $datos, 'La bitácora no copia textos.');
    }

    private function comoEvaluador(): void
    {
        $idFuncionario = (int) DB::table('vinculacion')->where('id_vinculacion', $this->idVincEvaluador)->value('id_funcionario');
        session(['usuario_autenticado' => [
            'id_usuario' => DB::table('funcionario')->where('id_funcionario', $idFuncionario)->value('id_usuario'),
            'id_funcionario' => $idFuncionario,
            'rol_activo' => 'evaluador',
            'roles' => ['evaluador'],
        ]]);
    }

    private function declararImpedimento()
    {
        return $this->post("/evaluacion/{$this->idEvaluacion}/impedimento", ['tipo' => 'IMPEDIMENTO', 'motivo' => 'Conflicto de interés sobreviniente']);
    }

    public function test_se_puede_declarar_despues_de_concertar_mientras_no_este_calificada(): void
    {
        // Ya concertada y en la fase de evidencias (fase 3), sin solicitud pendiente.
        DB::table('impedimento_recusacion')->where('id_impedimento', $this->idImpedimento)->update(['estado' => 'RECHAZADO']);
        $this->comoEvaluador();

        $this->declararImpedimento()->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(1, DB::table('impedimento_recusacion')->where('id_evaluacion', $this->idEvaluacion)->where('estado', 'PENDIENTE')->count());
    }

    public function test_no_se_declara_con_la_evaluacion_calificada(): void
    {
        DB::table('impedimento_recusacion')->where('id_impedimento', $this->idImpedimento)->update(['estado' => 'RECHAZADO']);
        DB::table('evaluacion')->where('id_evaluacion', $this->idEvaluacion)->update(['estado' => 'CALIFICADA', 'fase_actual' => 5]);
        $this->comoEvaluador();

        $this->declararImpedimento()->assertStatus(422);
    }

    public function test_no_se_duplica_una_solicitud_pendiente(): void
    {
        $this->comoEvaluador();

        $this->declararImpedimento()->assertStatus(422);
        $this->assertSame(1, DB::table('impedimento_recusacion')->where('id_evaluacion', $this->idEvaluacion)->count());
    }

    public function test_rechazada_desbloquea_sin_reasignar(): void
    {
        $this->resolver(['estado' => 'RECHAZADO'])->assertOk();

        $this->assertSame($this->idVincEvaluador, (int) DB::table('evaluacion')->where('id_evaluacion', $this->idEvaluacion)->value('id_vinc_evaluador'));
        $this->assertFalse(evaluacionBloqueadaPorImpedimento($this->idEvaluacion));
    }
}
