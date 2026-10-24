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

    public function test_solo_talento_humano_administra_el_catalogo(): void
    {
        session(['usuario_autenticado' => ['id_usuario' => $this->idUsuario, 'rol_activo' => 'evaluador', 'roles' => ['evaluador']]]);

        $this->getJson('/admin/plazas')->assertForbidden();
        $this->postJson('/admin/plazas/generar')->assertForbidden();
    }
}
