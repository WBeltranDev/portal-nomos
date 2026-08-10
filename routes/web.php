<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;


if (!function_exists('defaultPonderacionesConfig')) {
    function defaultPonderacionesConfig() {
        return [
            'RENDIMIENTO_LABORAL' => [
                'peso_compromisos' => 80.0,
                'peso_competencias' => 20.0,
                'peso_docencia' => 0.0,
                'peso_investigacion' => 0.0,
                'peso_proyeccion_social' => 0.0,
            ],
            'ACUERDO_GESTION' => [
                'peso_compromisos' => 50.0,
                'peso_competencias' => 20.0,
                'peso_docencia' => 10.0,
                'peso_investigacion' => 10.0,
                'peso_proyeccion_social' => 10.0,
            ],
        ];
    }
}

if (!function_exists('getPonderacionesConfig')) {
    function getPonderacionesConfig() {
        $configData = defaultPonderacionesConfig();

        if (Schema::hasTable('ponderacion')) {
            foreach (DB::table('ponderacion')->get() as $row) {
                if (!isset($configData[$row->sistema])) {
                    continue;
                }

                $configData[$row->sistema] = array_merge($configData[$row->sistema], [
                    'peso_compromisos' => (float) $row->peso_compromisos,
                    'peso_competencias' => (float) $row->peso_competencias,
                    'peso_docencia' => (float) $row->peso_docencia,
                    'peso_investigacion' => (float) $row->peso_investigacion,
                    'peso_proyeccion_social' => (float) $row->peso_proyeccion_social,
                ]);
            }
        }

        $configData['RENDIMIENTO_LABORAL']['peso_docencia'] = 0.0;
        $configData['RENDIMIENTO_LABORAL']['peso_investigacion'] = 0.0;
        $configData['RENDIMIENTO_LABORAL']['peso_proyeccion_social'] = 0.0;

        return $configData;
    }
}


function getTargetCompromisosWeight($id_evaluacion) {
    $evaluacion = DB::table('evaluacion as ev')
        ->join('vinculacion as ve', 've.id_vinculacion', '=', 'ev.id_vinc_evaluado')
        ->join('periodo as p', 'p.id_periodo', '=', 'ev.id_periodo')
        ->where('ev.id_evaluacion', $id_evaluacion)
        ->select('ev.*', 've.aplica_eje_misional', 'p.sistema')
        ->first();

    if (!$evaluacion) return defaultPonderacionesConfig()['RENDIMIENTO_LABORAL']['peso_compromisos'];

    $ponderaciones = getPonderacionesConfig();
    $sistema = strtoupper(trim((string) $evaluacion->sistema));
    $configSistema = $ponderaciones[$sistema] ?? $ponderaciones['RENDIMIENTO_LABORAL'];
    $target = (float) $configSistema['peso_compromisos'];

    if ($sistema === 'ACUERDO_GESTION' && $evaluacion->aplica_eje_misional) {
        $ejes = getEvaluacionEjes($id_evaluacion);

        // Docencia es el eje base y siempre aplica; los ejes que NO apliquen
        // devuelven su porcentaje a compromisos.
        if (empty($ejes['investigacion'])) {
            $target += (float) ($configSistema['peso_investigacion'] ?? 0);
        }
        if (empty($ejes['proyeccion_social'])) {
            $target += (float) ($configSistema['peso_proyeccion_social'] ?? 0);
        }
    } elseif ($sistema === 'ACUERDO_GESTION' && !$evaluacion->aplica_eje_misional) {
        // El funcionario no tiene eje misional: docencia, investigación y
        // proyección social no aplican, todo su peso vuelve a compromisos.
        $target += (float) ($configSistema['peso_docencia'] ?? 0)
            + (float) ($configSistema['peso_investigacion'] ?? 0)
            + (float) ($configSistema['peso_proyeccion_social'] ?? 0);
    }

    return max(0.0, $target);
}

function resolveOpenPeriodForVinculacion(int $idVinculacion, ?int $idPeriodo = null, ?string $tipoEvaluacion = null) {
    $vinculacion = DB::table('vinculacion')->where('id_vinculacion', $idVinculacion)->first();
    if (! $vinculacion || ! $vinculacion->sistema_evaluacion) {
        return null;
    }
    $sistema = strtoupper(trim((string) $vinculacion->sistema_evaluacion));

    $query = DB::table('periodo as p')
        ->whereRaw('UPPER(TRIM(p.sistema)) = ?', [$sistema])
        ->where('p.estado', 'ABIERTO');

    if (in_array($tipoEvaluacion, ['SEMESTRE_1', 'SEMESTRE_2'], true)) {
        $targetSemestre = $tipoEvaluacion === 'SEMESTRE_1' ? 1 : 2;

        if (! empty($idPeriodo)) {
            $specificPeriod = (clone $query)->where('p.id_periodo', $idPeriodo)->where('p.semestre', $targetSemestre)->first();
            if ($specificPeriod) {
                return $specificPeriod;
            }
        }

        $query->where('p.semestre', $targetSemestre);
    } elseif (! empty($idPeriodo)) {
        $specificPeriod = (clone $query)->where('p.id_periodo', $idPeriodo)->first();
        if ($specificPeriod) {
            return $specificPeriod;
        }
    }

    return $query->select('p.*')->orderByDesc('p.id_periodo')->first();
}

function getEvaluadorAsignaciones(): array {
    if (!Schema::hasTable('evaluador_asignacion')) {
        return [];
    }

    return DB::table('evaluador_asignacion')
        ->orderBy('id_asignacion')
        ->get()
        ->map(fn ($a) => [
            'id_vinc_evaluador' => (int) $a->id_vinc_evaluador,
            'id_vinc_evaluado' => (int) $a->id_vinc_evaluado,
            'fecha_asignacion' => $a->fecha_asignacion,
        ])
        ->all();
}

function evaluadorTieneEvaluadoAsignado(int $idVincEvaluador, int $idVincEvaluado): bool {
    if (!Schema::hasTable('evaluador_asignacion')) {
        return false;
    }

    return DB::table('evaluador_asignacion')
        ->where('id_vinc_evaluador', $idVincEvaluador)
        ->where('id_vinc_evaluado', $idVincEvaluado)
        ->exists();
}

function guardarEvaluadorAsignacion(int $idVincEvaluador, int $idVincEvaluado): void {
    if (!Schema::hasTable('evaluador_asignacion')) {
        return;
    }

    DB::table('evaluador_asignacion')->insertOrIgnore([
        'id_vinc_evaluador' => $idVincEvaluador,
        'id_vinc_evaluado' => $idVincEvaluado,
        'fecha_asignacion' => now(),
    ]);
}

function getEvaluacionEjes(int $idEvaluacion): array {
    if (!Schema::hasTable('evaluacion_eje')) {
        return ['investigacion' => false, 'proyeccion_social' => false];
    }

    $row = DB::table('evaluacion_eje')->where('id_evaluacion', $idEvaluacion)->first();

    return [
        'investigacion' => (bool) ($row->investigacion ?? false),
        'proyeccion_social' => (bool) ($row->proyeccion_social ?? false),
    ];
}

function guardarEvaluacionEjes(int $idEvaluacion, bool $investigacion, bool $proyeccionSocial): void {
    if (!Schema::hasTable('evaluacion_eje')) {
        return;
    }

    DB::table('evaluacion_eje')->updateOrInsert(
        ['id_evaluacion' => $idEvaluacion],
        [
            'investigacion' => (int) $investigacion,
            'proyeccion_social' => (int) $proyeccionSocial,
        ]
    );
}

function getEvaluacionObservaciones(int $idEvaluacion): array {
    if (!Schema::hasTable('compromiso_observacion')) {
        return [];
    }

    return DB::table('compromiso_observacion')
        ->where('id_evaluacion', $idEvaluacion)
        ->orderBy('id_compromiso')
        ->get()
        ->map(fn ($observacion) => [
            'id_observacion' => $observacion->id_observacion,
            'id_evaluacion' => $observacion->id_evaluacion,
            'id_compromiso' => $observacion->id_compromiso,
            'id_vinc_evaluador' => $observacion->id_vinc_evaluador,
            'texto' => $observacion->texto,
            'autor' => 'evaluador',
            'confirmada' => (bool) $observacion->confirmada,
            'fecha_inclusion' => $observacion->fecha_inclusion,
            'fecha_actualizacion' => $observacion->fecha_actualizacion,
            'fecha_confirmacion' => $observacion->fecha_confirmacion,
        ])
        ->toArray();
}

function guardarEvaluacionObservacion(int $idEvaluacion, int $idCompromiso, int $idVincEvaluador, string $texto, bool $confirmar = false): array {
    abort_unless(Schema::hasTable('compromiso_observacion'), 500, 'Falta ejecutar la migración de observaciones.');

    $actual = DB::table('compromiso_observacion')
        ->where('id_evaluacion', $idEvaluacion)
        ->where('id_compromiso', $idCompromiso)
        ->first();

    if ($actual && (bool) $actual->confirmada) {
        abort(403, 'La observación de este compromiso ya fue confirmada y no se puede modificar.');
    }

    $now = date('Y-m-d H:i:s');
    $values = [
        'id_compromiso' => $idCompromiso,
        'id_evaluacion' => $idEvaluacion,
        'id_vinc_evaluador' => $idVincEvaluador,
        'texto' => trim($texto),
        'confirmada' => $confirmar,
        'fecha_actualizacion' => $now,
        'fecha_confirmacion' => $confirmar ? $now : null,
    ];

    if ($actual) {
        DB::table('compromiso_observacion')
            ->where('id_observacion', $actual->id_observacion)
            ->update($values);
    } else {
        DB::table('compromiso_observacion')->insert(array_merge($values, [
            'fecha_inclusion' => $now,
        ]));
    }

    return (array) DB::table('compromiso_observacion')
        ->where('id_evaluacion', $idEvaluacion)
        ->where('id_compromiso', $idCompromiso)
        ->first();
}

Route::get('/', function () {
    return view('login');
});

Route::post('/login', function (Request $request) {
    $credentials = $request->validate([
        'correo' => ['required', 'email'],
        'password' => ['required', 'string'],
    ]);

    $correo = strtolower(trim($credentials['correo']));

    $user = DB::table('usuario as u')
        ->where(DB::raw('LOWER(TRIM(u.username))'), $correo)
        ->where('u.activo', 1)
        ->first();

    if (! $user) {
        $user = DB::table('usuario as u')
            ->where(function ($q) use ($correo) {
                $q->where(DB::raw('LOWER(TRIM(u.username))'), $correo . '@unitropico.edu.co')
                  ->orWhere(DB::raw('LOWER(TRIM(u.username))'), 'LIKE', $correo . '@%');
            })
            ->where('u.activo', 1)
            ->first();
    }

    if (! $user) {
        return back()->withErrors(['login' => 'Correo institucional o contraseña incorrectos.'])->onlyInput('correo');
    }

    $storedPassword = (string) $user->password;
    $passwordValid = str_starts_with($storedPassword, '$2y$') || str_starts_with($storedPassword, '$argon2')
        ? Hash::check($credentials['password'], $storedPassword)
        : hash_equals($storedPassword, $credentials['password']);

    if (! $passwordValid) {
        return back()->withErrors(['login' => 'Correo institucional o contraseña incorrectos.'])->onlyInput('correo');
    }

    $funcionario = DB::table('funcionario')
        ->where('id_usuario', $user->id_usuario)
        ->first();

    $request->session()->regenerate();

    $roles = [];

    if ($user->rol === 'ADMINISTRADOR') {
        $roles[] = 'admin';
    }

    if ($user->rol === 'INSTANCIA_EXTERNA') {
        $roles[] = 'instancia_externa';
    }

    if ($funcionario) {
        $vinculaciones = DB::table('vinculacion')
            ->where('id_funcionario', $funcionario->id_funcionario)
            ->where('activa', 1)
            ->get();

        $tieneVinculacionActiva = $vinculaciones->isNotEmpty();
        $esEvaluadorActivo = $vinculaciones->contains('es_evaluador', 1);

        if ($tieneVinculacionActiva) {
            $roles[] = 'evaluado';
        }

        if ($esEvaluadorActivo || $user->rol === 'EVALUADOR') {
            $roles[] = 'evaluador';
        }
    }

    $roles = array_values(array_unique($roles));

    if (empty($roles)) {
        $roles[] = 'evaluado';
    }

    $request->session()->put('usuario_autenticado', [
        'id_usuario' => $user->id_usuario,
        'correo' => $user->username,
        'id_funcionario' => $funcionario->id_funcionario ?? null,
        'nombres' => $funcionario->nombres ?? 'Usuario',
        'apellidos' => $funcionario->apellidos ?? 'Admin',
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
        'rol' => ['required', 'in:evaluado,evaluador,admin,instancia_externa'],
    ]);

    $roles = session('usuario_autenticado.roles', []);
    abort_unless(in_array($data['rol'], $roles, true), 403);

    $request->session()->put('usuario_autenticado.rol_activo', $data['rol']);
    return redirect('/dashboard');
})->name('role.select');

use App\Http\Controllers\DashboardController;

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

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

    $storedPassword = (string) $user->password;
    $currentValid = str_starts_with($storedPassword, '$2y$') || str_starts_with($storedPassword, '$argon2')
        ? Hash::check($data['current_password'], $storedPassword)
        : hash_equals($storedPassword, $data['current_password']);

    if (! $currentValid) {
        return back()->withErrors(['current_password' => 'La Contraseña actual no coincide.']);
    }

    DB::table('usuario')
        ->where('id_usuario', $auth['id_usuario'])
        ->update(['password' => Hash::make($data['password'])]);

    return back()->with('password_updated', true);
})->name('password.update');

Route::post('/usuarios/{id_usuario}/reset-contrasena', function (Request $request, int $id_usuario) {
    abort_unless(session()->has('usuario_autenticado'), 403);

    $tempPassword = substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(10))), 0, 10);

    $updated = DB::table('usuario')
        ->where('id_usuario', $id_usuario)
        ->update(['password' => Hash::make($tempPassword)]);

    abort_unless($updated, 404);

    return back()->with([
        'temp_password' => $tempPassword,
        'temp_password_user' => $id_usuario,
    ]);
})->name('usuarios.reset-password');

Route::post('/evaluador/asignaciones', function (Request $request) {
    abort_unless(session('usuario_autenticado.rol_activo') === 'evaluador', 403);

    $data = $request->validate([
        'id_periodo' => ['nullable', 'integer', 'exists:periodo,id_periodo'],
        'id_vinc_evaluado' => ['required', 'integer', 'exists:vinculacion,id_vinculacion'],
        'tipo_evaluacion' => ['required', 'in:SEMESTRE_1,SEMESTRE_2,PARCIAL'],
        'dias_laborados' => ['nullable', 'integer', 'min:1'],
        'referencia' => ['nullable', 'string', 'max:200'],
        'investigacion' => ['nullable', 'boolean'],
        'proyeccion_social' => ['nullable', 'boolean'],
    ]);

    $auth = session('usuario_autenticado');
    $miVinc = DB::table('vinculacion')
        ->where('id_funcionario', $auth['id_funcionario'])
        ->where('activa', 1)
        ->where('es_evaluador', 1)
        ->orderByDesc('id_vinculacion')
        ->first();

    if (! $miVinc) {
        return back()->withErrors(['asignaciones' => 'No tienes una vinculación activa como evaluador.']);
    }

    $evaluadoVinc = DB::table('vinculacion')
        ->where('id_vinculacion', $data['id_vinc_evaluado'])
        ->where('activa', 1)
        ->first();

    if (! $evaluadoVinc) {
        return back()->withErrors(['asignaciones' => 'El funcionario a evaluar no cuenta con una vinculación activa.']);
    }

    if (! evaluadorTieneEvaluadoAsignado($miVinc->id_vinculacion, (int) $data['id_vinc_evaluado'])) {
        return back()->withErrors(['asignaciones' => 'Este funcionario no está asignado a tu perfil de evaluador.']);
    }

    $periodo = resolveOpenPeriodForVinculacion((int) $data['id_vinc_evaluado'], $data['id_periodo'] ?? null, $data['tipo_evaluacion']);

    if (! $periodo) {
        return back()->withErrors(['asignaciones' => 'No hay un período abierto correspondiente al sistema de evaluación y ciclo seleccionado. Contacte al administrador.']);
    }

    if (strtoupper(trim((string) $periodo->sistema)) !== strtoupper(trim((string) $evaluadoVinc->sistema_evaluacion))) {
        return back()->withErrors(['asignaciones' => 'El sistema de evaluación del período abierto no coincide con el del funcionario.']);
    }

    $exists = DB::table('evaluacion')
        ->where('id_periodo', $periodo->id_periodo)
        ->where('id_vinc_evaluado', $data['id_vinc_evaluado'])
        ->where('tipo_evaluacion', $data['tipo_evaluacion'])
        ->exists();

    if ($exists) {
        return back()->withErrors(['asignaciones' => 'Ya existe una evaluacion para este funcionario en este perodo y ciclo.']);
    }

    // S6 — Bloqueo del flujo del evaluador: no se abre un nuevo ciclo si el
    // evaluado tiene un plan de mejoramiento pendiente por concertar y firmar.
    if (evaluadoTienePlanMejoramientoPendiente((int) $data['id_vinc_evaluado'], (int) $periodo->id_periodo)) {
        return back()->withErrors(['asignaciones' => 'El evaluado tiene un plan de mejoramiento pendiente por concertar y firmar de una evaluación anterior. Debes resolverlo antes de crear una nueva evaluación.']);
    }

    $referenciaEvaluacion = trim($data['referencia'] ?? '') ?: null;
    $diasLaborados = $data['dias_laborados'] ?? null;

    if ($data['tipo_evaluacion'] === 'PARCIAL') {
        $periodoParcial = DB::table('periodo_parcial')
            ->where('id_periodo', $periodo->id_periodo)
            ->where('id_vinc_funcionario', $data['id_vinc_evaluado'])
            ->where('estado', 'ABIERTO')
            ->first();

        if (! $periodoParcial) {
            return back()->withErrors(['asignaciones' => 'El funcionario no tiene un periodo parcial abierto. Debe crearlo el administrador en la sección de periodos.']);
        }

        if (! $referenciaEvaluacion) {
            $referenciaEvaluacion = $periodoParcial->referencia;
        }
        if ($diasLaborados === null && $periodoParcial->fecha_inicio && $periodoParcial->fecha_fin) {
            $diasLaborados = max(1, \Carbon\Carbon::parse($periodoParcial->fecha_inicio)->diffInDays(\Carbon\Carbon::parse($periodoParcial->fecha_fin)) + 1);
        }
    }

    $evaluacionId = DB::table('evaluacion')->insertGetId([
        'id_periodo' => $periodo->id_periodo,
        'id_vinc_evaluado' => $data['id_vinc_evaluado'],
        'id_vinc_evaluador' => $miVinc->id_vinculacion,
        'tipo_evaluacion' => $data['tipo_evaluacion'],
        'fase_actual' => 1,
        'concertacion_firmada' => 0,
        'estado' => 'EN_PROCESO',
        'dias_laborados' => $diasLaborados,
        'referencia' => $referenciaEvaluacion,
    ]);

    if (strtoupper(trim((string) $periodo->sistema)) === 'ACUERDO_GESTION' && $evaluadoVinc->aplica_eje_misional) {
        guardarEvaluacionEjes((int) $evaluacionId, (bool) ($data['investigacion'] ?? false), (bool) ($data['proyeccion_social'] ?? false));
    }

    return back()->with('success_asignacion', 'evaluacion creada para iniciar la concertacin.');
})->name('evaluador.asignaciones.store');


