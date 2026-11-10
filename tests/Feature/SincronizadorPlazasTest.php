<?php

namespace Tests\Feature;

use App\Services\SincronizadorPlazas;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * S14 — Fase E: lo que el flujo actual reasigna se vuelve a enlazar a la plaza
 * de la nueva persona; con el modelo activo, el cambio de ocupante de una plaza
 * pasa su trabajo abierto al ocupante vigente (relevo), salvo plaza vacante,
 * plaza con delegación vigente o evaluación ya en calificación. Las que están
 * en calificación las decide TH caso por caso (relevos por decidir). El fin de
 * una delegación toma como sucesor al ocupante vigente de la plaza.
 *
 * La prueba trabaja sobre la base configurada en el entorno y se ejecuta dentro
 * de una transacción que se revierte al terminar, de modo que no deja datos.
 */
class SincronizadorPlazasTest extends TestCase
{
    private int $idUsuario;
    private string $s;
    private int $idDependencia;
    private int $idCargo;
    private int $idPeriodo;
    private array $v = [];
    private array $plaza = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requiere una base MySQL con el esquema institucional aplicado.');
        }

        DB::beginTransaction();

        $this->s = 'S' . random_int(10000, 99999);
        $this->idUsuario = DB::table('usuario')->insertGetId(['username' => "tmp.sinc.{$this->s}", 'password' => 'x', 'rol' => 'ADMINISTRADOR', 'activo' => 1]);
        $this->idDependencia = DB::table('dependencia')->insertGetId(['nombre' => "OFICINA {$this->s}", 'activa' => 1]);
        DB::table('cargo')->insertOrIgnore(['nombre' => 'JEFE DE OFICINA', 'codigo_cargo' => 6, 'activo' => 1]);
        $this->idCargo = (int) DB::table('cargo')->where('nombre', 'JEFE DE OFICINA')->value('id_cargo');
        $this->idPeriodo = DB::table('periodo')->insertGetId([
            'id_usuario_apertura' => $this->idUsuario, 'sistema' => 'RENDIMIENTO_LABORAL', 'anio' => 2099, 'semestre' => 1,
            'fecha_inicio' => '2099-01-01', 'fecha_fin' => '2099-06-30', 'estado' => 'ABIERTO',
        ]);

        // Jefe saliente (plaza P1), jefe entrante (plaza P2 por ahora), otro jefe (P3), delegado y dos evaluados.
        foreach (['jefe', 'nuevo', 'otro', 'delegado', 'e1', 'e2'] as $clave) {
            $this->v[$clave] = $this->vinculacion($clave);
        }
        $this->plaza['P1'] = $this->ocupar($this->v['jefe'], 'P1');
        $this->plaza['P2'] = $this->ocupar($this->v['nuevo'], 'P2');
        $this->plaza['P3'] = $this->ocupar($this->v['otro'], 'P3');
        $this->ocupar($this->v['delegado'], 'P4');

        foreach (['e1', 'e2'] as $evaluado) {
            DB::table('evaluador_asignacion')->insert(['id_vinc_evaluador' => $this->v['jefe'], 'id_vinc_evaluado' => $this->v[$evaluado], 'fecha_asignacion' => now()]);
        }
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    private function vinculacion(string $clave): int
    {
        $idUsuario = DB::table('usuario')->insertGetId(['username' => "tmp.sinc.{$clave}.{$this->s}", 'password' => 'x', 'rol' => 'EVALUADO', 'activo' => 1]);
        $idFuncionario = DB::table('funcionario')->insertGetId([
            'id_usuario' => $idUsuario, 'tipo_documento' => 'CEDULA_CIUDADANIA', 'numero_doc' => (string) random_int(10 ** 9, 2 * 10 ** 9),
            'nombres' => strtoupper($clave), 'apellidos' => $this->s,
        ]);

        return DB::table('vinculacion')->insertGetId([
            'id_funcionario' => $idFuncionario, 'cargo' => 'JEFE DE OFICINA', 'codigo_cargo' => 6, 'grado_cargo' => 1,
            'nivel_jerarquico' => 'DIRECTIVO', 'area' => "OFICINA {$this->s}", 'tipo_vinculacion' => 'PROVISIONALIDAD',
            'sistema_evaluacion' => 'RENDIMIENTO_LABORAL', 'es_evaluador' => 1, 'fecha_ingreso' => '2098-01-01', 'activa' => 1,
        ]);
    }

    private function ocupar(int $idVinc, string $codigo, ?int $idPuesto = null): int
    {
        $idPuesto ??= DB::table('puesto')->insertGetId([
            'codigo_plaza' => "T-{$this->s}-{$codigo}", 'id_dependencia' => $this->idDependencia, 'id_cargo' => $this->idCargo,
            'codigo_cargo' => 6, 'grado' => 1, 'nivel_jerarquico' => 'DIRECTIVO', 'sistema_evaluacion' => 'RENDIMIENTO_LABORAL',
            'estado' => 'VALIDADA', 'requiere_revision' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('puesto_ocupacion')->insert([
            'id_puesto' => $idPuesto, 'id_vinculacion' => $idVinc, 'tipo_ocupacion' => 'TITULAR', 'vigente_desde' => '2098-01-01',
            'estado' => 'VIGENTE', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $idPuesto;
    }

    /** El jefe deja la plaza P1 y el entrante deja la suya para ocuparla. */
    private function relevarP1(): void
    {
        DB::table('puesto_ocupacion')->whereIn('id_vinculacion', [$this->v['jefe'], $this->v['nuevo']])
            ->update(['estado' => 'CERRADA', 'vigente_hasta' => '2099-03-31']);
        $this->ocupar($this->v['nuevo'], 'P1', $this->plaza['P1']);
    }

    private function modo(string $modo): void
    {
        DB::table('configuracion_sistema')->updateOrInsert(['clave' => 'modelo_puestos_modo'], ['valor' => $modo]);
    }

    private function evaluacion(string $evaluado, int $fase = 2): int
    {
        return DB::table('evaluacion')->insertGetId([
            'id_periodo' => $this->idPeriodo, 'id_vinc_evaluado' => $this->v[$evaluado], 'id_vinc_evaluador' => $this->v['jefe'],
            'tipo_evaluacion' => 'SEMESTRE_1', 'fase_actual' => $fase, 'concertacion_firmada' => 1, 'estado' => 'EN_PROCESO',
        ]);
    }

    private function sincronizar(): array
    {
        return app(SincronizadorPlazas::class)->sincronizar();
    }

    private function asignacion(string $evaluado): object
    {
        return DB::table('evaluador_asignacion')->where('id_vinc_evaluado', $this->v[$evaluado])->first();
    }

    public function test_si_el_flujo_actual_reasigna_se_enlaza_a_la_plaza_de_la_nueva_persona(): void
    {
        $this->modo('ACTIVO');
        $this->sincronizar();
        $this->assertSame($this->plaza['P1'], (int) $this->asignacion('e1')->id_puesto_evaluador);

        // Un traslado (flujo actual) le pone otro evaluador: manda la persona.
        DB::table('evaluador_asignacion')->where('id_vinc_evaluado', $this->v['e1'])->update(['id_vinc_evaluador' => $this->v['otro']]);
        $this->sincronizar();

        $a = $this->asignacion('e1');
        $this->assertSame($this->v['otro'], (int) $a->id_vinc_evaluador);
        $this->assertSame($this->plaza['P3'], (int) $a->id_puesto_evaluador);
    }

    public function test_con_el_modelo_activo_el_relevo_pasa_el_trabajo_abierto_al_nuevo_ocupante(): void
    {
        $this->modo('ACTIVO');
        $abierta = $this->evaluacion('e1');
        $enCalificacion = $this->evaluacion('e2', 4);
        $this->sincronizar();

        $this->relevarP1();
        $r = $this->sincronizar();

        $this->assertSame($this->v['nuevo'], (int) $this->asignacion('e1')->id_vinc_evaluador);
        $this->assertSame($this->v['nuevo'], (int) $this->asignacion('e2')->id_vinc_evaluador);
        $this->assertSame($this->v['nuevo'], (int) DB::table('evaluacion')->where('id_evaluacion', $abierta)->value('id_vinc_evaluador'));
        $this->assertSame($this->v['jefe'], (int) DB::table('evaluacion')->where('id_evaluacion', $enCalificacion)->value('id_vinc_evaluador'));
        $this->assertGreaterThanOrEqual(1, $r['relevo']['en_calificacion']);

        $this->assertTrue(DB::table('evaluacion_evento')->where('id_evaluacion', $abierta)->where('tipo_evento', 'CAMBIO_EVALUADOR')->exists());
        $avisos = DB::table('notificacion as n')->join('funcionario as f', 'f.id_usuario', '=', 'n.id_usuario_destino')
            ->join('vinculacion as v', 'v.id_funcionario', '=', 'f.id_funcionario')
            ->whereIn('v.id_vinculacion', [$this->v['e1'], $this->v['nuevo']])->pluck('n.tipo')->all();
        $this->assertEqualsCanonicalizing(['CAMBIO_EVALUADOR', 'NUEVO_EVALUADO'], $avisos);
    }

    public function test_sin_el_modelo_activo_no_hay_relevo(): void
    {
        $this->modo('OBSERVACION');
        $abierta = $this->evaluacion('e1');
        $this->sincronizar();

        $this->relevarP1();
        $r = $this->sincronizar();

        $this->assertNull($r['relevo']);
        $this->assertSame($this->v['jefe'], (int) $this->asignacion('e1')->id_vinc_evaluador);
        $this->assertSame($this->v['jefe'], (int) DB::table('evaluacion')->where('id_evaluacion', $abierta)->value('id_vinc_evaluador'));
    }

    public function test_plaza_vacante_o_con_delegacion_vigente_no_se_releva(): void
    {
        $this->modo('ACTIVO');
        $this->sincronizar();

        // Vacante: el jefe deja la plaza y nadie la ocupa.
        DB::table('puesto_ocupacion')->where('id_vinculacion', $this->v['jefe'])->update(['estado' => 'CERRADA', 'vigente_hasta' => '2099-03-31']);
        $this->sincronizar();
        $this->assertSame($this->v['jefe'], (int) $this->asignacion('e1')->id_vinc_evaluador);

        // Con delegación vigente sobre la plaza, la delegación decide quién atiende.
        DB::table('puesto_ocupacion')->where('id_vinculacion', $this->v['nuevo'])->update(['estado' => 'CERRADA', 'vigente_hasta' => '2099-03-31']);
        $this->ocupar($this->v['nuevo'], 'P1', $this->plaza['P1']);
        DB::table('delegacion')->insert([
            'id_vinc_delegante' => $this->v['jefe'], 'id_vinc_delegado' => $this->v['delegado'], 'motivo' => 'VACACIONES', 'id_puesto' => $this->plaza['P1'],
            'fecha_inicio' => '2099-02-01', 'fecha_fin' => '2099-02-20', 'estado' => 'ACTIVA', 'id_usuario_registra' => $this->idUsuario,
        ]);
        $this->sincronizar();
        $this->assertSame($this->v['jefe'], (int) $this->asignacion('e1')->id_vinc_evaluador);
    }

    private function comoAdmin(): void
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
        session(['usuario_autenticado' => ['id_usuario' => $this->idUsuario, 'rol_activo' => 'admin', 'roles' => ['admin']]]);
    }

    public function test_la_delegacion_se_registra_por_cargo_y_queda_ligada_a_la_plaza(): void
    {
        $this->comoAdmin();

        $this->post('/admin/delegaciones', [
            'id_puesto_delegante' => $this->plaza['P1'],
            'id_vinc_delegado' => $this->v['delegado'],
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->addDays(9)->toDateString(),
            'motivo' => 'Vacaciones',
        ])->assertSessionHasNoErrors();

        $delegacion = DB::table('delegacion')->where('id_puesto', $this->plaza['P1'])->first();
        $this->assertNotNull($delegacion);
        $this->assertSame($this->v['jefe'], (int) $delegacion->id_vinc_delegante, 'Delega el ocupante vigente del cargo.');
        $this->assertSame($this->v['delegado'], (int) $delegacion->id_vinc_delegado);
        $this->assertSame('ACTIVA', $delegacion->estado);
    }

    public function test_delegar_por_persona_tambien_guarda_la_plaza_del_titular(): void
    {
        $this->comoAdmin();

        $this->post('/admin/delegaciones', [
            'id_vinc_delegante' => $this->v['jefe'],
            'id_vinc_delegado' => $this->v['delegado'],
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->addDays(9)->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertSame($this->plaza['P1'], (int) DB::table('delegacion')->where('id_vinc_delegante', $this->v['jefe'])->value('id_puesto'));
    }

    public function test_el_formulario_de_delegaciones_lista_los_cargos_con_su_ocupante(): void
    {
        $this->comoAdmin();

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('Cargo que delega (titular)')
            ->assertSee("T-{$this->s}-P1", false)
            ->assertSee('Ocupa: JEFE ' . $this->s, false);
    }

    /** El jefe se retira: su vinculación queda inactiva y la plaza P1 vacante, con sus evaluados aún asignados. */
    private function retirarJefe(): void
    {
        DB::table('puesto_ocupacion')->where('id_puesto', $this->plaza['P1'])->update(['estado' => 'CERRADA', 'vigente_hasta' => now()->toDateString()]);
        DB::table('vinculacion')->where('id_vinculacion', $this->v['jefe'])->update(['activa' => 0]);
    }

    private function delegarVacante(array $extra = [])
    {
        return $this->post('/admin/delegaciones', $extra + [
            'id_puesto_delegante' => $this->plaza['P1'],
            'id_vinc_delegado' => $this->v['delegado'],
            'fecha_inicio' => now()->toDateString(),
            'motivo' => 'Encargo de la jefatura',
        ]);
    }

    public function test_un_cargo_vacante_sin_ocupante_previo_no_se_delega(): void
    {
        $this->comoAdmin();
        $vacante = DB::table('puesto')->insertGetId([
            'codigo_plaza' => "T-{$this->s}-NUNCA", 'id_dependencia' => $this->idDependencia, 'id_cargo' => $this->idCargo,
            'codigo_cargo' => 6, 'grado' => 1, 'nivel_jerarquico' => 'DIRECTIVO', 'sistema_evaluacion' => 'RENDIMIENTO_LABORAL',
            'estado' => 'VALIDADA', 'requiere_revision' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->post('/admin/delegaciones', ['id_puesto_delegante' => $vacante, 'id_vinc_delegado' => $this->v['delegado'], 'fecha_inicio' => now()->toDateString()])
            ->assertSessionHasErrors(['delegaciones' => 'El cargo está vacante y nunca tuvo un ocupante: no tiene personas a cargo ni trabajo que delegar.']);
    }

    public function test_el_cargo_vacante_se_delega_sin_tramos_aunque_sea_indefinida(): void
    {
        $this->comoAdmin();
        $e1 = $this->evaluacion('e1');
        $this->retirarJefe();

        // Sin fecha fin (vigencia abierta): en un cargo con titular exigiría tramos.
        $this->delegarVacante()->assertSessionHasNoErrors();

        $delegacion = DB::table('delegacion')->where('id_puesto', $this->plaza['P1'])->first();
        $this->assertSame('ACTIVA', $delegacion->estado);
        $this->assertSame(1, (int) $delegacion->sin_titular);
        $this->assertSame(0, (int) $delegacion->es_larga);
        $this->assertSame($this->v['jefe'], (int) $delegacion->id_vinc_delegante, 'El último ocupante es el titular formal.');
        $this->assertSame(0, DB::table('traslado')->where('id_delegacion', $delegacion->id_delegacion)->count(), 'No hay tramos ni pausa del titular.');

        $evaluacion = DB::table('evaluacion')->where('id_evaluacion', $e1)->first();
        $this->assertSame($this->v['delegado'], (int) $evaluacion->id_vinc_evaluador);
        $this->assertSame($this->v['jefe'], (int) $evaluacion->id_vinc_suplente);
        $this->assertEqualsCanonicalizing(
            [$this->v['e1'], $this->v['e2']],
            DB::table('evaluador_asignacion')->where('id_vinc_evaluador', $this->v['delegado'])->pluck('id_vinc_evaluado')->map(fn ($v) => (int) $v)->all()
        );
    }

    public function test_si_el_delegado_dependia_del_cargo_vacante_debe_cambiar_de_evaluador(): void
    {
        $this->comoAdmin();
        DB::table('evaluador_asignacion')->insert(['id_vinc_evaluador' => $this->v['jefe'], 'id_vinc_evaluado' => $this->v['delegado'], 'fecha_asignacion' => now()]);
        $this->retirarJefe();

        $this->delegarVacante()->assertSessionHasErrors(['delegaciones' => 'El delegado era evaluado por el cargo vacante y quedó sin evaluador: indica que el delegado cambia de evaluador y elige su superior.']);
        $this->assertSame(0, DB::table('delegacion')->where('id_puesto', $this->plaza['P1'])->count());

        // Con el cambio indicado: pasa directo a Rectoría (aquí, la plaza "otro"), sin tramo, y no queda como su propio evaluado.
        $evalDelegado = DB::table('evaluacion')->insertGetId([
            'id_periodo' => $this->idPeriodo, 'id_vinc_evaluado' => $this->v['delegado'], 'id_vinc_evaluador' => $this->v['jefe'],
            'tipo_evaluacion' => 'SEMESTRE_1', 'fase_actual' => 2, 'concertacion_firmada' => 1, 'estado' => 'EN_PROCESO',
        ]);
        $this->delegarVacante(['delegado_cambia_evaluador' => 1, 'id_vinc_superior_delegado' => $this->v['otro']])->assertSessionHasNoErrors();

        $this->assertSame($this->v['otro'], (int) DB::table('evaluador_asignacion')->where('id_vinc_evaluado', $this->v['delegado'])->value('id_vinc_evaluador'));
        $this->assertSame($this->v['otro'], (int) DB::table('evaluacion')->where('id_evaluacion', $evalDelegado)->value('id_vinc_evaluador'));
        $this->assertSame(0, DB::table('evaluador_asignacion')->where('id_vinc_evaluador', $this->v['delegado'])->where('id_vinc_evaluado', $this->v['delegado'])->count());
        $this->assertSame(0, DB::table('traslado')->count(), 'Sin tramo para el delegado.');
        $this->assertSame($this->v['delegado'], (int) DB::table('evaluador_asignacion')->where('id_vinc_evaluado', $this->v['e1'])->where('id_vinc_evaluador', $this->v['delegado'])->value('id_vinc_evaluador'));
    }

    public function test_cambiar_de_evaluador_en_un_cargo_vacante_exige_el_superior(): void
    {
        $this->comoAdmin();
        $this->retirarJefe();

        $this->delegarVacante(['delegado_cambia_evaluador' => 1])->assertSessionHasErrors(['delegaciones' => 'Indicaste que el delegado cambia de evaluador: selecciona el superior que lo evaluará mientras ejerce el cargo.']);
    }

    public function test_al_terminar_la_delegacion_del_cargo_vacante_el_trabajo_pasa_al_sucesor_y_el_delegado_vuelve_a_su_evaluador(): void
    {
        $this->comoAdmin();
        $e1 = $this->evaluacion('e1');
        $this->retirarJefe();
        $this->delegarVacante(['delegado_cambia_evaluador' => 1, 'id_vinc_superior_delegado' => $this->v['otro']])->assertSessionHasNoErrors();
        $delegacion = DB::table('delegacion')->where('id_puesto', $this->plaza['P1'])->first();

        // La plaza sigue vacante y el titular no está: TH debe elegir quién asume.
        $this->postJson("/admin/delegaciones/{$delegacion->id_delegacion}/finalizar")->assertStatus(422);

        $this->postJson("/admin/delegaciones/{$delegacion->id_delegacion}/finalizar", [
            'id_vinc_sucesor' => $this->v['nuevo'],
            'id_vinc_evaluador_regreso' => $this->v['nuevo'],
        ])->assertOk();

        $this->assertSame('FINALIZADA', DB::table('delegacion')->where('id_delegacion', $delegacion->id_delegacion)->value('estado'));
        $this->assertSame($this->v['nuevo'], (int) DB::table('evaluacion')->where('id_evaluacion', $e1)->value('id_vinc_evaluador'));
        $this->assertSame($this->v['nuevo'], (int) DB::table('evaluador_asignacion')->where('id_vinc_evaluado', $this->v['e1'])->value('id_vinc_evaluador'));
        $this->assertSame($this->v['nuevo'], (int) DB::table('evaluador_asignacion')->where('id_vinc_evaluado', $this->v['delegado'])->value('id_vinc_evaluador'));
    }

    public function test_el_formulario_lista_todos_los_cargos_ocupados_con_personas_a_cargo(): void
    {
        $this->comoAdmin();
        // Un segundo y un tercer cargo ocupados con personas a cargo: todos deben salir con su ocupante.
        foreach (['nuevo', 'otro'] as $clave) {
            DB::table('evaluador_asignacion')->insert(['id_vinc_evaluador' => $this->v[$clave], 'id_vinc_evaluado' => $this->v['e1'], 'fecha_asignacion' => now()]);
        }

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee("T-{$this->s}-P1", false)
            ->assertSee("T-{$this->s}-P2", false)
            ->assertSee("T-{$this->s}-P3", false)
            ->assertSee('Ocupa: JEFE ' . $this->s, false)
            ->assertSee('Ocupa: NUEVO ' . $this->s, false)
            ->assertSee('Ocupa: OTRO ' . $this->s, false)
            ->assertSee('name="id_puesto_delegante" data-buscable', false);
    }

    public function test_el_formulario_mezcla_cargos_ocupados_y_vacantes(): void
    {
        $this->comoAdmin();
        DB::table('evaluador_asignacion')->insert(['id_vinc_evaluador' => $this->v['otro'], 'id_vinc_evaluado' => $this->v['e1'], 'fecha_asignacion' => now()]);
        $this->retirarJefe();

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('VACANTE, último ocupante: JEFE ' . $this->s, false)
            ->assertSee('Ocupa: OTRO ' . $this->s, false);
    }

    public function test_el_formulario_lista_el_cargo_vacante_con_su_ultimo_ocupante(): void
    {
        $this->comoAdmin();
        $this->retirarJefe();

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee("T-{$this->s}-P1", false)
            ->assertSee('VACANTE, último ocupante: JEFE ' . $this->s, false);
    }

    public function test_al_terminar_la_delegacion_el_sucesor_es_el_ocupante_vigente_de_la_plaza(): void
    {
        $this->modo('ACTIVO');
        $delegacion = (object) ['id_vinc_delegante' => $this->v['jefe'], 'id_vinc_delegado' => $this->v['delegado'], 'id_puesto' => $this->plaza['P1']];

        $this->assertSame($this->v['jefe'], resolverSucesorDelegacion($delegacion, null));

        $this->relevarP1();
        $this->assertSame($this->v['nuevo'], resolverSucesorDelegacion($delegacion, null));

        DB::table('puesto_ocupacion')->where('id_puesto', $this->plaza['P1'])->update(['estado' => 'CERRADA']);
        $this->assertStringContainsString('vacante', resolverSucesorDelegacion($delegacion, null));
    }

    public function test_no_se_permiten_dos_delegaciones_de_la_misma_plaza_aunque_cambie_el_ocupante(): void
    {
        DB::table('delegacion')->insert([
            'id_vinc_delegante' => $this->v['jefe'], 'id_vinc_delegado' => $this->v['delegado'], 'motivo' => 'VACACIONES', 'id_puesto' => $this->plaza['P1'],
            'fecha_inicio' => '2099-02-01', 'fecha_fin' => '2099-02-20', 'estado' => 'ACTIVA', 'id_usuario_registra' => $this->idUsuario,
        ]);
        $this->relevarP1();

        $this->assertTrue(delegacionActivaTraslapada($this->v['nuevo'], '2099-02-10', '2099-02-15'));
        $this->assertFalse(delegacionActivaTraslapada($this->v['nuevo'], '2099-03-01', '2099-03-15'));
    }

    public function test_th_pasa_al_nuevo_ocupante_una_evaluacion_en_calificacion(): void
    {
        $this->modo('ACTIVO');
        $enCalificacion = $this->evaluacion('e2', 4);
        $this->sincronizar();
        $this->relevarP1();
        $this->sincronizar();

        $relevos = app(SincronizadorPlazas::class)->relevosPorDecidir();
        $this->assertSame([$enCalificacion], array_column($relevos, 'id_evaluacion'));
        $this->assertTrue($relevos[0]['conservable']);

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
        session(['usuario_autenticado' => ['id_usuario' => $this->idUsuario, 'rol_activo' => 'admin', 'roles' => ['admin']]]);
        $this->postJson("/admin/plazas/relevos/{$enCalificacion}", ['decision' => 'NUEVO_OCUPANTE'])->assertOk();

        $this->assertSame($this->v['nuevo'], (int) DB::table('evaluacion')->where('id_evaluacion', $enCalificacion)->value('id_vinc_evaluador'));
        $this->assertTrue(DB::table('evaluacion_evento')->where('id_evaluacion', $enCalificacion)->where('tipo_evento', 'CAMBIO_EVALUADOR')->exists());
        $this->assertSame([], app(SincronizadorPlazas::class)->relevosPorDecidir());
    }

    public function test_th_deja_que_termine_quien_empezo_la_calificacion(): void
    {
        $this->modo('ACTIVO');
        $enCalificacion = $this->evaluacion('e2', 4);
        $this->sincronizar();
        $this->relevarP1();

        $this->assertNull(app(SincronizadorPlazas::class)->decidirRelevo($enCalificacion, 'CONSERVAR'));
        $this->sincronizar();

        $this->assertSame($this->v['jefe'], (int) DB::table('evaluacion')->where('id_evaluacion', $enCalificacion)->value('id_vinc_evaluador'));
        $this->assertTrue(DB::table('evaluacion_evento')->where('id_evaluacion', $enCalificacion)->where('tipo_evento', 'RELEVO_CONSERVADO')->exists());
        $this->assertSame([], app(SincronizadorPlazas::class)->relevosPorDecidir(), 'La decisión no se vuelve a pedir.');
    }

    public function test_no_se_deja_la_calificacion_a_quien_ya_no_esta_activo(): void
    {
        $this->modo('ACTIVO');
        $enCalificacion = $this->evaluacion('e2', 4);
        $this->sincronizar();
        $this->relevarP1();
        DB::table('vinculacion')->where('id_vinculacion', $this->v['jefe'])->update(['activa' => 0]);

        $this->assertFalse(app(SincronizadorPlazas::class)->relevosPorDecidir()[0]['conservable']);
        $this->assertNotNull(app(SincronizadorPlazas::class)->decidirRelevo($enCalificacion, 'CONSERVAR'));
        $this->assertSame($this->v['jefe'], (int) DB::table('evaluacion')->where('id_evaluacion', $enCalificacion)->value('id_vinc_evaluador'));
    }

    public function test_los_relevos_solo_se_deciden_con_el_modelo_activo(): void
    {
        $this->modo('OBSERVACION');
        $enCalificacion = $this->evaluacion('e2', 4);
        $this->sincronizar();
        $this->relevarP1();

        $this->assertNotNull(app(SincronizadorPlazas::class)->decidirRelevo($enCalificacion, 'NUEVO_OCUPANTE'));
        $this->assertSame($this->v['jefe'], (int) DB::table('evaluacion')->where('id_evaluacion', $enCalificacion)->value('id_vinc_evaluador'));
    }
}
