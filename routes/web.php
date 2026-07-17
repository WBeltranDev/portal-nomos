<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Exception; // <-- Corrección: Importación de la clase global Exception para evitar errores 500

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

function getPonderacionesConfig() {
    $configData = defaultPonderacionesConfig();
    $jsonPath = storage_path('app/ponderaciones.json');

    if (file_exists($jsonPath)) {
        $storedData = json_decode(file_get_contents($jsonPath), true) ?? [];
        foreach ($storedData as $sistema => $vals) {
            if (!isset($configData[$sistema]) || !is_array($vals)) {
                continue;
            }

            $configData[$sistema] = array_merge($configData[$sistema], [
                'peso_compromisos' => (float) ($vals['peso_compromisos'] ?? $configData[$sistema]['peso_compromisos']),
                'peso_competencias' => (float) ($vals['peso_competencias'] ?? $configData[$sistema]['peso_competencias']),
                'peso_docencia' => (float) ($vals['peso_docencia'] ?? $configData[$sistema]['peso_docencia']),
                'peso_investigacion' => (float) ($vals['peso_investigacion'] ?? $configData[$sistema]['peso_investigacion']),
                'peso_proyeccion_social' => (float) ($vals['peso_proyeccion_social'] ?? $configData[$sistema]['peso_proyeccion_social']),
            ]);
        }
    }

    $configData['RENDIMIENTO_LABORAL']['peso_docencia'] = 0.0;
    $configData['RENDIMIENTO_LABORAL']['peso_investigacion'] = 0.0;
    $configData['RENDIMIENTO_LABORAL']['peso_proyeccion_social'] = 0.0;

    return $configData;
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
        $jsonPath = storage_path('app/evaluacion_ejes.json');
        $ejesData = [];
        if (file_exists($jsonPath)) {
            $ejesData = json_decode(file_get_contents($jsonPath), true) ?? [];
        }

        $ejes = $ejesData[$id_evaluacion] ?? [
            'investigacion' => false,
            'proyeccion_social' => false
        ];

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

function resolveOpenPeriodForVinculacion(int $idVinculacion, ?int $idPeriodo = null) {
    $query = DB::table('periodo as p')
        ->join('vinculacion as v', function ($join) {
            $join->on(DB::raw('UPPER(TRIM(v.sistema_evaluacion))'), '=', DB::raw('UPPER(TRIM(p.sistema))'));
        })
        ->where('v.id_vinculacion', $idVinculacion)
        ->where('p.estado', 'ABIERTO');

    if (!empty($idPeriodo)) {
        $query->where('p.id_periodo', $idPeriodo);
    }

    return $query->select('p.*')->orderByDesc('p.id_periodo')->first();
}

function getEvaluadorAsignaciones(): array {
    $jsonPath = storage_path('app/evaluador_asignaciones.json');
    if (!file_exists($jsonPath)) {
        return [];
    }

    $data = json_decode(file_get_contents($jsonPath), true);
    if (!is_array($data)) {
        return [];
    }

    return array_values(array_filter($data, function ($row) {
        return isset($row['id_vinc_evaluador'], $row['id_vinc_evaluado']);
    }));
}

function evaluadorTieneEvaluadoAsignado(int $idVincEvaluador, int $idVincEvaluado): bool {
    foreach (getEvaluadorAsignaciones() as $asignacion) {
        if ((int) $asignacion['id_vinc_evaluador'] === $idVincEvaluador
            && (int) $asignacion['id_vinc_evaluado'] === $idVincEvaluado) {
            return true;
        }
    }

    return false;
}

function guardarEvaluadorAsignacion(int $idVincEvaluador, int $idVincEvaluado): void {
    $asignaciones = getEvaluadorAsignaciones();

    if (!evaluadorTieneEvaluadoAsignado($idVincEvaluador, $idVincEvaluado)) {
        $asignaciones[] = [
            'id_vinc_evaluador' => $idVincEvaluador,
            'id_vinc_evaluado' => $idVincEvaluado,
            'fecha_asignacion' => date('Y-m-d H:i:s'),
        ];
    }

    file_put_contents(storage_path('app/evaluador_asignaciones.json'), json_encode($asignaciones, JSON_PRETTY_PRINT));
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

    $user = DB::table('usuario as u')
        ->where('u.username', $credentials['correo'])
        ->where('u.activo', 1)
        ->first();

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

Route::get('/dashboard', function () {
    abort_unless(session()->has('usuario_autenticado'), 403);

    if (! session('usuario_autenticado.rol_activo')) {
        return redirect('/seleccionar-rol');
    }

    $usuario = session('usuario_autenticado');
    $rolActivo = session('usuario_autenticado.rol_activo');

    // Default empty collections
    $usuarios = collect();
    $empleados = collect();
    $evaluaciones = collect();
    $periodos = collect();
    $ponderaciones = collect();
    $evaluacionesEvaluador = collect();
    $evaluacionesEvaluado = collect();
    $evaluadosDisponibles = collect();
    $miVinculacionEvaluador = null;

    // 1. Data for Admin
    if ($rolActivo === 'admin') {
        $usuarios = DB::table('usuario as u')
            ->leftJoin('funcionario as f', 'f.id_usuario', '=', 'u.id_usuario')
            ->select('u.id_usuario', 'u.username as correo_institucional', 'u.rol', 'f.nombres', 'f.apellidos', 'f.tipo_documento', 'f.numero_doc as documento_identidad')
            ->orderBy('f.apellidos')
            ->get();

        $empleados = DB::table('funcionario as f')
            ->leftJoin('vinculacion as v', function($join) {
                $join->on('v.id_funcionario', '=', 'f.id_funcionario')->where('v.activa', '=', 1);
            })
            ->select('f.id_funcionario', 'f.nombres', 'f.apellidos', 'f.correo_cargo as correo_institucional', 'f.numero_doc as documento_identidad', 'f.tipo_documento', 'v.cargo as nombre_cargo', 'v.area as nombre_area', 'v.activa as activo', 'v.id_vinculacion', 'v.es_evaluador')
            ->orderBy('f.apellidos')
            ->get();

        $evaluaciones = DB::table('evaluacion as ev')
            ->join('vinculacion as ve', 've.id_vinculacion', '=', 'ev.id_vinc_evaluado')
            ->join('funcionario as fe', 'fe.id_funcionario', '=', 've.id_funcionario')
            ->join('vinculacion as va', 'va.id_vinculacion', '=', 'ev.id_vinc_evaluador')
            ->join('funcionario as fa', 'fa.id_funcionario', '=', 'va.id_funcionario')
            ->join('periodo as p', 'p.id_periodo', '=', 'ev.id_periodo')
            ->select('ev.id_evaluacion', 'ev.estado', 'p.fecha_inicio', 'p.fecha_fin', 'ev.tipo_evaluacion as tipo_nombre', 'fe.nombres as evaluado_nombres', 'fe.apellidos as evaluado_apellidos', 'fa.nombres as evaluador_nombres', 'fa.apellidos as evaluador_apellidos', 'p.sistema')
            ->orderByDesc('ev.id_evaluacion')
            ->get();

        $periodos = DB::table('periodo')->orderByDesc('id_periodo')->get();

        $configData = getPonderacionesConfig();
        $ponderacionesList = [];
        foreach ($configData as $sistema => $vals) {
            $ponderacionesList[] = (object) array_merge(['sistema' => $sistema], $vals);
        }
        $ponderaciones = collect($ponderacionesList);
    }

    // 2. Data for Evaluador
    if ($rolActivo === 'evaluador' && $usuario['id_funcionario']) {
        $miVinculacionEvaluador = DB::table('vinculacion')
            ->where('id_funcionario', $usuario['id_funcionario'])
            ->where('activa', 1)
            ->where('es_evaluador', 1)
            ->orderByDesc('id_vinculacion')
            ->first();

        $evaluacionesEvaluador = DB::table('evaluacion as ev')
            ->join('vinculacion as ve', 've.id_vinculacion', '=', 'ev.id_vinc_evaluado')
            ->join('funcionario as fe', 'fe.id_funcionario', '=', 've.id_funcionario')
            ->join('vinculacion as va', 'va.id_vinculacion', '=', 'ev.id_vinc_evaluador')
            ->where('va.id_funcionario', $usuario['id_funcionario'])
            ->join('periodo as p', 'p.id_periodo', '=', 'ev.id_periodo')
            ->leftJoin('firma as f_ev', function($join) {
                $join->on('f_ev.id_evaluacion', '=', 'ev.id_evaluacion')
                     ->where('f_ev.tipo_firma', '=', 'CONCERTACION_EVALUADO');
            })
            ->leftJoin('firma as f_er', function($join) {
                $join->on('f_er.id_evaluacion', '=', 'ev.id_evaluacion')
                     ->where('f_er.tipo_firma', '=', 'CONCERTACION_EVALUADOR');
            })
            ->select('ev.id_evaluacion', 'ev.estado', 'p.fecha_inicio', 'p.fecha_fin', 'ev.tipo_evaluacion as tipo_nombre', 'fe.nombres as evaluado_nombres', 'fe.apellidos as evaluado_apellidos', 'p.sistema', 've.cargo as evaluado_cargo', 've.area as evaluado_area', 'ev.fase_actual', 've.aplica_eje_misional', 'ev.concertacion_firmada', DB::raw('IF(f_ev.id_firma IS NOT NULL, 1, 0) as evaluado_firmado'), DB::raw('IF(f_er.id_firma IS NOT NULL, 1, 0) as evaluador_firmado'))
            ->orderByDesc('ev.id_evaluacion')
            ->get();

        if ($miVinculacionEvaluador) {
            $idsEvaluadosAsignados = collect(getEvaluadorAsignaciones())
                ->where('id_vinc_evaluador', $miVinculacionEvaluador->id_vinculacion)
                ->pluck('id_vinc_evaluado')
                ->unique()
                ->values()
                ->all();

            if (!empty($idsEvaluadosAsignados)) {
                $evaluadosDisponibles = DB::table('vinculacion as v')
                    ->join('funcionario as f', 'f.id_funcionario', '=', 'v.id_funcionario')
                    ->whereIn('v.id_vinculacion', $idsEvaluadosAsignados)
                    ->where('v.activa', 1)
                    ->select(
                        'v.id_vinculacion',
                        'v.cargo',
                        'v.codigo_cargo',
                        'v.grado_cargo',
                        'v.nivel_jerarquico',
                        'v.area',
                        'v.tipo_vinculacion',
                        'v.sistema_evaluacion',
                        'v.es_evaluador',
                        'v.aplica_eje_misional',
                        'v.fecha_ingreso',
                        'v.fecha_retiro',
                        'v.resolucion',
                        'f.nombres',
                        'f.apellidos',
                        'f.numero_doc',
                        'f.correo_cargo'
                    )
                    ->orderBy('v.area')
                    ->orderBy('f.apellidos')
                    ->get();
            }
        }
    }

    // 3. Data for Evaluado
    if ($rolActivo === 'evaluado' && $usuario['id_funcionario']) {
        $evaluacionesEvaluado = DB::table('evaluacion as ev')
            ->join('vinculacion as ve', 've.id_vinculacion', '=', 'ev.id_vinc_evaluado')
            ->where('ve.id_funcionario', $usuario['id_funcionario'])
            ->join('vinculacion as va', 'va.id_vinculacion', '=', 'ev.id_vinc_evaluador')
            ->join('funcionario as fa', 'fa.id_funcionario', '=', 'va.id_funcionario')
            ->join('periodo as p', 'p.id_periodo', '=', 'ev.id_periodo')
            ->leftJoin('firma as f_ev', function($join) {
                $join->on('f_ev.id_evaluacion', '=', 'ev.id_evaluacion')
                    ->where('f_ev.tipo_firma', '=', 'CONCERTACION_EVALUADO');
            })
            ->leftJoin('firma as f_er', function($join) {
                $join->on('f_er.id_evaluacion', '=', 'ev.id_evaluacion')
                    ->where('f_er.tipo_firma', '=', 'CONCERTACION_EVALUADOR');
            })
            ->select('ev.id_evaluacion', 'ev.estado', 'p.fecha_inicio', 'p.fecha_fin', 'ev.tipo_evaluacion as tipo_nombre', 'fa.nombres as evaluador_nombres', 'fa.apellidos as evaluador_apellidos', 'p.sistema', 've.cargo as evaluado_cargo', 've.area as evaluado_area', 'ev.concertacion_firmada', 'ev.fase_actual', 've.aplica_eje_misional', DB::raw('IF(f_ev.id_firma IS NOT NULL, 1, 0) as evaluado_firmado'), DB::raw('IF(f_er.id_firma IS NOT NULL, 1, 0) as evaluador_firmado'))
            ->orderByDesc('ev.id_evaluacion')
            ->get();
    }

    // Support lists
    $configData = getPonderacionesConfig();
    $acuerdosRL = isset($configData['RENDIMIENTO_LABORAL']) 
        ? (object) array_merge(['sistema' => 'RENDIMIENTO_LABORAL'], $configData['RENDIMIENTO_LABORAL']) 
        : null;
    $acuerdosAG = isset($configData['ACUERDO_GESTION']) 
        ? (object) array_merge(['sistema' => 'ACUERDO_GESTION'], $configData['ACUERDO_GESTION']) 
        : null;
    $ponderacionesConfig = $configData;

    return view('dashboard', compact(
        'usuario', 'rolActivo', 'usuarios', 'empleados', 'evaluaciones',
        'periodos', 'ponderaciones', 'evaluacionesEvaluador', 'evaluacionesEvaluado',
        'evaluadosDisponibles', 'miVinculacionEvaluador', 'acuerdosRL', 'acuerdosAG',
        'ponderacionesConfig'
    ));
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
        'investigacion' => ['nullable', 'boolean'],
        'proyeccion_social' => ['nullable', 'boolean'],
    ]);

    $auth = session('usuario_autenticado');
    $miVinc = DB::table('vinculacion')
        ->where('id_funcionario', $auth['id_funcionario'])
        ->where('activa', 1)
        ->where('es_evaluador', 1)
        ->first();

    abort_unless($miVinc, 403);

    $evaluadoVinc = DB::table('vinculacion')
        ->where('id_vinculacion', $data['id_vinc_evaluado'])
        ->where('activa', 1)
        ->first();

    abort_unless($evaluadoVinc, 403);

    abort_unless(evaluadorTieneEvaluadoAsignado($miVinc->id_vinculacion, (int) $data['id_vinc_evaluado']), 403);

    $periodo = resolveOpenPeriodForVinculacion($data['id_vinc_evaluado'], $data['id_periodo'] ?? null);

    abort_unless($periodo, 403);

    abort_unless(
        strtoupper(trim((string) $periodo->sistema)) === strtoupper(trim((string) $evaluadoVinc->sistema_evaluacion)),
        403
    );

    $exists = DB::table('evaluacion')
        ->where('id_periodo', $periodo->id_periodo)
        ->where('id_vinc_evaluado', $data['id_vinc_evaluado'])
        ->where('tipo_evaluacion', $data['tipo_evaluacion'])
        ->exists();

    if ($exists) {
        return back()->withErrors(['asignaciones' => 'Ya existe una evaluacion para este funcionario en este perodo y ciclo.']);
    }

    $evaluacionId = DB::table('evaluacion')->insertGetId([
        'id_periodo' => $periodo->id_periodo,
        'id_vinc_evaluado' => $data['id_vinc_evaluado'],
        'id_vinc_evaluador' => $miVinc->id_vinculacion,
        'tipo_evaluacion' => $data['tipo_evaluacion'],
        'fase_actual' => 1,
        'concertacion_firmada' => 0,
        'estado' => 'EN_PROCESO',
        'dias_laborados' => $data['dias_laborados'],
    ]);

    if (strtoupper(trim((string) $periodo->sistema)) === 'ACUERDO_GESTION' && $evaluadoVinc->aplica_eje_misional) {
        $jsonPath = storage_path('app/evaluacion_ejes.json');
        $ejesData = [];
        if (file_exists($jsonPath)) {
            $ejesData = json_decode(file_get_contents($jsonPath), true) ?? [];
        }

        $ejesData[$evaluacionId] = [
            'investigacion' => (bool) ($data['investigacion'] ?? false),
            'proyeccion_social' => (bool) ($data['proyeccion_social'] ?? false),
        ];

        file_put_contents($jsonPath, json_encode($ejesData, JSON_PRETTY_PRINT));
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

    $jsonPath = storage_path('app/ponderaciones.json');
    $configData = getPonderacionesConfig();

    $configData[$data['sistema']] = [
        'peso_compromisos' => (float)$data['peso_compromisos'],
        'peso_competencias' => (float)$data['peso_competencias'],
        'peso_docencia' => (float)$data['peso_docencia'],
        'peso_investigacion' => (float)$data['peso_investigacion'],
        'peso_proyeccion_social' => (float)$data['peso_proyeccion_social'],
    ];

    file_put_contents($jsonPath, json_encode($configData, JSON_PRETTY_PRINT));

    return back()->with('success_ponderacion', 'Ponderaciones actualizadas correctamente.');
})->name('admin.ponderaciones.update');


// --- ASIGNACIN DE evaluacionES ---
Route::post('/admin/asignaciones', function (Request $request) {
    abort_unless(session('usuario_autenticado.rol_activo') === 'admin', 403);

    $data = $request->validate([
        'id_vinc_evaluado' => ['required', 'integer', 'exists:vinculacion,id_vinculacion'],
        'id_vinc_evaluador' => ['required', 'integer', 'exists:vinculacion,id_vinculacion', 'different:id_vinc_evaluado'],
    ]);

    $evaluado = DB::table('vinculacion')
        ->where('id_vinculacion', $data['id_vinc_evaluado'])
        ->where('activa', 1)
        ->first();

    $evaluador = DB::table('vinculacion')
        ->where('id_vinculacion', $data['id_vinc_evaluador'])
        ->where('activa', 1)
        ->where('es_evaluador', 1)
        ->first();

    abort_unless($evaluado && $evaluador, 403);

    guardarEvaluadorAsignacion((int) $data['id_vinc_evaluador'], (int) $data['id_vinc_evaluado']);

    return back()->with('success_asignacion', 'Evaluado asignado al evaluador correctamente.');
})->name('admin.asignaciones.store');


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
            $correo = trim($data['correo'] ?? $data['correo_institucional'] ?? '');
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
        ->exists();

    $evaluadorFirmado = DB::table('firma')
        ->where('id_evaluacion', $id)
        ->where('tipo_firma', 'CONCERTACION_EVALUADOR')
        ->exists();

    return response()->json([
        'compromisos' => $compromisos,
        'evidencias' => $evidencias,
        'observaciones' => getEvaluacionObservaciones($id),
        'estado' => [
            'evaluado_firmado' => $evaluadoFirmado,
            'evaluador_firmado' => $evaluadorFirmado,
            'congelada' => (bool) $evaluacion->concertacion_firmada,
            'fase_actual' => $evaluacion->fase_actual,
        ],
    ]);
});

