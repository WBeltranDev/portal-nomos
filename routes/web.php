<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

// Ruta raíz: Carga el formulario de Login
Route::get('/', function () {
    return view('login');
});

Route::post('/login', function (Request $request) {
    $credentials = $request->validate([
        'correo' => ['required', 'email'],
        'password' => ['required', 'string'],
    ]);

    $user = DB::table('usuario as u')
        ->join('empleado as e', 'e.id_empleado', '=', 'u.id_empleado')
        ->where('e.correo_institucional', $credentials['correo'])
        ->select('u.id_usuario', 'u.contrasena_hash', 'u.rol', 'u.id_empleado', 'e.correo_institucional')
        ->first();

    if (! $user) {
        return back()
            ->withErrors(['login' => 'Correo institucional o contraseña incorrectos.'])
            ->onlyInput('correo');
    }

    $storedPassword = (string) $user->contrasena_hash;
    $passwordValid = str_starts_with($storedPassword, '$2y$') || str_starts_with($storedPassword, '$argon2')
        ? Hash::check($credentials['password'], $storedPassword)
        : hash_equals($storedPassword, $credentials['password']);

    if (! $passwordValid) {
        return back()
            ->withErrors(['login' => 'Correo institucional o contraseña incorrectos.'])
            ->onlyInput('correo');
    }

    $request->session()->regenerate();

    $roles = ['evaluado'];

    $esEvaluador = DB::table('evaluacion')
        ->where('id_empleado_evaluador', $user->id_empleado)
        ->exists();

    $esEvaluado = DB::table('evaluacion')
        ->where('id_empleado_evaluado', $user->id_empleado)
        ->exists();

    if ($esEvaluador) {
        $roles[] = 'evaluador';
    }

    if (($user->rol ?? 'USUARIO') === 'ADMIN') {
        $roles[] = 'admin';
    }

    $roles = array_values(array_unique($roles));

    if (! $esEvaluado && ! $esEvaluador && ($user->rol ?? 'USUARIO') !== 'ADMIN') {
        $roles = ['evaluado'];
    }

    $request->session()->put('usuario_autenticado', [
        'id_usuario' => $user->id_usuario,
        'correo' => $user->correo_institucional,
        'id_empleado' => $user->id_empleado,
        'roles' => $roles,
        'rol_activo' => null,
    ]);

    if (count($roles) === 1) {
        $request->session()->put('usuario_autenticado.rol_activo', $roles[0]);

        return redirect('/dashboard');
    }

    return redirect('/seleccionar-rol');
})->name('login.store');

Route::post('/logout', function (Request $request) {
    $request->session()->forget('usuario_autenticado');
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/');
})->name('logout');

Route::get('/seleccionar-rol', function () {
    abort_unless(session()->has('usuario_autenticado'), 403);

    $usuario = session('usuario_autenticado');
    $roles = $usuario['roles'] ?? [];

    if (count($roles) <= 1) {
        return redirect('/dashboard');
    }

    return view('select-role', [
        'roles' => $roles,
    ]);
});

Route::post('/seleccionar-rol', function (Request $request) {
    $data = $request->validate([
        'rol' => ['required', 'in:evaluado,evaluador,admin'],
    ]);

    $roles = session('usuario_autenticado.roles', []);

    abort_unless(in_array($data['rol'], $roles, true), 403);

    $request->session()->put('usuario_autenticado.rol_activo', $data['rol']);

    return redirect('/dashboard');
})->name('role.select');

// Ruta interna: Carga el Dashboard unificado que ya aprobaste
Route::get('/dashboard', function () {
    abort_unless(session()->has('usuario_autenticado'), 403);

    if (! session('usuario_autenticado.rol_activo')) {
        return redirect('/seleccionar-rol');
    }

    return view('dashboard');
});