// --- ADMINISTRACIN DE PERIODOS ---
Route::post('/admin/periodos', function (Request $request) {
    abort_unless(session('usuario_autenticado.rol_activo') === 'admin', 403);

    $data = $request->validate([
        'sistema' => ['required', 'in:RENDIMIENTO_LABORAL,ACUERDO_GESTION'],
        'anio' => ['required', 'integer', 'min:2020', 'max:2100'],
        'semestre' => ['required', 'integer', 'in:1,2'],
        'fecha_inicio' => ['required', 'date'],
        'fecha_fin' => ['required', 'date', 'after:fecha_inicio'],
    ]);

    $exists = DB::table('periodo')
        ->where('sistema', $data['sistema'])
        ->where('anio', $data['anio'])
        ->where('semestre', $data['semestre'])
        ->exists();

    if ($exists) {
        return back()->withErrors(['periodo' => 'Este perodo ya existe registrado.']);
    }

    DB::table('periodo')->insert([
        'id_usuario_apertura' => session('usuario_autenticado.id_usuario'),
        'sistema' => $data['sistema'],
        'anio' => $data['anio'],
        'semestre' => $data['semestre'],
        'fecha_inicio' => $data['fecha_inicio'],
        'fecha_fin' => $data['fecha_fin'],
        'estado' => 'ABIERTO',
    ]);

    return back()->with('success_periodo', 'Perodo creado exitosamente.');
})->name('admin.periodos.store');

Route::post('/admin/periodos/{id}/toggle', function (int $id) {
    abort_unless(session('usuario_autenticado.rol_activo') === 'admin', 403);

    $periodo = DB::table('periodo')->where('id_periodo', $id)->first();
    abort_unless($periodo, 404);

    $nuevoEstado = $periodo->estado === 'ABIERTO' ? 'CERRADO' : 'ABIERTO';

    DB::table('periodo')
        ->where('id_periodo', $id)
        ->update(['estado' => $nuevoEstado]);

    return back()->with('success_periodo', 'Estado de perodo actualizado.');
})->name('admin.periodos.toggle');

// --- PERIODOS PARCIALES ---
// Crea un periodo PARCIAL para un funcionario que no estuvo desde el inicio
// del semestre (ingreso a mitad de periodo o traslado). Su evaluador podrá
// abrir una evaluación PARCIAL solo si existe un periodo parcial abierto.
Route::post('/admin/periodos-parciales', function (Request $request) {
    abort_unless(session('usuario_autenticado.rol_activo') === 'admin', 403);

    $data = $request->validate([
        'id_periodo' => ['required', 'integer', 'exists:periodo,id_periodo'],
        'id_vinc_funcionario' => ['required', 'integer', 'exists:vinculacion,id_vinculacion'],
        'fecha_inicio' => ['required', 'date'],
        'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
        'referencia' => ['nullable', 'string', 'max:200'],
    ]);

    $periodo = DB::table('periodo')->where('id_periodo', $data['id_periodo'])->first();
    abort_unless($periodo, 404);

    $funcionario = DB::table('vinculacion')
        ->where('id_vinculacion', $data['id_vinc_funcionario'])
        ->where('activa', 1)
        ->first();
    abort_unless($funcionario, 403);

    if (strtoupper(trim((string) $funcionario->sistema_evaluacion)) !== strtoupper(trim((string) $periodo->sistema))) {
        return back()->withErrors(['periodo_parcial' => 'El sistema de evaluación del funcionario no coincide con el del periodo base seleccionado.']);
    }

    $existe = DB::table('periodo_parcial')
        ->where('id_periodo', $data['id_periodo'])
        ->where('id_vinc_funcionario', $data['id_vinc_funcionario'])
        ->where('estado', 'ABIERTO')
        ->exists();

    if ($existe) {
        return back()->withErrors(['periodo_parcial' => 'El funcionario ya tiene un periodo parcial abierto en este semestre.']);
    }

    DB::table('periodo_parcial')->insert([
        'id_periodo' => (int) $data['id_periodo'],
        'id_vinc_funcionario' => (int) $data['id_vinc_funcionario'],
        'fecha_inicio' => $data['fecha_inicio'],
        'fecha_fin' => $data['fecha_fin'],
        'referencia' => trim($data['referencia'] ?? '') ?: null,
        'estado' => 'ABIERTO',
        'id_usuario_apertura' => session('usuario_autenticado.id_usuario') ?? null,
    ]);

    return back()->with('success_periodo', 'Periodo parcial creado exitosamente.');
})->name('admin.periodos-parciales.store');

Route::post('/admin/periodos-parciales/{id}/toggle', function (int $id) {
    abort_unless(session('usuario_autenticado.rol_activo') === 'admin', 403);

    $pp = DB::table('periodo_parcial')->where('id_periodo_parcial', $id)->first();
    abort_unless($pp, 404);

    $nuevoEstado = $pp->estado === 'ABIERTO' ? 'CERRADO' : 'ABIERTO';

    DB::table('periodo_parcial')->where('id_periodo_parcial', $id)->update(['estado' => $nuevoEstado]);

    return back()->with('success_periodo', 'Estado del periodo parcial actualizado.');
})->name('admin.periodos-parciales.toggle');


// --- PONDERACIONES DE SISTEMAS ---
Route::post('/admin/ponderaciones', function (Request $request) {
    abort_unless(session('usuario_autenticado.rol_activo') === 'admin', 403);

    $data = $request->validate([
        'sistema' => ['required', 'in:RENDIMIENTO_LABORAL,ACUERDO_GESTION'],
        'peso_compromisos' => ['required', 'numeric', 'min:0', 'max:100'],
        'peso_competencias' => ['required', 'numeric', 'min:0', 'max:100'],
        'peso_docencia' => ['nullable', 'numeric', 'min:0', 'max:100'],
        'peso_investigacion' => ['nullable', 'numeric', 'min:0', 'max:100'],
        'peso_proyeccion_social' => ['nullable', 'numeric', 'min:0', 'max:100'],
    ]);

    if ($data['sistema'] === 'RENDIMIENTO_LABORAL') {
        $data['peso_docencia'] = 0;
        $data['peso_investigacion'] = 0;
        $data['peso_proyeccion_social'] = 0;
    } else {
        foreach (['peso_docencia', 'peso_investigacion', 'peso_proyeccion_social'] as $campo) {
            if (!isset($data[$campo])) {
                return back()->withErrors(['ponderaciones' => 'Los pesos de Docencia, Horas de Investigación y Proyección Social son obligatorios para Acuerdos de Gestión.']);
            }
        }
    }

    $sum = $data['peso_compromisos'] + $data['peso_competencias'] + $data['peso_docencia'] + $data['peso_investigacion'] + $data['peso_proyeccion_social'];
    if (abs($sum - 100.0) > 0.01) {
        return back()->withErrors(['ponderaciones' => 'La suma de las ponderaciones debe ser exactamente 100%.']);
    }

    DB::table('ponderacion')->updateOrInsert(
        ['sistema' => $data['sistema']],
        [
            'peso_compromisos' => (float) $data['peso_compromisos'],
            'peso_competencias' => (float) $data['peso_competencias'],
            'peso_docencia' => (float) $data['peso_docencia'],
            'peso_investigacion' => (float) $data['peso_investigacion'],
            'peso_proyeccion_social' => (float) $data['peso_proyeccion_social'],
        ]
    );

    return back()->with('success_ponderacion', 'Ponderaciones actualizadas correctamente.');
})->name('admin.ponderaciones.update');


// --- ASIGNACIN DE evaluacionES ---
Route::post('/admin/asignaciones', function (Request $request) {
    abort_unless(session('usuario_autenticado.rol_activo') === 'admin', 403);

    $data = $request->validate([
        'id_vinc_evaluado' => ['required', 'array', 'min:1'],
        'id_vinc_evaluado.*' => ['required', 'integer', 'exists:vinculacion,id_vinculacion'],
        'id_vinc_evaluador' => ['required', 'integer', 'exists:vinculacion,id_vinculacion'],
    ]);

    $idsEvaluados = array_values(array_unique(array_map('intval', $data['id_vinc_evaluado'])));

    $evaluador = DB::table('vinculacion')
        ->where('id_vinculacion', $data['id_vinc_evaluador'])
        ->where('activa', 1)
        ->where('es_evaluador', 1)
        ->first();

    abort_unless($evaluador, 403);

    if (in_array((int) $data['id_vinc_evaluador'], $idsEvaluados, true)) {
        return back()->withErrors(['asignaciones' => 'El evaluador no puede asignarse a sí mismo como evaluado.']);
    }

    $evaluados = DB::table('vinculacion')
        ->whereIn('id_vinculacion', $idsEvaluados)
        ->where('activa', 1)
        ->get();

    abort_unless($evaluados->count() === count($idsEvaluados), 403);

    $contador = 0;
    foreach ($evaluados as $evaluado) {
        guardarEvaluadorAsignacion((int) $data['id_vinc_evaluador'], (int) $evaluado->id_vinculacion);
        $contador++;
    }

    $sufijo = $contador === 1 ? '' : 's';
    return back()->with('success_asignacion', $contador . ' evaluado' . $sufijo . ' asignado' . $sufijo . ' al evaluador correctamente.');
})->name('admin.asignaciones.store');

// --- S6: GESTIÓN DE TRASLADOS ---
// Registra el traslado de un funcionario: cambia de evaluador, traslada al
// nuevo evaluador la evaluación vigente sin concertar y genera una evaluación
// PARCIAL prorrateada por los días laborados en la dependencia origen (RF3).
Route::post('/admin/traslados', function (Request $request) {
    abort_unless(session('usuario_autenticado.rol_activo') === 'admin', 403);

    $data = $request->validate([
        'id_vinc_funcionario' => ['required', 'integer', 'exists:vinculacion,id_vinculacion'],
        'id_vinc_evaluador_nuevo' => ['required', 'integer', 'exists:vinculacion,id_vinculacion', 'different:id_vinc_funcionario'],
        'fecha_traslado' => ['required', 'date'],
        'area_nuevo' => ['nullable', 'string', 'max:250'],
        'cargo_nuevo' => ['nullable', 'string', 'max:200'],
        'resolucion' => ['nullable', 'string', 'max:200'],
        'motivo' => ['nullable', 'string', 'max:500'],
        'referencia' => ['nullable', 'string', 'max:200'],
    ]);

    $evaluado = DB::table('vinculacion')
        ->where('id_vinculacion', $data['id_vinc_funcionario'])
        ->where('activa', 1)
        ->first();

    abort_unless($evaluado, 403);

    $areaOrigen = $evaluado->area;
    $cargoOrigen = $evaluado->cargo;

    $evaluadorNuevo = DB::table('vinculacion')
        ->where('id_vinculacion', $data['id_vinc_evaluador_nuevo'])
        ->where('activa', 1)
        ->where('es_evaluador', 1)
        ->first();

    abort_unless($evaluadorNuevo, 403);

    // Evaluador origen: el asignado actualmente al funcionario
    $evaluadorOrigenId = null;
    foreach (getEvaluadorAsignaciones() as $asig) {
        if ((int) $asig['id_vinc_evaluado'] === (int) $data['id_vinc_funcionario']) {
            $evaluadorOrigenId = (int) $asig['id_vinc_evaluador'];
            break;
        }
    }

    if (! $evaluadorOrigenId) {
        return back()->withErrors(['traslados' => 'El funcionario no tiene un evaluador asignado. Asigna un evaluador antes de registrar el traslado.']);
    }

    if ($evaluadorOrigenId === (int) $data['id_vinc_evaluador_nuevo']) {
        return back()->withErrors(['traslados' => 'El evaluador nuevo no puede ser el mismo que el evaluador actual del funcionario.']);
    }

    // Evaluación PARCIAL prorrateada por los días trabajados en la dependencia origen
    $idEvaluacionParcial = null;
    $diasLaborados = null;
    $periodo = DB::table('evaluacion as ev')
        ->join('periodo as p', 'p.id_periodo', '=', 'ev.id_periodo')
        ->where('ev.id_vinc_evaluado', $data['id_vinc_funcionario'])
        ->whereIn('ev.tipo_evaluacion', ['SEMESTRE_1', 'SEMESTRE_2'])
        ->where('p.estado', 'ABIERTO')
        ->orderByDesc('p.id_periodo')
        ->select('p.*')
        ->first();

    if (! $periodo) {
        $periodo = resolveOpenPeriodForVinculacion((int) $data['id_vinc_funcionario']);
    }

    if ($periodo) {
        $fechaInicio = new \DateTime($periodo->fecha_inicio);
        $fechaFin = new \DateTime($periodo->fecha_fin);
        $diasPeriodo = $fechaInicio->diff($fechaFin)->days + 1;
        $fechaTraslado = new \DateTime($data['fecha_traslado']);

        if ($fechaTraslado < $fechaInicio) {
            return back()->withErrors(['traslados' => 'La fecha del traslado no puede ser anterior al inicio del periodo vigente (' . $periodo->fecha_inicio . ').']);
        }

        if ($fechaTraslado > $fechaFin) {
            $fechaTraslado = $fechaFin;
        }

        $diasLaborados = max(1, min($fechaInicio->diff($fechaTraslado)->days + 1, $diasPeriodo));

        // La evaluación vigente del ciclo (SEMESTRE) sin concertar pasa al nuevo evaluador
        DB::table('evaluacion')
            ->where('id_periodo', $periodo->id_periodo)
            ->where('id_vinc_evaluado', $data['id_vinc_funcionario'])
            ->whereIn('tipo_evaluacion', ['SEMESTRE_1', 'SEMESTRE_2'])
            ->where('concertacion_firmada', 0)
            ->where('id_vinc_evaluador', $evaluadorOrigenId)
            ->update(['id_vinc_evaluador' => $data['id_vinc_evaluador_nuevo']]);

        // La evaluación SEMESTRE ya concertada y firmada queda bloqueada por
        // traslado: se etiqueta y solo se puede consultar.
        DB::table('evaluacion')
            ->where('id_periodo', $periodo->id_periodo)
            ->where('id_vinc_evaluado', $data['id_vinc_funcionario'])
            ->whereIn('tipo_evaluacion', ['SEMESTRE_1', 'SEMESTRE_2'])
            ->where('concertacion_firmada', 1)
            ->update(['es_traslado' => 1]);

        if ($diasLaborados < $diasPeriodo) {
            $existeParcial = DB::table('evaluacion')
                ->where('id_periodo', $periodo->id_periodo)
                ->where('id_vinc_evaluado', $data['id_vinc_funcionario'])
                ->where('tipo_evaluacion', 'PARCIAL')
                ->exists();

            if (! $existeParcial) {
                $idEvaluacionParcial = DB::table('evaluacion')->insertGetId([
                    'id_periodo' => $periodo->id_periodo,
                    'id_vinc_evaluado' => $data['id_vinc_funcionario'],
                    'id_vinc_evaluador' => $evaluadorOrigenId,
                    'tipo_evaluacion' => 'PARCIAL',
                    'fase_actual' => 1,
                    'concertacion_firmada' => 0,
                    'estado' => 'EN_PROCESO',
                    'es_parcial' => 1,
                    'dias_laborados' => $diasLaborados,
                    'referencia' => trim($data['referencia'] ?? '') ?: null,
                ]);
            }
        }
    }

    // Reasignar el funcionario al nuevo evaluador
    DB::table('evaluador_asignacion')
        ->where('id_vinc_evaluado', $data['id_vinc_funcionario'])
        ->delete();

    DB::table('evaluador_asignacion')->insert([
        'id_vinc_evaluador' => (int) $data['id_vinc_evaluador_nuevo'],
        'id_vinc_evaluado' => (int) $data['id_vinc_funcionario'],
        'fecha_asignacion' => now(),
    ]);

    // Actualizar dependencia/cargo en la vinculación activa
    $vinculacionUpdate = [];
    if (! empty($data['area_nuevo'])) {
        $vinculacionUpdate['area'] = trim($data['area_nuevo']);
    }
    if (! empty($data['cargo_nuevo'])) {
        $vinculacionUpdate['cargo'] = trim($data['cargo_nuevo']);
    }
    if ($vinculacionUpdate) {
        DB::table('vinculacion')->where('id_vinculacion', $data['id_vinc_funcionario'])->update($vinculacionUpdate);
    }

    DB::table('traslado')->insert([
        'id_vinc_funcionario' => (int) $data['id_vinc_funcionario'],
        'id_vinc_evaluador_origen' => $evaluadorOrigenId,
        'id_vinc_evaluador_nuevo' => (int) $data['id_vinc_evaluador_nuevo'],
        'area_origen' => $areaOrigen,
        'cargo_origen' => $cargoOrigen,
        'area_nuevo' => $data['area_nuevo'] ?? null,
        'cargo_nuevo' => $data['cargo_nuevo'] ?? null,
        'fecha_traslado' => $data['fecha_traslado'],
        'dias_laborados' => $diasLaborados,
        'id_evaluacion_parcial' => $idEvaluacionParcial,
        'resolucion' => $data['resolucion'] ?? null,
        'motivo' => $data['motivo'] ?? null,
        'id_usuario_registra' => session('usuario_autenticado.id_usuario') ?? null,
    ]);

    return back()->with('success_traslado', 'Traslado registrado correctamente.');
})->name('admin.traslados.store');

