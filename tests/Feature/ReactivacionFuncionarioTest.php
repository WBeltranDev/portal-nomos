<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Caso de producción (rotación de Ana Rocío y Natalia Pulido): una funcionaria
 * quedó inactiva y al intentar activarla con «Crear usuario y cargo» salió
 * «Duplicate entry … for key id_usuario». Además, aun sin el error, la persona
 * volvía con una vinculación nueva, sin evaluador ni plaza, y no aparecía entre
 * los evaluados de su jefe. Reactivar debe recuperar la vinculación anterior.
 */
class ReactivacionFuncionarioTest extends TestCase
{
    private string $s;
    private int $idFuncionario;
    private int $idUsuarioPropio;
    private int $idVinc;
    private int $idJefe;
    private int $idPuesto;
    private int $idUsuarioAjeno;
    private string $cedula;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requiere una base MySQL con el esquema institucional aplicado.');
        }

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
        DB::beginTransaction();

        $this->s = 'R' . random_int(10000, 99999);
        $this->cedula = (string) random_int(10 ** 9, 2 * 10 ** 9);
        DB::table('cargo')->insertOrIgnore(['nombre' => 'AUXILIAR ADMINISTRATIVO', 'codigo_cargo' => 407, 'activo' => 1]);
        $idCargo = (int) DB::table('cargo')->where('nombre', 'AUXILIAR ADMINISTRATIVO')->value('id_cargo');
        $idDependencia = DB::table('dependencia')->insertGetId(['nombre' => "CLINICA {$this->s}", 'activa' => 1]);
        $this->idPuesto = DB::table('puesto')->insertGetId([
            'codigo_plaza' => "T-{$this->s}", 'id_dependencia' => $idDependencia, 'id_cargo' => $idCargo, 'codigo_cargo' => 407, 'grado' => 11,
            'nivel_jerarquico' => 'ASISTENCIAL', 'sistema_evaluacion' => 'RENDIMIENTO_LABORAL', 'estado' => 'VALIDADA', 'requiere_revision' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // El jefe y la funcionaria, con su propia cuenta (correo de la clínica).
        $this->idJefe = $this->vinculacion($this->funcionario('J', 'jefe'), 'JEFE DE OFICINA', 1);
        $this->idUsuarioPropio = DB::table('usuario')->insertGetId(['username' => "aux1.clinica.{$this->s}@unitropico.edu.co", 'password' => 'x', 'rol' => 'EVALUADO', 'activo' => 1]);
        $this->idFuncionario = DB::table('funcionario')->insertGetId([
            'id_usuario' => $this->idUsuarioPropio, 'tipo_documento' => 'CEDULA_CIUDADANIA', 'numero_doc' => $this->cedula, 'nombres' => 'NATALIA', 'apellidos' => "PULIDO {$this->s}",
        ]);
        $this->idVinc = $this->vinculacion($this->idFuncionario, 'AUXILIAR ADMINISTRATIVO', 0, "CLINICA {$this->s}");
        DB::table('evaluador_asignacion')->insert(['id_vinc_evaluador' => $this->idJefe, 'id_vinc_evaluado' => $this->idVinc, 'fecha_asignacion' => now()]);
        DB::table('puesto_ocupacion')->insert([
            'id_puesto' => $this->idPuesto, 'id_vinculacion' => $this->idVinc, 'tipo_ocupacion' => 'PROVISIONALIDAD', 'vigente_desde' => '2090-01-01', 'estado' => 'VIGENTE',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // La cuenta con el correo del cargo de otra plaza pertenece a OTRA persona (Ana Rocío).
        $this->idUsuarioAjeno = DB::table('usuario')->insertGetId(['username' => "circulacion.{$this->s}@unitropico.edu.co", 'password' => 'x', 'rol' => 'EVALUADO', 'activo' => 1]);
        DB::table('funcionario')->insert([
            'id_usuario' => $this->idUsuarioAjeno, 'tipo_documento' => 'CEDULA_CIUDADANIA', 'numero_doc' => (string) random_int(2 * 10 ** 9, 3 * 10 ** 9),
            'nombres' => 'ROCIO', 'apellidos' => "CAMPOS {$this->s}",
        ]);

        session(['usuario_autenticado' => ['id_usuario' => $this->idUsuarioPropio, 'rol_activo' => 'admin', 'roles' => ['admin']]]);
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    private function funcionario(string $clave, string $rol): int
    {
        $idUsuario = DB::table('usuario')->insertGetId(['username' => "tmp.{$clave}.{$this->s}", 'password' => 'x', 'rol' => 'EVALUADO', 'activo' => 1]);

        return DB::table('funcionario')->insertGetId([
            'id_usuario' => $idUsuario, 'tipo_documento' => 'CEDULA_CIUDADANIA', 'numero_doc' => (string) random_int(3 * 10 ** 9, 4 * 10 ** 9), 'nombres' => $clave, 'apellidos' => $this->s,
        ]);
    }

    private function vinculacion(int $idFuncionario, string $cargo, int $esEvaluador, ?string $area = null): int
    {
        return DB::table('vinculacion')->insertGetId([
            'id_funcionario' => $idFuncionario, 'cargo' => $cargo, 'codigo_cargo' => 1, 'grado_cargo' => 1, 'nivel_jerarquico' => 'ASISTENCIAL', 'area' => $area ?? 'OFICINA',
            'tipo_vinculacion' => 'PROVISIONALIDAD', 'sistema_evaluacion' => 'RENDIMIENTO_LABORAL', 'es_evaluador' => $esEvaluador, 'fecha_ingreso' => '2090-01-01', 'activa' => 1,
        ]);
    }

    private function inhabilitar(): void
    {
        $this->post("/admin/funcionarios/{$this->idFuncionario}/disable")->assertSessionHasNoErrors();
    }

    private function datosCrear(array $extra = []): array
    {
        return $extra + [
            'nombres' => 'NATALIA', 'apellidos' => "PULIDO {$this->s}", 'tipo_documento' => 'CEDULA_CIUDADANIA', 'numero_doc' => $this->cedula,
            'cargo' => 'AUXILIAR ADMINISTRATIVO', 'area' => "CLINICA {$this->s}", 'rol' => 'EVALUADO', 'sistema_evaluacion' => 'RENDIMIENTO_LABORAL',
        ];
    }

    private function aparecePara(int $idJefe): bool
    {
        return DB::table('evaluador_asignacion as ea')->join('vinculacion as v', 'v.id_vinculacion', '=', 'ea.id_vinc_evaluado')
            ->where('ea.id_vinc_evaluador', $idJefe)->where('v.id_funcionario', $this->idFuncionario)->where('v.activa', 1)->exists();
    }

    public function test_inhabilitada_deja_de_aparecer_entre_los_evaluados_de_su_jefe(): void
    {
        $this->assertTrue($this->aparecePara($this->idJefe));
        $this->inhabilitar();
        $this->assertFalse($this->aparecePara($this->idJefe));
    }

    public function test_crear_usuario_y_cargo_con_el_correo_de_otra_persona_ya_no_choca_ni_toma_su_cuenta(): void
    {
        $this->inhabilitar();

        // La versión anterior enviaba el correo del cargo; la cuenta de ese correo es de otra persona.
        $this->post('/admin/usuarios', $this->datosCrear(['correo' => "circulacion.{$this->s}@unitropico.edu.co"]))
            ->assertSessionHasNoErrors();

        $this->assertSame($this->idUsuarioPropio, (int) DB::table('funcionario')->where('id_funcionario', $this->idFuncionario)->value('id_usuario'), 'Conserva su propia cuenta.');
        $this->assertSame(1, (int) DB::table('usuario')->where('id_usuario', $this->idUsuarioPropio)->value('activo'));
        $this->assertSame(1, DB::table('funcionario')->where('id_usuario', $this->idUsuarioAjeno)->count(), 'La cuenta de la otra persona no se toca.');
        $this->assertNull(DB::table('usuario')->where('username', $this->cedula)->first(), 'No se crea una cuenta duplicada.');
    }

    public function test_crear_usuario_y_cargo_reactiva_la_vinculacion_anterior_con_su_evaluador_y_su_plaza(): void
    {
        $this->inhabilitar();

        $this->post('/admin/usuarios', $this->datosCrear())->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'se recuperó su vinculación anterior') && str_contains($m, "T-{$this->s}"));

        // Misma vinculación (no una nueva), con su evaluador y su plaza; aparece otra vez ante su jefe.
        $this->assertSame(1, DB::table('vinculacion')->where('id_funcionario', $this->idFuncionario)->count());
        $this->assertSame(1, (int) DB::table('vinculacion')->where('id_vinculacion', $this->idVinc)->value('activa'));
        $this->assertTrue($this->aparecePara($this->idJefe));
        $this->assertSame($this->idPuesto, (int) DB::table('puesto_ocupacion')->where('id_vinculacion', $this->idVinc)->where('estado', 'VIGENTE')->value('id_puesto'));
    }

    public function test_un_cargo_distinto_si_crea_una_vinculacion_nueva(): void
    {
        $this->inhabilitar();

        $this->post('/admin/usuarios', $this->datosCrear(['area' => 'OTRA DEPENDENCIA']))->assertSessionHasNoErrors();

        $this->assertSame(2, DB::table('vinculacion')->where('id_funcionario', $this->idFuncionario)->count());
        $this->assertSame(1, DB::table('vinculacion')->where('id_funcionario', $this->idFuncionario)->where('activa', 1)->count());
    }

    public function test_no_se_duplica_una_vinculacion_activa_en_el_mismo_cargo(): void
    {
        $this->post('/admin/usuarios', $this->datosCrear())
            ->assertSessionHasErrors(['error' => 'Error al crear usuario y cargo: Esta persona ya tiene una vinculación activa en ese cargo y dependencia.']);
        $this->assertSame(1, DB::table('vinculacion')->where('id_funcionario', $this->idFuncionario)->count());
    }

    public function test_reactivar_funcionario_recupera_cuenta_vinculacion_y_plaza(): void
    {
        $this->inhabilitar();
        $this->assertSame(0, (int) DB::table('usuario')->where('id_usuario', $this->idUsuarioPropio)->value('activo'));

        $this->post("/admin/funcionarios/{$this->idFuncionario}/enable", ['id_vinculacion' => $this->idVinc])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'Funcionario reactivado') && str_contains($m, "Volvió a su plaza T-{$this->s}"));

        $this->assertSame(1, (int) DB::table('usuario')->where('id_usuario', $this->idUsuarioPropio)->value('activo'));
        $this->assertTrue($this->aparecePara($this->idJefe));
        $this->assertSame($this->idPuesto, (int) DB::table('puesto_ocupacion')->where('id_vinculacion', $this->idVinc)->where('estado', 'VIGENTE')->value('id_puesto'));
    }

    public function test_si_otra_persona_ya_ocupa_su_plaza_se_reactiva_sin_plaza_y_se_avisa(): void
    {
        $this->inhabilitar();
        $otra = $this->vinculacion($this->funcionario('O', 'otra'), 'AUXILIAR ADMINISTRATIVO', 0);
        DB::table('puesto_ocupacion')->insert([
            'id_puesto' => $this->idPuesto, 'id_vinculacion' => $otra, 'tipo_ocupacion' => 'PROVISIONALIDAD', 'vigente_desde' => '2091-01-01', 'estado' => 'VIGENTE',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->post("/admin/funcionarios/{$this->idFuncionario}/enable")->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'asígnala en el Catálogo de plazas'));

        $this->assertSame(1, (int) DB::table('vinculacion')->where('id_vinculacion', $this->idVinc)->value('activa'));
        $this->assertNull(DB::table('puesto_ocupacion')->where('id_vinculacion', $this->idVinc)->where('estado', 'VIGENTE')->value('id_puesto'));
    }

    public function test_no_se_reactiva_a_quien_ya_esta_activo_ni_lo_hace_otro_rol(): void
    {
        $this->post("/admin/funcionarios/{$this->idFuncionario}/enable")->assertSessionHasErrors(['error' => 'Este funcionario ya tiene una vinculación activa.']);

        session(['usuario_autenticado' => ['id_usuario' => $this->idUsuarioPropio, 'rol_activo' => 'evaluador', 'roles' => ['evaluador']]]);
        $this->post("/admin/funcionarios/{$this->idFuncionario}/enable")->assertForbidden();
    }

    public function test_el_detalle_del_empleado_ofrece_reactivar(): void
    {
        session(['usuario_autenticado' => ['id_usuario' => $this->idUsuarioPropio, 'rol_activo' => 'admin', 'roles' => ['admin']]]);

        $this->get('/dashboard')->assertOk()
            ->assertSee('id="form-habilitar-empleado"', false)
            ->assertSee('Reactivar Funcionario');
    }
}