Route::get('/evaluaciones/{id}/ejes', function (int $id) {
    abort_unless(session()->has('usuario_autenticado'), 403);
    
    $jsonPath = storage_path('app/evaluacion_ejes.json');
    $ejesData = [];
    if (file_exists($jsonPath)) {
        $ejesData = json_decode(file_get_contents($jsonPath), true) ?? [];
    }

    $ejes = $ejesData[$id] ?? [
        'investigacion' => false,
        'proyeccion_social' => false
    ];

    return response()->json($ejes);
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

    $data = $request->validate([
        'investigacion' => ['required', 'boolean'],
        'proyeccion_social' => ['required', 'boolean'],
    ]);

    $jsonPath = storage_path('app/evaluacion_ejes.json');
    $ejesData = [];
    if (file_exists($jsonPath)) {
        $ejesData = json_decode(file_get_contents($jsonPath), true) ?? [];
    }

    $ejesData[$id] = [
        'investigacion' => (bool)$data['investigacion'],
        'proyeccion_social' => (bool)$data['proyeccion_social'],
    ];

    file_put_contents($jsonPath, json_encode($ejesData, JSON_PRETTY_PRINT));

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

    if (!$evaluacion->concertacion_firmada) {
        return response()->json(['message' => 'Debes esperar a que el evaluador y el evaluado firmen la concertación antes de registrar evidencias.'], 422);
    }

    $data = $request->validate([
        'id_compromiso' => ['required', 'integer'],
        'descripcion' => ['nullable', 'string', 'max:500'],
        'url' => ['required', 'url', 'max:1000'],
    ]);

    $compromiso = DB::table('compromiso')
        ->where('id_compromiso', $data['id_compromiso'])
        ->where('id_evaluacion', $id)
        ->first();

    abort_unless($compromiso, 422);

    DB::table('evidencia')->insert([
        'id_evaluacion' => $id,
        'id_compromiso' => $compromiso->id_compromiso,
        'descripcion' => ($data['descripcion'] ?? null) ?: 'Evidencia registrada',
        'tipo_evidencia' => 'LINK',
        'url_o_ubicacion' => $data['url'],
        'fecha_inclusion' => date('Y-m-d H:i:s'),
        'id_vinc_registra' => $vinculacionRegistra->id_vinculacion,
    ]);

    return response()->json(['success' => true]);
})->name('evaluaciones.evidencias.store');