// --- GET: Histórico de traslados (admin) ---
Route::get('/admin/traslados', function () {
    abort_unless(session('usuario_autenticado.rol_activo') === 'admin', 403);

    return DB::table('traslado as t')
        ->join('vinculacion as vf', 'vf.id_vinculacion', '=', 't.id_vinc_funcionario')
        ->join('funcionario as ff', 'ff.id_funcionario', '=', 'vf.id_funcionario')
        ->leftJoin('vinculacion as veo', 'veo.id_vinculacion', '=', 't.id_vinc_evaluador_origen')
        ->leftJoin('funcionario as feo', 'feo.id_funcionario', '=', 'veo.id_funcionario')
        ->leftJoin('vinculacion as ven', 'ven.id_vinculacion', '=', 't.id_vinc_evaluador_nuevo')
        ->leftJoin('funcionario as fen', 'fen.id_funcionario', '=', 'ven.id_funcionario')
        ->leftJoin('evaluacion as evp', 'evp.id_evaluacion', '=', 't.id_evaluacion_parcial')
        ->select('t.*', 'evp.referencia', 'ff.nombres as funcionario_nombres', 'ff.apellidos as funcionario_apellidos', 'feo.nombres as origen_nombres', 'feo.apellidos as origen_apellidos', 'fen.nombres as nuevo_nombres', 'fen.apellidos as nuevo_apellidos')
        ->orderByDesc('t.id_traslado')
        ->get();
})->name('admin.traslados.index');

// --- GET: Evaluador actual de un funcionario (admin) ---
Route::get('/admin/traslados/evaluador-actual/{id_vinc_evaluado}', function (int $idVincEvaluado) {
    abort_unless(session('usuario_autenticado.rol_activo') === 'admin', 403);

    foreach (getEvaluadorAsignaciones() as $asig) {
        if ((int) $asig['id_vinc_evaluado'] === $idVincEvaluado) {
            return DB::table('vinculacion as v')
                ->leftJoin('funcionario as f', 'f.id_funcionario', '=', 'v.id_funcionario')
                ->where('v.id_vinculacion', $asig['id_vinc_evaluador'])
                ->select('v.id_vinculacion', 'v.cargo', 'v.area', 'f.nombres', 'f.apellidos')
                ->first();
        }
    }

    return response()->json(null);
})->name('admin.traslados.evaluador-actual');

// --- IMPORTACIN MASIVA DE USUARIOS (EXCEL/CSV) ---
Route::post('/admin/importar-usuarios', function (Request $request) {
    abort_unless(session('usuario_autenticado.rol_activo') === 'admin', 403);

    $request->validate([
        'archivo' => ['required', 'file'],
    ]);

    $file = $request->file('archivo');
    $path = $file->getRealPath();

    try {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new Exception("No se pudo abrir el archivo.");
        }

        $header = fgetcsv($handle, 1000, ";");
        if (!$header) {
            $header = fgetcsv($handle, 1000, ",");
        }

        $header = array_map(function($h) {
            return trim(strtolower(str_replace([' ', "\xEF\xBB\xBF"], '', $h)));
        }, $header);

        $imported = 0;

        while (($row = fgetcsv($handle, 1000, ";")) !== false || ($row = fgetcsv($handle, 1000, ",")) !== false) {
            if (empty($row) || count($row) < 3) continue;

            $data = array_combine(array_slice($header, 0, count($row)), $row);

            $documento = trim($data['documento'] ?? $data['cedula'] ?? '');
            $nombres = trim($data['nombres'] ?? '');
            $apellidos = trim($data['apellidos'] ?? '');
            $correoRaw = trim($data['correo'] ?? $data['correo_institucional'] ?? '');
            $correoSplitted = preg_split('/[\s,;\n\r]+/', $correoRaw);
            $correo = strtolower(trim($correoSplitted[0] ?? ''));
            $cargo = trim($data['cargo'] ?? 'Profesional');
            $nivel = trim(strtoupper($data['nivel'] ?? 'PROFESIONAL'));
            $area = trim($data['area'] ?? 'Sistemas');
            $tipoVinculacion = trim(strtoupper($data['tipo_vinculacion'] ?? 'PROVISIONALIDAD'));
            $sistema = trim(strtoupper($data['sistema_evaluacion'] ?? 'RENDIMIENTO_LABORAL'));
            $esEvaluador = filter_var($data['es_evaluador'] ?? false, FILTER_VALIDATE_BOOLEAN) || strtolower($data['es_evaluador'] ?? '') === 'si' ? 1 : 0;
            $aplicaEje = filter_var($data['aplica_eje'] ?? false, FILTER_VALIDATE_BOOLEAN) || strtolower($data['aplica_eje'] ?? '') === 'si' ? 1 : 0;

            if (empty($documento) || empty($nombres) || empty($correo)) continue;

            $userId = DB::table('usuario')->where('username', $correo)->value('id_usuario');
            if (!$userId) {
                $userId = DB::table('usuario')->insertGetId([
                    'username' => $correo,
                    'password' => Hash::make('123456789'),
                    'rol' => $esEvaluador ? 'EVALUADOR' : 'EVALUADO',
                    'activo' => 1,
                ]);
            }

            $funcId = DB::table('funcionario')->where('numero_doc', $documento)->value('id_funcionario');
            if (!$funcId) {
                $funcId = DB::table('funcionario')->insertGetId([
                    'id_usuario' => $userId,
                    'tipo_documento' => 'CEDULA_CIUDADANIA',
                    'numero_doc' => $documento,
                    'nombres' => $nombres,
                    'apellidos' => $apellidos,
                    'correo_cargo' => $correo,
                ]);
            } else {
                DB::table('funcionario')->where('id_funcionario', $funcId)->update([
                    'id_usuario' => $userId,
                    'nombres' => $nombres,
                    'apellidos' => $apellidos,
                    'correo_cargo' => $correo,
                ]);
            }

            DB::table('vinculacion')->insert([
                'id_funcionario' => $funcId,
                'cargo' => $cargo,
                'codigo_cargo' => 101,
                'grado_cargo' => 1,
                'nivel_jerarquico' => in_array($nivel, ['DIRECTIVO','ASESOR','PROFESIONAL','TECNICO','ASISTENCIAL']) ? $nivel : 'PROFESIONAL',
                'area' => $area,
                'tipo_vinculacion' => in_array($tipoVinculacion, ['PROVISIONALIDAD','LNR','PERIODO_FIJO','INDEFINIDO']) ? $tipoVinculacion : 'PROVISIONALIDAD',
                'sistema_evaluacion' => in_array($sistema, ['RENDIMIENTO_LABORAL','ACUERDO_GESTION']) ? $sistema : 'RENDIMIENTO_LABORAL',
                'es_evaluador' => $esEvaluador,
                'aplica_eje_misional' => $aplicaEje,
                'fecha_ingreso' => date('Y-m-d'),
                'activa' => 1,
            ]);

            $imported++;
        }
        fclose($handle);

        return back()->with('success_import', "Se importaron $imported funcionarios y vinculaciones correctamente.");
    } catch (Exception $e) {
        return back()->withErrors(['importar' => 'Error al leer el archivo: ' . $e->getMessage()]);
    }
})->name('admin.importar.store');


// --- CONCERTACIÓN DE COMPROMISOS (S3) ---
Route::get('/evaluaciones/{id}/compromisos', function (int $id) {
    abort_unless(session()->has('usuario_autenticado'), 403);

    $evaluacion = DB::table('evaluacion')->where('id_evaluacion', $id)->first();
    abort_unless($evaluacion, 404);

    $auth = session('usuario_autenticado');
    $rolActivo = $auth['rol_activo'] ?? null;
    if ($rolActivo !== 'admin') {
        $puedeVer = DB::table('vinculacion')
            ->whereIn('id_vinculacion', [$evaluacion->id_vinc_evaluado, $evaluacion->id_vinc_evaluador])
            ->where('id_funcionario', $auth['id_funcionario'] ?? null)
            ->exists();

        abort_unless($puedeVer, 403);
    }

    $compromisos = DB::table('compromiso')
        ->where('id_evaluacion', $id)
        ->orderBy('numero_orden')
        ->get();

    foreach ($compromisos as $c) {
        $c->metas = DB::table('compromiso_meta')
            ->where('id_compromiso', $c->id_compromiso)
            ->pluck('meta')
            ->toArray();
    }

    $evidencias = DB::table('evidencia')
        ->where('id_evaluacion', $id)
        ->orderByDesc('fecha_inclusion')
        ->get();

    $evaluadoFirmado = DB::table('firma')
        ->where('id_evaluacion', $id)
        ->where('tipo_firma', 'CONCERTACION_EVALUADO')
        ->first();

    $evaluadorFirmado = DB::table('firma')
        ->where('id_evaluacion', $id)
        ->where('tipo_firma', 'CONCERTACION_EVALUADOR')
        ->first();

    $notificacionFirmada = DB::table('firma')
        ->where('id_evaluacion', $id)
        ->where('tipo_firma', 'NOTIFICACION_EVALUADO')
        ->first();

    $testigosNotificacion = [];
    if ($notificacionFirmada && $notificacionFirmada->renuencia) {
        $testigosNotificacion = DB::table('testigo_renuencia')
            ->where('id_firma', $notificacionFirmada->id_firma)
            ->select('nombre_testigo', 'cargo_testigo')
            ->get();
    }

    return response()->json([
        'compromisos' => $compromisos,
        'evidencias' => $evidencias,
        'observaciones' => getEvaluacionObservaciones($id),
        'estado_evaluacion' => $evaluacion->estado,
        'estado' => [
            'evaluado_firmado' => (bool) $evaluadoFirmado,
            'evaluador_firmado' => (bool) $evaluadorFirmado,
            'renuencia_evaluador' => false,
            'renuencia_evaluado' => false,
            'notificacion_firmada' => (bool) $notificacionFirmada,
            'renuencia_notificacion' => (bool) ($notificacionFirmada->renuencia ?? false),
            'fecha_notificacion' => $notificacionFirmada->fecha_firma ?? null,
            'testigos' => getTestigosConcertacion($id),
            'testigos_notificacion' => $testigosNotificacion,
            'congelada' => (bool) $evaluacion->concertacion_firmada,
            'traslado' => (bool) $evaluacion->es_traslado,
            'calificada' => $evaluacion->estado === 'CALIFICADA',
            'fase_actual' => $evaluacion->fase_actual,
        ],
    ]);
});

Route::get('/evaluaciones/{id}/ejes', function (int $id) {
    abort_unless(session()->has('usuario_autenticado'), 403);

    return response()->json(getEvaluacionEjes($id));
});

Route::post('/evaluaciones/{id}/ejes', function (Request $request, int $id) {
    abort_unless(session('usuario_autenticado.rol_activo') === 'evaluador', 403);

    $evaluacion = DB::table('evaluacion')->where('id_evaluacion', $id)->first();
    abort_unless($evaluacion, 404);

    $auth = session('usuario_autenticado');
    $puedeEditar = DB::table('vinculacion')
        ->where('id_vinculacion', $evaluacion->id_vinc_evaluador)
        ->where('id_funcionario', $auth['id_funcionario'] ?? null)
        ->exists();

    abort_unless($puedeEditar, 403);

    abort_if($evaluacion->es_traslado, 422, 'Esta evaluación quedó bloqueada por traslado y solo se puede consultar.');

    $data = $request->validate([
        'investigacion' => ['required', 'boolean'],
        'proyeccion_social' => ['required', 'boolean'],
    ]);

    guardarEvaluacionEjes((int) $id, (bool) $data['investigacion'], (bool) $data['proyeccion_social']);

    return response()->json(['success' => true]);
});

Route::get('/evaluaciones/{id}/observaciones', function (int $id) {
    abort_unless(session()->has('usuario_autenticado'), 403);

    $evaluacion = DB::table('evaluacion')->where('id_evaluacion', $id)->first();
    abort_unless($evaluacion, 404);

    $auth = session('usuario_autenticado');
    $rolActivo = $auth['rol_activo'] ?? null;

    if ($rolActivo !== 'admin') {
        $puedeVer = DB::table('vinculacion')
            ->whereIn('id_vinculacion', [$evaluacion->id_vinc_evaluado, $evaluacion->id_vinc_evaluador])
            ->where('id_funcionario', $auth['id_funcionario'] ?? null)
            ->exists();

        abort_unless($puedeVer, 403);
    }

    return response()->json([
        'observaciones' => getEvaluacionObservaciones($id),
    ]);
});

Route::post('/evaluaciones/{id}/observaciones', function (Request $request, int $id) {
    abort_unless(session('usuario_autenticado.rol_activo') === 'evaluador', 403);

    $evaluacion = DB::table('evaluacion')->where('id_evaluacion', $id)->first();
    abort_unless($evaluacion, 404);
    abort_if($evaluacion->es_traslado, 422, 'Esta evaluación quedó bloqueada por traslado y solo se puede consultar.');
    abort_unless($evaluacion->concertacion_firmada, 403, 'La concertación debe estar firmada por ambas partes antes de registrar observaciones.');

    $auth = session('usuario_autenticado');
    $puedeEditar = DB::table('vinculacion')
        ->where('id_vinculacion', $evaluacion->id_vinc_evaluador)
        ->where('id_funcionario', $auth['id_funcionario'] ?? null)
        ->exists();

    abort_unless($puedeEditar, 403);

    $data = $request->validate([
        'texto' => ['required', 'string', 'max:2000'],
        'id_compromiso' => ['required', 'integer', 'exists:compromiso,id_compromiso'],
        'confirmar' => ['nullable', 'boolean'],
    ]);

    $compromisoPertenece = DB::table('compromiso')
        ->where('id_compromiso', $data['id_compromiso'])
        ->where('id_evaluacion', $id)
        ->exists();

    abort_unless($compromisoPertenece, 422, 'El compromiso no pertenece a esta evaluación.');

    $observacion = guardarEvaluacionObservacion(
        $id,
        (int) $data['id_compromiso'],
        (int) $evaluacion->id_vinc_evaluador,
        $data['texto'],
        (bool) ($data['confirmar'] ?? false)
    );

    return response()->json(['success' => true, 'observacion' => $observacion]);
});

Route::post('/evaluaciones/{id}/evidencias', function (Request $request, int $id) {
    abort_unless(session('usuario_autenticado.rol_activo') === 'evaluado', 403);

    $evaluacion = DB::table('evaluacion')->where('id_evaluacion', $id)->first();
    abort_unless($evaluacion, 404);

    $auth = session('usuario_autenticado');
    $vinculacionRegistra = DB::table('vinculacion')
        ->where('id_vinculacion', $evaluacion->id_vinc_evaluado)
        ->where('id_funcionario', $auth['id_funcionario'] ?? null)
        ->where('activa', 1)
        ->first();

    abort_unless($vinculacionRegistra, 403);

    abort_if($evaluacion->es_traslado, 422, 'Esta evaluación quedó bloqueada por traslado y solo se puede consultar.');

    if (!$evaluacion->concertacion_firmada) {
        return response()->json(['message' => 'Debes esperar a que el evaluador y el evaluado firmen la concertación antes de registrar evidencias.'], 422);
    }

    if ($evaluacion->estado === 'CALIFICADA') {
        return response()->json(['message' => 'Esta evaluación ya fue calificada y calculada; no se pueden registrar más evidencias.'], 422);
    }

    $data = $request->validate([
        'componente' => ['nullable', 'in:B,C,D,F'],
        'id_compromiso' => ['required_if:componente,B', 'nullable', 'integer'],
        'descripcion' => ['nullable', 'string', 'max:500'],
        'url' => ['required', 'url', 'max:1000'],
    ]);

    $componente = $data['componente'] ?? 'B';

    $idCompromiso = null;
    if ($componente === 'B') {
        $compromiso = DB::table('compromiso')
            ->where('id_compromiso', $data['id_compromiso'])
            ->where('id_evaluacion', $id)
            ->first();

        abort_unless($compromiso, 422);
        $idCompromiso = $compromiso->id_compromiso;
    }

    DB::table('evidencia')->insert([
        'id_evaluacion' => $id,
        'id_compromiso' => $idCompromiso,
        'componente' => $componente,
        'descripcion' => ($data['descripcion'] ?? null) ?: 'Evidencia registrada',
        'tipo_evidencia' => 'LINK',
        'url_o_ubicacion' => $data['url'],
        'fecha_inclusion' => date('Y-m-d H:i:s'),
        'id_vinc_registra' => $vinculacionRegistra->id_vinculacion,
        'estado_aprobacion' => 'PENDIENTE',
    ]);

    return response()->json(['success' => true]);
})->name('evaluaciones.evidencias.store');

Route::post('/evaluaciones/{id}/evidencias/{idEvidencia}/aprobar', function (Request $request, int $id, int $idEvidencia) {
    abort_unless(session('usuario_autenticado.rol_activo') === 'evaluador', 403);

    $evaluacion = DB::table('evaluacion')->where('id_evaluacion', $id)->first();
    abort_unless($evaluacion, 404);

    $auth = session('usuario_autenticado');
    $vinculacionEvaluador = DB::table('vinculacion')
        ->where('id_vinculacion', $evaluacion->id_vinc_evaluador)
        ->where('id_funcionario', $auth['id_funcionario'] ?? null)
        ->first();

    abort_unless($vinculacionEvaluador, 403);

    abort_if($evaluacion->es_traslado, 422, 'Esta evaluación quedó bloqueada por traslado y solo se puede consultar.');

    abort_if($evaluacion->estado === 'CALIFICADA', 422, 'Esta evaluación ya fue calificada y calculada; las evidencias quedaron congeladas y no se pueden modificar.');

    $evidencia = DB::table('evidencia')
        ->where('id_evidencia', $idEvidencia)
        ->where('id_evaluacion', $id)
        ->first();

    abort_unless($evidencia, 404);

    $data = $request->validate([
        'decision' => ['required', 'in:APROBADA,RECHAZADA'],
        'observacion' => ['nullable', 'string', 'max:1000'],
    ]);

    DB::table('evidencia')->where('id_evidencia', $idEvidencia)->update([
        'estado_aprobacion' => $data['decision'],
        'id_vinc_aprueba' => $vinculacionEvaluador->id_vinculacion,
        'fecha_aprobacion' => date('Y-m-d H:i:s'),
        'observacion_aprobacion' => $data['observacion'] ?? null,
    ]);

    return response()->json(['success' => true, 'message' => 'Evidencia ' . strtolower($data['decision']) . ' correctamente.']);
})->name('evaluaciones.evidencias.aprobar');

