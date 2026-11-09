<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Concerns\CreaDatosSerag;
use Tests\TestCase;

class Fase6IngresoDocumentoTest extends TestCase
{
    use CreaDatosSerag;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarDatosSerag();
        RateLimiter::clear('11111|127.0.0.1');
        RateLimiter::clear('1098765432|127.0.0.1');
    }

    public function test_ingreso_exitoso_con_numero_de_documento(): void
    {
        $doc = '1098765432';
        $idUsuario = DB::table('usuario')->insertGetId([
            'username' => 'funcionario.prueba@unitropico.edu.co',
            'documento_login' => $doc,
            'password' => Hash::make('ClaveSegura2026!'),
            'rol' => 'EVALUADO',
            'activo' => 1,
        ]);

        $idFunc = DB::table('funcionario')->insertGetId([
            'id_usuario' => $idUsuario,
            'tipo_documento' => 'CEDULA_CIUDADANIA',
            'numero_doc' => $doc,
            'nombres' => 'Funcionario',
            'apellidos' => 'Prueba Doc',
        ]);

        $resp = $this->post('/login', [
            'documento' => $doc,
            'password' => 'ClaveSegura2026!',
        ]);

        $resp->assertRedirect('/dashboard');
        $this->assertSame($idUsuario, session('usuario_autenticado.id_usuario'));
        $this->assertSame($doc, session('usuario_autenticado.documento'));
    }

    public function test_ingreso_rechazado_con_documento_inexistente(): void
    {
        $resp = $this->post('/login', [
            'documento' => '999999999999',
            'password' => 'cualquiera',
        ]);

        $resp->assertRedirect();
        $resp->assertSessionHasErrors('login');
        $this->assertStringContainsString('Documento o contraseña incorrectos', session('errors')->first('login'));
        $this->assertNull(session('usuario_autenticado'));
    }

    public function test_ingreso_rechazado_con_contrasena_incorrecta(): void
    {
        $doc = '88888888';
        $idUsuario = DB::table('usuario')->insertGetId([
            'username' => 'funcionario.clave@unitropico.edu.co',
            'documento_login' => $doc,
            'password' => Hash::make('Correcta123!'),
            'rol' => 'EVALUADO',
            'activo' => 1,
        ]);

        $resp = $this->post('/login', [
            'documento' => $doc,
            'password' => 'ClaveEquivocada',
        ]);

        $resp->assertRedirect();
        $resp->assertSessionHasErrors('login');
        $this->assertStringContainsString('Documento o contraseña incorrectos', session('errors')->first('login'));
        $this->assertNull(session('usuario_autenticado'));
    }

    public function test_bloqueo_tras_5_intentos_fallidos(): void
    {
        $doc = '77777777';
        RateLimiter::clear("{$doc}|127.0.0.1");

        DB::table('usuario')->insertGetId([
            'username' => 'funcionario.throttle@unitropico.edu.co',
            'documento_login' => $doc,
            'password' => Hash::make('ClaveReal!'),
            'rol' => 'EVALUADO',
            'activo' => 1,
        ]);

        // 5 intentos fallidos
        for ($i = 1; $i <= 5; $i++) {
            $resp = $this->post('/login', [
                'documento' => $doc,
                'password' => "Equivocada_{$i}",
            ]);
            $resp->assertRedirect();
            $resp->assertSessionHasErrors('login');
            $this->assertStringContainsString('Documento o contraseña incorrectos', session('errors')->first('login'));
        }

        // El 6º intento debe ser bloqueado por RateLimiter (throttle)
        $respBloqueado = $this->post('/login', [
            'documento' => $doc,
            'password' => 'CualquierClave',
        ]);

        $respBloqueado->assertRedirect();
        $respBloqueado->assertSessionHasErrors('login');
        $this->assertStringContainsString('Demasiados intentos fallidos', session('errors')->first('login'));
    }

    public function test_ingreso_administrador_con_11111(): void
    {
        // En migracion th1 queda con 11111
        $admin = DB::table('usuario')->where('documento_login', '11111')->first();
        if (! $admin) {
            $idAdmin = DB::table('usuario')->insertGetId([
                'username' => 'administradorth1@unitropico.edu.co',
                'documento_login' => '11111',
                'password' => Hash::make('AdminPass123!'),
                'rol' => 'ADMINISTRADOR',
                'activo' => 1,
            ]);
        } else {
            DB::table('usuario')->where('id_usuario', $admin->id_usuario)->update([
                'password' => Hash::make('AdminPass123!'),
            ]);
            $idAdmin = $admin->id_usuario;
        }

        $resp = $this->post('/login', [
            'documento' => '11111',
            'password' => 'AdminPass123!',
        ]);

        $resp->assertRedirect('/dashboard');
        $this->assertSame($idAdmin, session('usuario_autenticado.id_usuario'));
        $this->assertContains('admin', session('usuario_autenticado.roles'));
    }

    public function test_ingreso_administradores_th2_y_th3_con_11112_y_11113(): void
    {
        foreach (['11112', '11113'] as $docAdmin) {
            $admin = DB::table('usuario')->where('documento_login', $docAdmin)->first();
            if (! $admin) {
                DB::table('usuario')->insert([
                    'username' => "administrador{$docAdmin}@unitropico.edu.co",
                    'documento_login' => $docAdmin,
                    'password' => Hash::make('AdminPass!'),
                    'rol' => 'ADMINISTRADOR',
                    'activo' => 1,
                ]);
            } else {
                DB::table('usuario')->where('id_usuario', $admin->id_usuario)->update([
                    'password' => Hash::make('AdminPass!'),
                ]);
            }

            $resp = $this->post('/login', [
                'documento' => $docAdmin,
                'password' => 'AdminPass!',
            ]);

            $resp->assertRedirect('/dashboard');
            $this->assertContains('admin', session('usuario_autenticado.roles'));
        }
    }

    public function test_contrasena_en_texto_plano_se_convierte_a_hash_en_ingreso_exitoso(): void
    {
        $doc = '66666666';
        $claveTextoPlano = 'ContrasenaPlana2026';

        $idUsuario = DB::table('usuario')->insertGetId([
            'username' => 'usuario.plano@unitropico.edu.co',
            'documento_login' => $doc,
            'password' => $claveTextoPlano, // Texto plano en BD
            'rol' => 'EVALUADO',
            'activo' => 1,
        ]);

        // Verificamos que arranca en texto plano (no empieza con $2y$ ni $argon2)
        $passInicial = DB::table('usuario')->where('id_usuario', $idUsuario)->value('password');
        $this->assertSame($claveTextoPlano, $passInicial);
        $this->assertFalse(str_starts_with($passInicial, '$2y$'));

        // Inicio de sesión exitoso
        $resp = $this->post('/login', [
            'documento' => $doc,
            'password' => $claveTextoPlano,
        ]);

        $resp->assertRedirect('/dashboard');

        // La base de datos ahora debe tener la contraseña hasheada
        $passActualizada = DB::table('usuario')->where('id_usuario', $idUsuario)->value('password');
        $this->assertTrue(str_starts_with($passActualizada, '$2y$'), 'La contraseña debe haber sido migrada a hash Bcrypt.');
        $this->assertTrue(Hash::check($claveTextoPlano, $passActualizada));
    }

    public function test_compatibilidad_con_correo_institucional_durante_transicion(): void
    {
        $correo = 'funcionario.transicion@unitropico.edu.co';
        $doc = '55555555';
        $idUsuario = DB::table('usuario')->insertGetId([
            'username' => $correo,
            'documento_login' => $doc,
            'password' => Hash::make('ClaveTransicion!'),
            'rol' => 'EVALUADO',
            'activo' => 1,
        ]);

        // Envía el correo por el campo 'correo'
        $resp = $this->post('/login', [
            'correo' => $correo,
            'password' => 'ClaveTransicion!',
        ]);

        $resp->assertRedirect('/dashboard');
        $this->assertSame($idUsuario, session('usuario_autenticado.id_usuario'));
    }

    public function test_pantalla_de_login_muestra_aviso_de_transicion_y_campo_documento(): void
    {
        $resp = $this->get('/');

        $resp->assertOk();
        $resp->assertSee('Ahora ingresa con tu número de documento');
        $resp->assertSee('name="documento"', false);
        $resp->assertSee('Número de documento');
    }

    public function test_limite_de_intentos_se_reinicia_tras_ingreso_exitoso(): void
    {
        $doc = '44444444';
        RateLimiter::clear("{$doc}|127.0.0.1");

        $idUsuario = DB::table('usuario')->insertGetId([
            'username' => 'funcionario.reinicio@unitropico.edu.co',
            'documento_login' => $doc,
            'password' => Hash::make('ClaveCorrecta!'),
            'rol' => 'EVALUADO',
            'activo' => 1,
        ]);

        // 3 intentos fallidos
        for ($i = 0; $i < 3; $i++) {
            $this->post('/login', [
                'documento' => $doc,
                'password' => 'MalaClave',
            ]);
        }

        // 4º intento exitoso
        $respExitoso = $this->post('/login', [
            'documento' => $doc,
            'password' => 'ClaveCorrecta!',
        ]);
        $respExitoso->assertRedirect('/dashboard');

        // Tras el éxito, el contador debe haberse limpiado
        $this->assertSame(0, RateLimiter::attempts("{$doc}|127.0.0.1"));
    }
}