Route::post('/evaluaciones/{id}/compromisos', function (Request $request, int $id) {
    abort_unless(session('usuario_autenticado.rol_activo') === 'evaluador', 403);

    $evaluacion = DB::table('evaluacion')->where('id_evaluacion', $id)->first();
    abort_unless($evaluacion, 404);

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

    if ($evaluacion->concertacion_firmada) {
        return back()->withErrors(['firma' => 'Esta concertación ya se encuentra firmada.']);
    }

    $rolActivo = session('usuario_autenticado.rol_activo');
    $auth = session('usuario_autenticado');

    if ($rolActivo === 'evaluador') {
        abort_unless($evaluacion->id_vinc_evaluador == $auth['id_funcionario'], 403);

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

        DB::table('firma')->updateOrInsert(
            ['id_evaluacion' => $id, 'tipo_firma' => 'CONCERTACION_EVALUADOR'],
            [
                'id_vinc_firmante' => $auth['id_funcionario'],
                'fecha_firma' => date('Y-m-d H:i:s'),
                'renuencia' => 0
            ]
        );
    } elseif ($rolActivo === 'evaluado') {
        abort_unless($evaluacion->id_vinc_evaluado == $auth['id_funcionario'], 403);

        $evaluadorFirmado = DB::table('firma')
            ->where('id_evaluacion', $id)
            ->where('tipo_firma', 'CONCERTACION_EVALUADOR')
            ->exists();

        if (!$evaluadorFirmado) {
            return back()->withErrors(['firma' => 'El evaluador debe proponer y firmar la concertación antes de que el evaluado pueda revisarla y firmar.']);
        }

        DB::table('firma')->updateOrInsert(
            ['id_evaluacion' => $id, 'tipo_firma' => 'CONCERTACION_EVALUADO'],
            [
                'id_vinc_firmante' => $auth['id_funcionario'],
                'fecha_firma' => date('Y-m-d H:i:s'),
                'renuencia' => 0
            ]
        );
    } else {
        abort(403);
    }

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