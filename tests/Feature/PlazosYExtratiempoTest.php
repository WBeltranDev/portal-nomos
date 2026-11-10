<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Corrección X del plan de pruebas 29-10-2026: el periodo tiene cierre de
 * concertación y de calificación; pasada la fecha, concertar o calificar exige
 * extratiempo autorizado por Talento Humano para esa evaluación y ese tipo.
 */
class PlazosYExtratiempoTest extends TestCase
{
    private int $idUsuario;
    private int $idFuncionarioEvaluador;
    private int $idPeriodo;
    private int $idEvaluacion;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requiere una base MySQL con el esquema institucional aplicado.');
        }

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
        DB::beginTransaction();

        $s = 'P' . random_int(10000, 99999);
        $this->idUsuario = $this->usuario("tmp.plazo.{$s}");
        $evaluado = $this->vinculacion("{$s}A", false)[0];
        [$vincEvaluador, $this->idFuncionarioEvaluador] = $this->vinculacion("{$s}B", true);

        $this->idPeriodo = DB::table('periodo')->insertGetId([
            'id_usuario_apertura' => $this->idUsuario, 'sistema' => 'RENDIMIENTO_LABORAL', 'anio' => 2099, 'semestre' => 1,
            'fecha_inicio' => '2099-01-01', 'fecha_fin' => '2099-06-30', 'estado' => 'ABIERTO',
        ]);
        $this->idEvaluacion = DB::table('evaluacion')->insertGetId([
            'id_periodo' => $this->idPeriodo, 'id_vinc_evaluado' => $evaluado, 'id_vinc_evaluador' => $vincEvaluador,
            'tipo_evaluacion' => 'SEMESTRE_1', 'fase_actual' => 4, 'concertacion_firmada' => 1, 'estado' => 'EN_PROCESO',
        ]);

        $this->comoEvaluador();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    private function usuario(string $username): int
    {
        return DB::table('usuario')->insertGetId(['username' => $username, 'password' => 'x', 'rol' => 'ADMINISTRADOR', 'activo' => 1]);
    }

    /** @return array{0:int,1:int} [id_vinculacion, id_funcionario] */
    private function vinculacion(string $doc, bool $esEvaluador): array
    {
        $idFuncionario = DB::table('funcionario')->insertGetId([
            'id_usuario' => $this->usuario('tmp.plazo.' . strtolower($doc)), 'tipo_documento' => 'CEDULA_CIUDADANIA',
            'numero_doc' => $doc, 'nombres' => 'TMP', 'apellidos' => $doc,
        ]);
        $idVinc = DB::table('vinculacion')->insertGetId([
            'id_funcionario' => $idFuncionario, 'cargo' => 'PRUEBA', 'codigo_cargo' => 1, 'grado_cargo' => 1,
            'nivel_jerarquico' => 'PROFESIONAL', 'area' => 'PRUEBA', 'tipo_vinculacion' => 'PROVISIONALIDAD',
            'sistema_evaluacion' => 'RENDIMIENTO_LABORAL', 'es_evaluador' => $esEvaluador ? 1 : 0, 'fecha_ingreso' => '2098-01-01', 'activa' => 1,
        ]);

        return [$idVinc, $idFuncionario];
    }

    private function comoEvaluador(): void
    {
        session(['usuario_autenticado' => [
            'id_usuario' => $this->idUsuario, 'id_funcionario' => $this->idFuncionarioEvaluador,
            'rol_activo' => 'evaluador', 'roles' => ['evaluador'],
        ]]);
    }

    private function comoAdmin(): void
    {
        session(['usuario_autenticado' => ['id_usuario' => $this->idUsuario, 'rol_activo' => 'admin', 'roles' => ['admin']]]);
    }

    private function vencerCalificacion(): void
    {
        DB::table('periodo')->where('id_periodo', $this->idPeriodo)->update(['fecha_fin_calificacion' => Carbon::yesterday()->toDateString()]);
    }

    private function extratiempo(string $tipo, Carbon $limite): void
    {
        DB::table('concertacion_extratiempo')->insert([
            'id_evaluacion' => $this->idEvaluacion, 'tipo' => $tipo, 'justificacion' => 'Prueba', 'autorizado_por' => 'TH',
            'fecha_limite' => $limite->toDateString(), 'activo' => 1,
        ]);
    }

    private function mensajeVencido(): string
    {
        return 'La fecha límite de calificación de este periodo finalizó (' . Carbon::yesterday()->format('d/m/Y') . '). Solicita a Talento Humano la habilitación de calificación extratiempo.';
    }

    public function test_sin_fecha_limite_se_califica_sin_bloqueo(): void
    {
        $this->postJson("/evaluaciones/{$this->idEvaluacion}/calificar-compromisos", [])->assertJsonValidationErrors('compromisos');
    }

    public function test_vencido_el_cierre_de_calificacion_el_evaluador_no_califica_nada(): void
    {
        $this->vencerCalificacion();

        foreach (['calificar-compromisos', 'calificar-competencias', 'calificar-ejes'] as $accion) {
            $this->postJson("/evaluaciones/{$this->idEvaluacion}/{$accion}", [])
                ->assertStatus(422)
                ->assertJsonPath('message', $this->mensajeVencido());
        }

        $this->postJson("/evaluaciones/{$this->idEvaluacion}/calcular-final")
            ->assertStatus(422)
            ->assertJsonPath('message', $this->mensajeVencido());
    }

    public function test_con_extratiempo_de_calificacion_vigente_se_puede_calificar(): void
    {
        $this->vencerCalificacion();
        $this->extratiempo('CALIFICACION', Carbon::today()->addDays(3));

        $this->postJson("/evaluaciones/{$this->idEvaluacion}/calificar-compromisos", [])->assertJsonValidationErrors('compromisos');
    }

    public function test_el_extratiempo_de_concertacion_no_habilita_calificar_ni_el_vencido_tampoco(): void
    {
        $this->vencerCalificacion();
        $this->extratiempo('CONCERTACION', Carbon::today()->addDays(3));
        $this->postJson("/evaluaciones/{$this->idEvaluacion}/calificar-compromisos", [])->assertStatus(422)->assertJsonPath('message', $this->mensajeVencido());

        DB::table('concertacion_extratiempo')->delete();
        $this->extratiempo('CALIFICACION', Carbon::yesterday());
        $this->postJson("/evaluaciones/{$this->idEvaluacion}/calificar-compromisos", [])->assertStatus(422)->assertJsonPath('message', $this->mensajeVencido());
    }

    public function test_el_extratiempo_es_por_evaluacion(): void
    {
        $this->vencerCalificacion();
        $otra = DB::table('evaluacion')->where('id_evaluacion', $this->idEvaluacion)->first();
        $idOtra = DB::table('evaluacion')->insertGetId([
            'id_periodo' => $this->idPeriodo, 'id_vinc_evaluado' => $otra->id_vinc_evaluado, 'id_vinc_evaluador' => $otra->id_vinc_evaluador,
            'tipo_evaluacion' => 'PARCIAL', 'fase_actual' => 4, 'concertacion_firmada' => 1, 'estado' => 'EN_PROCESO',
        ]);
        $this->extratiempo('CALIFICACION', Carbon::today()->addDay());

        $this->postJson("/evaluaciones/{$idOtra}/calificar-compromisos", [])->assertStatus(422)->assertJsonPath('message', $this->mensajeVencido());
    }

    public function test_th_autoriza_extratiempo_de_calificacion_con_su_tipo(): void
    {
        $this->comoAdmin();

        $this->post("/admin/evaluaciones/{$this->idEvaluacion}/extratiempo", [
            'tipo' => 'CALIFICACION', 'justificacion' => 'Incapacidad médica.', 'fecha_limite' => Carbon::today()->addDays(5)->toDateString(),
        ])->assertSessionHas('success', 'Calificación en extratiempo habilitada correctamente.');

        $this->assertSame('CALIFICACION', DB::table('concertacion_extratiempo')->where('id_evaluacion', $this->idEvaluacion)->value('tipo'));
        $evento = DB::table('evaluacion_evento')->where('id_evaluacion', $this->idEvaluacion)->where('tipo_evento', 'EXTRATIEMPO_HABILITADO')->first();
        $this->assertSame('CALIFICACION', json_decode($evento->datos, true)['tipo']);
    }

    public function test_el_extratiempo_sin_tipo_sigue_siendo_de_concertacion(): void
    {
        $this->comoAdmin();

        $this->post("/admin/evaluaciones/{$this->idEvaluacion}/extratiempo", [
            'justificacion' => 'Permiso.', 'fecha_limite' => Carbon::today()->addDays(2)->toDateString(),
        ])->assertSessionHas('success', 'Concertación en extratiempo habilitada correctamente.');

        $this->assertSame('CONCERTACION', DB::table('concertacion_extratiempo')->where('id_evaluacion', $this->idEvaluacion)->value('tipo'));
    }

    public function test_vencido_el_cierre_de_concertacion_no_se_agregan_compromisos_sin_extratiempo(): void
    {
        DB::table('evaluacion')->where('id_evaluacion', $this->idEvaluacion)->update(['concertacion_firmada' => 0, 'fase_actual' => 1]);
        DB::table('periodo')->where('id_periodo', $this->idPeriodo)->update(['fecha_fin_concertacion' => Carbon::yesterday()->toDateString()]);
        $payload = ['descripcion' => 'Compromiso', 'porcentaje_peso' => 10, 'metas' => ['Meta']];

        $this->postJson("/evaluaciones/{$this->idEvaluacion}/compromisos", $payload)
            ->assertStatus(422)
            ->assertJsonPath('error', 'La fecha límite de concertación para este periodo ha finalizado (' . Carbon::yesterday()->format('d/m/Y') . '). Solicita a Talento Humano la habilitación de concertación extratiempo.');

        $this->extratiempo('CALIFICACION', Carbon::today()->addDay());
        $this->postJson("/evaluaciones/{$this->idEvaluacion}/compromisos", $payload)->assertStatus(422)->assertJsonStructure(['error']);

        DB::table('concertacion_extratiempo')->delete();
        $this->extratiempo('CONCERTACION', Carbon::today()->addDay());
        $respuesta = $this->postJson("/evaluaciones/{$this->idEvaluacion}/compromisos", $payload);
        $this->assertStringNotContainsString('fecha límite de concertación', (string) $respuesta->getContent());
    }

    public function test_el_periodo_guarda_los_dos_cierres_y_valida_su_coherencia(): void
    {
        $this->comoAdmin();
        $base = ['sistema' => 'ACUERDO_GESTION', 'anio' => 2098, 'semestre' => 1, 'fecha_inicio' => '2098-01-01', 'fecha_fin' => '2098-06-30'];

        $this->post('/admin/periodos', $base + ['fecha_fin_concertacion' => '2098-02-28', 'fecha_fin_calificacion' => '2098-07-15'])
            ->assertSessionHasNoErrors();
        $periodo = DB::table('periodo')->where('sistema', 'ACUERDO_GESTION')->where('anio', 2098)->first();
        $this->assertSame('2098-02-28', $periodo->fecha_fin_concertacion);
        $this->assertSame('2098-07-15', $periodo->fecha_fin_calificacion);

        $this->post("/admin/periodos/{$periodo->id_periodo}", [
            'fecha_inicio' => '2098-01-01', 'fecha_fin' => '2098-06-30', 'estado' => 'ABIERTO',
            'fecha_fin_concertacion' => '2098-05-01', 'fecha_fin_calificacion' => '2098-03-01',
        ])->assertSessionHasErrors(['periodo' => 'El cierre de calificación no puede ser anterior al cierre de concertación.']);

        $this->post("/admin/periodos/{$periodo->id_periodo}", [
            'fecha_inicio' => '2098-01-01', 'fecha_fin' => '2098-06-30', 'estado' => 'ABIERTO',
            'fecha_fin_concertacion' => '2098-07-30',
        ])->assertSessionHasErrors(['periodo' => 'El cierre de concertación no puede ser posterior al fin del periodo.']);

        $this->post("/admin/periodos/{$periodo->id_periodo}", [
            'fecha_inicio' => '2098-01-01', 'fecha_fin' => '2098-06-30', 'estado' => 'ABIERTO',
            'fecha_fin_concertacion' => '2098-02-28', 'fecha_fin_calificacion' => '2098-08-01',
        ])->assertSessionHasNoErrors();
        $this->assertSame('2098-08-01', DB::table('periodo')->where('id_periodo', $periodo->id_periodo)->value('fecha_fin_calificacion'));
    }
}