Route::post('/evaluaciones/{id}/compromisos', function (Request $request, int $id) {
    abort_unless(session('usuario_autenticado.rol_activo') === 'evaluador', 403);

    $evaluacion = DB::table('evaluacion')->where('id_evaluacion', $id)->first();
    abort_unless($evaluacion, 404);
    abort_if($evaluacion->es_traslado, 422, 'Esta evaluación quedó bloqueada por traslado y solo se puede consultar.');

    if ($evaluacion->concertacion_firmada) {
        return response()->json(['error' => 'La concertación ya está firmada y congelada.'], 422);
    }

    $data = $request->validate([
        'descripcion' => ['required', 'string'],
        'porcentaje_peso' => ['required', 'numeric', 'min:1', 'max:15'],
        'metas' => ['required', 'array', 'min:1'],
        'metas.*' => ['required', 'string'],
    ]);

    $actualCount = DB::table('compromiso')->where('id_evaluacion', $id)->count();
    if ($actualCount >= 10) {
        return response()->json(['error' => 'No puedes agregar más de 10 compromisos.'], 422);
    }

    $targetWeight = getTargetCompromisosWeight($id);
    $actualSum = DB::table('compromiso')->where('id_evaluacion', $id)->sum('porcentaje_peso');
    if ($actualSum + $data['porcentaje_peso'] > $targetWeight + 0.01) {
        return response()->json(['error' => 'La suma de porcentajes excede el ' . $targetWeight . '%.'], 422);
    }

    $orden = $actualCount + 1;

    $compromisoId = DB::table('compromiso')->insertGetId([
        'id_evaluacion' => $id,
        'numero_orden' => $orden,
        'descripcion' => $data['descripcion'],
        'porcentaje_peso' => $data['porcentaje_peso'],
    ]);

    foreach ($data['metas'] as $meta) {
        DB::table('compromiso_meta')->insert([
            'id_compromiso' => $compromisoId,
            'meta' => $meta,
        ]);
    }

    return response()->json(['success' => true]);
});

Route::delete('/compromisos/{id}', function (int $id) {
    abort_unless(session('usuario_autenticado.rol_activo') === 'evaluador', 403);

    $compromiso = DB::table('compromiso')->where('id_compromiso', $id)->first();
    abort_unless($compromiso, 404);

    $evaluacion = DB::table('evaluacion')->where('id_evaluacion', $compromiso->id_evaluacion)->first();

    abort_if($evaluacion->es_traslado, 422, 'Esta evaluación quedó bloqueada por traslado y solo se puede consultar.');

    if ($evaluacion->concertacion_firmada) {
        return response()->json(['error' => 'La concertación ya está firmada y congelada.'], 422);
    }

    DB::table('compromiso_meta')->where('id_compromiso', $id)->delete();
    DB::table('compromiso')->where('id_compromiso', $id)->delete();

    $compromisos = DB::table('compromiso')
        ->where('id_evaluacion', $compromiso->id_evaluacion)
        ->orderBy('numero_orden')
        ->get();

    $i = 1;
    foreach ($compromisos as $c) {
        DB::table('compromiso')
            ->where('id_compromiso', $c->id_compromiso)
            ->update(['numero_orden' => $i++]);
    }

    return response()->json(['success' => true]);
});

Route::post('/evaluaciones/{id}/firmar', function (Request $request, int $id) {
    abort_unless(session()->has('usuario_autenticado'), 403);

    $evaluacion = DB::table('evaluacion')->where('id_evaluacion', $id)->first();
    abort_unless($evaluacion, 404);
    abort_if($evaluacion->es_traslado, 422, 'Esta evaluación quedó bloqueada por traslado y solo se puede consultar.');

    if ($evaluacion->concertacion_firmada) {
        return back()->withErrors(['firma' => 'Esta concertación ya se encuentra firmada.']);
    }

    $rolActivo = session('usuario_autenticado.rol_activo');
    $auth = session('usuario_autenticado');

    // S6 — Firma de la concertación de compromisos (sin renuencia, que aplica a la notificación de la nota).
    $tipoFirma = null;
    $idVincFirmante = null;

    if ($rolActivo === 'evaluador') {
        $puedeEvaluador = DB::table('vinculacion')
            ->where('id_vinculacion', $evaluacion->id_vinc_evaluador)
            ->where('id_funcionario', $auth['id_funcionario'] ?? null)
            ->exists();

        abort_unless($puedeEvaluador, 403);

        $compromisos = DB::table('compromiso')->where('id_evaluacion', $id)->get();
        $count = $compromisos->count();
        $sum = $compromisos->sum('porcentaje_peso');
        $targetWeight = getTargetCompromisosWeight($id);

        if ($count < 7 || $count > 10) {
            return back()->withErrors(['firma' => 'Debe registrar entre 7 y 10 compromisos para poder firmar (actuales: ' . $count . ').']);
        }

        if (abs($sum - $targetWeight) > 0.01) {
            return back()->withErrors(['firma' => 'La suma de porcentajes de los compromisos debe ser exactamente ' . $targetWeight . '% (actual: ' . $sum . '%).']);
        }

        $tipoFirma = 'CONCERTACION_EVALUADOR';
        $idVincFirmante = (int) $evaluacion->id_vinc_evaluador;
    } elseif ($rolActivo === 'evaluado') {
        $puedeEvaluado = DB::table('vinculacion')
            ->where('id_vinculacion', $evaluacion->id_vinc_evaluado)
            ->where('id_funcionario', $auth['id_funcionario'] ?? null)
            ->exists();

        abort_unless($puedeEvaluado, 403);

        $evaluadorFirmado = DB::table('firma')
            ->where('id_evaluacion', $id)
            ->where('tipo_firma', 'CONCERTACION_EVALUADOR')
            ->exists();

        if (!$evaluadorFirmado) {
            return back()->withErrors(['firma' => 'El evaluador debe proponer y firmar la concertación antes de que el evaluado pueda revisarla y firmar.']);
        }

        $tipoFirma = 'CONCERTACION_EVALUADO';
        $idVincFirmante = (int) $evaluacion->id_vinc_evaluado;
    } else {
        abort(403);
    }

    DB::table('firma')->updateOrInsert(
        ['id_evaluacion' => $id, 'tipo_firma' => $tipoFirma],
        [
            'id_vinc_firmante' => $idVincFirmante,
            'fecha_firma' => date('Y-m-d H:i:s'),
            'renuencia' => 0
        ]
    );

    $firmasConcertacion = DB::table('firma')
        ->where('id_evaluacion', $id)
        ->whereIn('tipo_firma', ['CONCERTACION_EVALUADOR', 'CONCERTACION_EVALUADO'])
        ->count();

    if ($firmasConcertacion === 2) {
        DB::table('evaluacion')
            ->where('id_evaluacion', $id)
            ->update([
                'concertacion_firmada' => 1,
                'fase_actual' => 3
            ]);
    } else {
        if ($evaluacion->fase_actual === 1) {
            DB::table('evaluacion')
                ->where('id_evaluacion', $id)
                ->update(['fase_actual' => 2]);
        }
    }

    return back()->with('success_firma', 'Firma registrada con éxito.');
})->name('evaluaciones.firmar');


