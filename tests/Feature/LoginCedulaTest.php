<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Corrección XI del plan de pruebas 29-10-2026: los funcionarios ingresan con su
 * cédula; las cuentas sin cédula (administradores de Talento Humano) con su
 * usuario. El correo de un funcionario ya no sirve para entrar.
 */
class LoginCedulaTest extends TestCase
{
    private string $cedula;
    private string $correo;
    private string $usuarioAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requiere una base MySQL con el esquema institucional aplicado.');
        }

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
        DB::beginTransaction();

        $s = 'L' . random_int(10000, 99999);
        $this->cedula = (string) random_int(10 ** 9, 2 * 10 ** 9);
        $this->correo = "tmp.login.{$s}@unitropico.edu.co";
        $this->usuarioAdmin = "tmp.admin.{$s}@unitropico.edu.co";

        $idUsuario = DB::table('usuario')->insertGetId(['username' => $this->correo, 'password' => bcrypt('clave-func'), 'rol' => 'EVALUADO', 'activo' => 1]);
        $idFuncionario = DB::table('funcionario')->insertGetId([
            'id_usuario' => $idUsuario, 'tipo_documento' => 'CEDULA_CIUDADANIA', 'numero_doc' => $this->cedula,
            'nombres' => 'TMP', 'apellidos' => $s,
        ]);
        DB::table('vinculacion')->insert([
            'id_funcionario' => $idFuncionario, 'cargo' => 'PRUEBA', 'codigo_cargo' => 1, 'grado_cargo' => 1,
            'nivel_jerarquico' => 'PROFESIONAL', 'area' => 'PRUEBA', 'tipo_vinculacion' => 'PROVISIONALIDAD',
            'sistema_evaluacion' => 'RENDIMIENTO_LABORAL', 'es_evaluador' => 0, 'fecha_ingreso' => '2026-01-01', 'activa' => 1,
        ]);
        DB::table('usuario')->insert(['username' => $this->usuarioAdmin, 'password' => bcrypt('clave-admin'), 'rol' => 'ADMINISTRADOR', 'activo' => 1]);
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    private function ingresar(string $usuario, string $clave)
    {
        return $this->post('/login', ['usuario' => $usuario, 'password' => $clave]);
    }

    public function test_el_funcionario_ingresa_con_su_cedula(): void
    {
        $this->ingresar($this->cedula, 'clave-func')->assertRedirect();

        $this->assertSame($this->cedula, session('usuario_autenticado.identificacion'));
        $this->assertSame('TMP', session('usuario_autenticado.nombres'));
    }

    public function test_la_cedula_se_acepta_con_puntos_y_espacios(): void
    {
        $con = number_format((int) $this->cedula, 0, ',', '.');

        $this->ingresar(" {$con} ", 'clave-func')->assertRedirect();
        $this->assertSame($this->cedula, session('usuario_autenticado.identificacion'));
    }

    public function test_el_correo_de_un_funcionario_ya_no_sirve_para_ingresar(): void
    {
        $this->ingresar($this->correo, 'clave-func')
            ->assertSessionHasErrors(['login' => 'Cédula, usuario o contraseña incorrectos.']);
        $this->assertNull(session('usuario_autenticado'));
    }

    public function test_el_administrador_sin_cedula_ingresa_con_su_usuario(): void
    {
        $this->ingresar($this->usuarioAdmin, 'clave-admin')->assertRedirect('/dashboard');

        $this->assertSame($this->usuarioAdmin, session('usuario_autenticado.identificacion'));
        $this->assertSame(['admin'], session('usuario_autenticado.roles'));
    }

    public function test_clave_incorrecta_o_cedula_inexistente_no_ingresan(): void
    {
        $this->ingresar($this->cedula, 'otra-clave')->assertSessionHasErrors('login');
        $this->ingresar('9' . $this->cedula, 'clave-func')->assertSessionHasErrors('login');
        $this->assertNull(session('usuario_autenticado'));
    }

    public function test_una_cuenta_inactiva_no_ingresa(): void
    {
        DB::table('usuario')->where('username', $this->correo)->update(['activo' => 0]);

        $this->ingresar($this->cedula, 'clave-func')->assertSessionHasErrors('login');
    }

    public function test_el_panel_muestra_la_cedula_y_no_el_correo(): void
    {
        $this->ingresar($this->cedula, 'clave-func')->assertRedirect();

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('C.C. ' . $this->cedula, false)
            ->assertDontSee($this->correo, false);
    }

    public function test_la_pantalla_de_ingreso_pide_cedula_o_usuario(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Cédula o usuario')
            ->assertDontSee('Correo institucional');
    }
}
