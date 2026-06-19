<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

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
        ->select('u.id_usuario', 'u.contrasena_hash', 'u.rol', 'u.id_empleado', 'e.correo_institucional', 'e.nombres', 'e.apellidos')
        ->first();

    if (! $user) {
        return back()->withErrors(['login' => 'Correo institucional o contraseña incorrectos.'])->onlyInput('correo');
    }

    $storedPassword = (string) $user->contrasena_hash;
    $passwordValid = str_starts_with($storedPassword, '$2y$') || str_starts_with($storedPassword, '$argon2')
        ? Hash::check($credentials['password'], $storedPassword)
        : hash_equals($storedPassword, $credentials['password']);

    if (! $passwordValid) {
        return back()->withErrors(['login' => 'Correo institucional o contraseña incorrectos.'])->onlyInput('correo');
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
        'nombres' => $user->nombres,
        'apellidos' => $user->apellidos,
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

    return view('select-role', ['roles' => $roles]);
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

Route::get('/dashboard', function () {
    abort_unless(session()->has('usuario_autenticado'), 403);

    if (! session('usuario_autenticado.rol_activo')) {
        return redirect('/seleccionar-rol');
    }

    $usuario = session('usuario_autenticado');
    $rolActivo = session('usuario_autenticado.rol_activo');

    $usuarios = DB::table('usuario as u')
        ->join('empleado as e', 'e.id_empleado', '=', 'u.id_empleado')
        ->leftJoin('dependencia_jerarquica as d', 'd.id_dependencia', '=', 'e.id_dependencia')
        ->leftJoin('cargo as c', 'c.id_cargo', '=', 'd.id_cargo')
        ->leftJoin('naturaleza_cargo as n', 'n.id_naturaleza', '=', 'c.id_naturaleza')
        ->select('u.id_usuario', 'u.rol', 'u.id_empleado', 'e.nombres', 'e.apellidos', 'e.correo_institucional', 'e.documento_identidad', 'e.tipo_documento', 'c.nombre_cargo', 'n.descripcion as naturaleza')
        ->orderBy('e.apellidos')
        ->get();

    $empleados = DB::table('empleado as e')
        ->leftJoin('dependencia_jerarquica as d', 'd.id_dependencia', '=', 'e.id_dependencia')
        ->leftJoin('cargo as c', 'c.id_cargo', '=', 'd.id_cargo')
        ->leftJoin('area as a', 'a.id_area', '=', 'd.id_area')
        ->select('e.id_empleado', 'e.nombres', 'e.apellidos', 'e.correo_institucional', 'e.documento_identidad', 'e.tipo_documento', 'e.activo', 'c.nombre_cargo', 'a.nombre_area')
        ->orderBy('e.apellidos')
        ->get();

    $evaluaciones = DB::table('evaluacion as ev')
        ->join('empleado as ee', 'ee.id_empleado', '=', 'ev.id_empleado_evaluado')
        ->join('empleado as ea', 'ea.id_empleado', '=', 'ev.id_empleado_evaluador')
        ->leftJoin('tipo_evaluacion as te', 'te.id_tipo_evaluacion', '=', 'ev.id_tipo_evaluacion')
        ->select('ev.id_evaluacion', 'ev.estado', 'ev.fecha_inicio', 'ev.fecha_fin', 'ev.id_tipo_evaluacion', 'ee.nombres as evaluado_nombres', 'ee.apellidos as evaluado_apellidos', 'ea.nombres as evaluador_nombres', 'ea.apellidos as evaluador_apellidos', 'te.nombre as tipo_nombre')
        ->orderByDesc('ev.id_evaluacion')
        ->get();

    $acuerdosRL = DB::table('tipo_evaluacion')->where('nombre', 'Rendimiento Laboral')->first();
    $acuerdosAG = DB::table('tipo_evaluacion')->where('nombre', 'Acuerdos de Gestión')->first();

    return view('dashboard', compact('usuario', 'rolActivo', 'usuarios', 'empleados', 'evaluaciones', 'acuerdosRL', 'acuerdosAG'));
});

Route::post('/cambiar-contrasena', function (Request $request) {
    abort_unless(session()->has('usuario_autenticado'), 403);

    $data = $request->validate([
        'current_password' => ['required', 'string'],
        'password' => ['required', 'string', 'min:6', 'confirmed'],
    ]);

    $auth = session('usuario_autenticado');

    $user = DB::table('usuario as u')
        ->where('u.id_usuario', $auth['id_usuario'])
        ->first();

    if (! $user) {
        return back()->withErrors(['password' => 'No se encontró el perfil autenticado.']);
    }

    $storedPassword = (string) $user->contrasena_hash;
    $currentValid = str_starts_with($storedPassword, '$2y$') || str_starts_with($storedPassword, '$argon2')
        ? Hash::check($data['current_password'], $storedPassword)
        : hash_equals($storedPassword, $data['current_password']);

    if (! $currentValid) {
        return back()->withErrors(['current_password' => 'La contraseña actual no coincide.']);
    }

    DB::table('usuario')
        ->where('id_usuario', $auth['id_usuario'])
        ->update(['contrasena_hash' => Hash::make($data['password'])]);

    return back()->with('password_updated', true);
})->name('password.update');

Route::post('/usuarios/{id_usuario}/reset-contrasena', function (Request $request, int $id_usuario) {
    abort_unless(session()->has('usuario_autenticado'), 403);

    $tempPassword = substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(10))), 0, 10);

    $updated = DB::table('usuario')
        ->where('id_usuario', $id_usuario)
        ->update(['contrasena_hash' => Hash::make($tempPassword)]);

    abort_unless($updated, 404);

    return back()->with([
        'temp_password' => $tempPassword,
        'temp_password_user' => $id_usuario,
    ]);
})->name('usuarios.reset-password');
