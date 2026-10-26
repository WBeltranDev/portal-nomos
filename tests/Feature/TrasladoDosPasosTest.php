<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * S14 — Traslado en dos pasos.
 *
 * Talento Humano programa el traslado; el evaluador de origen concierta y
 * califica el tramo laborado (sin mínimo de días) y solo con esa nota se
 * confirma el traslado, que abre la evaluación PARCIAL del cargo nuevo. La nota
 * del semestre es el promedio de la nota propia de cada tramo ponderado por
 * sus días.
 *
 * La prueba trabaja sobre la base configurada en el entorno y se ejecuta dentro
 * de una transacción que se revierte al terminar, de modo que no deja datos.
 */
class TrasladoDosPasosTest extends TestCase
{
    private const DIAS_ORIGEN = 150;
    private const DIAS_DESTINO = 91;

    private int $idUsuario;
    private int $idPeriodo;
    private int $idVincEvaluado;
    private int $idVincEvaluadorOrigen;
    private int $idVincEvaluadorNuevo;
    private int $idFuncionarioEvaluadorNuevo;
    private int $idEvaluacionOrigen;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requiere una base MySQL con el esquema institucional aplicado.');
        }

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
        DB::beginTransaction();

        $sufijo = 'T' . random_int(10000, 99999);
        $this->idUsuario = $this->crearUsuario("tmp.traslado.{$sufijo}");

        $this->idVincEvaluado = $this->crearVinculacion($this->crearFuncionario('Tmp', 'Evaluado', "{$sufijo}A"), false);
        $this->idVincEvaluadorOrigen = $this->crearVinculacion($this->crearFuncionario('Tmp', 'Origen', "{$sufijo}B"), true);
        $this->idFuncionarioEvaluadorNuevo = $this->crearFuncionario('Tmp', 'Destino', "{$sufijo}C");
        $this->idVincEvaluadorNuevo = $this->crearVinculacion($this->idFuncionarioEvaluadorNuevo, true);

        // El traslado se registra hoy: el tramo de origen dura DIAS_ORIGEN y el
        // de destino DIAS_DESTINO (incluye el día del traslado y el de cierre).
        $hoy = Carbon::today();
        $this->idPeriodo = DB::table('periodo')->insertGetId([
            'id_usuario_apertura' => $this->idUsuario,
            'sistema' => 'RENDIMIENTO_LABORAL',
            'anio' => 2099,
            'semestre' => 2,
            'fecha_inicio' => $hoy->copy()->subDays(self::DIAS_ORIGEN)->toDateString(),
            'fecha_fin' => $hoy->copy()->addDays(self::DIAS_DESTINO - 1)->toDateString(),
            'estado' => 'ABIERTO',
        ]);

        DB::table('evaluador_asignacion')->insert([
            'id_vinc_evaluador' => $this->idVincEvaluadorOrigen,
            'id_vinc_evaluado' => $this->idVincEvaluado,
            'fecha_asignacion' => now(),
        ]);

        $this->idEvaluacionOrigen = $this->crearEvaluacion($this->idVincEvaluadorOrigen, 'SEMESTRE_2', 'EN_PROCESO');

        $this->comoAdmin();
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
            'id_usuario' => $this->crearUsuario('tmp.traslado.' . strtolower($doc)),
            'tipo_documento' => 'CEDULA_CIUDADANIA',
            'numero_doc' => $doc,
            'nombres' => $nombres,
            'apellidos' => $apellidos,
        ]);
    }

    private function crearVinculacion(int $idFuncionario, bool $esEvaluador): int
    {
        return DB::table('vinculacion')->insertGetId([
            'id_funcionario' => $idFuncionario,
            'cargo' => $esEvaluador ? 'DIRECTIVO' : 'PROFESIONAL',
            'codigo_cargo' => 1,
            'grado_cargo' => 1,
            'nivel_jerarquico' => $esEvaluador ? 'DIRECTIVO' : 'PROFESIONAL',
            'area' => 'PRUEBA ORIGEN',
            'tipo_vinculacion' => 'PROVISIONALIDAD',
            'sistema_evaluacion' => 'RENDIMIENTO_LABORAL',
            'es_evaluador' => $esEvaluador ? 1 : 0,
            'fecha_ingreso' => '2026-01-01',
            'activa' => 1,
        ]);
    }

    private function crearEvaluacion(int $idVincEvaluador, string $tipo, string $estado): int
    {
        return DB::table('evaluacion')->insertGetId([
            'id_periodo' => $this->idPeriodo,
            'id_vinc_evaluado' => $this->idVincEvaluado,
            'id_vinc_evaluador' => $idVincEvaluador,
            'tipo_evaluacion' => $tipo,
            'fase_actual' => $estado === 'EN_PROCESO' ? 4 : 5,
            'concertacion_firmada' => 1,
            'estado' => $estado,
        ]);
    }

    private function calificar(int $idEvaluacion, float $nota): void
    {
        DB::table('compromiso')->insert([
            'id_evaluacion' => $idEvaluacion,
            'numero_orden' => 1,
            'descripcion' => 'Compromiso de prueba',
            'porcentaje_peso' => 100,
            'calificacion_definitiva' => $nota,
        ]);
        DB::table('evaluacion')->where('id_evaluacion', $idEvaluacion)->update(['estado' => 'CALIFICADA', 'fase_actual' => 5]);
    }

    private function comoAdmin(): void
    {
        session(['usuario_autenticado' => [
            'id_usuario' => $this->idUsuario,
            'rol_activo' => 'admin',
            'roles' => ['admin'],
        ]]);
    }

    private function programar(): object
    {
        $this->post('/admin/traslados', [
            'id_vinc_funcionario' => $this->idVincEvaluado,
            'id_vinc_evaluador_nuevo' => $this->idVincEvaluadorNuevo,
            'fecha_traslado' => Carbon::today()->toDateString(),
            'area_nuevo' => 'PRUEBA DESTINO',
        ])->assertRedirect()->assertSessionHasNoErrors();

        return DB::table('traslado')->where('id_vinc_funcionario', $this->idVincEvaluado)->orderByDesc('id_traslado')->first();
    }

    private function evaluadorAsignado(): int
    {
        return (int) DB::table('evaluador_asignacion')->where('id_vinc_evaluado', $this->idVincEvaluado)->value('id_vinc_evaluador');
    }

    private function evaluacionDestino(): ?object
    {
        return DB::table('evaluacion')
            ->where('id_periodo', $this->idPeriodo)
            ->where('id_vinc_evaluado', $this->idVincEvaluado)
            ->where('id_vinc_evaluador', $this->idVincEvaluadorNuevo)
            ->first();
    }

    public function test_programar_deja_el_traslado_pendiente_de_la_nota_de_origen(): void
    {
        $traslado = $this->programar();

        $this->assertSame('PROGRAMADO', $traslado->estado);
        $this->assertSame(self::DIAS_ORIGEN, (int) $traslado->dias_laborados);
        $this->assertSame($this->idEvaluacionOrigen, (int) $traslado->id_evaluacion_origen);

        $origen = DB::table('evaluacion')->where('id_evaluacion', $this->idEvaluacionOrigen)->first();
        $this->assertSame(self::DIAS_ORIGEN, (int) $origen->dias_laborados);
        $this->assertSame(0, (int) $origen->es_traslado, 'La evaluación de origen debe poder calificarse.');

        // Aún no se abre la evaluación del cargo nuevo ni se reasigna.
        $this->assertNull($this->evaluacionDestino());
        $this->assertSame($this->idVincEvaluadorOrigen, $this->evaluadorAsignado());
        $this->assertSame('PRUEBA ORIGEN', DB::table('vinculacion')->where('id_vinculacion', $this->idVincEvaluado)->value('area'));
    }

    public function test_no_se_confirma_sin_la_nota_del_tramo_de_origen(): void
    {
        $traslado = $this->programar();

        $this->postJson("/admin/traslados/{$traslado->id_traslado}/confirmar")
            ->assertStatus(422)
            ->assertJsonPath('message', 'El evaluador de origen debe concertar y calificar la evaluación del tramo antes de confirmar el traslado.');

        $this->assertSame('PROGRAMADO', DB::table('traslado')->where('id_traslado', $traslado->id_traslado)->value('estado'));
        $this->assertNull($this->evaluacionDestino());
    }

    public function test_con_la_nota_se_confirma_y_se_abre_la_evaluacion_del_cargo_nuevo(): void
    {
        $traslado = $this->programar();
        $this->calificar($this->idEvaluacionOrigen, 4.0);

        $this->postJson("/admin/traslados/{$traslado->id_traslado}/confirmar")->assertOk();

        $traslado = DB::table('traslado')->where('id_traslado', $traslado->id_traslado)->first();
        $this->assertSame('CONFIRMADO', $traslado->estado);

        $destino = $this->evaluacionDestino();
        $this->assertNotNull($destino);
        $this->assertSame((int) $destino->id_evaluacion, (int) $traslado->id_evaluacion_destino);
        $this->assertSame('PARCIAL', $destino->tipo_evaluacion);
        $this->assertSame('EN_PROCESO', $destino->estado);
        $this->assertSame(1, (int) $destino->fase_actual, 'La evaluación nueva se concierta de nuevo.');
        $this->assertSame(self::DIAS_DESTINO, (int) $destino->dias_laborados);

        $this->assertSame($this->idVincEvaluadorNuevo, $this->evaluadorAsignado());
        $this->assertSame('PRUEBA DESTINO', DB::table('vinculacion')->where('id_vinculacion', $this->idVincEvaluado)->value('area'));
    }

    public function test_si_la_evaluacion_de_origen_ya_tiene_nota_se_confirma_de_inmediato(): void
    {
        $this->calificar($this->idEvaluacionOrigen, 4.0);

        $traslado = $this->programar();

        $this->assertSame('CONFIRMADO', $traslado->estado);
        $this->assertNotNull($this->evaluacionDestino());
    }

    public function test_sin_evaluacion_de_origen_el_traslado_espera_a_que_se_concierte_y_califique(): void
    {
        DB::table('evaluacion')->where('id_evaluacion', $this->idEvaluacionOrigen)->delete();

        $traslado = $this->programar();
        $this->assertSame('PROGRAMADO', $traslado->estado);
        $this->assertNull($traslado->id_evaluacion_origen);

        $this->postJson("/admin/traslados/{$traslado->id_traslado}/confirmar")->assertStatus(422);

        // El evaluador de origen crea, concierta y califica la evaluación del tramo.
        $idOrigen = $this->crearEvaluacion($this->idVincEvaluadorOrigen, 'SEMESTRE_2', 'EN_PROCESO');
        $this->calificar($idOrigen, 4.0);

        $this->postJson("/admin/traslados/{$traslado->id_traslado}/confirmar")->assertOk();

        $traslado = DB::table('traslado')->where('id_traslado', $traslado->id_traslado)->first();
        $this->assertSame($idOrigen, (int) $traslado->id_evaluacion_origen);
        $this->assertSame(self::DIAS_ORIGEN, (int) DB::table('evaluacion')->where('id_evaluacion', $idOrigen)->value('dias_laborados'));
    }

    public function test_cancelar_restaura_la_evaluacion_de_origen(): void
    {
        $traslado = $this->programar();

        $this->postJson("/admin/traslados/{$traslado->id_traslado}/cancelar")->assertOk();

        $this->assertSame('CANCELADO', DB::table('traslado')->where('id_traslado', $traslado->id_traslado)->value('estado'));
        $this->assertNull(DB::table('evaluacion')->where('id_evaluacion', $this->idEvaluacionOrigen)->value('dias_laborados'));
        $this->assertSame($this->idVincEvaluadorOrigen, $this->evaluadorAsignado());
    }

    public function test_no_se_cancela_cuando_el_tramo_de_origen_ya_tiene_nota(): void
    {
        $traslado = $this->programar();
        $this->calificar($this->idEvaluacionOrigen, 4.0);

        $this->postJson("/admin/traslados/{$traslado->id_traslado}/cancelar")->assertStatus(422);

        $this->assertSame('PROGRAMADO', DB::table('traslado')->where('id_traslado', $traslado->id_traslado)->value('estado'));
    }

    public function test_no_se_programan_dos_traslados_a_la_vez(): void
    {
        $this->programar();

        $this->post('/admin/traslados', [
            'id_vinc_funcionario' => $this->idVincEvaluado,
            'id_vinc_evaluador_nuevo' => $this->idVincEvaluadorNuevo,
            'fecha_traslado' => Carbon::today()->toDateString(),
        ])->assertSessionHasErrors('traslados');

        $this->assertSame(1, DB::table('traslado')->where('id_vinc_funcionario', $this->idVincEvaluado)->count());
    }

    public function test_el_tramo_se_califica_con_su_nota_propia_sin_prorrateo(): void
    {
        $this->programar();
        $this->calificar($this->idEvaluacionOrigen, 4.5);

        $calculo = calcularNotaEvaluacion($this->idEvaluacionOrigen);

        $this->assertSame(self::DIAS_ORIGEN, (int) $calculo['dias_laborados']);
        $this->assertNull($calculo['nota_prorrateo']);
        $this->assertSame($calculo['nota_final'], $calculo['nota_definitiva']);
        $this->assertSame(nivelEscalaCalificacion($calculo['nota_final']), $calculo['categoria']);
    }

    public function test_la_nota_del_semestre_pondera_cada_tramo_por_sus_dias(): void
    {
        $traslado = $this->programar();
        $this->calificar($this->idEvaluacionOrigen, 5.0);
        $this->postJson("/admin/traslados/{$traslado->id_traslado}/confirmar")->assertOk();

        // Mientras el tramo de destino no tenga nota, el semestre no se consolida.
        $pendiente = obtenerNotaSemestreConsolidada($this->idVincEvaluado, 2099, 'RENDIMIENTO_LABORAL', 2);
        $this->assertSame('TRAMOS_PENDIENTES', $pendiente['tipo']);
        $this->assertNull($pendiente['nota']);

        $destino = $this->evaluacionDestino();
        $this->calificar($destino->id_evaluacion, 2.0);

        $notaOrigen = calcularNotaEvaluacion($this->idEvaluacionOrigen)['nota_final'];
        $notaDestino = calcularNotaEvaluacion((int) $destino->id_evaluacion)['nota_final'];
        $esperada = redondearEscala(($notaOrigen * self::DIAS_ORIGEN + $notaDestino * self::DIAS_DESTINO) / (self::DIAS_ORIGEN + self::DIAS_DESTINO));

        // Con días distintos, el ponderado no coincide con el promedio simple.
        $this->assertNotEquals(redondearEscala(($notaOrigen + $notaDestino) / 2), $esperada);

        $semestre = obtenerNotaSemestreConsolidada($this->idVincEvaluado, 2099, 'RENDIMIENTO_LABORAL', 2);
        $this->assertSame('TRAMOS_PONDERADOS', $semestre['tipo']);
        $this->assertEquals($esperada, $semestre['nota']);
        $this->assertSame(self::DIAS_ORIGEN + self::DIAS_DESTINO, $semestre['dias_laborados']);
    }

    private function avisosDe(int $idVinc)
    {
        $idUsuario = DB::table('vinculacion as v')
            ->join('funcionario as f', 'f.id_funcionario', '=', 'v.id_funcionario')
            ->where('v.id_vinculacion', $idVinc)
            ->value('f.id_usuario');

        return DB::table('notificacion')->where('id_usuario_destino', $idUsuario)->get();
    }

    public function test_al_confirmar_se_avisa_al_evaluado_y_al_nuevo_evaluador(): void
    {
        $traslado = $this->programar();
        $this->assertCount(0, $this->avisosDe($this->idVincEvaluado), 'Programar no avisa: el cambio aún no ocurre.');

        $this->calificar($this->idEvaluacionOrigen, 4.0);
        $this->postJson("/admin/traslados/{$traslado->id_traslado}/confirmar")->assertOk();

        $avisoEvaluado = $this->avisosDe($this->idVincEvaluado)->first();
        $this->assertSame('CAMBIO_EVALUADOR', $avisoEvaluado->tipo);
        $this->assertStringContainsString('por un traslado', $avisoEvaluado->mensaje);
        $this->assertStringContainsString('Tmp Destino', $avisoEvaluado->mensaje);

        $avisoEvaluador = $this->avisosDe($this->idVincEvaluadorNuevo)->first();
        $this->assertSame('NUEVO_EVALUADO', $avisoEvaluador->tipo);
        $this->assertStringContainsString('Tmp Evaluado', $avisoEvaluador->mensaje);

        // Los avisos personales no aparecen en la campana de Talento Humano.
        $this->getJson('/admin/notificaciones')
            ->assertOk()
            ->assertJsonMissing(['tipo' => 'CAMBIO_EVALUADOR'])
            ->assertJsonMissing(['tipo' => 'NUEVO_EVALUADO']);
    }

    public function test_cada_usuario_ve_y_marca_solo_sus_avisos(): void
    {
        $traslado = $this->programar();
        $this->calificar($this->idEvaluacionOrigen, 4.0);
        $this->postJson("/admin/traslados/{$traslado->id_traslado}/confirmar")->assertOk();

        $idUsuarioEvaluado = (int) DB::table('vinculacion as v')
            ->join('funcionario as f', 'f.id_funcionario', '=', 'v.id_funcionario')
            ->where('v.id_vinculacion', $this->idVincEvaluado)
            ->value('f.id_usuario');
        session(['usuario_autenticado' => ['id_usuario' => $idUsuarioEvaluado, 'rol_activo' => 'evaluado', 'roles' => ['evaluado']]]);

        $this->getJson('/mis-notificaciones')
            ->assertOk()
            ->assertJsonPath('no_leidas', 1)
            ->assertJsonCount(1, 'notificaciones')
            ->assertJsonPath('notificaciones.0.tipo', 'CAMBIO_EVALUADOR');

        $this->postJson('/mis-notificaciones/marcar-leidas')->assertOk();

        $this->getJson('/mis-notificaciones')->assertJsonPath('no_leidas', 0);
        $this->assertSame(0, (int) $this->avisosDe($this->idVincEvaluadorNuevo)->first()->leida, 'No marca los avisos de otros.');
    }

    public function test_el_evaluado_consulta_sus_tramos_con_responsable_fechas_y_nota(): void
    {
        $traslado = $this->programar();
        $this->calificar($this->idEvaluacionOrigen, 5.0);
        $this->postJson("/admin/traslados/{$traslado->id_traslado}/confirmar")->assertOk();

        $idFuncionario = (int) DB::table('vinculacion')->where('id_vinculacion', $this->idVincEvaluado)->value('id_funcionario');
        $periodos = tramosEvaluadoPorPeriodo($idFuncionario);

        $this->assertCount(1, $periodos);
        [$origen, $destino] = $periodos[0]['tramos'];

        $this->assertSame('Tmp Origen', $origen['evaluador']);
        $this->assertSame('Inicio del periodo', $origen['motivo']);
        $this->assertSame(Carbon::today()->subDay()->toDateString(), $origen['hasta']);
        $this->assertSame(self::DIAS_ORIGEN, (int) $origen['dias']);

        $this->assertSame('Tmp Destino', $destino['evaluador']);
        $this->assertSame('Traslado', $destino['motivo']);
        $this->assertSame(Carbon::today()->toDateString(), $destino['desde']);
        $this->assertNull($destino['nota']);
        $this->assertNull($periodos[0]['nota_semestre'], 'El semestre se consolida cuando todos los tramos tienen nota.');
    }

    public function test_el_panel_del_evaluado_muestra_sus_tramos_y_la_campana(): void
    {
        $traslado = $this->programar();
        $this->calificar($this->idEvaluacionOrigen, 5.0);
        $this->postJson("/admin/traslados/{$traslado->id_traslado}/confirmar")->assertOk();

        $evaluado = DB::table('vinculacion as v')
            ->join('funcionario as f', 'f.id_funcionario', '=', 'v.id_funcionario')
            ->where('v.id_vinculacion', $this->idVincEvaluado)
            ->first(['f.id_usuario', 'f.id_funcionario']);
        session(['usuario_autenticado' => [
            'id_usuario' => $evaluado->id_usuario,
            'id_funcionario' => $evaluado->id_funcionario,
            'nombres' => 'Tmp',
            'apellidos' => 'Evaluado',
            'rol_activo' => 'evaluado',
            'roles' => ['evaluado'],
        ]]);

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('Mis tramos de evaluación')
            ->assertSee('Tmp Origen')
            ->assertSee('Tmp Destino')
            ->assertSee('Nota del semestre pendiente')
            ->assertSee('data-url-lista="/mis-notificaciones"', false);
    }

    private function eventos(int $idEvaluacion): array
    {
        return DB::table('evaluacion_evento')->where('id_evaluacion', $idEvaluacion)->orderBy('id_evento')->pluck('tipo_evento')->all();
    }

    public function test_la_bitacora_registra_programacion_cierre_del_tramo_y_apertura(): void
    {
        $traslado = $this->programar();
        $this->calificar($this->idEvaluacionOrigen, 4.0);
        $this->postJson("/admin/traslados/{$traslado->id_traslado}/confirmar")->assertOk();

        $this->assertSame(['CAMBIO_EVALUADOR_PROGRAMADO', 'TRAMO_CERRADO'], $this->eventos($this->idEvaluacionOrigen));

        $destino = $this->evaluacionDestino();
        $this->assertSame(['EVALUACION_ABIERTA'], $this->eventos($destino->id_evaluacion));

        $apertura = DB::table('evaluacion_evento')->where('id_evaluacion', $destino->id_evaluacion)->first();
        $this->assertSame($this->idUsuario, (int) $apertura->id_usuario_actor);
        $this->assertSame('admin', $apertura->rol_actor);
        $this->assertSame('EN_PROCESO', $apertura->estado_nuevo);
        $datos = json_decode($apertura->datos, true);
        $this->assertSame('TRASLADO', $datos['origen']);
        $this->assertSame($this->idVincEvaluadorOrigen, $datos['id_vinc_evaluador_anterior']);
        $this->assertSame(self::DIAS_DESTINO, $datos['dias_laborados']);
    }

    public function test_la_bitacora_registra_la_cancelacion(): void
    {
        $traslado = $this->programar();
        $this->postJson("/admin/traslados/{$traslado->id_traslado}/cancelar")->assertOk();

        $this->assertSame(['CAMBIO_EVALUADOR_PROGRAMADO', 'CAMBIO_EVALUADOR_CANCELADO'], $this->eventos($this->idEvaluacionOrigen));
    }

    public function test_la_bitacora_la_consultan_th_y_el_evaluado_pero_no_un_ajeno(): void
    {
        $traslado = $this->programar();
        $this->calificar($this->idEvaluacionOrigen, 4.0);
        $this->postJson("/admin/traslados/{$traslado->id_traslado}/confirmar")->assertOk();
        $idDestino = $this->evaluacionDestino()->id_evaluacion;

        $this->getJson("/evaluaciones/{$idDestino}/bitacora")
            ->assertOk()
            ->assertJsonPath('eventos.0.tipo_evento', 'EVALUACION_ABIERTA')
            ->assertJsonPath("vinculaciones.{$this->idVincEvaluadorNuevo}", nombreFuncionarioVinc($this->idVincEvaluadorNuevo));

        $evaluado = DB::table('vinculacion as v')->join('funcionario as f', 'f.id_funcionario', '=', 'v.id_funcionario')
            ->where('v.id_vinculacion', $this->idVincEvaluado)->first(['f.id_usuario', 'f.id_funcionario']);
        session(['usuario_autenticado' => ['id_usuario' => $evaluado->id_usuario, 'id_funcionario' => $evaluado->id_funcionario, 'rol_activo' => 'evaluado', 'roles' => ['evaluado']]]);
        $this->getJson("/evaluaciones/{$idDestino}/bitacora")->assertOk();

        // El evaluador de origen no tuvo la evaluación del cargo nuevo.
        $origen = DB::table('vinculacion')->where('id_vinculacion', $this->idVincEvaluadorOrigen)->first();
        $idUsuarioOrigen = DB::table('funcionario')->where('id_funcionario', $origen->id_funcionario)->value('id_usuario');
        session(['usuario_autenticado' => ['id_usuario' => $idUsuarioOrigen, 'id_funcionario' => $origen->id_funcionario, 'rol_activo' => 'evaluador', 'roles' => ['evaluador']]]);
        $this->getJson("/evaluaciones/{$idDestino}/bitacora")->assertForbidden();
        $this->getJson("/evaluaciones/{$this->idEvaluacionOrigen}/bitacora")->assertOk();
    }

    /** Funcionario reemplazado con dos compromisos y una evidencia en el mismo periodo. */
    private function crearReemplazado(): array
    {
        $vinc = $this->crearVinculacion($this->crearFuncionario('Tmp', 'Reemplazado', 'R' . random_int(10000, 99999)), false);
        $idEvaluacion = DB::table('evaluacion')->insertGetId([
            'id_periodo' => $this->idPeriodo,
            'id_vinc_evaluado' => $vinc,
            'id_vinc_evaluador' => $this->idVincEvaluadorNuevo,
            'tipo_evaluacion' => 'SEMESTRE_2',
            'fase_actual' => 3,
            'concertacion_firmada' => 1,
            'estado' => 'EN_PROCESO',
        ]);
        $compromisos = [];
        // Suman el 80 % que pesan los compromisos en Rendimiento Laboral.
        foreach ([1 => 50, 2 => 30] as $orden => $peso) {
            $compromisos[] = DB::table('compromiso')->insertGetId([
                'id_evaluacion' => $idEvaluacion,
                'numero_orden' => $orden,
                'descripcion' => "Compromiso del cargo {$orden}",
                'porcentaje_peso' => $peso,
            ]);
            DB::table('compromiso_meta')->insert(['id_compromiso' => end($compromisos), 'meta' => "Meta {$orden}"]);
        }
        DB::table('evidencia')->insert([
            'id_evaluacion' => $idEvaluacion,
            'id_compromiso' => $compromisos[0],
            'componente' => 'B',
            'descripcion' => 'Evidencia del reemplazado',
            'tipo_evidencia' => 'LINK',
            'url_o_ubicacion' => 'https://unitropico.edu.co/e.pdf',
            'fecha_inclusion' => now(),
            'id_vinc_registra' => $vinc,
            'estado_aprobacion' => 'PENDIENTE',
        ]);

        return [$vinc, $idEvaluacion, $compromisos];
    }

    private function trasladarReemplazando(int $vincReemplazado): object
    {
        $this->post('/admin/traslados', [
            'id_vinc_funcionario' => $this->idVincEvaluado,
            'id_vinc_evaluador_nuevo' => $this->idVincEvaluadorNuevo,
            'id_vinc_reemplazado' => $vincReemplazado,
            'fecha_traslado' => Carbon::today()->toDateString(),
        ])->assertSessionHasNoErrors();
        $this->calificar($this->idEvaluacionOrigen, 4.0);
        $traslado = DB::table('traslado')->where('id_vinc_funcionario', $this->idVincEvaluado)->first();
        $this->postJson("/admin/traslados/{$traslado->id_traslado}/confirmar")->assertOk();

        return $this->evaluacionDestino();
    }

    public function test_los_compromisos_copiados_conservan_la_referencia_al_original(): void
    {
        [$vincReemplazado, $idEvalReemplazado, $originales] = $this->crearReemplazado();
        $destino = $this->trasladarReemplazando($vincReemplazado);

        $copias = DB::table('compromiso')->where('id_evaluacion', $destino->id_evaluacion)->orderBy('numero_orden')->get();
        $this->assertSame($originales, $copias->pluck('id_compromiso_origen')->map(fn ($v) => (int) $v)->all());
        $this->assertTrue($copias->every(fn ($c) => (int) $c->es_copia === 1 && (int) $c->copia_ajustada === 0));
        $this->assertSame(0, DB::table('evidencia')->where('id_evaluacion', $destino->id_evaluacion)->count(), 'Las evidencias no se copian.');

        $evento = DB::table('evaluacion_evento')->where('id_evaluacion', $destino->id_evaluacion)->where('tipo_evento', 'COMPROMISOS_COPIADOS')->first();
        $this->assertSame(['cantidad' => 2, 'id_evaluacion_origen' => $idEvalReemplazado, 'id_vinc_funcionario_origen' => $vincReemplazado], json_decode($evento->datos, true));
    }

    public function test_th_ve_el_origen_y_el_evaluado_solo_la_marca(): void
    {
        [$vincReemplazado, $idEvalReemplazado] = $this->crearReemplazado();
        $destino = $this->trasladarReemplazando($vincReemplazado);

        $this->getJson("/evaluaciones/{$destino->id_evaluacion}/compromisos")
            ->assertOk()
            ->assertJsonPath('compromisos.0.es_copia', true)
            ->assertJsonPath('compromisos.0.origen.id_evaluacion', $idEvalReemplazado)
            ->assertJsonPath('compromisos.0.origen.funcionario', nombreFuncionarioVinc($vincReemplazado));

        $evaluado = DB::table('vinculacion as v')->join('funcionario as f', 'f.id_funcionario', '=', 'v.id_funcionario')
            ->where('v.id_vinculacion', $this->idVincEvaluado)->first(['f.id_usuario', 'f.id_funcionario']);
        session(['usuario_autenticado' => ['id_usuario' => $evaluado->id_usuario, 'id_funcionario' => $evaluado->id_funcionario, 'rol_activo' => 'evaluado', 'roles' => ['evaluado']]]);

        $compromisos = $this->getJson("/evaluaciones/{$destino->id_evaluacion}/compromisos")->assertOk()->json('compromisos');
        $this->assertTrue($compromisos[0]['es_copia']);
        $this->assertArrayNotHasKey('origen', $compromisos[0]);
        $this->assertArrayNotHasKey('id_compromiso_origen', $compromisos[0]);

        $bitacora = $this->getJson("/evaluaciones/{$destino->id_evaluacion}/bitacora")->assertOk();
        $copia = collect($bitacora->json('eventos'))->firstWhere('tipo_evento', 'COMPROMISOS_COPIADOS');
        $this->assertSame(['cantidad' => 2], $copia['datos']);
        $this->assertArrayNotHasKey((string) $vincReemplazado, $bitacora->json('vinculaciones') ?? []);
    }

    public function test_al_editar_una_copia_se_conserva_la_referencia_y_se_marca_ajustada(): void
    {
        [$vincReemplazado, , $originales] = $this->crearReemplazado();
        $destino = $this->trasladarReemplazando($vincReemplazado);
        $copia = DB::table('compromiso')->where('id_evaluacion', $destino->id_evaluacion)->where('numero_orden', 1)->first();

        $idUsuarioNuevo = DB::table('funcionario')->where('id_funcionario', $this->idFuncionarioEvaluadorNuevo)->value('id_usuario');
        session(['usuario_autenticado' => ['id_usuario' => $idUsuarioNuevo, 'id_funcionario' => $this->idFuncionarioEvaluadorNuevo, 'rol_activo' => 'evaluador', 'roles' => ['evaluador']]]);

        $this->putJson("/compromisos/{$copia->id_compromiso}", [
            'descripcion' => 'Compromiso ajustado al nuevo cargo',
            'porcentaje_peso' => 50,
            'metas' => ['Meta ajustada'],
        ])->assertOk();

        $copia = DB::table('compromiso')->where('id_compromiso', $copia->id_compromiso)->first();
        $this->assertSame($originales[0], (int) $copia->id_compromiso_origen);
        $this->assertSame(1, (int) $copia->copia_ajustada);
    }

    public function test_th_abre_periodos_parciales_de_menos_de_90_dias(): void
    {
        $inicio = Carbon::today()->subDays(20);
        $this->post('/admin/periodos-parciales', [
            'id_periodo' => $this->idPeriodo,
            'id_vinc_funcionario' => $this->idVincEvaluado,
            'fecha_inicio' => $inicio->toDateString(),
            'fecha_fin' => $inicio->copy()->addDays(14)->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, DB::table('periodo_parcial')->where('id_vinc_funcionario', $this->idVincEvaluado)->count());
    }

    public function test_una_sola_parcial_es_la_nota_del_semestre(): void
    {
        DB::table('evaluacion')->where('id_evaluacion', $this->idEvaluacionOrigen)->update(['tipo_evaluacion' => 'PARCIAL', 'dias_laborados' => 40]);
        $this->calificar($this->idEvaluacionOrigen, 4.5);

        $semestre = obtenerNotaSemestreConsolidada($this->idVincEvaluado, 2099, 'RENDIMIENTO_LABORAL', 2);

        $this->assertSame('PARCIALES_PONDERADAS', $semestre['tipo']);
        $this->assertEquals(calcularNotaEvaluacion($this->idEvaluacionOrigen)['nota_final'], $semestre['nota']);
    }

    public function test_varias_parciales_se_ponderan_por_dias_sin_minimo(): void
    {
        DB::table('evaluacion')->where('id_evaluacion', $this->idEvaluacionOrigen)->update(['tipo_evaluacion' => 'PARCIAL', 'dias_laborados' => 30]);
        $this->calificar($this->idEvaluacionOrigen, 5.0);
        $segunda = $this->crearEvaluacion($this->idVincEvaluadorNuevo, 'PARCIAL', 'EN_PROCESO');
        DB::table('evaluacion')->where('id_evaluacion', $segunda)->update(['dias_laborados' => 120]);
        $this->calificar($segunda, 2.0);

        $notaA = calcularNotaEvaluacion($this->idEvaluacionOrigen)['nota_final'];
        $notaB = calcularNotaEvaluacion($segunda)['nota_final'];
        $esperada = redondearEscala(($notaA * 30 + $notaB * 120) / 150);
        $this->assertNotEquals(redondearEscala(($notaA + $notaB) / 2), $esperada);

        $semestre = obtenerNotaSemestreConsolidada($this->idVincEvaluado, 2099, 'RENDIMIENTO_LABORAL', 2);
        $this->assertEquals($esperada, $semestre['nota']);
        $this->assertSame(150, $semestre['dias_laborados'], 'El tramo de 30 días también cuenta.');
    }

    public function test_no_se_abre_otra_evaluacion_mientras_haya_una_abierta(): void
    {
        $traslado = $this->programar();
        $this->calificar($this->idEvaluacionOrigen, 4.0);
        $this->postJson("/admin/traslados/{$traslado->id_traslado}/confirmar")->assertOk();

        // Sin la semestral de origen, solo queda abierta la PARCIAL de destino.
        DB::table('evaluacion')->where('id_evaluacion', $this->idEvaluacionOrigen)->update(['tipo_evaluacion' => 'PARCIAL']);

        session(['usuario_autenticado' => [
            'id_usuario' => $this->idUsuario,
            'id_funcionario' => $this->idFuncionarioEvaluadorNuevo,
            'rol_activo' => 'evaluador',
            'roles' => ['evaluador'],
        ]]);

        $this->post('/evaluador/asignaciones', [
            'id_periodo' => $this->idPeriodo,
            'id_vinc_evaluado' => $this->idVincEvaluado,
            'tipo_evaluacion' => 'SEMESTRE_2',
        ])->assertSessionHasErrors(['asignaciones' => 'El funcionario ya tiene una evaluación abierta en este periodo. Debe calificarse antes de abrir otra.']);
    }
}
