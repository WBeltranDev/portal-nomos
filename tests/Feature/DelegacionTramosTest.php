<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * S14 — Delegación por tramos.
 *
 * Una delegación de más de 30 días cambia de evaluador como un traslado: el
 * titular califica el tramo de todas sus personas a cargo y el evaluador del
 * delegado califica el suyo; con todas las notas se confirma. Al terminar, el
 * trabajo pasa al sucesor que elija Talento Humano (por defecto el titular) y
 * el delegado vuelve con su evaluador original. Una delegación de 30 días o
 * menos no genera tramos: el delegado atiende las mismas evaluaciones.
 *
 * La prueba trabaja sobre la base configurada en el entorno y se ejecuta dentro
 * de una transacción que se revierte al terminar, de modo que no deja datos.
 */
class DelegacionTramosTest extends TestCase
{
    private int $idUsuario;
    private int $idPeriodo;
    private int $titular;
    private int $delegado;
    private int $superior;
    private int $otroEvaluador;
    private int $evaluado1;
    private int $evaluado2;
    private array $evaluaciones = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requiere una base MySQL con el esquema institucional aplicado.');
        }

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
        DB::beginTransaction();

        $sufijo = 'T' . random_int(10000, 99999);
        $this->idUsuario = $this->crearUsuario("tmp.deleg.{$sufijo}");

        $this->titular = $this->crearVinculacion("{$sufijo}A", true);
        $this->delegado = $this->crearVinculacion("{$sufijo}B", false);
        $this->superior = $this->crearVinculacion("{$sufijo}C", true);
        $this->otroEvaluador = $this->crearVinculacion("{$sufijo}D", true);
        $this->evaluado1 = $this->crearVinculacion("{$sufijo}E", false);
        $this->evaluado2 = $this->crearVinculacion("{$sufijo}F", false);

        $hoy = Carbon::today();
        $this->idPeriodo = DB::table('periodo')->insertGetId([
            'id_usuario_apertura' => $this->idUsuario,
            'sistema' => 'RENDIMIENTO_LABORAL',
            'anio' => 2099,
            'semestre' => 2,
            'fecha_inicio' => $hoy->copy()->subDays(150)->toDateString(),
            'fecha_fin' => $hoy->copy()->addDays(90)->toDateString(),
            'estado' => 'ABIERTO',
        ]);

        // El delegado también es una persona a cargo del titular.
        foreach ([$this->evaluado1, $this->evaluado2, $this->delegado] as $vinc) {
            DB::table('evaluador_asignacion')->insert([
                'id_vinc_evaluador' => $this->titular,
                'id_vinc_evaluado' => $vinc,
                'fecha_asignacion' => now(),
            ]);
            $this->evaluaciones[$vinc] = $this->crearEvaluacion($vinc, $this->titular, 'SEMESTRE_2');
        }

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

    private function crearVinculacion(string $doc, bool $esEvaluador): int
    {
        $idFuncionario = DB::table('funcionario')->insertGetId([
            'id_usuario' => $this->crearUsuario('tmp.deleg.' . strtolower($doc)),
            'tipo_documento' => 'CEDULA_CIUDADANIA',
            'numero_doc' => $doc,
            'nombres' => 'Tmp',
            'apellidos' => $doc,
        ]);

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

    private function crearEvaluacion(int $evaluado, int $evaluador, string $tipo): int
    {
        return DB::table('evaluacion')->insertGetId([
            'id_periodo' => $this->idPeriodo,
            'id_vinc_evaluado' => $evaluado,
            'id_vinc_evaluador' => $evaluador,
            'tipo_evaluacion' => $tipo,
            'fase_actual' => 4,
            'concertacion_firmada' => 1,
            'estado' => 'EN_PROCESO',
        ]);
    }

    private function calificar(int $idEvaluacion): void
    {
        DB::table('compromiso')->insert([
            'id_evaluacion' => $idEvaluacion,
            'numero_orden' => 1,
            'descripcion' => 'Compromiso de prueba',
            'porcentaje_peso' => 100,
            'calificacion_definitiva' => 4.0,
        ]);
        DB::table('evaluacion')->where('id_evaluacion', $idEvaluacion)->update(['estado' => 'CALIFICADA', 'fase_actual' => 5]);
    }

    private function registrar(int $diasVigencia, ?int $superior = null)
    {
        return $this->post('/admin/delegaciones', array_filter([
            'id_vinc_delegante' => $this->titular,
            'id_vinc_delegado' => $this->delegado,
            'fecha_inicio' => Carbon::today()->toDateString(),
            'fecha_fin' => Carbon::today()->addDays($diasVigencia - 1)->toDateString(),
            'motivo' => 'Licencia',
            'id_vinc_superior_delegado' => $superior,
        ]));
    }

    private function delegacion(): ?object
    {
        return DB::table('delegacion')->where('id_vinc_delegante', $this->titular)->orderByDesc('id_delegacion')->first();
    }

    private function evaluadorDe(int $vinc): int
    {
        return (int) DB::table('evaluador_asignacion')->where('id_vinc_evaluado', $vinc)->value('id_vinc_evaluador');
    }

    private function parcialCon(int $evaluado, int $evaluador): ?object
    {
        return DB::table('evaluacion')
            ->where('id_periodo', $this->idPeriodo)
            ->where('id_vinc_evaluado', $evaluado)
            ->where('id_vinc_evaluador', $evaluador)
            ->where('tipo_evaluacion', 'PARCIAL')
            ->first();
    }

    private function activarLarga(): object
    {
        $this->registrar(60, $this->superior)->assertSessionHasNoErrors();
        foreach ($this->evaluaciones as $idEvaluacion) {
            $this->calificar($idEvaluacion);
        }
        $this->postJson('/admin/delegaciones/' . $this->delegacion()->id_delegacion . '/confirmar')->assertOk();

        return $this->delegacion();
    }

    // --- Delegación de más de 30 días ---

    public function test_la_delegacion_larga_exige_el_superior_del_delegado(): void
    {
        $this->registrar(60)->assertSessionHasErrors('delegaciones');
        $this->assertNull($this->delegacion());
    }

    public function test_la_delegacion_larga_se_programa_con_un_cambio_por_persona_y_otro_para_el_delegado(): void
    {
        $this->registrar(60, $this->superior)->assertSessionHasNoErrors();

        $delegacion = $this->delegacion();
        $this->assertSame('PROGRAMADA', $delegacion->estado);
        $this->assertSame(1, (int) $delegacion->es_larga);
        $this->assertSame($this->titular, (int) $delegacion->id_vinc_evaluador_original_delegado);

        $cambios = DB::table('traslado')->where('id_delegacion', $delegacion->id_delegacion)->get()->keyBy('id_vinc_funcionario');
        $this->assertCount(3, $cambios);
        $this->assertSame($this->delegado, (int) $cambios[$this->evaluado1]->id_vinc_evaluador_nuevo);
        $this->assertSame($this->delegado, (int) $cambios[$this->evaluado2]->id_vinc_evaluador_nuevo);
        // El delegado no se evalúa a sí mismo: su cambio va al superior elegido.
        $this->assertSame($this->superior, (int) $cambios[$this->delegado]->id_vinc_evaluador_nuevo);
        $this->assertTrue($cambios->every(fn ($t) => $t->estado === 'PROGRAMADO' && (int) $t->dias_laborados === 150));

        // Nada cambia hasta confirmar.
        $this->assertSame($this->titular, $this->evaluadorDe($this->evaluado1));
    }

    public function test_el_inicio_solo_se_confirma_con_todos_los_tramos_calificados(): void
    {
        $this->registrar(60, $this->superior)->assertSessionHasNoErrors();
        $id = $this->delegacion()->id_delegacion;

        $this->calificar($this->evaluaciones[$this->evaluado1]);

        $respuesta = $this->postJson("/admin/delegaciones/{$id}/confirmar")->assertStatus(422);
        $this->assertStringContainsString('Faltan 2 tramo(s) por calificar', $respuesta->json('message'));
        $this->assertSame('PROGRAMADA', $this->delegacion()->estado);
        $this->assertNull($this->parcialCon($this->evaluado1, $this->delegado), 'Todo o nada: ningún cambio se confirma suelto.');

        $this->calificar($this->evaluaciones[$this->evaluado2]);
        $this->calificar($this->evaluaciones[$this->delegado]);

        $this->postJson("/admin/delegaciones/{$id}/confirmar")->assertOk();

        $this->assertSame('ACTIVA', $this->delegacion()->estado);
        $this->assertSame($this->delegado, $this->evaluadorDe($this->evaluado1));
        $this->assertSame($this->delegado, $this->evaluadorDe($this->evaluado2));
        $this->assertSame($this->superior, $this->evaluadorDe($this->delegado));
        $this->assertNotNull($this->parcialCon($this->evaluado1, $this->delegado));
        $this->assertNotNull($this->parcialCon($this->delegado, $this->superior));
        $this->assertSame(0, (int) $this->parcialCon($this->evaluado1, $this->delegado)->concertacion_firmada, 'Se concierta de nuevo.');
    }

    public function test_los_cambios_de_una_delegacion_no_se_confirman_sueltos_desde_traslados(): void
    {
        $this->registrar(60, $this->superior)->assertSessionHasNoErrors();
        $idTraslado = DB::table('traslado')->where('id_delegacion', $this->delegacion()->id_delegacion)->value('id_traslado');

        $this->postJson("/admin/traslados/{$idTraslado}/confirmar")->assertStatus(422);
        $this->postJson("/admin/traslados/{$idTraslado}/cancelar")->assertStatus(422);
    }

    public function test_cancelar_la_delegacion_programada_restaura_las_evaluaciones(): void
    {
        $this->registrar(60, $this->superior)->assertSessionHasNoErrors();
        $id = $this->delegacion()->id_delegacion;

        $this->postJson("/admin/delegaciones/{$id}/cancelar")->assertOk();

        $this->assertSame('CANCELADA', $this->delegacion()->estado);
        $this->assertSame(0, DB::table('traslado')->where('id_delegacion', $id)->where('estado', '!=', 'CANCELADO')->count());
        $this->assertNull(DB::table('evaluacion')->where('id_evaluacion', $this->evaluaciones[$this->evaluado1])->value('dias_laborados'));
    }

    public function test_al_terminar_el_trabajo_vuelve_al_titular_y_el_delegado_a_su_evaluador(): void
    {
        $delegacion = $this->activarLarga();

        $this->postJson("/admin/delegaciones/{$delegacion->id_delegacion}/finalizar")->assertOk();
        $this->assertSame('POR_FINALIZAR', $this->delegacion()->estado);
        $this->assertSame($this->titular, (int) $this->delegacion()->id_vinc_sucesor);

        // El delegado califica sus tramos y el superior califica al delegado.
        $this->calificar($this->parcialCon($this->evaluado1, $this->delegado)->id_evaluacion);
        $this->calificar($this->parcialCon($this->evaluado2, $this->delegado)->id_evaluacion);
        $this->calificar($this->parcialCon($this->delegado, $this->superior)->id_evaluacion);

        $this->postJson("/admin/delegaciones/{$delegacion->id_delegacion}/confirmar")->assertOk();

        $this->assertSame('FINALIZADA', $this->delegacion()->estado);
        $this->assertSame($this->titular, $this->evaluadorDe($this->evaluado1));
        $this->assertSame($this->titular, $this->evaluadorDe($this->evaluado2));
        $this->assertSame($this->titular, $this->evaluadorDe($this->delegado), 'El delegado vuelve con su evaluador original.');
        $this->assertNotNull($this->parcialCon($this->evaluado1, $this->titular));
        $this->assertNotNull($this->parcialCon($this->delegado, $this->titular));
    }

    public function test_si_el_titular_ya_no_esta_activo_th_debe_elegir_el_sucesor(): void
    {
        $delegacion = $this->activarLarga();
        DB::table('vinculacion')->where('id_vinculacion', $this->titular)->update(['activa' => 0]);

        $this->postJson("/admin/delegaciones/{$delegacion->id_delegacion}/finalizar")
            ->assertStatus(422)
            ->assertJsonPath('message', 'El titular ya no está activo: selecciona quién asume sus evaluaciones.');

        $this->postJson("/admin/delegaciones/{$delegacion->id_delegacion}/finalizar", [
            'id_vinc_sucesor' => $this->otroEvaluador,
            'id_vinc_evaluador_regreso' => $this->otroEvaluador,
        ])->assertOk();

        $cambio = DB::table('traslado')
            ->where('id_delegacion', $delegacion->id_delegacion)
            ->where('estado', 'PROGRAMADO')
            ->where('id_vinc_funcionario', $this->evaluado1)
            ->first();
        $this->assertSame($this->otroEvaluador, (int) $cambio->id_vinc_evaluador_nuevo);
    }

    // --- Delegación de 30 días o menos ---

    public function test_la_delegacion_corta_no_genera_tramos(): void
    {
        DB::table('evaluacion')->where('id_evaluacion', $this->evaluaciones[$this->evaluado2])->update(['estado' => 'APELACION']);

        $this->registrar(15)->assertSessionHasNoErrors();

        $delegacion = $this->delegacion();
        $this->assertSame('ACTIVA', $delegacion->estado);
        $this->assertSame(0, (int) $delegacion->es_larga);
        $this->assertSame(0, DB::table('traslado')->where('id_delegacion', $delegacion->id_delegacion)->count());
        $this->assertSame(0, DB::table('periodo_parcial')->where('id_periodo', $this->idPeriodo)->count());

        // El delegado atiende las mismas evaluaciones abiertas; la que está en apelación no se mueve.
        $ev1 = DB::table('evaluacion')->where('id_evaluacion', $this->evaluaciones[$this->evaluado1])->first();
        $this->assertSame($this->delegado, (int) $ev1->id_vinc_evaluador);
        $this->assertSame($this->titular, (int) $ev1->id_vinc_suplente);
        $this->assertSame($this->titular, (int) DB::table('evaluacion')->where('id_evaluacion', $this->evaluaciones[$this->evaluado2])->value('id_vinc_evaluador'));
    }

    public function test_al_finalizar_la_corta_el_trabajo_vuelve_al_titular(): void
    {
        $this->registrar(15)->assertSessionHasNoErrors();

        $this->postJson('/admin/delegaciones/' . $this->delegacion()->id_delegacion . '/finalizar')->assertOk();

        $this->assertSame('FINALIZADA', $this->delegacion()->estado);
        $this->assertSame($this->titular, (int) DB::table('evaluacion')->where('id_evaluacion', $this->evaluaciones[$this->evaluado1])->value('id_vinc_evaluador'));
    }

    public function test_al_finalizar_la_corta_con_titular_retirado_el_trabajo_pasa_al_sucesor(): void
    {
        $this->registrar(15)->assertSessionHasNoErrors();
        $id = $this->delegacion()->id_delegacion;
        DB::table('vinculacion')->where('id_vinculacion', $this->titular)->update(['activa' => 0]);

        $this->postJson("/admin/delegaciones/{$id}/finalizar")->assertStatus(422);

        $this->postJson("/admin/delegaciones/{$id}/finalizar", ['id_vinc_sucesor' => $this->otroEvaluador])->assertOk();

        $ev1 = DB::table('evaluacion')->where('id_evaluacion', $this->evaluaciones[$this->evaluado1])->first();
        $this->assertSame($this->otroEvaluador, (int) $ev1->id_vinc_evaluador);
        $this->assertNull($ev1->id_vinc_suplente);
        $this->assertSame($this->otroEvaluador, $this->evaluadorDe($this->evaluado1));
        $this->assertSame(0, DB::table('evaluador_asignacion')->where('id_vinc_evaluador', $this->titular)->count(), 'No queda trabajo a nombre del titular retirado.');
    }

    private function avisos(int $idVinc): array
    {
        return DB::table('notificacion as n')
            ->join('funcionario as f', 'f.id_usuario', '=', 'n.id_usuario_destino')
            ->join('vinculacion as v', 'v.id_funcionario', '=', 'f.id_funcionario')
            ->where('v.id_vinculacion', $idVinc)
            ->pluck('n.tipo')
            ->all();
    }

    public function test_la_delegacion_larga_avisa_a_cada_evaluado_y_al_delegado(): void
    {
        $this->activarLarga();

        $this->assertSame(['CAMBIO_EVALUADOR'], $this->avisos($this->evaluado1));
        $this->assertSame(['CAMBIO_EVALUADOR'], $this->avisos($this->evaluado2));
        // El delegado recibe aviso como evaluado (nuevo superior) y como nuevo evaluador de cada persona.
        $avisosDelegado = $this->avisos($this->delegado);
        sort($avisosDelegado);
        $this->assertSame(['CAMBIO_EVALUADOR', 'NUEVO_EVALUADO', 'NUEVO_EVALUADO'], $avisosDelegado);
        $this->assertSame(['NUEVO_EVALUADO'], $this->avisos($this->superior));
    }

    public function test_la_delegacion_corta_no_genera_avisos(): void
    {
        $this->registrar(15)->assertSessionHasNoErrors();
        $this->postJson('/admin/delegaciones/' . $this->delegacion()->id_delegacion . '/finalizar')->assertOk();

        $this->assertSame([], $this->avisos($this->evaluado1));
        $this->assertSame([], $this->avisos($this->delegado));
    }

    public function test_la_bitacora_registra_la_ida_y_vuelta_de_la_delegacion_corta(): void
    {
        $this->registrar(15)->assertSessionHasNoErrors();
        $this->postJson('/admin/delegaciones/' . $this->delegacion()->id_delegacion . '/finalizar')->assertOk();

        $eventos = DB::table('evaluacion_evento')
            ->where('id_evaluacion', $this->evaluaciones[$this->evaluado1])
            ->orderBy('id_evento')
            ->get();

        $this->assertSame(['CAMBIO_EVALUADOR', 'CAMBIO_EVALUADOR'], $eventos->pluck('tipo_evento')->all());
        $ida = json_decode($eventos[0]->datos, true);
        $vuelta = json_decode($eventos[1]->datos, true);
        $this->assertSame(['DELEGACION_CORTA', $this->titular, $this->delegado], [$ida['origen'], $ida['id_vinc_evaluador_anterior'], $ida['id_vinc_evaluador_nuevo']]);
        $this->assertSame(['FIN_DELEGACION_CORTA', $this->delegado, $this->titular], [$vuelta['origen'], $vuelta['id_vinc_evaluador_anterior'], $vuelta['id_vinc_evaluador_nuevo']]);
    }

    // --- Ausencia larga del titular: pausa y reanudación de su propio tramo ---

    /** El titular es evaluado por otroEvaluador; retorna su evaluación vigente. */
    private function titularConEvaluador(): int
    {
        DB::table('evaluador_asignacion')->insert([
            'id_vinc_evaluador' => $this->otroEvaluador,
            'id_vinc_evaluado' => $this->titular,
            'fecha_asignacion' => now(),
        ]);
        $idEvaluacion = $this->crearEvaluacion($this->titular, $this->otroEvaluador, 'SEMESTRE_2');
        DB::table('compromiso')->insert(['id_evaluacion' => $idEvaluacion, 'numero_orden' => 1, 'descripcion' => 'Compromiso del titular', 'porcentaje_peso' => 80]);

        return $idEvaluacion;
    }

    public function test_sin_evaluador_el_tramo_del_titular_se_omite(): void
    {
        $this->registrar(60, $this->superior)->assertSessionHasNoErrors();

        $this->assertSame(0, DB::table('traslado')->where('id_delegacion', $this->delegacion()->id_delegacion)->where('tipo', 'PAUSA')->count());
    }

    public function test_la_ausencia_larga_cierra_el_tramo_del_titular_sin_cambiar_su_evaluador(): void
    {
        $evaluacionTitular = $this->titularConEvaluador();
        $this->registrar(60, $this->superior)->assertSessionHasNoErrors();
        $id = $this->delegacion()->id_delegacion;

        $pausa = DB::table('traslado')->where('id_delegacion', $id)->where('tipo', 'PAUSA')->first();
        $this->assertSame($this->titular, (int) $pausa->id_vinc_funcionario);
        $this->assertSame(150, (int) $pausa->dias_laborados);

        // Con todas las demás notas, falta solo la del titular.
        foreach ($this->evaluaciones as $idEvaluacion) {
            $this->calificar($idEvaluacion);
        }
        $respuesta = $this->postJson("/admin/delegaciones/{$id}/confirmar")->assertStatus(422);
        $this->assertStringContainsString('Faltan 1 tramo(s)', $respuesta->json('message'));

        $this->calificar($evaluacionTitular);
        $this->postJson("/admin/delegaciones/{$id}/confirmar")->assertOk();

        $this->assertSame(150, (int) DB::table('evaluacion')->where('id_evaluacion', $evaluacionTitular)->value('dias_laborados'));
        $this->assertSame($this->otroEvaluador, $this->evaluadorDe($this->titular), 'La pausa no cambia el evaluador.');
        $this->assertSame(1, DB::table('evaluacion')->where('id_vinc_evaluado', $this->titular)->count(), 'Durante la ausencia no hay evaluación abierta.');
    }

    public function test_al_regresar_el_titular_concierta_una_evaluacion_nueva_con_sus_compromisos_previos(): void
    {
        $evaluacionTitular = $this->titularConEvaluador();
        $this->registrar(60, $this->superior)->assertSessionHasNoErrors();
        $id = $this->delegacion()->id_delegacion;
        foreach ($this->evaluaciones + [-1 => $evaluacionTitular] as $idEvaluacion) {
            $this->calificar($idEvaluacion);
        }
        $this->postJson("/admin/delegaciones/{$id}/confirmar")->assertOk();

        $this->postJson("/admin/delegaciones/{$id}/finalizar")->assertOk();
        $reanudacion = DB::table('traslado')->where('id_delegacion', $id)->where('tipo', 'REANUDACION')->first();
        $this->assertSame(Carbon::today()->addDays(60)->toDateString(), $reanudacion->fecha_traslado);

        foreach ([$this->evaluado1, $this->evaluado2] as $vinc) {
            $this->calificar($this->parcialCon($vinc, $this->delegado)->id_evaluacion);
        }
        $this->calificar($this->parcialCon($this->delegado, $this->superior)->id_evaluacion);
        $this->postJson("/admin/delegaciones/{$id}/confirmar")->assertOk();

        $nueva = $this->parcialCon($this->titular, $this->otroEvaluador);
        $this->assertNotNull($nueva);
        $this->assertSame(31, (int) $nueva->dias_laborados, 'Desde el regreso hasta el cierre del periodo.');
        $this->assertSame(0, (int) $nueva->concertacion_firmada);

        $copia = DB::table('compromiso')->where('id_evaluacion', $nueva->id_evaluacion)->first();
        $this->assertSame('Compromiso del titular', $copia->descripcion);
        $this->assertSame(1, (int) $copia->es_copia);

        $this->assertContains('NUEVA_EVALUACION', $this->avisos($this->titular));

        // La ausencia no cuenta: el semestre suma solo los días trabajados.
        $this->calificar($nueva->id_evaluacion);
        $semestre = obtenerNotaSemestreConsolidada($this->titular, 2099, 'RENDIMIENTO_LABORAL', 2);
        $this->assertSame('TRAMOS_PONDERADOS', $semestre['tipo']);
        $this->assertSame(150 + 31, $semestre['dias_laborados']);
    }

    public function test_si_el_titular_no_regresa_no_se_reanuda(): void
    {
        $evaluacionTitular = $this->titularConEvaluador();
        $this->registrar(60, $this->superior)->assertSessionHasNoErrors();
        $id = $this->delegacion()->id_delegacion;
        foreach ($this->evaluaciones + [-1 => $evaluacionTitular] as $idEvaluacion) {
            $this->calificar($idEvaluacion);
        }
        $this->postJson("/admin/delegaciones/{$id}/confirmar")->assertOk();
        DB::table('vinculacion')->where('id_vinculacion', $this->titular)->update(['activa' => 0]);

        $this->postJson("/admin/delegaciones/{$id}/finalizar", [
            'id_vinc_sucesor' => $this->superior,
            'id_vinc_evaluador_regreso' => $this->otroEvaluador,
        ])->assertOk();

        $this->assertSame(0, DB::table('traslado')->where('id_delegacion', $id)->where('tipo', 'REANUDACION')->count());
    }

    public function test_la_corta_no_se_amplia_a_mas_de_30_dias(): void
    {
        $this->registrar(15)->assertSessionHasNoErrors();

        $this->putJson('/admin/delegaciones/' . $this->delegacion()->id_delegacion, [
            'fecha_fin' => Carbon::today()->addDays(60)->toDateString(),
        ])->assertStatus(422);

        $this->putJson('/admin/delegaciones/' . $this->delegacion()->id_delegacion, [
            'fecha_fin' => Carbon::today()->addDays(25)->toDateString(),
        ])->assertOk();
    }
}