// --- POST: Firmar notificación de la calificación / registrar renuencia con testigos ---
Route::post('/evaluaciones/{id}/firmar-notificacion', function (Request $request, int $id) {
    $auth = session('usuario_autenticado');
    abort_unless($auth, 403);
    $rolActivo = session('usuario_autenticado.rol_activo');

    $evaluacion = DB::table('evaluacion')->where('id_evaluacion', $id)->first();
    abort_unless($evaluacion, 404);
    abort_if($evaluacion->es_traslado, 422, 'Esta evaluación quedó bloqueada por traslado y solo se puede consultar.');
    abort_unless($evaluacion->estado === 'CALIFICADA', 422, 'La evaluación aún no ha sido calificada.');

    $renuncia = filter_var($request->input('renuencia', false), FILTER_VALIDATE_BOOLEAN);
    $testigos = collect($request->input('testigos', []))
        ->filter(fn ($t) => is_array($t)
            && !empty(trim((string) ($t['nombre'] ?? '')))
            && !empty(trim((string) ($t['cargo'] ?? ''))))
        ->map(fn ($t) => [
            'nombre_testigo' => trim((string) ($t['nombre'] ?? '')),
            'cargo_testigo' => trim((string) ($t['cargo'] ?? '')),
        ])
        ->values()
        ->all();

    if ($renuncia && count($testigos) < 1) {
        return response()->json(['error' => 'Debes registrar al menos un testigo (nombre y cargo) cuando renuncias a firmar.'], 422);
    }

    $puedeFirmar = false;
    if ($rolActivo === 'evaluado') {
        $puedeFirmar = DB::table('vinculacion')
            ->where('id_vinculacion', $evaluacion->id_vinc_evaluado)
            ->where('id_funcionario', $auth['id_funcionario'] ?? null)
            ->exists();
    } elseif (in_array($rolActivo, ['evaluador', 'admin'])) {
        $puedeFirmar = true;
    }

    abort_unless($puedeFirmar, 403);

    DB::table('firma')->updateOrInsert(
        ['id_evaluacion' => $id, 'tipo_firma' => 'NOTIFICACION_EVALUADO'],
        [
            'id_vinc_firmante' => (int) $evaluacion->id_vinc_evaluado,
            'fecha_firma' => date('Y-m-d H:i:s'),
            'renuencia' => $renuncia ? 1 : 0
        ]
    );

    $firmaRegistrada = DB::table('firma')
        ->where('id_evaluacion', $id)
        ->where('tipo_firma', 'NOTIFICACION_EVALUADO')
        ->first();

    if ($firmaRegistrada) {
        DB::table('testigo_renuencia')->where('id_firma', $firmaRegistrada->id_firma)->delete();
        if ($renuncia) {
            foreach ($testigos as $testigo) {
                DB::table('testigo_renuencia')->insert([
                    'id_firma' => $firmaRegistrada->id_firma,
                    'nombre_testigo' => $testigo['nombre_testigo'],
                    'cargo_testigo' => $testigo['cargo_testigo'],
                    'fecha_registro' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    return response()->json([
        'success' => true,
        'message' => $renuncia ? 'Renuencia con testigos registrada con éxito.' : 'Notificación de la calificación firmada con éxito.'
    ]);
})->name('evaluaciones.firmar-notificacion');



/**
 * Calcula la nota final de una evaluación según los documentos oficiales de Unitrópico.
 *
 * Las ponderaciones provienen de la configuración parametrizada
 * (tabla `ponderacion` o defaultPonderacionesConfig()):
 *
 * RENDIMIENTO_LABORAL (RL):
 *   - Compromisos:              80 %  (suma ponderada en escala 0-100)
 *   - Competencias comunes:     10 %  (promedio en escala 0-100)
 *   - Competencias nivel jer:   10 %  (promedio en escala 0-100)
 *   Total:                     100 %
 *
 * ACUERDO_GESTION (AG) sin ejes misionales:
 *   - Compromisos:              80 %
 *   - Competencias comunes:     10 %
 *   - Competencias nivel jer:   10 %
 *   Total:                     100 %
 *
 * ACUERDO_GESTION (AG) con ejes misionales (solo líderes de programa/departamento/escuela):
 *   Docencia siempre aplica; investigación y proyección social según el caso.
 *   Cada eje activo toma su peso (por defecto 10%) de compromisos.
 *   - Con 1 eje activo:   compromisos=70%, ejes=10%, comun=10%, nivel=10%   → 100%
 *   - Con 2 ejes activos: compromisos=60%, ejes=20%, comun=10%, nivel=10%   → 100%
 *   - Con 3 ejes activos: compromisos=50%, ejes=30%, comun=10%, nivel=10%   → 100%
 *
 * Escala individual (0-100):
 *    0-50: Deficiente | 51-70: Bajo | 71-80: Aceptable | 81-90: Alto | 91-100: Muy alto
 *
 * Categorías finales (0-100):
 *   ≥ 91:           SOBRESALIENTE
 *   81 a 90:        BUENO
 *   71 a 80:        APROBADO_MEJORA  (Susceptible de mejora)
 *    0 a 70:        NO_SATISFACTORIO
 *
 * Plan de mejoramiento (1er semestre):
 *   RL: aplica si calificación ∈ [0, 70]   (No satisfactorio)
 *   AG: aplica si calificación ∈ [0, 70]   (No satisfactorio)
 *
 * Prorrateo RF3:
 *   nota_final_prorrateo = nota_final × (dias_laborados / dias_totales_periodo)
 *   Solo aplica si dias_laborados < dias_totales_periodo (evaluaciones eventuales/parciales).
 *
 * Fuente: requerimientos tl (2).pdf | Pesos y ejes misionales (1).pdf | Formatos AG y RL XLSX
 */
if (!function_exists('calcularNotaEvaluacion')) {
    function calcularNotaEvaluacion(int $idEvaluacion): array {

    $evaluacion = DB::table('evaluacion as ev')
        ->join('vinculacion as ve', 've.id_vinculacion', '=', 'ev.id_vinc_evaluado')
        ->join('periodo as p', 'p.id_periodo', '=', 'ev.id_periodo')
        ->where('ev.id_evaluacion', $idEvaluacion)
        ->select('ev.*', 'p.sistema', 'p.fecha_inicio', 'p.fecha_fin', 've.aplica_eje_misional', 've.nivel_jerarquico')
        ->first();

    if (!$evaluacion) {
        return ['error' => 'Evaluación no encontrada.'];
    }

    $sistema = strtoupper(trim((string) $evaluacion->sistema));

    // -------------------------------------------------------
    // PESOS SEGÚN SISTEMA Y EJES ACTIVOS (ponderación parametrizada)
    //   RL:                compromisos=80%, comunes=10%, nivel=10%
    //   AG sin ejes:       compromisos=80%, comunes=10%, nivel=10%
    //   AG con ejes:       compromisos=50-70%, ejes=10-30%, comunes=10%, nivel=10%
    // -------------------------------------------------------
    $ponderaciones = getPonderacionesConfig();
    $configSistema = $ponderaciones[$sistema] ?? $ponderaciones['RENDIMIENTO_LABORAL'];

    $pesoCompromisos = (float) ($configSistema['peso_compromisos'] ?? 80.0);
    $pesoCompComun   = (float) ($configSistema['peso_competencias'] ?? 20.0) / 2;
    $pesoCompNivel   = (float) ($configSistema['peso_competencias'] ?? 20.0) / 2;

    // -------------------------------------------------------
    // EJES MISIONALES ACTIVOS (solo AG con aplica_eje_misional)
    // -------------------------------------------------------
    $ejesActivos   = [];
    $notasPorEje   = [];
    $ejeCals       = [];

    if ($sistema === 'ACUERDO_GESTION' && $evaluacion->aplica_eje_misional) {
        // Leer qué ejes están habilitados desde la tabla evaluacion_eje (investigacion / proyeccion_social)
        $ejesConfig = getEvaluacionEjes($idEvaluacion);

        // Docencia SIEMPRE activa si aplica_eje_misional = 1
        $pesoEjes = [
            'DOCENCIA' => (float) ($configSistema['peso_docencia'] ?? 10.0),
        ];
        if (!empty($ejesConfig['investigacion'])) {
            $pesoEjes['INVESTIGACION'] = (float) ($configSistema['peso_investigacion'] ?? 10.0);
        }
        if (!empty($ejesConfig['proyeccion_social'])) {
            $pesoEjes['PROYECCION_SOCIAL'] = (float) ($configSistema['peso_proyeccion_social'] ?? 10.0);
        }

        $ejesActivos = array_keys($pesoEjes);

        // Obtener calificaciones de cada eje desde la tabla eje_misional_calificacion
        $ejeCals = DB::table('eje_misional_calificacion')
            ->where('id_evaluacion', $idEvaluacion)
            ->whereNotNull('calificacion')
            ->pluck('calificacion', 'eje')
            ->toArray();

        foreach ($ejesActivos as $tipoEje) {
            $notasPorEje[$tipoEje] = isset($ejeCals[$tipoEje]) ? (float)$ejeCals[$tipoEje] : 0.0;
        }

        // Los ejes que NO aplican devuelven su peso a compromisos
        $pesoCompromisos += (float) ($configSistema['peso_docencia'] ?? 0.0)
            + (float) ($configSistema['peso_investigacion'] ?? 0.0)
            + (float) ($configSistema['peso_proyeccion_social'] ?? 0.0)
            - array_sum($pesoEjes);
    } elseif ($sistema === 'ACUERDO_GESTION' && !$evaluacion->aplica_eje_misional) {
        // El funcionario no tiene eje misional: docencia, investigación y
        // proyección social no aplican, todo su peso vuelve a compromisos.
        $pesoCompromisos += (float) ($configSistema['peso_docencia'] ?? 0.0)
            + (float) ($configSistema['peso_investigacion'] ?? 0.0)
            + (float) ($configSistema['peso_proyeccion_social'] ?? 0.0);
        $pesoEjes = [];
    } else {
        // RL: sin ejes misionales
        $pesoEjes = [];
    }

    // -------------------------------------------------------
    // 1. NOTA COMPROMISOS — suma ponderada (0-100 cada uno)
    // -------------------------------------------------------
    $compromisos = DB::table('compromiso')
        ->where('id_evaluacion', $idEvaluacion)
        ->whereNotNull('calificacion_definitiva')
        ->get(['porcentaje_peso', 'calificacion_definitiva']);

    $totalPesoCompromisos = DB::table('compromiso')
        ->where('id_evaluacion', $idEvaluacion)
        ->sum('porcentaje_peso');

    $notaCompromisos = 0.0;
    if ($totalPesoCompromisos > 0 && $compromisos->isNotEmpty()) {
        foreach ($compromisos as $c) {
            $notaCompromisos += ((float)$c->calificacion_definitiva * (float)$c->porcentaje_peso);
        }
        $notaCompromisos = $notaCompromisos / (float)$totalPesoCompromisos;
    }

    // -------------------------------------------------------
    // 2. NOTA COMPETENCIAS COMUNES (promedio escala 0-100)
    // -------------------------------------------------------
    $compComun = DB::table('competencia_evaluada as ce')
        ->join('competencia_catalogo as cc', 'cc.id_competencia', '=', 'ce.id_competencia')
        ->where('ce.id_evaluacion', $idEvaluacion)
        ->where('cc.tipo', 'COMUN')
        ->whereNotNull('ce.calificacion_definitiva')
        ->avg('ce.calificacion_definitiva');
    $notaCompComun = $compComun ? (float)$compComun : 0.0;

    // -------------------------------------------------------
    // 3. NOTA COMPETENCIAS NIVEL JERÁRQUICO (promedio 0-100)
    // -------------------------------------------------------
    $compNivel = DB::table('competencia_evaluada as ce')
        ->join('competencia_catalogo as cc', 'cc.id_competencia', '=', 'ce.id_competencia')
        ->where('ce.id_evaluacion', $idEvaluacion)
        ->where('cc.tipo', 'NIVEL_JERARQUICO')
        ->whereNotNull('ce.calificacion_definitiva')
        ->avg('ce.calificacion_definitiva');
    $notaCompNivel = $compNivel ? (float)$compNivel : 0.0;

    // -------------------------------------------------------
    // 4. NOTA FINAL (antes de prorrateo)
    // -------------------------------------------------------
    $subtotalCompromisos = $notaCompromisos * ($pesoCompromisos / 100.0);
    $subtotalComun       = $notaCompComun   * ($pesoCompComun   / 100.0);
    $subtotalNivel       = $notaCompNivel   * ($pesoCompNivel   / 100.0);

    $subtotalesEjes = [];
    $subtotalEjesTotal = 0.0;
    foreach ($pesoEjes as $tipoEje => $pesoEje) {
        $subtotalEje = ($notasPorEje[$tipoEje] ?? 0.0) * ($pesoEje / 100.0);
        $subtotalesEjes[$tipoEje] = round($subtotalEje, 4);
        $subtotalEjesTotal += $subtotalEje;
    }

    $notaFinal = round($subtotalCompromisos + $subtotalComun + $subtotalNivel + $subtotalEjesTotal, 2);

    // -------------------------------------------------------
    // 5. PRORRATEO RF3 — evaluaciones eventuales/parciales
    // -------------------------------------------------------
    $notaProrrateo   = null;
    $factorProrrateo = null;
    if ($evaluacion->dias_laborados && (int)$evaluacion->dias_laborados > 0) {
        $fechaInicio = new \DateTime($evaluacion->fecha_inicio);
        $fechaFin    = new \DateTime($evaluacion->fecha_fin);
        $diasPeriodo = $fechaInicio->diff($fechaFin)->days + 1;
        if ($diasPeriodo > 0 && (int)$evaluacion->dias_laborados < $diasPeriodo) {
            $factorProrrateo = (int)$evaluacion->dias_laborados / $diasPeriodo;
            $notaProrrateo   = round($notaFinal * $factorProrrateo, 2);
        }
    }

    // -------------------------------------------------------
    // 6. CATEGORÍA FINAL
    // -------------------------------------------------------
    $notaParaCategoria = $notaProrrateo ?? $notaFinal;
    $categoria = match(true) {
        $notaParaCategoria >= 91 => 'SOBRESALIENTE',
        $notaParaCategoria >= 81 => 'BUENO',
        $notaParaCategoria >= 71 => 'APROBADO_MEJORA',
        default                  => 'NO_SATISFACTORIO',
    };

    // -------------------------------------------------------
    // 7. PLAN DE MEJORAMIENTO (1er semestre)
    // -------------------------------------------------------
    $requierePlanMejoramiento = false;
    $tipoEval = $evaluacion->tipo_evaluacion ?? $evaluacion->tipo ?? 'SEMESTRE_1';
    if ($tipoEval === 'SEMESTRE_1') {
        if (in_array($sistema, ['RENDIMIENTO_LABORAL', 'ACUERDO_GESTION']) && $categoria === 'NO_SATISFACTORIO') {
            $requierePlanMejoramiento = true;
        }
    }

    // -------------------------------------------------------
    // 8. PENDIENTES — qué falta por calificar antes de poder cerrar la evaluación
    // -------------------------------------------------------
    $totalCompromisos = DB::table('compromiso')->where('id_evaluacion', $idEvaluacion)->count();
    $compromisosSinCalificar = DB::table('compromiso')
        ->where('id_evaluacion', $idEvaluacion)
        ->whereNull('calificacion_definitiva')
        ->count();

    $catalogoPath = storage_path('app/competencias_catalogo.json');
    $catalogo = file_exists($catalogoPath) ? (json_decode(file_get_contents($catalogoPath), true) ?? []) : [];
    $nivelJerarquico = strtoupper(trim((string) $evaluacion->nivel_jerarquico));

    $comunesEsperadas = collect($catalogo[$sistema]['COMUN'] ?? [])->pluck('nombre')->all();
    $nivelEsperadas   = collect($catalogo[$sistema]['NIVEL_JERARQUICO'][$nivelJerarquico] ?? [])->pluck('nombre')->all();

    $comunesCalificadas = DB::table('competencia_evaluada as ce')
        ->join('competencia_catalogo as cc', 'cc.id_competencia', '=', 'ce.id_competencia')
        ->where('ce.id_evaluacion', $idEvaluacion)->where('cc.tipo', 'COMUN')
        ->whereNotNull('ce.calificacion_definitiva')->pluck('cc.nombre')->all();
    $nivelCalificadas = DB::table('competencia_evaluada as ce')
        ->join('competencia_catalogo as cc', 'cc.id_competencia', '=', 'ce.id_competencia')
        ->where('ce.id_evaluacion', $idEvaluacion)->where('cc.tipo', 'NIVEL_JERARQUICO')
        ->whereNotNull('ce.calificacion_definitiva')->pluck('cc.nombre')->all();

    $comunesFaltantes = array_values(array_diff($comunesEsperadas, $comunesCalificadas));
    $nivelFaltantes   = array_values(array_diff($nivelEsperadas, $nivelCalificadas));

    $ejesFaltantes = [];
    foreach ($ejesActivos as $tipoEjeActivo) {
        if (!isset($ejeCals[$tipoEjeActivo])) {
            $ejesFaltantes[] = $tipoEjeActivo;
        }
    }

    $pendientes = [
        'compromisos_sin_calificar'      => $compromisosSinCalificar,
        'competencias_comunes_faltantes' => $comunesFaltantes,
        'competencias_nivel_faltantes'   => $nivelFaltantes,
        'ejes_faltantes'                 => $ejesFaltantes,
    ];

    $calificacionCompleta = $totalCompromisos > 0
        && $compromisosSinCalificar === 0
        && empty($comunesFaltantes)
        && empty($nivelFaltantes)
        && empty($ejesFaltantes);

    return [
        'sistema'                   => $sistema,
        'pesos' => [
            'compromisos'       => $pesoCompromisos,
            'comun'             => $pesoCompComun,
            'nivel_jerarquico'  => $pesoCompNivel,
            'ejes'              => $pesoEjes,
        ],
        'ejes_activos'              => $ejesActivos,
        'notas_ejes_raw'            => $notasPorEje,
        'nota_compromisos_raw'      => round($notaCompromisos, 4),
        'nota_comp_comun_raw'       => round($notaCompComun, 4),
        'nota_comp_nivel_raw'       => round($notaCompNivel, 4),
        'subtotal_compromisos'      => round($subtotalCompromisos, 4),
        'subtotal_comun'            => round($subtotalComun, 4),
        'subtotal_nivel'            => round($subtotalNivel, 4),
        'subtotales_ejes'           => $subtotalesEjes,
        'subtotal_ejes_total'       => round($subtotalEjesTotal, 4),
        'nota_final'                => $notaFinal,
        'dias_laborados'            => $evaluacion->dias_laborados,
        'factor_prorrateo'          => $factorProrrateo ? round($factorProrrateo, 6) : null,
        'nota_prorrateo'            => $notaProrrateo,
        'nota_definitiva'           => $notaProrrateo ?? $notaFinal,
        'categoria'                 => $categoria,
        'requiere_plan_mejoramiento'=> $requierePlanMejoramiento,
        'calificacion_completa'     => $calificacionCompleta,
        'pendientes'                => $pendientes,
        'estado'                    => $evaluacion->estado,
    ];
}
}

if (!function_exists('informeAnualDisponible')) {
    /**
     * Indica si el informe anual está disponible para una evaluación:
     * se requiere que AMBOS semestres (A y B) del mismo año/sistema/evaluado
     * estén calificados.
     */
    function informeAnualDisponible(int $idEvaluacion): bool
    {
        $evaluacion = DB::table('evaluacion as ev')
            ->join('periodo as p', 'p.id_periodo', '=', 'ev.id_periodo')
            ->where('ev.id_evaluacion', $idEvaluacion)
            ->select('ev.id_vinc_evaluado', 'p.anio', 'p.sistema')
            ->first();

        if (!$evaluacion) {
            return false;
        }

        $tipos = DB::table('evaluacion as ev')
            ->join('periodo as p', 'p.id_periodo', '=', 'ev.id_periodo')
            ->where('ev.id_vinc_evaluado', $evaluacion->id_vinc_evaluado)
            ->where('p.anio', $evaluacion->anio)
            ->where('p.sistema', $evaluacion->sistema)
            ->whereIn('ev.tipo_evaluacion', ['SEMESTRE_1', 'SEMESTRE_2'])
            ->where('ev.estado', 'CALIFICADA')
            ->pluck('ev.tipo_evaluacion')
            ->unique();

        return $tipos->contains('SEMESTRE_1') && $tipos->contains('SEMESTRE_2');
    }
}



// --- GET: Vista previa del cálculo de nota (sin guardar) ---
Route::get('/evaluaciones/{id}/calculo', function (int $id) {
    abort_unless(session()->has('usuario_autenticado'), 403);

    $evaluacion = DB::table('evaluacion')->where('id_evaluacion', $id)->first();
    abort_unless($evaluacion, 404);

    $auth     = session('usuario_autenticado');
    $rolActivo = $auth['rol_activo'] ?? null;

    if ($rolActivo !== 'admin') {
        $puedeVer = DB::table('vinculacion')
            ->whereIn('id_vinculacion', [$evaluacion->id_vinc_evaluado, $evaluacion->id_vinc_evaluador])
            ->where('id_funcionario', $auth['id_funcionario'] ?? null)
            ->exists();
        abort_unless($puedeVer, 403);
    }

    $calculo = calcularNotaEvaluacion($id);
    $calculo['informe_anual_disponible'] = informeAnualDisponible($id);

    return response()->json($calculo);
})->name('evaluaciones.calculo');


// --- POST: Guardar calificación de compromisos (evaluador) ---
Route::post('/evaluaciones/{id}/calificar-compromisos', function (Request $request, int $id) {
    abort_unless(session('usuario_autenticado.rol_activo') === 'evaluador', 403);

    $evaluacion = DB::table('evaluacion')->where('id_evaluacion', $id)->first();
    abort_unless($evaluacion, 404);
    abort_unless($evaluacion->concertacion_firmada, 403, 'La concertación debe estar firmada antes de calificar.');
    abort_if($evaluacion->estado === 'CALIFICADA', 422, 'Esta evaluación ya fue calificada y calculada; las notas quedaron congeladas y no se pueden modificar.');

    $auth = session('usuario_autenticado');
    $puedeEditar = DB::table('vinculacion')
        ->where('id_vinculacion', $evaluacion->id_vinc_evaluador)
        ->where('id_funcionario', $auth['id_funcionario'] ?? null)
        ->exists();
    abort_unless($puedeEditar, 403);
    abort_if($evaluacion->es_traslado, 422, 'Esta evaluación quedó bloqueada por traslado y solo se puede consultar.');

    $data = $request->validate([
        'compromisos' => ['required', 'array'],
        'compromisos.*.id_compromiso'          => ['required', 'integer'],
        'compromisos.*.calificacion_sem1'      => ['nullable', 'numeric', 'min:0', 'max:100'],
        'compromisos.*.calificacion_sem2'      => ['nullable', 'numeric', 'min:0', 'max:100'],
        'compromisos.*.calificacion_definitiva'=> ['nullable', 'numeric', 'min:0', 'max:100'],
    ]);

    foreach ($data['compromisos'] as $item) {
        $comp = DB::table('compromiso')
            ->where('id_compromiso', $item['id_compromiso'])
            ->where('id_evaluacion', $id)
            ->first();

        if (!$comp) continue;

        $update = [];
        if (array_key_exists('calificacion_sem1', $item)) {
            $update['calificacion_sem1'] = $item['calificacion_sem1'];
        }
        if (array_key_exists('calificacion_sem2', $item)) {
            $update['calificacion_sem2'] = $item['calificacion_sem2'];
        }
        if (array_key_exists('calificacion_definitiva', $item)) {
            $update['calificacion_definitiva'] = $item['calificacion_definitiva'];
        }

        if (!empty($update)) {
            DB::table('compromiso')
                ->where('id_compromiso', $item['id_compromiso'])
                ->update($update);
        }
    }

    return response()->json(['success' => true, 'message' => 'Calificaciones de compromisos guardadas.']);
})->name('evaluaciones.calificar-compromisos');


// --- POST: Guardar calificación de competencias (evaluador) ---
Route::post('/evaluaciones/{id}/calificar-competencias', function (Request $request, int $id) {
    abort_unless(session('usuario_autenticado.rol_activo') === 'evaluador', 403);

    $evaluacion = DB::table('evaluacion')->where('id_evaluacion', $id)->first();
    abort_unless($evaluacion, 404);
    abort_unless($evaluacion->concertacion_firmada, 403, 'La concertación debe estar firmada antes de calificar.');
    abort_if($evaluacion->estado === 'CALIFICADA', 422, 'Esta evaluación ya fue calificada y calculada; las notas quedaron congeladas y no se pueden modificar.');

    $auth = session('usuario_autenticado');
    $puedeEditar = DB::table('vinculacion')
        ->where('id_vinculacion', $evaluacion->id_vinc_evaluador)
        ->where('id_funcionario', $auth['id_funcionario'] ?? null)
        ->exists();
    abort_unless($puedeEditar, 403);
    abort_if($evaluacion->es_traslado, 422, 'Esta evaluación quedó bloqueada por traslado y solo se puede consultar.');

    $data = $request->validate([
        'competencias'   => ['required', 'array'],
        'competencias.*.id_competencia'         => ['required', 'integer', 'exists:competencia_catalogo,id_competencia'],
        'competencias.*.calificacion_definitiva'=> ['nullable', 'numeric', 'min:0', 'max:100'],
        'competencias.*.calificacion_sem1'      => ['nullable', 'numeric', 'min:0', 'max:100'],
        'competencias.*.calificacion_sem2'      => ['nullable', 'numeric', 'min:0', 'max:100'],
    ]);

    foreach ($data['competencias'] as $item) {
        $existing = DB::table('competencia_evaluada')
            ->where('id_evaluacion', $id)
            ->where('id_competencia', $item['id_competencia'])
            ->first();

        $fields = [
            'calificacion_sem1'       => $item['calificacion_sem1'] ?? null,
            'calificacion_sem2'       => $item['calificacion_sem2'] ?? null,
            'calificacion_definitiva' => $item['calificacion_definitiva'] ?? null,
        ];

        if ($existing) {
            DB::table('competencia_evaluada')
                ->where('id_comp_eval', $existing->id_comp_eval)
                ->update($fields);
        } else {
            DB::table('competencia_evaluada')->insert(array_merge($fields, [
                'id_evaluacion'      => $id,
                'id_competencia'     => $item['id_competencia'],
            ]));
        }
    }

    return response()->json(['success' => true, 'message' => 'Competencias guardadas.']);
})->name('evaluaciones.calificar-competencias');


// --- POST: Ejecutar motor de cálculo y guardar nota final (evaluador/admin) ---
Route::post('/evaluaciones/{id}/calcular-final', function (Request $request, int $id) {
    $rolActivo = session('usuario_autenticado.rol_activo');
    abort_unless(in_array($rolActivo, ['evaluador', 'admin']), 403);

    $evaluacion = DB::table('evaluacion')->where('id_evaluacion', $id)->first();
    abort_unless($evaluacion, 404);
    abort_if($evaluacion->es_traslado, 422, 'Esta evaluación quedó bloqueada por traslado y solo se puede consultar.');

    // Solo admin puede forzar el cálculo; evaluador solo puede calificar sus propias
    if ($rolActivo === 'evaluador') {
        $auth = session('usuario_autenticado');
        $puedeEditar = DB::table('vinculacion')
            ->where('id_vinculacion', $evaluacion->id_vinc_evaluador)
            ->where('id_funcionario', $auth['id_funcionario'] ?? null)
            ->exists();
        abort_unless($puedeEditar, 403);
    }

    // Solo el admin puede forzar el cálculo pasando por encima de estas dos validaciones
    // (evaluación ya calificada, o calificaciones incompletas). El evaluador no puede.
    if ($rolActivo === 'evaluador') {
        abort_if($evaluacion->estado === 'CALIFICADA', 422, 'Esta evaluación ya fue calificada y calculada; no se puede volver a calcular.');
    }

    $calculo = calcularNotaEvaluacion($id);

    if (isset($calculo['error'])) {
        return response()->json(['error' => $calculo['error']], 422);
    }

    if ($rolActivo === 'evaluador' && !($calculo['calificacion_completa'] ?? false)) {
        return response()->json([
            'error' => 'Faltan calificaciones por registrar antes de calcular la nota final.',
            'pendientes' => $calculo['pendientes'],
        ], 422);
    }

    // Guardar en la base de datos
    DB::table('evaluacion')->where('id_evaluacion', $id)->update([
        'nota_compromisos'    => $calculo['nota_compromisos_raw'],
        'nota_competencias'   => round(($calculo['nota_comp_comun_raw'] + $calculo['nota_comp_nivel_raw']) / 2, 4),
        'nota_ejes_misionales'=> $calculo['subtotal_ejes_total'] ?? 0,
        'calificacion_final'  => $calculo['nota_definitiva'],
        'calificacion_parcial'=> $calculo['nota_prorrateo'],
        'categoria_final'     => $calculo['categoria'],
        'estado'              => 'CALIFICADA',
        'fase_actual'         => 5,
    ]);

    return response()->json([
        'success' => true,
        'calculo' => $calculo,
        'message' => 'Nota final calculada y guardada correctamente.',
    ]);
})->name('evaluaciones.calcular-final');


// --- GET: Listado de competencias calificadas de una evaluación ---
Route::get('/evaluaciones/{id}/competencias', function (int $id) {
    abort_unless(session()->has('usuario_autenticado'), 403);

    $evaluacion = DB::table('evaluacion')->where('id_evaluacion', $id)->first();
    abort_unless($evaluacion, 404);

    $auth = session('usuario_autenticado');
    $rolActivo = $auth['rol_activo'] ?? null;

    if ($rolActivo !== 'admin') {
        $puedeVer = DB::table('vinculacion')
            ->whereIn('id_vinculacion', [$evaluacion->id_vinc_evaluado, $evaluacion->id_vinc_evaluador])
            ->where('id_funcionario', $auth['id_funcionario'] ?? null)
            ->exists();
        abort_unless($puedeVer, 403);
    }

    $competencias = DB::table('competencia_evaluada as ce')
        ->join('competencia_catalogo as cc', 'cc.id_competencia', '=', 'ce.id_competencia')
        ->where('ce.id_evaluacion', $id)
        ->orderBy('cc.tipo')
        ->orderBy('cc.orden')
        ->get([
            'ce.id_comp_eval',
            'ce.id_competencia',
            'cc.nombre as nombre_competencia',
            'cc.tipo',
            'ce.calificacion_sem1',
            'ce.calificacion_sem2',
            'ce.calificacion_definitiva',
        ]);

    return response()->json(['competencias' => $competencias]);
})->name('evaluaciones.competencias');


// --- GET: Catálogo de competencias por sistema y nivel jerárquico ---
Route::get('/catalogo/competencias', function (Request $request) {
    abort_unless(session()->has('usuario_autenticado'), 403);

    $catalogoPath = storage_path('app/competencias_catalogo.json');
    $escala = file_exists($catalogoPath)
        ? (json_decode(file_get_contents($catalogoPath), true)['escala_calificacion'] ?? [])
        : [];

    // Filtrar por sistema y nivel si se pasan como query params
    $sistema = strtoupper($request->query('sistema', ''));
    $nivel   = strtoupper($request->query('nivel', ''));

    if (! in_array($sistema, ['RENDIMIENTO_LABORAL', 'ACUERDO_GESTION'])) {
        return response()->json(['error' => 'Sistema inválido.'], 404);
    }

    $comun = DB::table('competencia_catalogo')
        ->where('sistema', $sistema)
        ->where('tipo', 'COMUN')
        ->orderBy('orden')
        ->get(['id_competencia', 'nombre', 'afirmacion']);

    if ($nivel) {
        $nivelRows = DB::table('competencia_catalogo')
            ->where('sistema', $sistema)
            ->where('tipo', 'NIVEL_JERARQUICO')
            ->where('nivel_jerarquico', $nivel)
            ->orderBy('orden')
            ->get(['id_competencia', 'nombre', 'afirmacion']);

        return response()->json([
            'sistema' => $sistema,
            'nivel'   => $nivel,
            'comun'   => $comun,
            'nivel_jerarquico' => $nivelRows,
            'escala'  => $escala,
        ]);
    }

    $nivelGrouped = DB::table('competencia_catalogo')
        ->where('sistema', $sistema)
        ->where('tipo', 'NIVEL_JERARQUICO')
        ->orderBy('nivel_jerarquico')
        ->orderBy('orden')
        ->get(['id_competencia', 'nivel_jerarquico', 'nombre', 'afirmacion'])
        ->groupBy('nivel_jerarquico');

    return response()->json([
        'sistema' => $sistema,
        'data'    => ['COMUN' => $comun, 'NIVEL_JERARQUICO' => $nivelGrouped],
        'escala'  => $escala,
    ]);
})->name('catalogo.competencias');


// --- POST: Calificar ejes misionales (evaluador AG con aplica_eje_misional) ---
Route::post('/evaluaciones/{id}/calificar-ejes', function (Request $request, int $id) {
    abort_unless(session('usuario_autenticado.rol_activo') === 'evaluador', 403);

    $evaluacion = DB::table('evaluacion')->where('id_evaluacion', $id)->first();
    abort_unless($evaluacion, 404);
    abort_unless($evaluacion->concertacion_firmada, 403, 'La concertación debe estar firmada antes de calificar ejes misionales.');
    abort_if($evaluacion->estado === 'CALIFICADA', 422, 'Esta evaluación ya fue calificada y calculada; las notas quedaron congeladas y no se pueden modificar.');

    $auth = session('usuario_autenticado');
    $puedeEditar = DB::table('vinculacion')
        ->where('id_vinculacion', $evaluacion->id_vinc_evaluador)
        ->where('id_funcionario', $auth['id_funcionario'] ?? null)
        ->exists();
    abort_unless($puedeEditar, 403);
    abort_if($evaluacion->es_traslado, 422, 'Esta evaluación quedó bloqueada por traslado y solo se puede consultar.');

    // Verificar que el evaluado tiene aplica_eje_misional = 1
    $vinculacionEvaluado = DB::table('vinculacion')
        ->where('id_vinculacion', $evaluacion->id_vinc_evaluado)
        ->first();
    abort_unless($vinculacionEvaluado && $vinculacionEvaluado->aplica_eje_misional, 403, 'Este evaluado no tiene ejes misionales habilitados.');

    $data = $request->validate([
        'ejes' => ['required', 'array'],
        'ejes.*.tipo_eje'    => ['required', 'in:DOCENCIA,INVESTIGACION,PROYECCION_SOCIAL'],
        'ejes.*.calificacion'=> ['required', 'numeric', 'min:0', 'max:100'],
        'ejes.*.observacion' => ['nullable', 'string', 'max:500'],
    ]);

    foreach ($data['ejes'] as $eje) {
        DB::table('eje_misional_calificacion')->updateOrInsert(
            ['id_evaluacion' => $id, 'eje' => $eje['tipo_eje']],
            [
                'calificacion' => $eje['calificacion'],
                'observaciones'=> $eje['observacion'] ?? null,
                'id_vinc_ingresador' => $evaluacion->id_vinc_evaluador,
                'id_usuario_ingresador' => $auth['id_usuario'] ?? null,
                'origen' => 'EVALUADOR',
            ]
        );
    }

    return response()->json(['success' => true, 'message' => 'Ejes misionales calificados correctamente.']);
})->name('evaluaciones.calificar-ejes');


/**
 * Lista TODOS los funcionarios con ejes misionales habilitados (aplica_eje_misional=1,
 * sistema AG, vinculación activa), para el módulo de Instancias Externas (Vicerrectoría
 * de Investigación, Vicerrectoría de Proyección Social, CEDP). El pliego indica que la
 * carga de estas notas es exclusiva de las instancias externas; ver también
 * /evaluaciones/{id}/calificar-ejes (evaluador) — ambos endpoints conviven hasta que
 * Talento Humano confirme si el evaluador debe perder ese permiso.
 *
 * A propósito NO se filtra por si ya existe evaluación o concertación firmada: la lista
 * debe mostrar a todo el que tiene el eje habilitado, para que la instancia externa vea
 * quién falta por tener evaluación abierta. La carga de notas en sí sigue exigiendo que
 * exista la evaluación y que la concertación esté firmada (ver /ejes-externa).
 */
if (!function_exists('obtenerEvaluacionesAgConEjesMisionales')) {
    function obtenerEvaluacionesAgConEjesMisionales() {
        $periodoAG = DB::table('periodo')
            ->where('sistema', 'ACUERDO_GESTION')
            ->where('estado', 'ABIERTO')
            ->orderByDesc('id_periodo')
            ->first();

        $personas = DB::table('vinculacion as v')
            ->join('funcionario as f', 'f.id_funcionario', '=', 'v.id_funcionario')
            ->where('v.sistema_evaluacion', 'ACUERDO_GESTION')
            ->where('v.aplica_eje_misional', 1)
            ->where('v.activa', 1)
            ->select('v.id_vinculacion', 'v.cargo as evaluado_cargo', 'v.area as evaluado_area', 'f.nombres as evaluado_nombres', 'f.apellidos as evaluado_apellidos')
            ->orderBy('f.apellidos')
            ->get();

        foreach ($personas as $persona) {
            $evaluacion = $periodoAG
                ? DB::table('evaluacion')
                    ->where('id_periodo', $periodoAG->id_periodo)
                    ->where('id_vinc_evaluado', $persona->id_vinculacion)
                    ->orderByDesc('id_evaluacion')
                    ->first()
                : null;

            $persona->id_evaluacion = $evaluacion->id_evaluacion ?? null;
            $persona->fase_actual = $evaluacion->fase_actual ?? null;
            $persona->estado = $evaluacion->estado ?? null;
            $persona->concertacion_firmada = $evaluacion ? (bool) $evaluacion->concertacion_firmada : false;

            $config = $evaluacion ? getEvaluacionEjes((int) $evaluacion->id_evaluacion) : [];
            $ejesActivos = ['DOCENCIA'];
            if (!empty($config['investigacion'])) $ejesActivos[] = 'INVESTIGACION';
            if (!empty($config['proyeccion_social'])) $ejesActivos[] = 'PROYECCION_SOCIAL';
            $persona->ejes_activos = $ejesActivos;

            $persona->calificaciones = $evaluacion
                ? DB::table('eje_misional_calificacion')
                    ->where('id_evaluacion', $evaluacion->id_evaluacion)
                    ->get(['eje', 'calificacion', 'observaciones', 'origen', 'fecha_ingreso'])
                : collect();
        }

        return $personas;
    }
}

Route::get('/instancia-externa/evaluaciones', function () {
    abort_unless(session('usuario_autenticado.rol_activo') === 'instancia_externa', 403);

    return response()->json([
        'evaluaciones' => obtenerEvaluacionesAgConEjesMisionales(),
    ]);
})->name('instancia-externa.evaluaciones');

// --- POST: Calificar ejes misionales — Instancia Externa (Vicerrectoría Investigación / Proyección Social / CEDP) ---
// Endpoint aditivo: no reemplaza /evaluaciones/{id}/calificar-ejes (evaluador). Ver nota arriba.
Route::post('/evaluaciones/{id}/ejes-externa', function (Request $request, int $id) {
    abort_unless(session('usuario_autenticado.rol_activo') === 'instancia_externa', 403);

    $evaluacion = DB::table('evaluacion as ev')
        ->join('periodo as p', 'p.id_periodo', '=', 'ev.id_periodo')
        ->where('ev.id_evaluacion', $id)
        ->select('ev.*', 'p.sistema')
        ->first();
    abort_unless($evaluacion, 404);
    abort_if($evaluacion->es_traslado, 422, 'Esta evaluación quedó bloqueada por traslado y solo se puede consultar.');
    abort_unless(strtoupper(trim((string) $evaluacion->sistema)) === 'ACUERDO_GESTION', 422, 'Solo aplica a evaluaciones de Acuerdo de Gestión.');
    abort_unless($evaluacion->concertacion_firmada, 403, 'La concertación debe estar firmada antes de calificar ejes misionales.');
    abort_if($evaluacion->estado === 'CALIFICADA', 422, 'Esta evaluación ya fue calificada y calculada; las notas quedaron congeladas y no se pueden modificar.');

    $vinculacionEvaluado = DB::table('vinculacion')
        ->where('id_vinculacion', $evaluacion->id_vinc_evaluado)
        ->first();
    abort_unless($vinculacionEvaluado && $vinculacionEvaluado->aplica_eje_misional, 403, 'Este evaluado no tiene ejes misionales habilitados.');

    $data = $request->validate([
        'ejes' => ['required', 'array'],
        'ejes.*.tipo_eje'    => ['required', 'in:DOCENCIA,INVESTIGACION,PROYECCION_SOCIAL'],
        'ejes.*.calificacion'=> ['required', 'numeric', 'min:0', 'max:100'],
        'ejes.*.observacion' => ['nullable', 'string', 'max:500'],
    ]);

    $auth = session('usuario_autenticado');

    foreach ($data['ejes'] as $eje) {
        DB::table('eje_misional_calificacion')->updateOrInsert(
            ['id_evaluacion' => $id, 'eje' => $eje['tipo_eje']],
            [
                'calificacion' => $eje['calificacion'],
                'observaciones'=> $eje['observacion'] ?? null,
                'id_vinc_ingresador' => null,
                'id_usuario_ingresador' => $auth['id_usuario'] ?? null,
                'origen' => 'INSTANCIA_EXTERNA',
            ]
        );
    }

    return response()->json(['success' => true, 'message' => 'Notas de componente académico cargadas correctamente.']);
})->name('evaluaciones.ejes-externa');


// ============================================================================
// S6 — RECURSOS EN LÍNEA (Reposición / Apelación), RENUENCIA CON TESTIGOS y
//      PLAN DE MEJORAMIENTO CONDICIONADO
// ============================================================================

/**
 * Testigos de la renuencia de las firmas de concertación de una evaluación.
 * Devuelve una lista de {tipo_firma, nombre_testigo, cargo_testigo}.
 */
if (!function_exists('getTestigosConcertacion')) {
    function getTestigosConcertacion(int $idEvaluacion) {
        return DB::table('testigo_renuencia as t')
            ->join('firma as f', 'f.id_firma', '=', 't.id_firma')
            ->where('f.id_evaluacion', $idEvaluacion)
            ->whereIn('f.tipo_firma', ['NOTIFICACION_EVALUADO', 'CONCERTACION_EVALUADOR', 'CONCERTACION_EVALUADO'])
            ->select('f.tipo_firma', 't.nombre_testigo', 't.cargo_testigo')
            ->orderBy('f.tipo_firma')
            ->orderBy('t.id_testigo')
            ->get();
    }
}

/**
 * Devuelve los recursos de una evaluación con nombres de receptor/solicitante.
 */
if (!function_exists('adjuntarEvidenciasRecursos')) {
    function adjuntarEvidenciasRecursos($recursos) {
        $ids = collect($recursos)
            ->pluck('id_recurso')
            ->map(fn ($i) => (int) $i)
            ->filter()
            ->all();

        $evidencias = $ids
            ? DB::table('recurso_evidencia')
                ->whereIn('id_recurso', $ids)
                ->orderBy('id_recurso_evidencia')
                ->get()
                ->groupBy('id_recurso')
            : collect();

        foreach ($recursos as $r) {
            $r->evidencias = $evidencias[(int) $r->id_recurso] ?? [];
        }

        return $recursos;
    }
}

if (!function_exists('getRecursosEvaluacion')) {
    function getRecursosEvaluacion(int $idEvaluacion) {
        return adjuntarEvidenciasRecursos(
            DB::table('recurso as r')
                ->leftJoin('vinculacion as vrec', 'vrec.id_vinculacion', '=', 'r.id_vinc_receptor')
                ->leftJoin('funcionario as frec', 'frec.id_funcionario', '=', 'vrec.id_funcionario')
                ->where('r.id_evaluacion', $idEvaluacion)
                ->select(
                    'r.*',
                    'frec.nombres as receptor_nombres',
                    'frec.apellidos as receptor_apellidos'
                )
                ->orderByDesc('r.id_recurso')
                ->get()
        );
    }
}

/**
 * Evaluación con el sistema del periodo (necesario para evaluar el plan de mejoramiento).
 */
if (!function_exists('getEvaluacionConSistema')) {
    function getEvaluacionConSistema(int $idEvaluacion) {
        return DB::table('evaluacion as ev')
            ->join('periodo as p', 'p.id_periodo', '=', 'ev.id_periodo')
            ->where('ev.id_evaluacion', $idEvaluacion)
            ->select('ev.*', 'p.sistema')
            ->first();
    }
}

/**
 * S6 — Plan de mejoramiento CONDICIONADO (1er semestre):
 *   RL: calificación final ∈ [71, 80] (APROBADO_MEJORA)
 *   AG: calificación final ∈ [0, 70]  (NO_SATISFACTORIO)
 * El bloqueo del flujo del evaluador aplica hasta concertar y firmar el plan.
 */
if (!function_exists('evaluacionRequierePlanMejoramiento')) {
    function evaluacionRequierePlanMejoramiento($evaluacion): bool {
        if (!$evaluacion) {
            return false;
        }

        $categoria = strtoupper(trim((string) ($evaluacion->categoria_final ?? '')));
        if ($categoria === 'NO_SATISFACTORIO') {
            return true;
        }

        $notaDef = $evaluacion->calificacion_final ?? $evaluacion->calificacion_parcial ?? null;
        if ($notaDef !== null && (float) $notaDef <= 70.0 && (float) $notaDef > 0) {
            return true;
        }

        if (isset($evaluacion->id_evaluacion)) {
            $planExiste = DB::table('plan_mejoramiento')
                ->where('id_evaluacion', $evaluacion->id_evaluacion)
                ->exists();
            if ($planExiste) {
                return true;
            }
        }

        return false;
    }
}

/**
 * S6 — Indica si el evaluado tiene un plan de mejoramiento pendiente de concertar
 * y firmar (RL y AG: NO_SATISFACTORIO, primer semestre) en una
 * evaluación ya calificada. Se usa para bloquear la creación de una nueva
 * evaluación hasta resolver el plan anterior.
 */
if (!function_exists('evaluadoTienePlanMejoramientoPendiente')) {
    function evaluadoTienePlanMejoramientoPendiente(int $idVincEvaluado, ?int $idPeriodo = null): bool {
        $query = DB::table('evaluacion as ev')
            ->join('periodo as p', 'p.id_periodo', '=', 'ev.id_periodo')
            ->leftJoin('plan_mejoramiento as pm', 'pm.id_evaluacion', '=', 'ev.id_evaluacion')
            ->where('ev.id_vinc_evaluado', $idVincEvaluado)
            ->where('ev.estado', 'CALIFICADA')
            ->where('ev.tipo_evaluacion', 'SEMESTRE_1')
            ->whereIn('p.sistema', ['RENDIMIENTO_LABORAL', 'ACUERDO_GESTION'])
            ->where('ev.categoria_final', 'NO_SATISFACTORIO')
            ->where(function ($q) {
                $q->whereNull('pm.id_plan')->orWhere('pm.estado', '!=', 'CONCERTADO');
            });

        if ($idPeriodo !== null) {
            $query->where('ev.id_periodo', '!=', $idPeriodo);
        }

        return $query->exists();
    }
}

/**
 * S6 — Vinculación de Talento Humano (receptor de las apelaciones).
 * Se busca la vinculación activa de un usuario ADMINISTRADOR; si no existe,
 * se usa la vinculación del evaluador como receptor de respaldo.
 */
if (!function_exists('resolverVinculacionReceptorApelacion')) {
    function resolverVinculacionReceptorApelacion(int $idEvaluacion): int {
        $evaluacion = DB::table('evaluacion')->where('id_evaluacion', $idEvaluacion)->first();

        // 1) Superior jerárquico del evaluador (vinculacion.id_vinc_jefe).
        if ($evaluacion) {
            $jefeEvaluador = DB::table('vinculacion')
                ->where('id_vinculacion', $evaluacion->id_vinc_evaluador)
                ->value('id_vinc_jefe');

            if (!empty($jefeEvaluador)) {
                return (int) $jefeEvaluador;
            }
        }

        // 2) Fallback: Talento Humano (rol ADMINISTRADOR).
        $vinculacionTH = DB::table('usuario as u')
            ->join('funcionario as f', 'f.id_usuario', '=', 'u.id_usuario')
            ->join('vinculacion as v', function ($join) {
                $join->on('v.id_funcionario', '=', 'f.id_funcionario')
                    ->where('v.activa', '=', 1);
            })
            ->where('u.rol', 'ADMINISTRADOR')
            ->select('v.id_vinculacion')
            ->orderBy('v.id_vinculacion')
            ->first();

        if ($vinculacionTH) {
            return (int) $vinculacionTH->id_vinculacion;
        }

        // 3) Último respaldo: el propio evaluador.
        return (int) ($evaluacion->id_vinc_evaluador ?? 0);
    }
}


// --- GET: Recursos de una evaluación ---
Route::get('/evaluaciones/{id}/recursos', function (int $id) {
    abort_unless(session()->has('usuario_autenticado'), 403);

    $evaluacion = DB::table('evaluacion')->where('id_evaluacion', $id)->first();
    abort_unless($evaluacion, 404);

    $auth = session('usuario_autenticado');
    $rolActivo = $auth['rol_activo'] ?? null;

    if ($rolActivo !== 'admin') {
        $puedeVer = DB::table('vinculacion')
            ->whereIn('id_vinculacion', [$evaluacion->id_vinc_evaluado, $evaluacion->id_vinc_evaluador])
            ->where('id_funcionario', $auth['id_funcionario'] ?? null)
            ->exists();

        abort_unless($puedeVer, 403);
    }

    return response()->json([
        'recursos' => getRecursosEvaluacion($id),
        'estado' => $evaluacion->estado,
        'categoria_final' => $evaluacion->categoria_final,
        'traslado' => (bool) $evaluacion->es_traslado,
    ]);
})->name('evaluaciones.recursos');


// --- POST: Radicar un recurso (solo el evaluado, evaluación calificada) ---
Route::post('/evaluaciones/{id}/recursos', function (Request $request, int $id) {
    abort_unless(session('usuario_autenticado.rol_activo') === 'evaluado', 403);

    $evaluacion = DB::table('evaluacion')->where('id_evaluacion', $id)->first();
    abort_unless($evaluacion, 404);
    abort_if($evaluacion->es_traslado, 422, 'Esta evaluación quedó bloqueada por traslado y solo se puede consultar.');
    abort_unless($evaluacion->estado === 'CALIFICADA', 422, 'Solo puedes radicar un recurso cuando la evaluación haya sido calificada y calculada.');

    $auth = session('usuario_autenticado');
    $vinculacionSolicitante = DB::table('vinculacion')
        ->where('id_vinculacion', $evaluacion->id_vinc_evaluado)
        ->where('id_funcionario', $auth['id_funcionario'] ?? null)
        ->where('activa', 1)
        ->first();

    abort_unless($vinculacionSolicitante, 403);

    $data = $request->validate([
        'tipo_recurso' => ['required', 'in:REPOSICION,APELACION'],
        'numero_folios' => ['required', 'integer', 'min:1'],
        'motivacion' => ['required', 'string', 'max:3000'],
        'evidencias' => ['required', 'array', 'min:1'],
        'evidencias.*.url' => ['required', 'url', 'max:1000'],
        'evidencias.*.descripcion' => ['nullable', 'string', 'max:200'],
    ], [
        'evidencias.required' => 'Es obligatorio incluir al menos un enlace (link) de evidencia para radicar el recurso.',
        'evidencias.min' => 'Es obligatorio incluir al menos un enlace (link) de evidencia para radicar el recurso.',
        'evidencias.*.url.required' => 'El enlace (link) de evidencia es obligatorio.',
        'evidencias.*.url.url' => 'Cada enlace de evidencia debe ser una URL válida (ej. https://...).',
    ]);

    $evidencias = array_values(array_filter($data['evidencias'] ?? [], function ($e) {
        return !empty(trim((string) ($e['url'] ?? '')));
    }));

    abort_if(count($evidencias) === 0, 422, 'Es obligatorio incluir al menos un enlace (link) de evidencia para radicar el recurso.');

    $yaExistePendiente = DB::table('recurso')
        ->where('id_evaluacion', $id)
        ->where('tipo_recurso', $data['tipo_recurso'])
        ->where('decision', 'PENDIENTE')
        ->exists();

    abort_if($yaExistePendiente, 422, 'Ya existe un recurso de ' . ($data['tipo_recurso'] === 'REPOSICION' ? 'reposición' : 'apelación') . ' pendiente por decidir para esta evaluación.');

    $idReceptor = $data['tipo_recurso'] === 'REPOSICION'
        ? (int) $evaluacion->id_vinc_evaluador
        : resolverVinculacionReceptorApelacion($id);

    $idRecurso = DB::table('recurso')->insertGetId([
        'id_evaluacion' => $id,
        'tipo_recurso' => $data['tipo_recurso'],
        'id_vinc_receptor' => $idReceptor,
        'numero_folios' => $data['numero_folios'],
        'fecha_recurso' => date('Y-m-d'),
        'decision' => 'PENDIENTE',
        'motivacion' => trim($data['motivacion']),
    ]);

    $prefijo = $data['tipo_recurso'] === 'REPOSICION' ? 'REP' : 'APL';
    DB::table('recurso')->where('id_recurso', $idRecurso)->update([
        'numero_radicado' => sprintf('%s-%s-%04d', $prefijo, date('Y'), $idRecurso),
    ]);

    foreach ($evidencias as $evidencia) {
        DB::table('recurso_evidencia')->insert([
            'id_recurso' => $idRecurso,
            'descripcion' => trim((string) ($evidencia['descripcion'] ?? '')) ?: null,
            'url' => trim($evidencia['url']),
        ]);
    }

    return response()->json([
        'success' => true,
        'message' => 'Recurso radicado correctamente (radicado ' . sprintf('%s-%s-%04d', $prefijo, date('Y'), $idRecurso) . ').',
        'recurso' => DB::table('recurso')->where('id_recurso', $idRecurso)->first(),
    ]);
})->name('evaluaciones.recursos.store');


// --- POST: Decidir un recurso (evaluador para reposición, TH/admin para apelación) ---
Route::post('/recursos/{id}/decision', function (Request $request, int $id) {
    abort_unless(session()->has('usuario_autenticado'), 403);

    $recurso = DB::table('recurso')->where('id_recurso', $id)->first();
    abort_unless($recurso, 404);
    abort_unless($recurso->decision === 'PENDIENTE', 422, 'Este recurso ya fue decidido.');

    $evaluacion = DB::table('evaluacion')->where('id_evaluacion', $recurso->id_evaluacion)->first();
    abort_unless($evaluacion, 404);
    abort_if($evaluacion->es_traslado, 422, 'Esta evaluación quedó bloqueada por traslado y solo se puede consultar.');

    $auth = session('usuario_autenticado');
    $rolActivo = $auth['rol_activo'] ?? null;

    $esAdmin = $rolActivo === 'admin';
    $esReceptor = $rolActivo === 'evaluador' && DB::table('vinculacion')
        ->where('id_vinculacion', $recurso->id_vinc_receptor)
        ->where('id_funcionario', $auth['id_funcionario'] ?? null)
        ->exists();

    abort_unless($esAdmin || $esReceptor, 403, 'No tienes permisos para decidir este recurso.');

    $data = $request->validate([
        'decision' => ['required', 'in:APROBADO,NEGADO'],
        'motivacion' => ['required', 'string', 'max:3000'],
    ]);

    // Se conserva la motivación del solicitante y se anexa la de la decisión.
    $motivacionBase = trim((string) ($recurso->motivacion ?? ''));
    $motivacionDecision = trim($data['motivacion']);
    $motivacionFinal = $motivacionBase;
    $motivacionFinal .= ($motivacionFinal ? "\n\n" : '')
        . 'DECISIÓN (' . ($data['decision'] === 'APROBADO' ? 'Favorable' : 'Desfavorable')
        . ', ' . date('Y-m-d') . '): ' . $motivacionDecision;

    DB::table('recurso')->where('id_recurso', $id)->update([
        'decision' => $data['decision'],
        'motivacion' => $motivacionFinal,
        'fecha_decision' => date('Y-m-d'),
    ]);

    if ($data['decision'] === 'APROBADO') {
        DB::table('evaluacion')
            ->where('id_evaluacion', $recurso->id_evaluacion)
            ->update([
                'estado' => 'EN_PROCESO',
            ]);
    }

    $mensaje = $data['decision'] === 'APROBADO'
        ? 'Decisión favorable registrada correctamente. La evaluación ha sido reabierta para su modificación y nuevo cálculo.'
        : 'Decisión registrada correctamente.';

    return response()->json([
        'success' => true,
        'message' => $mensaje,
        'recurso' => DB::table('recurso')->where('id_recurso', $id)->first(),
    ]);
})->name('recursos.decision');


// --- GET: Todos los recursos (vista Talento Humano / admin) ---
Route::get('/recursos', function () {
    abort_unless(session('usuario_autenticado.rol_activo') === 'admin', 403);

    $recursos = DB::table('recurso as r')
        ->join('evaluacion as ev', 'ev.id_evaluacion', '=', 'r.id_evaluacion')
        ->join('vinculacion as ve', 've.id_vinculacion', '=', 'ev.id_vinc_evaluado')
        ->join('funcionario as fe', 'fe.id_funcionario', '=', 've.id_funcionario')
        ->join('vinculacion as va', 'va.id_vinculacion', '=', 'ev.id_vinc_evaluador')
        ->join('funcionario as fa', 'fa.id_funcionario', '=', 'va.id_funcionario')
        ->leftJoin('vinculacion as vrec', 'vrec.id_vinculacion', '=', 'r.id_vinc_receptor')
        ->leftJoin('funcionario as frec', 'frec.id_funcionario', '=', 'vrec.id_funcionario')
        ->select(
            'r.*',
            'fe.nombres as evaluado_nombres',
            'fe.apellidos as evaluado_apellidos',
            'fa.nombres as evaluador_nombres',
            'fa.apellidos as evaluador_apellidos',
            'frec.nombres as receptor_nombres',
            'frec.apellidos as receptor_apellidos'
        )
        ->orderByDesc('r.id_recurso')
        ->get();

    return response()->json(['recursos' => adjuntarEvidenciasRecursos($recursos)]);
})->name('recursos.index');


// --- GET: Recursos por decidir asignados a mí (evaluador / superior jerárquico) ---
Route::get('/recursos/mios', function () {
    abort_unless(session('usuario_autenticado.rol_activo') === 'evaluador', 403);

    $auth = session('usuario_autenticado');

    $misVinculaciones = DB::table('vinculacion')
        ->where('id_funcionario', $auth['id_funcionario'] ?? null)
        ->where('activa', 1)
        ->where('es_evaluador', 1)
        ->pluck('id_vinculacion')
        ->all();

    $recursos = DB::table('recurso as r')
        ->join('evaluacion as ev', 'ev.id_evaluacion', '=', 'r.id_evaluacion')
        ->join('vinculacion as ve', 've.id_vinculacion', '=', 'ev.id_vinc_evaluado')
        ->join('funcionario as fe', 'fe.id_funcionario', '=', 've.id_funcionario')
        ->join('vinculacion as va', 'va.id_vinculacion', '=', 'ev.id_vinc_evaluador')
        ->join('funcionario as fa', 'fa.id_funcionario', '=', 'va.id_funcionario')
        ->leftJoin('vinculacion as vrec', 'vrec.id_vinculacion', '=', 'r.id_vinc_receptor')
        ->leftJoin('funcionario as frec', 'frec.id_funcionario', '=', 'vrec.id_funcionario')
        ->whereIn('r.id_vinc_receptor', $misVinculaciones)
        ->where('r.tipo_recurso', 'APELACION')
        ->where('r.decision', 'PENDIENTE')
        ->select(
            'r.*',
            've.id_vinculacion as evaluado_id_vinc',
            've.cargo as evaluado_cargo',
            'fe.nombres as evaluado_nombres',
            'fe.apellidos as evaluado_apellidos',
            'fa.nombres as evaluador_nombres',
            'fa.apellidos as evaluador_apellidos',
            'frec.nombres as receptor_nombres',
            'frec.apellidos as receptor_apellidos'
        )
        ->orderBy('r.fecha_recurso')
        ->orderByDesc('r.id_recurso')
        ->get();

    return response()->json(['recursos' => adjuntarEvidenciasRecursos($recursos)]);
})->name('recursos.mios');


// --- GET: Estado del plan de mejoramiento de una evaluación ---
Route::get('/evaluaciones/{id}/plan-mejoramiento', function (int $id) {
    abort_unless(session()->has('usuario_autenticado'), 403);

    $evaluacion = getEvaluacionConSistema($id);
    abort_unless($evaluacion, 404);

    $auth = session('usuario_autenticado');
    $rolActivo = $auth['rol_activo'] ?? null;

    if ($rolActivo !== 'admin') {
        $puedeVer = DB::table('vinculacion')
            ->whereIn('id_vinculacion', [$evaluacion->id_vinc_evaluado, $evaluacion->id_vinc_evaluador])
            ->where('id_funcionario', $auth['id_funcionario'] ?? null)
            ->exists();

        abort_unless($puedeVer, 403);
    }

    $plan = DB::table('plan_mejoramiento')->where('id_evaluacion', $id)->first();
    $requiere = evaluacionRequierePlanMejoramiento($evaluacion);

    return response()->json([
        'plan' => $plan,
        'requiere_plan' => $requiere,
        'concertado' => $plan ? ($plan->estado === 'CONCERTADO') : false,
        'bloqueado' => $requiere && (!$plan || $plan->estado !== 'CONCERTADO'),
        'sistema' => $evaluacion->sistema,
        'categoria' => $evaluacion->categoria_final,
    ]);
})->name('evaluaciones.plan-mejoramiento');


// --- POST: Crear / actualizar plan de mejoramiento (evaluador) ---
Route::post('/evaluaciones/{id}/plan-mejoramiento', function (Request $request, int $id) {
    abort_unless(session('usuario_autenticado.rol_activo') === 'evaluador', 403);

    $evaluacion = getEvaluacionConSistema($id);
    abort_unless($evaluacion, 404);
    abort_if($evaluacion->es_traslado, 422, 'Esta evaluación quedó bloqueada por traslado y solo se puede consultar.');
    abort_unless(evaluacionRequierePlanMejoramiento($evaluacion), 422, 'Esta evaluación no requiere plan de mejoramiento según la calificación obtenida.');

    $auth = session('usuario_autenticado');
    $puedeEditar = DB::table('vinculacion')
        ->where('id_vinculacion', $evaluacion->id_vinc_evaluador)
        ->where('id_funcionario', $auth['id_funcionario'] ?? null)
        ->exists();

    abort_unless($puedeEditar, 403);

    $data = $request->validate([
        'descripcion_temas' => ['required', 'string', 'max:5000'],
    ]);

    $plan = DB::table('plan_mejoramiento')->where('id_evaluacion', $id)->first();

    if ($plan) {
        abort_unless(!$plan->firmado_evaluador && $plan->estado === 'PENDIENTE', 422, 'El plan de mejoramiento ya fue firmado; no se puede modificar.');

        DB::table('plan_mejoramiento')->where('id_plan', $plan->id_plan)->update([
            'descripcion_temas' => trim($data['descripcion_temas']),
        ]);
        $idPlan = $plan->id_plan;
    } else {
        $idPlan = DB::table('plan_mejoramiento')->insertGetId([
            'id_evaluacion' => $id,
            'descripcion_temas' => trim($data['descripcion_temas']),
            'fecha_creacion' => date('Y-m-d H:i:s'),
        ]);
    }

    return response()->json([
        'success' => true,
        'message' => 'Plan de mejoramiento guardado correctamente.',
        'plan' => DB::table('plan_mejoramiento')->where('id_plan', $idPlan)->first(),
    ]);
})->name('evaluaciones.plan-mejoramiento.store');


// --- POST: Firmar el plan de mejoramiento (evaluador / evaluado) ---
Route::post('/plan-mejoramiento/{id}/firmar', function (Request $request, int $id) {
    abort_unless(session()->has('usuario_autenticado'), 403);

    $plan = DB::table('plan_mejoramiento')->where('id_plan', $id)->first();
    abort_unless($plan, 404);

    $evaluacion = DB::table('evaluacion')->where('id_evaluacion', $plan->id_evaluacion)->first();
    abort_unless($evaluacion, 404);
    abort_if($evaluacion->es_traslado, 422, 'Esta evaluación quedó bloqueada por traslado y solo se puede consultar.');

    $auth = session('usuario_autenticado');
    $rolActivo = $auth['rol_activo'] ?? null;
    $now = date('Y-m-d H:i:s');

    $update = [];

    if ($rolActivo === 'evaluador') {
        $puede = DB::table('vinculacion')
            ->where('id_vinculacion', $evaluacion->id_vinc_evaluador)
            ->where('id_funcionario', $auth['id_funcionario'] ?? null)
            ->exists();

        abort_unless($puede, 403);
        abort_unless(!$plan->firmado_evaluador, 422, 'El evaluador ya firmó este plan de mejoramiento.');

        $update = [
            'firmado_evaluador' => 1,
            'fecha_firma_evaluador' => $now,
        ];
    } elseif ($rolActivo === 'evaluado') {
        $puede = DB::table('vinculacion')
            ->where('id_vinculacion', $evaluacion->id_vinc_evaluado)
            ->where('id_funcionario', $auth['id_funcionario'] ?? null)
            ->exists();

        abort_unless($puede, 403);
        abort_unless(!$plan->firmado_evaluado, 422, 'El evaluado ya firmó este plan de mejoramiento.');
        abort_unless($plan->firmado_evaluador, 422, 'El evaluador debe firmar el plan de mejoramiento antes que el evaluado.');

        $update = [
            'firmado_evaluado' => 1,
            'fecha_firma_evaluado' => $now,
        ];
    } else {
        abort(403);
    }

    DB::table('plan_mejoramiento')->where('id_plan', $id)->update($update);

    $planActualizado = DB::table('plan_mejoramiento')->where('id_plan', $id)->first();

    if ($planActualizado->firmado_evaluador && $planActualizado->firmado_evaluado) {
        DB::table('plan_mejoramiento')->where('id_plan', $id)->update(['estado' => 'CONCERTADO']);
        $planActualizado = DB::table('plan_mejoramiento')->where('id_plan', $id)->first();
    }

    return response()->json([
        'success' => true,
        'message' => 'Firma del plan de mejoramiento registrada.',
        'plan' => $planActualizado,
    ]);
})->name('plan-mejoramiento.firmar');


// --- GET: Todos los planes de mejoramiento (vista Talento Humano / admin) ---
Route::get('/planes-mejoramiento', function () {
    abort_unless(session('usuario_autenticado.rol_activo') === 'admin', 403);

    $planes = DB::table('plan_mejoramiento as pm')
        ->join('evaluacion as ev', 'ev.id_evaluacion', '=', 'pm.id_evaluacion')
        ->join('periodo as p', 'p.id_periodo', '=', 'ev.id_periodo')
        ->join('vinculacion as ve', 've.id_vinculacion', '=', 'ev.id_vinc_evaluado')
        ->join('funcionario as fe', 'fe.id_funcionario', '=', 've.id_funcionario')
        ->join('vinculacion as va', 'va.id_vinculacion', '=', 'ev.id_vinc_evaluador')
        ->join('funcionario as fa', 'fa.id_funcionario', '=', 'va.id_funcionario')
        ->select(
            'pm.*',
            'p.sistema',
            'ev.categoria_final',
            'ev.calificacion_final',
            'fe.nombres as evaluado_nombres',
            'fe.apellidos as evaluado_apellidos',
            'fa.nombres as evaluador_nombres',
            'fa.apellidos as evaluador_apellidos'
        )
        ->orderByDesc('pm.id_plan')
        ->get();

    return response()->json(['planes' => $planes]);
})->name('planes-mejoramiento.index');


// --- GET: Renuncias a la firma de concertación (renuencia) con testigos ---
Route::get('/renuencias', function () {
    abort_unless(session('usuario_autenticado.rol_activo') === 'admin', 403);

    $renuencias = DB::table('firma as f')
        ->join('evaluacion as ev', 'ev.id_evaluacion', '=', 'f.id_evaluacion')
        ->join('periodo as p', 'p.id_periodo', '=', 'ev.id_periodo')
        ->join('vinculacion as ve', 've.id_vinculacion', '=', 'ev.id_vinc_evaluado')
        ->join('funcionario as fe', 'fe.id_funcionario', '=', 've.id_funcionario')
        ->join('vinculacion as va', 'va.id_vinculacion', '=', 'ev.id_vinc_evaluador')
        ->join('funcionario as fa', 'fa.id_funcionario', '=', 'va.id_funcionario')
        ->where('f.renuencia', 1)
        ->whereIn('f.tipo_firma', ['NOTIFICACION_EVALUADO', 'CONCERTACION_EVALUADOR', 'CONCERTACION_EVALUADO'])
        ->select(
            'f.id_firma',
            'f.id_evaluacion',
            'f.tipo_firma',
            'f.fecha_firma',
            'p.sistema',
            'ev.tipo_evaluacion',
            'fe.nombres as evaluado_nombres',
            'fe.apellidos as evaluado_apellidos',
            'fa.nombres as evaluador_nombres',
            'fa.apellidos as evaluador_apellidos'
        )
        ->orderByDesc('f.fecha_firma')
        ->get();

    foreach ($renuencias as $r) {
        $r->testigos = DB::table('testigo_renuencia')
            ->where('id_firma', $r->id_firma)
            ->select('nombre_testigo', 'cargo_testigo')
            ->orderBy('id_testigo')
            ->get();
    }

    return response()->json(['renuencias' => $renuencias]);
})->name('renuencias.index');


// ============================================================================
// S6 — INFORMES PDF INSTITUCIONALES (SEMESTRAL Y ANUAL)
// Plantillas base: "Informe evaluacion semestral SERAG ok.docx" y
// "Informe evaluacion anual SERAG.docx". Los valores en amarillo son dinámicos.
// Solo el evaluado puede descargar su propio informe.
// ============================================================================

if (!function_exists('prepararInformeSemestral')) {
    function prepararInformeSemestral(int $idEvaluacion): array
    {
        $evaluacion = DB::table('evaluacion as ev')
            ->join('periodo as p', 'p.id_periodo', '=', 'ev.id_periodo')
            ->join('vinculacion as ve', 've.id_vinculacion', '=', 'ev.id_vinc_evaluado')
            ->join('funcionario as fe', 'fe.id_funcionario', '=', 've.id_funcionario')
            ->join('vinculacion as va', 'va.id_vinculacion', '=', 'ev.id_vinc_evaluador')
            ->join('funcionario as fa', 'fa.id_funcionario', '=', 'va.id_funcionario')
            ->where('ev.id_evaluacion', $idEvaluacion)
            ->select('ev.*', 'p.sistema', 'p.fecha_inicio', 'p.fecha_fin', 'p.id_periodo',
                've.cargo as evaluado_cargo', 've.area as evaluado_area', 've.nivel_jerarquico as evaluado_nivel',
                'fe.nombres as evaluado_nombres', 'fe.apellidos as evaluado_apellidos', 'fe.numero_doc as evaluado_doc',
                'va.cargo as evaluador_cargo', 'va.area as evaluador_area', 'va.nivel_jerarquico as evaluador_nivel',
                'va.codigo_cargo as evaluador_codigo', 'va.grado_cargo as evaluador_grado',
                'fa.nombres as evaluador_nombres', 'fa.apellidos as evaluador_apellidos')
            ->first();

        abort_unless($evaluacion, 404);

        $sistema = strtoupper(trim((string) $evaluacion->sistema));

        $evaluador = (object) [
            'nombres' => $evaluacion->evaluador_nombres,
            'apellidos' => $evaluacion->evaluador_apellidos,
            'cargo' => $evaluacion->evaluador_cargo,
            'area' => $evaluacion->evaluador_area,
            'nivel_jerarquico' => $evaluacion->evaluador_nivel,
            'codigo_cargo' => $evaluacion->evaluador_codigo,
            'grado_cargo' => $evaluacion->evaluador_grado,
        ];

        $evaluado = (object) [
            'nombres' => $evaluacion->evaluado_nombres,
            'apellidos' => $evaluacion->evaluado_apellidos,
            'cargo' => $evaluacion->evaluado_cargo,
            'area' => $evaluacion->evaluado_area,
            'nivel_jerarquico' => $evaluacion->evaluado_nivel,
            'numero_doc' => $evaluacion->evaluado_doc,
        ];

        $periodo = (object) [
            'id_periodo' => $evaluacion->id_periodo,
            'sistema' => $sistema,
            'fecha_inicio' => $evaluacion->fecha_inicio,
            'fecha_fin' => $evaluacion->fecha_fin,
        ];

        // Competencias uniendo el catálogo (nombre, afirmación y orden)
        $competencias = DB::table('competencia_evaluada as ce')
            ->join('competencia_catalogo as cc', 'cc.id_competencia', '=', 'ce.id_competencia')
            ->where('ce.id_evaluacion', $idEvaluacion)
            ->orderBy('cc.orden')
            ->get(['cc.nombre as nombre_competencia', 'cc.tipo', 'cc.afirmacion', 'ce.calificacion_definitiva']);

        $competenciasComunes = $competencias->filter(fn ($c) => $c->tipo === 'COMUN')->values();
        $competenciasNivel   = $competencias->filter(fn ($c) => $c->tipo === 'NIVEL_JERARQUICO')->values();

        // Compromisos con metas, observaciones y links de evidencias
        $compromisos = DB::table('compromiso')
            ->where('id_evaluacion', $idEvaluacion)
            ->orderBy('numero_orden')
            ->get(['id_compromiso', 'id_evaluacion', 'numero_orden', 'descripcion', 'porcentaje_peso', 'calificacion_definitiva']);

        $compromisos = $compromisos->map(function ($comp) {
            $comp->metas = DB::table('compromiso_meta')
                ->where('id_compromiso', $comp->id_compromiso)
                ->orderBy('meta')
                ->pluck('meta')
                ->all();

            $observacion = DB::table('compromiso_observacion')
                ->where('id_compromiso', $comp->id_compromiso)
                ->value('texto');
            $comp->observacion = $observacion;

            $comp->links = DB::table('evidencia')
                ->where('id_evaluacion', $comp->id_evaluacion ?? null)
                ->where('id_compromiso', $comp->id_compromiso)
                ->whereNotNull('url_o_ubicacion')
                ->pluck('url_o_ubicacion')
                ->all();

            return $comp;
        });

        // Plan de mejoramiento
        $plan = DB::table('plan_mejoramiento')->where('id_evaluacion', $idEvaluacion)->first();

        // Recursos
        $recursos = DB::table('recurso as r')
            ->leftJoin('vinculacion as vr', 'vr.id_vinculacion', '=', 'r.id_vinc_receptor')
            ->leftJoin('funcionario as fr', 'fr.id_funcionario', '=', 'vr.id_funcionario')
            ->where('r.id_evaluacion', $idEvaluacion)
            ->select('r.*', 'vr.cargo as cargo_receptor', 'fr.nombres as receptor_nombres', 'fr.apellidos as receptor_apellidos')
            ->orderBy('r.fecha_recurso')
            ->get()
            ->map(function ($rec) {
                $rec->evidencias = DB::table('recurso_evidencia')
                    ->where('id_recurso', $rec->id_recurso)
                    ->select('descripcion', 'url')
                    ->get();
                return $rec;
            });

        // Renuencia del evaluado con testigos (en notificación de la nota)
        $renuencias = DB::table('firma')
            ->where('id_evaluacion', $idEvaluacion)
            ->whereIn('tipo_firma', ['NOTIFICACION_EVALUADO', 'CONCERTACION_EVALUADO'])
            ->where('renuencia', 1)
            ->get(['id_firma', 'fecha_firma']);

        foreach ($renuencias as $r) {
            $r->testigos = DB::table('testigo_renuencia')
                ->where('id_firma', $r->id_firma)
                ->select('nombre_testigo', 'cargo_testigo')
                ->get();
        }

        $calculo = calcularNotaEvaluacion($idEvaluacion);

        return [
            'tipo_nombre' => $evaluacion->tipo_evaluacion,
            'sistema' => $sistema,
            'periodo' => $periodo,
            'evaluador' => $evaluador,
            'evaluado' => $evaluado,
            'competencias_comunes' => $competenciasComunes,
            'competencias_nivel' => $competenciasNivel,
            'compromisos' => $compromisos,
            'calculo' => $calculo,
            'plan' => $plan,
            'requiere_plan' => (bool) ($calculo['requiere_plan_mejoramiento'] ?? false),
            'recursos' => $recursos,
            'renuencias' => $renuencias,
            'capacitaciones' => '',
            'escudo' => 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('escudo-color.png'))),
            'logo' => 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('logo.png'))),
        ];
    }
}

if (!function_exists('prepararInformeAnual')) {
    function prepararInformeAnual(int $idEvaluacion): array
    {
        $evaluacion = DB::table('evaluacion as ev')
            ->join('periodo as p', 'p.id_periodo', '=', 'ev.id_periodo')
            ->join('vinculacion as ve', 've.id_vinculacion', '=', 'ev.id_vinc_evaluado')
            ->join('vinculacion as va', 'va.id_vinculacion', '=', 'ev.id_vinc_evaluador')
            ->join('funcionario as fa', 'fa.id_funcionario', '=', 'va.id_funcionario')
            ->where('ev.id_evaluacion', $idEvaluacion)
            ->select('ev.*', 'p.sistema', 'p.anio', 'p.fecha_inicio', 'p.fecha_fin', 'p.id_periodo',
                've.id_vinculacion as id_vinc_evaluado',
                'va.cargo as evaluador_cargo', 'va.area as evaluador_area', 'va.nivel_jerarquico as evaluador_nivel',
                'va.codigo_cargo as evaluador_codigo', 'va.grado_cargo as evaluador_grado',
                'fa.nombres as evaluador_nombres', 'fa.apellidos as evaluador_apellidos')
            ->first();

        abort_unless($evaluacion, 404);

        $sistema = strtoupper(trim((string) $evaluacion->sistema));

        $evaluadoInfo = DB::table('vinculacion as ve')
            ->join('funcionario as fe', 'fe.id_funcionario', '=', 've.id_funcionario')
            ->where('ve.id_vinculacion', $evaluacion->id_vinc_evaluado)
            ->select('fe.nombres', 'fe.apellidos', 've.cargo', 've.area', 've.nivel_jerarquico')
            ->first();

        $evaluador = (object) [
            'nombres' => $evaluacion->evaluador_nombres,
            'apellidos' => $evaluacion->evaluador_apellidos,
            'cargo' => $evaluacion->evaluador_cargo,
            'area' => $evaluacion->evaluador_area,
            'nivel_jerarquico' => $evaluacion->evaluador_nivel,
            'codigo_cargo' => $evaluacion->evaluador_codigo,
            'grado_cargo' => $evaluacion->evaluador_grado,
        ];

        $evaluado = (object) [
            'nombres' => $evaluadoInfo->nombres ?? '',
            'apellidos' => $evaluadoInfo->apellidos ?? '',
            'cargo' => $evaluadoInfo->cargo ?? '',
            'area' => $evaluadoInfo->area ?? '',
            'nivel_jerarquico' => $evaluadoInfo->nivel_jerarquico ?? '',
        ];

        $periodo = (object) [
            'id_periodo' => $evaluacion->id_periodo,
            'sistema' => $sistema,
            'fecha_inicio' => $evaluacion->fecha_inicio,
            'fecha_fin' => $evaluacion->fecha_fin,
        ];

        // Buscar ambos semestres del mismo año/sistema/evaluado
        // (cada semestre vive en un periodo distinto: id_periodo A y B)
        $semestres = DB::table('evaluacion as ev')
            ->join('periodo as p', 'p.id_periodo', '=', 'ev.id_periodo')
            ->where('p.anio', $evaluacion->anio)
            ->where('p.sistema', $evaluacion->sistema)
            ->where('ev.id_vinc_evaluado', $evaluacion->id_vinc_evaluado)
            ->whereIn('ev.tipo_evaluacion', ['SEMESTRE_1', 'SEMESTRE_2'])
            ->where('ev.estado', 'CALIFICADA')
            ->pluck('ev.id_evaluacion', 'ev.tipo_evaluacion');

        $idSemA = $semestres['SEMESTRE_1'] ?? (($evaluacion->tipo_evaluacion === 'SEMESTRE_1') ? $evaluacion->id_evaluacion : null);
        $idSemB = $semestres['SEMESTRE_2'] ?? (($evaluacion->tipo_evaluacion === 'SEMESTRE_2') ? $evaluacion->id_evaluacion : null);

        $calcSemA = $idSemA ? calcularNotaEvaluacion((int) $idSemA) : null;
        $calcSemB = $idSemB ? calcularNotaEvaluacion((int) $idSemB) : null;

        $notaSemA = $calcSemA['nota_definitiva'] ?? null;
        $notaSemB = $calcSemB['nota_definitiva'] ?? null;

        $notaAnual = null;
        if ($notaSemA !== null && $notaSemB !== null) {
            $notaAnual = round(($notaSemA + $notaSemB) / 2, 2);
        }

        $categoria = match (true) {
            $notaAnual >= 91 => 'SOBRESALIENTE',
            $notaAnual >= 81 => 'BUENO',
            $notaAnual >= 71 => 'APROBADO_MEJORA',
            $notaAnual !== null => 'NO_SATISFACTORIO',
            default => '',
        };

        return [
            'sistema' => $sistema,
            'periodo' => $periodo,
            'evaluador' => $evaluador,
            'evaluado' => $evaluado,
            'tiene_semestre_a' => $idSemA !== null,
            'nota_semestre_a' => $notaSemA,
            'nota_semestre_b' => $notaSemB,
            'nota_anual' => $notaAnual,
            'categoria' => $categoria,
            'capacitaciones' => '',
            'escudo' => 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('escudo-color.png'))),
            'logo' => 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('logo.png'))),
        ];
    }
}

