<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * S14 — Catálogo de plazas (modelo de puestos, fases A-B).
 *
 * El borrador crea una plaza por vinculación activa con código
 * dependencia-cargo-grado-consecutivo, su ocupante y su superior según el jefe
 * registrado. Las que comparten dependencia, cargo y grado quedan por revisar;
 * las demás se validan en bloque.
 *
 * La prueba trabaja sobre la base configurada en el entorno y se ejecuta dentro
 * de una transacción que se revierte al terminar, de modo que no deja datos.
 */
class PlazaCatalogoTest extends TestCase
{
    private int $idUsuario;
    private int $idDependencia;
    private string $sufijo;
    private int $jefe;
    private int $profesional1;
    private int $profesional2;
    private int $tecnico;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requiere una base MySQL con el esquema institucional aplicado.');
        }

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
        DB::beginTransaction();

        $this->sufijo = 'T' . random_int(10000, 99999);
        $this->idUsuario = DB::table('usuario')->insertGetId(['username' => "tmp.plaza.{$this->sufijo}", 'password' => bcrypt('x'), 'rol' => 'ADMINISTRADOR', 'activo' => 1]);
        // El catálogo trae la dependencia sin tilde; la vinculación, con tilde.
        $this->idDependencia = DB::table('dependencia')->insertGetId(['nombre' => "OFICINA DE RECURSOS FISICOS {$this->sufijo}", 'activa' => 1]);
        foreach (['JEFE DE OFICINA' => [6, 'DIRECTIVO'], 'PROFESIONAL UNIVERSITARIO' => [219, 'PROFESIONAL'], 'TECNICO ADMINISTRATIVO' => [367, 'TECNICO']] as $nombre => [$codigo, $nivel]) {
            DB::table('cargo')->insertOrIgnore(['nombre' => $nombre, 'codigo_cargo' => $codigo, 'nivel_jerarquico' => $nivel, 'activo' => 1]);
        }

        $this->jefe = $this->vinculacion('JEFE DE OFICINA', 6, 1, null);
        $this->profesional1 = $this->vinculacion('PROFESIONAL UNIVERSITARIO', 219, 10, $this->jefe);
        $this->profesional2 = $this->vinculacion('PROFESIONAL UNIVERSITARIO', 219, 10, $this->jefe);
        $this->tecnico = $this->vinculacion('TECNICO ADMINISTRATIVO', 367, 12, $this->jefe, null);

        session(['usuario_autenticado' => ['id_usuario' => $this->idUsuario, 'rol_activo' => 'admin', 'roles' => ['admin']]]);
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    private function vinculacion(string $cargo, int $codigo, int $grado, ?int $jefe, ?string $ingreso = '2025-02-01'): int
    {
        $doc = 'P' . random_int(100000, 999999);
        $idFuncionario = DB::table('funcionario')->insertGetId([
            'id_usuario' => DB::table('usuario')->insertGetId(['username' => "tmp.plaza.{$doc}", 'password' => bcrypt('x'), 'rol' => 'EVALUADO', 'activo' => 1]),
            'tipo_documento' => 'CEDULA_CIUDADANIA',
            'numero_doc' => $doc,
            'nombres' => 'Tmp',
            'apellidos' => $doc,
        ]);

        return DB::table('vinculacion')->insertGetId([
            'id_funcionario' => $idFuncionario,
            'cargo' => $cargo,
            'codigo_cargo' => $codigo,
            'grado_cargo' => $grado,
            'nivel_jerarquico' => 'PROFESIONAL',
            'area' => "OFICINA DE RECURSOS FÍSICOS {$this->sufijo}",
            'tipo_vinculacion' => 'PROVISIONALIDAD',
            'sistema_evaluacion' => 'RENDIMIENTO_LABORAL',
            'es_evaluador' => 0,
            'id_vinc_jefe' => $jefe,
            'fecha_ingreso' => $ingreso,
            'activa' => 1,
        ]);
    }

    private function plazaDe(int $idVinc): object
    {
        return DB::table('puesto as p')
            ->join('puesto_ocupacion as po', 'po.id_puesto', '=', 'p.id_puesto')
            ->where('po.id_vinculacion', $idVinc)
            ->first(['p.*', 'po.vigente_desde', 'po.fecha_estimada', 'po.tipo_ocupacion']);
    }

    public function test_el_borrador_crea_una_plaza_por_vinculacion_con_codigo_ocupante_y_superior(): void
    {
        $this->postJson('/admin/plazas/generar')->assertOk()->assertJsonPath('sin_catalogo', []);

        $jefe = $this->plazaDe($this->jefe);
        $p1 = $this->plazaDe($this->profesional1);
        $p2 = $this->plazaDe($this->profesional2);
        $tec = $this->plazaDe($this->tecnico);

        $dep = sprintf('D%03d', $this->idDependencia);
        $this->assertSame("{$dep}-219-10-01", $p1->codigo_plaza);
        $this->assertSame("{$dep}-219-10-02", $p2->codigo_plaza);
        $this->assertSame('BORRADOR', $p1->estado);
        $this->assertSame((int) $jefe->id_puesto, (int) $p1->id_puesto_superior, 'El superior sale del jefe registrado.');
        $this->assertNull($jefe->id_puesto_superior);

        // Comparten dependencia, cargo y grado: por revisar. El técnico es único.
        $this->assertSame([1, 1, 0], [(int) $p1->requiere_revision, (int) $p2->requiere_revision, (int) $tec->requiere_revision]);

        $this->assertSame('2025-02-01', $p1->vigente_desde);
        $this->assertSame([null, 1], [$tec->vigente_desde, (int) $tec->fecha_estimada], 'Sin fecha de ingreso no se inventa: queda estimada.');
    }

    public function test_el_borrador_se_puede_repetir_y_solo_agrega_lo_que_falta(): void
    {
        $this->postJson('/admin/plazas/generar')->assertOk();
        $nuevo = $this->vinculacion('TECNICO ADMINISTRATIVO', 367, 12, $this->jefe);

        $this->postJson('/admin/plazas/generar')->assertOk()->assertJsonPath('creadas', 1);

        $this->assertSame(sprintf('D%03d-367-12-02', $this->idDependencia), $this->plazaDe($nuevo)->codigo_plaza);
        $this->assertSame(1, (int) $this->plazaDe($this->tecnico)->requiere_revision, 'Al aparecer otra igual, la primera también se revisa.');
    }

    public function test_la_validacion_masiva_deja_por_revisar_las_dudosas(): void
    {
        $this->postJson('/admin/plazas/generar')->assertOk();

        $this->postJson('/admin/plazas/validar-masivo')->assertOk()->assertJsonPath('validadas', 2);

        $this->assertSame('VALIDADA', $this->plazaDe($this->jefe)->estado);
        $this->assertSame('BORRADOR', $this->plazaDe($this->profesional1)->estado);

        $this->postJson('/admin/plazas/' . $this->plazaDe($this->profesional1)->id_puesto . '/validar')->assertOk();
        $p1 = $this->plazaDe($this->profesional1);
        $this->assertSame(['VALIDADA', 0], [$p1->estado, (int) $p1->requiere_revision]);
        $this->assertSame($this->idUsuario, (int) $p1->validada_por);
    }

    public function test_th_corrige_codigo_y_superior_sin_codigos_repetidos_ni_ciclos(): void
    {
        $this->postJson('/admin/plazas/generar')->assertOk();
        $jefe = $this->plazaDe($this->jefe);
        $p1 = $this->plazaDe($this->profesional1);
        $p2 = $this->plazaDe($this->profesional2);

        $this->postJson("/admin/plazas/{$p1->id_puesto}", ['codigo_plaza' => $p2->codigo_plaza, 'id_puesto_superior' => $jefe->id_puesto])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['codigo_plaza' => 'Ya existe otra plaza con ese código.']);

        // El jefe no puede depender de una plaza que depende de él.
        $this->postJson("/admin/plazas/{$jefe->id_puesto}", ['codigo_plaza' => $jefe->codigo_plaza, 'id_puesto_superior' => $p1->id_puesto])->assertStatus(422);

        $this->postJson("/admin/plazas/{$p1->id_puesto}", ['codigo_plaza' => 'PLZ-INSTITUCIONAL-001', 'id_puesto_superior' => $jefe->id_puesto])->assertOk();
        $this->assertSame('PLZ-INSTITUCIONAL-001', $this->plazaDe($this->profesional1)->codigo_plaza);
    }

    private function cambiarModo(string $modo)
    {
        return $this->postJson('/admin/plazas/modo', ['modo' => $modo]);
    }

    public function test_el_modelo_arranca_apagado_y_observacion_exige_plazas(): void
    {
        $this->getJson('/admin/plazas')->assertOk()->assertJsonPath('modelo.modo', 'APAGADO');

        $this->cambiarModo('OBSERVACION')->assertStatus(422);

        $this->postJson('/admin/plazas/generar')->assertOk();
        $this->cambiarModo('OBSERVACION')->assertOk();

        $this->getJson('/admin/plazas')->assertJsonPath('modelo.modo', 'OBSERVACION')
            ->assertJsonPath('modelo.historial.0.modo_anterior', 'APAGADO')
            ->assertJsonPath('modelo.historial.0.modo_nuevo', 'OBSERVACION');
    }

    public function test_activar_exige_observacion_catalogo_validado_y_sin_diferencias(): void
    {
        $this->postJson('/admin/plazas/generar')->assertOk();

        // Directo desde Apagado: no se compararon resultados.
        $this->cambiarModo('ACTIVO')->assertStatus(422);

        $this->cambiarModo('OBSERVACION')->assertOk();
        $mensaje = $this->cambiarModo('ACTIVO')->assertStatus(422)->json('message');
        $this->assertStringContainsString('sin validar', $mensaje);

        DB::table('puesto')->update(['estado' => 'VALIDADA', 'requiere_revision' => 0]);
        $idEvaluacion = DB::table('evaluacion')->insertGetId([
            'id_periodo' => DB::table('periodo')->insertGetId(['id_usuario_apertura' => $this->idUsuario, 'sistema' => 'RENDIMIENTO_LABORAL', 'anio' => 2099, 'semestre' => 1, 'fecha_inicio' => '2099-01-01', 'fecha_fin' => '2099-06-30', 'estado' => 'ABIERTO']),
            'id_vinc_evaluado' => $this->profesional1, 'id_vinc_evaluador' => $this->jefe, 'tipo_evaluacion' => 'SEMESTRE_1',
            'fase_actual' => 1, 'concertacion_firmada' => 0, 'estado' => 'EN_PROCESO',
        ]);
        DB::table('puesto_discrepancia')->insert(['id_evaluacion' => $idEvaluacion, 'motivo' => 'Prueba', 'created_at' => now(), 'updated_at' => now()]);

        $this->assertStringContainsString('diferencia(s)', $this->cambiarModo('ACTIVO')->assertStatus(422)->json('message'));

        DB::table('puesto_discrepancia')->update(['resuelta' => 1]);
        $this->cambiarModo('ACTIVO')->assertOk();

        // Apagar siempre está permitido.
        $this->cambiarModo('APAGADO')->assertOk();
    }

    // --- Resolver de responsable en modo Observación ---

    /** Evaluación abierta de profesional1, hoy a cargo de $evaluador. */
    private function evaluacionAbierta(int $evaluador, ?int $suplente = null): int
    {
        return DB::table('evaluacion')->insertGetId([
            'id_periodo' => DB::table('periodo')->insertGetId(['id_usuario_apertura' => $this->idUsuario, 'sistema' => 'RENDIMIENTO_LABORAL', 'anio' => 2099, 'semestre' => 2, 'fecha_inicio' => '2099-07-01', 'fecha_fin' => '2099-12-31', 'estado' => 'ABIERTO']),
            'id_vinc_evaluado' => $this->profesional1, 'id_vinc_evaluador' => $evaluador, 'id_vinc_suplente' => $suplente,
            'tipo_evaluacion' => 'SEMESTRE_2', 'fase_actual' => 1, 'concertacion_firmada' => 0, 'estado' => 'EN_PROCESO',
        ]);
    }

    private function observando(): void
    {
        $this->postJson('/admin/plazas/generar')->assertOk();
        $this->cambiarModo('OBSERVACION')->assertOk();
    }

    /** El jefe deja su plaza y la ocupa $nuevo (o queda vacante si es null). */
    private function relevarJefe(?int $nuevo): void
    {
        $plaza = $this->plazaDe($this->jefe)->id_puesto;
        DB::table('puesto_ocupacion')->where('id_vinculacion', $this->jefe)->update(['estado' => 'CERRADA', 'vigente_hasta' => now()->toDateString()]);
        if ($nuevo) {
            DB::table('puesto_ocupacion')->insert(['id_puesto' => $plaza, 'id_vinculacion' => $nuevo, 'estado' => 'VIGENTE', 'vigente_desde' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function pendientes(): array
    {
        return $this->postJson('/admin/plazas/observar')->assertOk()->json('resultado');
    }

    public function test_sin_cambios_de_ocupante_no_hay_diferencias(): void
    {
        $this->observando();
        $this->evaluacionAbierta($this->jefe);

        $this->assertSame(0, $this->pendientes()['diferencias_pendientes']);
    }

    public function test_detecta_evaluaciones_a_cargo_de_quien_ya_no_ocupa_la_plaza(): void
    {
        $this->observando();
        $idEvaluacion = $this->evaluacionAbierta($this->jefe);
        $nuevoJefe = $this->vinculacion('JEFE DE OFICINA', 6, 1, null);
        $this->relevarJefe($nuevoJefe);

        $this->assertSame(1, $this->pendientes()['diferencias_pendientes']);
        $diferencia = DB::table('puesto_discrepancia')->where('id_evaluacion', $idEvaluacion)->first();
        $this->assertSame([$this->jefe, $nuevoJefe], [(int) $diferencia->id_vinc_responsable_legado, (int) $diferencia->id_vinc_responsable_puesto]);
        $this->assertSame($this->jefe, (int) DB::table('evaluacion')->where('id_evaluacion', $idEvaluacion)->value('id_vinc_evaluador'), 'Observar no reasigna nada.');

        // Una segunda comparación no la duplica; al corregirla, se resuelve sola.
        $this->assertSame(0, $this->pendientes()['diferencias_nuevas']);
        DB::table('evaluacion')->where('id_evaluacion', $idEvaluacion)->update(['id_vinc_evaluador' => $nuevoJefe]);
        $this->assertSame(1, $this->pendientes()['resueltas_solas']);
    }

    public function test_plaza_vacante_queda_como_diferencia(): void
    {
        $this->observando();
        $this->evaluacionAbierta($this->jefe);
        $this->relevarJefe(null);

        $this->pendientes();
        $this->assertSame('La plaza está vacante.', DB::table('puesto_discrepancia')->value('motivo'));
    }

    public function test_la_delegacion_vigente_de_la_plaza_no_es_diferencia(): void
    {
        $this->observando();
        DB::table('delegacion')->insert([
            'id_vinc_delegante' => $this->jefe, 'id_vinc_delegado' => $this->tecnico, 'fecha_inicio' => now()->subDay()->toDateString(),
            'fecha_fin' => now()->addDays(10)->toDateString(), 'estado' => 'ACTIVA', 'created_at' => now(), 'updated_at' => now(),
        ]);
        // Delegación corta: la evaluación la atiende el delegado con el titular como suplente.
        $this->evaluacionAbierta($this->tecnico, $this->jefe);

        $this->assertSame(0, $this->pendientes()['diferencias_pendientes']);
    }

    public function test_una_diferencia_revisada_por_th_no_se_vuelve_a_abrir(): void
    {
        $this->observando();
        $this->evaluacionAbierta($this->jefe);
        $this->relevarJefe($this->vinculacion('JEFE DE OFICINA', 6, 1, null));
        $this->pendientes();
        $id = DB::table('puesto_discrepancia')->value('id_discrepancia');

        $this->postJson("/admin/plazas/diferencias/{$id}/revisar")->assertOk();

        $this->assertSame(['diferencias_nuevas' => 0, 'diferencias_pendientes' => 0], array_intersect_key($this->pendientes(), array_flip(['diferencias_nuevas', 'diferencias_pendientes'])));
        $this->assertSame($this->idUsuario, (int) DB::table('puesto_discrepancia')->where('id_discrepancia', $id)->value('resuelta_por'));
    }

    public function test_comparar_exige_modo_observacion(): void
    {
        $this->postJson('/admin/plazas/observar')->assertStatus(422);
    }

    // --- Ocupaciones al día (plan 3.1 y fase F) ---

    private function ocupacionVigente(int $idVinc): ?object
    {
        return DB::table('puesto_ocupacion')->where('id_vinculacion', $idVinc)->where('estado', 'VIGENTE')->first();
    }

    public function test_al_inhabilitar_al_funcionario_su_plaza_queda_vacante(): void
    {
        $this->postJson('/admin/plazas/generar')->assertOk();
        $plaza = $this->plazaDe($this->profesional1)->id_puesto;
        $idFuncionario = DB::table('vinculacion')->where('id_vinculacion', $this->profesional1)->value('id_funcionario');

        $this->post("/admin/funcionarios/{$idFuncionario}/disable")->assertRedirect();

        $this->assertNull($this->ocupacionVigente($this->profesional1));
        $cerrada = DB::table('puesto_ocupacion')->where('id_vinculacion', $this->profesional1)->first();
        $this->assertSame(['CERRADA', 'Funcionario inhabilitado', $this->idUsuario], [$cerrada->estado, $cerrada->motivo_fin, (int) $cerrada->cerrado_por]);
        $this->assertContains($plaza, array_map(fn ($p) => (int) $p->id_puesto, app(\App\Services\OcupacionPuestos::class)->vacantes()));
    }

    public function test_la_vacancia_libera_la_plaza_y_al_cerrarla_se_recupera_si_sigue_libre(): void
    {
        $this->postJson('/admin/plazas/generar')->assertOk();
        $plaza = (int) $this->plazaDe($this->tecnico)->id_puesto;

        $this->post("/admin/vinculaciones/{$this->tecnico}/vacancia", ['es_vacante' => 1])->assertRedirect();
        $this->assertNull($this->ocupacionVigente($this->tecnico));

        $this->post("/admin/vinculaciones/{$this->tecnico}/vacancia", ['es_vacante' => 0])->assertRedirect();
        $this->assertSame($plaza, (int) $this->ocupacionVigente($this->tecnico)->id_puesto);
    }

    public function test_al_cerrar_la_vacancia_no_desplaza_a_quien_ya_ocupa_la_plaza(): void
    {
        $this->postJson('/admin/plazas/generar')->assertOk();
        $plaza = (int) $this->plazaDe($this->tecnico)->id_puesto;
        $this->post("/admin/vinculaciones/{$this->tecnico}/vacancia", ['es_vacante' => 1])->assertRedirect();
        $reemplazo = $this->vinculacion('TECNICO ADMINISTRATIVO', 367, 12, $this->jefe);
        $this->assertNull(app(\App\Services\OcupacionPuestos::class)->ocupar($plaza, $reemplazo, now()->toDateString(), 'Prueba'));

        $this->post("/admin/vinculaciones/{$this->tecnico}/vacancia", ['es_vacante' => 0])->assertRedirect();

        $this->assertNull($this->ocupacionVigente($this->tecnico), 'Queda sin plaza para que TH se la asigne.');
        $this->assertSame($reemplazo, (int) $this->ocupacionVigente($reemplazo)->id_vinculacion);
    }

    public function test_una_plaza_no_admite_segundo_ocupante(): void
    {
        $this->postJson('/admin/plazas/generar')->assertOk();
        $plaza = (int) $this->plazaDe($this->jefe)->id_puesto;
        $otro = $this->vinculacion('JEFE DE OFICINA', 6, 1, null);

        $this->assertStringContainsString('ya tiene un ocupante', app(\App\Services\OcupacionPuestos::class)->ocupar($plaza, $otro, now()->toDateString(), 'Prueba'));

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        DB::table('puesto_ocupacion')->insert(['id_puesto' => $plaza, 'id_vinculacion' => $otro, 'estado' => 'VIGENTE']);
    }

    /** Traslado inmediato (sin periodo abierto) del profesional1 a otro cargo. */
    private ?int $nuevoEvaluador = null;

    private function trasladar(array $extra)
    {
        if (! $this->nuevoEvaluador) {
            DB::table('evaluador_asignacion')->insert(['id_vinc_evaluador' => $this->jefe, 'id_vinc_evaluado' => $this->profesional1, 'fecha_asignacion' => now()]);
            $this->nuevoEvaluador = $this->vinculacion('JEFE DE OFICINA', 6, 1, null);
            DB::table('vinculacion')->where('id_vinculacion', $this->nuevoEvaluador)->update(['es_evaluador' => 1]);
        }

        return $this->post('/admin/traslados', $extra + [
            'id_vinc_funcionario' => $this->profesional1,
            'id_vinc_evaluador_nuevo' => $this->nuevoEvaluador,
            'fecha_traslado' => now()->toDateString(),
            'cargo_nuevo' => 'TECNICO ADMINISTRATIVO',
        ]);
    }

    public function test_el_traslado_con_cambio_de_cargo_exige_plaza_destino_vacante(): void
    {
        $this->postJson('/admin/plazas/generar')->assertOk();

        $this->trasladar([])->assertSessionHasErrors('traslados');
        $this->assertSame(0, DB::table('traslado')->where('id_vinc_funcionario', $this->profesional1)->count());

        $ocupada = $this->plazaDe($this->tecnico)->id_puesto;
        $this->trasladar(['id_puesto_destino' => $ocupada])->assertSessionHasErrors('traslados');
    }

    public function test_al_confirmar_el_traslado_se_deja_la_plaza_de_origen_y_se_ocupa_la_destino(): void
    {
        $this->postJson('/admin/plazas/generar')->assertOk();
        $origen = (int) $this->plazaDe($this->profesional1)->id_puesto;
        $this->post("/admin/vinculaciones/{$this->tecnico}/vacancia", ['es_vacante' => 1])->assertRedirect();
        $destino = (int) DB::table('puesto_ocupacion')->where('id_vinculacion', $this->tecnico)->value('id_puesto');

        $this->trasladar(['id_puesto_destino' => $destino])->assertSessionHasNoErrors();

        $traslado = DB::table('traslado')->where('id_vinc_funcionario', $this->profesional1)->first();
        $this->assertSame(['CONFIRMADO', $origen, $destino], [$traslado->estado, (int) $traslado->id_puesto_origen, (int) $traslado->id_puesto_destino]);
        $this->assertSame($destino, (int) $this->ocupacionVigente($this->profesional1)->id_puesto);
        $cerrada = DB::table('puesto_ocupacion')->where('id_vinculacion', $this->profesional1)->where('id_puesto', $origen)->first();
        $this->assertSame(['CERRADA', now()->subDay()->toDateString()], [$cerrada->estado, $cerrada->vigente_hasta]);
    }

    public function test_vacantes_para_el_traslado_informa_la_plaza_actual(): void
    {
        $this->postJson('/admin/plazas/generar')->assertOk();
        $this->post("/admin/vinculaciones/{$this->tecnico}/vacancia", ['es_vacante' => 1])->assertRedirect();

        $this->getJson("/admin/plazas/vacantes?id_vinc={$this->profesional1}&cargo=TECNICO%20ADMINISTRATIVO")
            ->assertOk()
            ->assertJsonPath('plaza_actual', $this->plazaDe($this->profesional1)->codigo_plaza)
            ->assertJsonPath('vacantes.0.cargo', 'TECNICO ADMINISTRATIVO');
    }

    // --- Regla 7: receptor de la apelación por jerarquía de plazas ---

    private function activarModelo(): void
    {
        DB::table('configuracion_sistema')->updateOrInsert(['clave' => 'modelo_puestos_modo'], ['valor' => 'ACTIVO', 'updated_at' => now()]);
    }

    /** Evaluación del técnico a cargo del profesional1, cuya plaza superior es la del jefe. */
    private function evaluacionDelTecnico(): int
    {
        return DB::table('evaluacion')->insertGetId([
            'id_periodo' => DB::table('periodo')->insertGetId(['id_usuario_apertura' => $this->idUsuario, 'sistema' => 'RENDIMIENTO_LABORAL', 'anio' => 2099, 'semestre' => 1, 'fecha_inicio' => '2099-01-01', 'fecha_fin' => '2099-06-30', 'estado' => 'ABIERTO']),
            'id_vinc_evaluado' => $this->tecnico, 'id_vinc_evaluador' => $this->profesional1, 'tipo_evaluacion' => 'SEMESTRE_1',
            'fase_actual' => 5, 'concertacion_firmada' => 1, 'estado' => 'CALIFICADA',
        ]);
    }

    public function test_con_modelo_activo_la_apelacion_va_a_quien_responde_por_la_plaza_superior(): void
    {
        $this->postJson('/admin/plazas/generar')->assertOk();
        $this->activarModelo();
        $idEvaluacion = $this->evaluacionDelTecnico();

        $this->assertSame($this->jefe, resolverVinculacionReceptorApelacion($idEvaluacion), 'El ocupante de la plaza superior.');

        // Con delegación vigente sobre la plaza superior, la recibe el delegado.
        $delegado = $this->vinculacion('PROFESIONAL UNIVERSITARIO', 219, 10, null);
        DB::table('delegacion')->insert(['id_vinc_delegante' => $this->jefe, 'id_vinc_delegado' => $delegado, 'fecha_inicio' => now()->subDay()->toDateString(), 'estado' => 'ACTIVA', 'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame($delegado, resolverVinculacionReceptorApelacion($idEvaluacion));

        // Plaza superior vacante (y sin delegación): Talento Humano.
        DB::table('delegacion')->delete();
        app(\App\Services\OcupacionPuestos::class)->cerrar($this->jefe, now()->toDateString(), 'Prueba');
        $this->assertNull(resolverVinculacionReceptorApelacion($idEvaluacion));
    }

    public function test_con_modelo_apagado_la_apelacion_sigue_el_jefe_registrado(): void
    {
        $this->postJson('/admin/plazas/generar')->assertOk();
        $idEvaluacion = $this->evaluacionDelTecnico();
        // El profesional1 tiene registrado al jefe, aunque la plaza diga otra cosa.
        DB::table('puesto')->where('id_puesto', $this->plazaDe($this->profesional1)->id_puesto)->update(['id_puesto_superior' => null]);

        $this->assertSame($this->jefe, resolverVinculacionReceptorApelacion($idEvaluacion));
    }

    public function test_solo_talento_humano_administra_el_catalogo(): void
    {
        session(['usuario_autenticado' => ['id_usuario' => $this->idUsuario, 'rol_activo' => 'evaluador', 'roles' => ['evaluador']]]);

        $this->getJson('/admin/plazas')->assertForbidden();
        $this->postJson('/admin/plazas/generar')->assertForbidden();
        $this->cambiarModo('OBSERVACION')->assertForbidden();
    }
}
