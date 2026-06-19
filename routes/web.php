<?php

use App\Models\User;
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

    $user = User::where('correo', $credentials['correo'])->first();

    if (! $user || ! Hash::check($credentials['password'], $user->contrasena_hash)) {
        return back()
            ->withErrors(['login' => 'Correo institucional o contraseña incorrectos.'])
            ->onlyInput('correo');
    }

    $request->session()->regenerate();
    $roles = [];

    $info = DB::table('usuario as u')
        ->join('empleado as e', 'e.id_empleado', '=', 'u.id_empleado')
        ->join('dependencia_jerarquica as d', 'd.id_dependencia', '=', 'e.id_dependencia')
        ->join('cargo as c', 'c.id_cargo', '=', 'd.id_cargo')
        ->join('naturaleza_cargo as n', 'n.id_naturaleza', '=', 'c.id_naturaleza')
        ->where('u.id_usuario', $user->id_usuario)
        ->select('e.id_empleado', 'e.correo_institucional', 'c.nombre_cargo', 'n.abreviatura', 'n.descripcion')
        ->first();

    $roles[] = 'evaluado';

    if ($info) {
        if (in_array($info->abreviatura, ['LNR', 'PF'], true)) {
            $roles[] = 'evaluador';
        }

        if ((int) $user->id_usuario === 1 || str_contains(strtolower((string) $info->nombre_cargo), 'admin')) {
            $roles[] = 'admin';
        }
    }

    $roles = array_values(array_unique($roles));

    $request->session()->put('usuario_autenticado', [
        'id_usuario' => $user->id_usuario,
        'correo' => $user->correo,
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