if (!function_exists('descargarInformePdf')) {
    function descargarInformePdf(string $vista, array $info, string $filename, string $orientacion = 'portrait')
    {
        $generadoEn = now('America/Bogota');
        $html = view($vista, compact('info', 'generadoEn'))->render();

        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', $orientacion);
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-store',
        ]);
    }
}

// --- GET: Informe semestral en PDF (solo evaluado) ---
Route::get('/evaluaciones/{id}/informe', function (int $id) {
    abort_unless(session('usuario_autenticado.rol_activo') === 'evaluado', 403);

    $evaluacion = DB::table('evaluacion')->where('id_evaluacion', $id)->first();
    abort_unless($evaluacion, 404);

    $auth = session('usuario_autenticado');
    $esEvaluado = DB::table('vinculacion')
        ->where('id_vinculacion', $evaluacion->id_vinc_evaluado)
        ->where('id_funcionario', $auth['id_funcionario'] ?? null)
        ->exists();
    abort_unless($esEvaluado, 403);

    $info = prepararInformeSemestral($id);
    $nombre = 'Informe_Evaluacion_Semestral_' . $id . '.pdf';

    return descargarInformePdf('reportes.informe-semestral', $info, $nombre, 'landscape');
})->name('evaluaciones.informe');

// --- GET: Informe anual en PDF (solo evaluado, promedia ambos semestres) ---
Route::get('/evaluaciones/{id}/informe-anual', function (int $id) {
    abort_unless(session('usuario_autenticado.rol_activo') === 'evaluado', 403);

    $evaluacion = DB::table('evaluacion')->where('id_evaluacion', $id)->first();
    abort_unless($evaluacion, 404);

    $auth = session('usuario_autenticado');
    $esEvaluado = DB::table('vinculacion')
        ->where('id_vinculacion', $evaluacion->id_vinc_evaluado)
        ->where('id_funcionario', $auth['id_funcionario'] ?? null)
        ->exists();
    abort_unless($esEvaluado, 403);

    $info = prepararInformeAnual($id);
    $nombre = 'Informe_Evaluacion_Anual_' . $id . '.pdf';

    return descargarInformePdf('reportes.informe-anual', $info, $nombre, 'portrait');
})->name('evaluaciones.informe-anual');
