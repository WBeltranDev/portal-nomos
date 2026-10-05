<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request for the dashboard based on user role.
     */
    public function index(Request $request)
    {
        abort_unless(session()->has('usuario_autenticado'), 403);

        if (! session('usuario_autenticado.rol_activo')) {
            return redirect('/seleccionar-rol');
        }

        $usuario = session('usuario_autenticado');
        $rolActivo = session('usuario_autenticado.rol_activo');

        // Refrescar roles si la sesión no los trae (p.ej. sesión antigua o cambio manual)
        if (! isset($usuario['roles']) || ! is_array($usuario['roles']) || empty($usuario['roles'])) {
            $roles = [];

            // La sesión no guarda la clave 'rol': se consulta desde la tabla usuario.
            $rolUsuario = DB::table('usuario')
                ->where('id_usuario', $usuario['id_usuario'] ?? 0)
                ->value('rol');

            if ($rolUsuario === 'ADMINISTRADOR') {
                $roles[] = 'admin';
            }

            if ($usuario['id_funcionario'] ?? null) {
                $vinculaciones = DB::table('vinculacion')
                    ->where('id_funcionario', $usuario['id_funcionario'])
                    ->where('activa', 1)
                    ->get();
                if ($vinculaciones->isNotEmpty()) {
                    $roles[] = 'evaluado';
                    $vincIds = $vinculaciones->pluck('id_vinculacion')->all();
                    $esEvaluadorPorVinculacion = $vinculaciones->contains(
                        fn ($v) => (bool) $v->es_evaluador
                    );
                    $esDelegadoActivo = DB::table('delegacion')
                        ->whereIn('id_vinc_delegado', $vincIds)
                        ->where('estado', 'ACTIVA')
                        ->exists();
                    $tieneAsignacionesEvaluador = DB::table('evaluador_asignacion')
                        ->whereIn('id_vinc_evaluador', $vincIds)
                        ->exists();
                    $esEvaluadorActivo = $rolUsuario === 'EVALUADOR';
                    if ($esEvaluadorActivo || $esEvaluadorPorVinculacion || $esDelegadoActivo || $tieneAsignacionesEvaluador) {
                        $roles[] = 'evaluador';
                    }
                }
            }
            $roles = array_values(array_unique($roles));
            if (empty($roles)) {
                $roles[] = 'evaluado';
            }
            $usuario['roles'] = $roles;
            session()->put('usuario_autenticado', $usuario);
        }

        // Default empty collections
        $usuarios = collect();
        $empleados = collect();
        $evaluaciones = collect();
        $evaluacionesAdmin = collect();
        $periodos = collect();
        $ponderaciones = collect();
        $periodosParciales = collect();
        $funcionariosParaPeriodoParcial = collect();
        $evaluacionesEvaluador = collect();
        $evaluacionesEvaluado = collect();
        $informesEvaluador = collect();
        $evaluadosDisponibles = collect();
        $evaluacionesInstanciaExterna = collect();
        $planesPendientesEvaluador = collect();
        $miVinculacionEvaluador = null;
        $vinculacionesReemplazo = collect();
        $evaluadoresDelegacion = collect();
        $delegadosDisponibles = collect();
        $impedimentos = collect();
        $cargosCatalogo = collect();
        $dependenciasCatalogo = collect();
        $funcionariosNoCalificados = collect();
        $evaluacionesExtratiempo = collect();
        $historialExtratiempo = collect();
        $vinculacionesJerarquia = collect();
        $jefesDisponibles = collect();

        // 1. Data for Admin
        if ($rolActivo === 'admin') {
            $usuarios = DB::table('usuario as u')
                ->leftJoin('funcionario as f', 'f.id_usuario', '=', 'u.id_usuario')
                ->select(
                    'u.id_usuario',
                    'u.username as correo_institucional',
                    'u.rol',
                    'u.activo as usuario_activo',
                    'f.id_funcionario',
                    'f.nombres',
                    'f.apellidos',
                    'f.tipo_documento',
                    'f.numero_doc as documento_identidad'
                )
                ->orderBy('f.apellidos')
                ->get();

            $empleados = DB::table('funcionario as f')
                ->leftJoin('vinculacion as v', function ($join) {
                    $join->on('v.id_funcionario', '=', 'f.id_funcionario')->where('v.activa', '=', 1);
                })
                ->select(
                    'f.id_funcionario',
                    'f.nombres',
                    'f.apellidos',
                    'f.correo_cargo as correo_institucional',
                    'f.numero_doc as documento_identidad',
                    'f.tipo_documento',
                    'v.cargo as nombre_cargo',
                    'v.area as nombre_area',
                    'v.activa as activo',
                    'v.id_vinculacion',
                    'v.es_evaluador',
                    'v.id_vinc_jefe',
                    DB::raw('IFNULL(v.es_vacante, 0) as es_vacante')
                )
                ->orderBy('f.apellidos')
                ->get();

            // Catálogo de Cargos
            if (Schema::hasTable('cargo')) {
                $cargosCatalogo = DB::table('cargo')->orderBy('nombre')->get();
            } else {
                $cargosCatalogo = DB::table('vinculacion')
                    ->whereNotNull('cargo')
                    ->where('cargo', '!=', '')
                    ->select('cargo as nombre', 'codigo_cargo', 'grado_cargo', 'nivel_jerarquico', DB::raw('1 as activo'))
                    ->distinct()
                    ->get();
            }

            // Catálogo de Dependencias / Áreas
            if (Schema::hasTable('dependencia')) {
                $dependenciasCatalogo = DB::table('dependencia')->orderBy('nombre')->get();
            } else {
                $dependenciasCatalogo = DB::table('vinculacion')
                    ->whereNotNull('area')
                    ->where('area', '!=', '')
                    ->select('area as nombre', DB::raw('1 as activa'))
                    ->distinct()
                    ->get();
            }

            // Vinculaciones para selección de traslados
            $vinculacionesReemplazo = DB::table('vinculacion as v')
                ->join('funcionario as f', 'f.id_funcionario', '=', 'v.id_funcionario')
                ->select(
                    'v.id_vinculacion',
                    'v.activa',
                    'v.cargo',
                    'v.area',
                    'f.nombres',
                    'f.apellidos'
                )
                ->orderBy('v.activa', 'desc')
                ->orderBy('f.apellidos')
                ->get();

            $evaluaciones = DB::table('evaluacion as ev')
                ->join('vinculacion as ve', 've.id_vinculacion', '=', 'ev.id_vinc_evaluado')
                ->join('funcionario as fe', 'fe.id_funcionario', '=', 've.id_funcionario')
                ->join('vinculacion as va', 'va.id_vinculacion', '=', 'ev.id_vinc_evaluador')
                ->join('funcionario as fa', 'fa.id_funcionario', '=', 'va.id_funcionario')
                ->join('periodo as p', 'p.id_periodo', '=', 'ev.id_periodo')
                ->select(
                    'ev.id_evaluacion',
                    'ev.estado',
                    'ev.fase_actual',
                    'ev.concertacion_firmada',
                    'p.anio',
                    'p.semestre',
                    'p.fecha_inicio',
                    'p.fecha_fin',
                    'ev.tipo_evaluacion as tipo_nombre',
                    'ev.es_traslado',
                    'fe.nombres as evaluado_nombres',
                    'fe.apellidos as evaluado_apellidos',
                    'fa.nombres as evaluador_nombres',
                    'fa.apellidos as evaluador_apellidos',
                    'p.sistema'
                )
                ->orderByDesc('ev.id_evaluacion')
                ->get();

            // Vinculaciones para configuración de jerarquía (superior jerárquico)
            $vinculacionesJerarquia = DB::table('vinculacion as v')
                ->join('funcionario as f', 'f.id_funcionario', '=', 'v.id_funcionario')
                ->leftJoin('vinculacion as vj', 'vj.id_vinculacion', '=', 'v.id_vinc_jefe')
                ->leftJoin('funcionario as fj', 'fj.id_funcionario', '=', 'vj.id_funcionario')
                ->select(
                    'v.id_vinculacion',
                    'v.cargo',
                    'v.area',
                    'v.nivel_jerarquico',
                    'v.es_evaluador',
                    'v.es_vacante',
                    'v.activa',
                    'v.id_vinc_jefe',
                    'f.nombres',
                    'f.apellidos',
                    'fj.nombres as jefe_nombres',
                    'fj.apellidos as jefe_apellidos'
                )
                ->orderBy('f.apellidos')
                ->orderBy('f.nombres')
                ->get();

            // Candidatos a jefe superior: vinculaciones activas habilitadas como evaluador
            $jefesDisponibles = DB::table('vinculacion as v')
                ->join('funcionario as f', 'f.id_funcionario', '=', 'v.id_funcionario')
                ->where('v.activa', 1)
                ->where('v.es_evaluador', 1)
                ->select(
                    'v.id_vinculacion',
                    'v.cargo',
                    'v.area',
                    'f.nombres',
                    'f.apellidos'
                )
                ->orderBy('f.apellidos')
                ->orderBy('f.nombres')
                ->get();

            $periodos = DB::table('periodo')->orderByDesc('id_periodo')->get();

            // Lista de Funcionarios No Calificados / Sin Concertación
            $periodoAbiertoIds = DB::table('periodo')->where('estado', 'ABIERTO')->pluck('id_periodo')->all();
            $vincsConEvaluacion = DB::table('evaluacion')
                ->whereIn('id_periodo', $periodoAbiertoIds)
                ->pluck('id_vinc_evaluado')
                ->all();

            $funcionariosNoCalificados = DB::table('vinculacion as v')
                ->join('funcionario as f', 'f.id_funcionario', '=', 'v.id_funcionario')
                ->where('v.activa', 1)
                ->whereNotIn('v.id_vinculacion', $vincsConEvaluacion)
                ->select(
                    'f.nombres',
                    'f.apellidos',
                    'f.numero_doc',
                    'f.correo_cargo',
                    'v.id_vinculacion',
                    'v.cargo',
                    'v.area',
                    'v.sistema_evaluacion'
                )
                ->orderBy('f.apellidos')
                ->get();

            $evaluacionesExtratiempo = DB::table('evaluacion as e')
                ->join('vinculacion as v', 'v.id_vinculacion', '=', 'e.id_vinc_evaluado')
                ->join('funcionario as f', 'f.id_funcionario', '=', 'v.id_funcionario')
                ->join('periodo as p', 'p.id_periodo', '=', 'e.id_periodo')
                ->select(
                    'e.id_evaluacion',
                    'e.estado',
                    'f.nombres',
                    'f.apellidos',
                    'v.cargo',
                    'p.sistema',
                    'p.anio',
                    'p.semestre'
                )
                ->whereIn('p.estado', ['ABIERTO'])
                ->orderByDesc('e.id_evaluacion')
                ->get();

            $historialExtratiempo = [];
            if (Schema::hasTable('concertacion_extratiempo')) {
                $historialExtratiempo = DB::table('concertacion_extratiempo as ce')
                    ->join('evaluacion as e', 'e.id_evaluacion', '=', 'ce.id_evaluacion')
                    ->join('vinculacion as v', 'v.id_vinculacion', '=', 'e.id_vinc_evaluado')
                    ->join('funcionario as f', 'f.id_funcionario', '=', 'v.id_funcionario')
                    ->select('ce.*', 'f.nombres', 'f.apellidos', 'v.cargo')
                    ->orderByDesc('ce.id_extratiempo')
                    ->get();
            }

            $periodosParciales = DB::table('periodo_parcial as pp')
                ->join('periodo as p', 'p.id_periodo', '=', 'pp.id_periodo')
                ->join('vinculacion as vf', 'vf.id_vinculacion', '=', 'pp.id_vinc_funcionario')
                ->join('funcionario as ff', 'ff.id_funcionario', '=', 'vf.id_funcionario')
                ->select(
                    'pp.*',
                    'p.sistema',
                    'p.anio',
                    'p.semestre',
                    'p.fecha_inicio as periodo_inicio',
                    'p.fecha_fin as periodo_fin',
                    'ff.nombres as funcionario_nombres',
                    'ff.apellidos as funcionario_apellidos',
                    'vf.cargo as funcionario_cargo',
                    'vf.area as funcionario_area'
                )
                ->orderByDesc('pp.id_periodo_parcial')
                ->get();

            $funcionariosParaPeriodoParcial = DB::table('vinculacion as v')
                ->join('funcionario as f', 'f.id_funcionario', '=', 'v.id_funcionario')
                ->where('v.activa', 1)
                ->select('v.id_vinculacion', 'v.cargo', 'v.area', 'v.sistema_evaluacion', 'f.nombres', 'f.apellidos')
                ->orderBy('f.apellidos')
                ->get();

            // Evaluadores disponibles para delegación (S8)
            $idsEvaluadoresDelegacion = DB::table('evaluador_asignacion')->distinct()->pluck('id_vinc_evaluador')->all();
            $evaluadoresDelegacion = DB::table('vinculacion as v')
                ->join('funcionario as f', 'f.id_funcionario', '=', 'v.id_funcionario')
                ->where('v.activa', 1)
                ->whereIn('v.id_vinculacion', $idsEvaluadoresDelegacion)
                ->select('v.id_vinculacion', 'v.cargo', 'v.area', 'v.nivel_jerarquico', 'v.sistema_evaluacion', 'f.nombres', 'f.apellidos')
                ->orderBy('f.apellidos')
                ->get();

            // Delegado: cualquier funcionario activo disponible
            $delegadosDisponibles = DB::table('vinculacion as v')
                ->join('funcionario as f', 'f.id_funcionario', '=', 'v.id_funcionario')
                ->where('v.activa', 1)
                ->select('v.id_vinculacion', 'v.cargo', 'v.area', 'v.nivel_jerarquico', 'v.sistema_evaluacion', 'v.es_evaluador', 'f.nombres', 'f.apellidos')
                ->orderBy('f.apellidos')
                ->get();

            $configData = getPonderacionesConfig();
            $ponderacionesList = [];
            foreach ($configData as $sistema => $vals) {
                $ponderacionesList[] = (object) array_merge(['sistema' => $sistema], $vals);
            }
            $ponderaciones = collect($ponderacionesList);

            if (Schema::hasTable('impedimento_recusacion')) {
                $impedimentos = DB::table('impedimento_recusacion as ir')
                    ->join('evaluacion as ev', 'ev.id_evaluacion', '=', 'ir.id_evaluacion')
                    ->join('vinculacion as vs', 'vs.id_vinculacion', '=', 'ir.id_vinc_solicitante')
                    ->join('funcionario as fs', 'fs.id_funcionario', '=', 'vs.id_funcionario')
                    ->select(
                        'ir.*',
                        'fs.nombres as solicitante_nombres',
                        'fs.apellidos as solicitante_apellidos',
                        'vs.cargo as solicitante_cargo',
                        'ev.estado as estado_evaluacion'
                    )
                    ->orderByDesc('ir.id_impedimento')
                    ->get();
            }

            // Visor general de TODAS las evaluaciones (menú "Evaluaciones")
            $evaluacionesAdmin = DB::table('evaluacion as ev')
                ->join('periodo as p', 'p.id_periodo', '=', 'ev.id_periodo')
                ->join('vinculacion as vdo', 'vdo.id_vinculacion', '=', 'ev.id_vinc_evaluado')
                ->join('funcionario as fdo', 'fdo.id_funcionario', '=', 'vdo.id_funcionario')
                ->join('vinculacion as vor', 'vor.id_vinculacion', '=', 'ev.id_vinc_evaluador')
                ->join('funcionario as forr', 'forr.id_funcionario', '=', 'vor.id_funcionario')
                ->leftJoin('firma as f_ev', function ($join) {
                    $join->on('f_ev.id_evaluacion', '=', 'ev.id_evaluacion')
                        ->where('f_ev.tipo_firma', '=', 'CONCERTACION_EVALUADO');
                })
                ->leftJoin('firma as f_er', function ($join) {
                    $join->on('f_er.id_evaluacion', '=', 'ev.id_evaluacion')
                        ->where('f_er.tipo_firma', '=', 'CONCERTACION_EVALUADOR');
                })
                ->select(
                    'ev.id_evaluacion',
                    'ev.estado',
                    'ev.tipo_evaluacion as tipo_nombre',
                    'ev.referencia',
                    'ev.es_traslado',
                    'ev.calificacion_final',
                    'ev.categoria_final',
                    'ev.fase_actual',
                    'ev.concertacion_firmada',
                    'p.anio',
                    'p.semestre',
                    'p.sistema',
                    'p.fecha_inicio',
                    'p.fecha_fin',
                    'fdo.nombres as evaluado_nombres',
                    'fdo.apellidos as evaluado_apellidos',
                    'vdo.cargo as evaluado_cargo',
                    'vdo.area as evaluado_area',
                    'forr.nombres as evaluador_nombres',
                    'forr.apellidos as evaluador_apellidos',
                    DB::raw('IF(f_ev.id_firma IS NOT NULL, 1, 0) as evaluado_firmado'),
                    DB::raw('IF(f_er.id_firma IS NOT NULL, 1, 0) as evaluador_firmado')
                )
                ->orderByDesc('ev.id_evaluacion')
                ->get();
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
                ->leftJoin('firma as f_ev', function ($join) {
                    $join->on('f_ev.id_evaluacion', '=', 'ev.id_evaluacion')
                        ->where('f_ev.tipo_firma', '=', 'CONCERTACION_EVALUADO');
                })
                ->leftJoin('firma as f_er', function ($join) {
                    $join->on('f_er.id_evaluacion', '=', 'ev.id_evaluacion')
                        ->where('f_er.tipo_firma', '=', 'CONCERTACION_EVALUADOR');
                })
                ->leftJoin('firma as f_no', function ($join) {
                    $join->on('f_no.id_evaluacion', '=', 'ev.id_evaluacion')
                        ->where('f_no.tipo_firma', '=', 'NOTIFICACION_EVALUADO');
                })
                ->leftJoin('vinculacion as vs', 'vs.id_vinculacion', '=', 'ev.id_vinc_suplente')
                ->leftJoin('funcionario as fs', 'fs.id_funcionario', '=', 'vs.id_funcionario')
                ->select(
                    'ev.id_evaluacion',
                    'ev.id_vinc_evaluado',
                    'ev.estado',
                    'p.anio',
                    'p.semestre',
                    'p.fecha_inicio',
                    'p.fecha_fin',
                    'ev.tipo_evaluacion as tipo_nombre',
                    'ev.referencia',
                    'ev.es_traslado',
                    'ev.id_vinc_suplente',
                    'fs.nombres as suplente_nombres',
                    'fs.apellidos as suplente_apellidos',
                    'ev.calificacion_final',
                    'ev.categoria_final',
                    'fe.nombres as evaluado_nombres',
                    'fe.apellidos as evaluado_apellidos',
                    'p.sistema',
                    've.cargo as evaluado_cargo',
                    've.area as evaluado_area',
                    've.nivel_jerarquico as evaluado_nivel_jerarquico',
                    'ev.fase_actual',
                    've.aplica_eje_misional',
                    'ev.concertacion_firmada',
                    'ev.desacuerdo_evaluado',
                    DB::raw('IF(f_ev.id_firma IS NOT NULL, 1, 0) as evaluado_firmado'),
                    DB::raw('IF(f_er.id_firma IS NOT NULL, 1, 0) as evaluador_firmado'),
                    DB::raw('(SELECT COUNT(*) FROM concertacion_extratiempo ce WHERE ce.id_evaluacion = ev.id_evaluacion AND ce.activo = 1) as tiene_extratiempo')
                )
                ->orderByDesc('ev.id_evaluacion')
                ->get();

            $planesPendientesEvaluador = DB::table('evaluacion as ev')
                ->join('periodo as p', 'p.id_periodo', '=', 'ev.id_periodo')
                ->join('vinculacion as va', 'va.id_vinculacion', '=', 'ev.id_vinc_evaluador')
                ->join('vinculacion as ve', 've.id_vinculacion', '=', 'ev.id_vinc_evaluado')
                ->join('funcionario as fe', 'fe.id_funcionario', '=', 've.id_funcionario')
                ->leftJoin('plan_mejoramiento as pm', 'pm.id_evaluacion', '=', 'ev.id_evaluacion')
                ->where('va.id_funcionario', $usuario['id_funcionario'])
                ->where('ev.estado', 'CALIFICADA')
                ->where(function ($q) {
                    $q->whereIn('ev.categoria_final', ['NO_SATISFACTORIO', 'APROBADO_MEJORA'])
                      ->orWhere(function ($q2) {
                          $q2->whereNotNull('ev.calificacion_final')
                             ->where('ev.calificacion_final', '<=', 80)
                             ->where('ev.calificacion_final', '>', 0);
                      })
                      ->orWhere(function ($q3) {
                          $q3->whereNotNull('ev.calificacion_parcial')
                             ->where('ev.calificacion_parcial', '<=', 80)
                             ->where('ev.calificacion_parcial', '>', 0);
                      })
                      ->orWhereNotNull('pm.id_plan');
                })
                ->where(function ($q) {
                    $q->whereNull('pm.id_plan')->orWhere('pm.estado', '!=', 'CONCERTADO');
                })
                ->select(
                    'ev.id_evaluacion',
                    'ev.categoria_final',
                    'ev.calificacion_final',
                    'ev.calificacion_parcial',
                    'p.sistema',
                    'fe.nombres as evaluado_nombres',
                    'fe.apellidos as evaluado_apellidos',
                    'pm.id_plan',
                    'pm.estado as plan_estado'
                )
                ->orderByDesc('ev.id_evaluacion')
                ->get();

            if ($miVinculacionEvaluador) {
                $idsEvaluadosAsignados = collect(getEvaluadorAsignaciones())
                    ->where('id_vinc_evaluador', $miVinculacionEvaluador->id_vinculacion)
                    ->pluck('id_vinc_evaluado')
                    ->unique()
                    ->values()
                    ->all();

                if (! empty($idsEvaluadosAsignados)) {
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

                    $periodosParcialesAbiertos = DB::table('periodo_parcial')
                        ->where('estado', 'ABIERTO')
                        ->get(['id_vinc_funcionario', 'fecha_inicio', 'fecha_fin', 'referencia']);

                    $idsConPeriodoParcialAbierto = $periodosParcialesAbiertos
                        ->pluck('id_vinc_funcionario')
                        ->map(fn ($id) => (int) $id)
                        ->all();

                    foreach ($evaluadosDisponibles as $evaluado) {
                        $vincId = (int) $evaluado->id_vinculacion;
                        $tiene = in_array($vincId, $idsConPeriodoParcialAbierto, true);
                        $evaluado->tiene_periodo_parcial = $tiene;
                        $evaluado->dias_periodo_parcial = null;
                        $evaluado->referencia_periodo_parcial = null;
                        if ($tiene) {
                            $tramo = $periodosParcialesAbiertos->first(fn ($pp) => (int) $pp->id_vinc_funcionario === $vincId);
                            if ($tramo) {
                                if ($tramo->fecha_inicio && $tramo->fecha_fin) {
                                    $evaluado->dias_periodo_parcial = max(1, (int) (\Carbon\Carbon::parse($tramo->fecha_inicio)->diffInDays(\Carbon\Carbon::parse($tramo->fecha_fin))) + 1);
                                }
                                $evaluado->referencia_periodo_parcial = $tramo->referencia ?? null;
                            }
                        }
                    }
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
                ->leftJoin('firma as f_ev', function ($join) {
                    $join->on('f_ev.id_evaluacion', '=', 'ev.id_evaluacion')
                        ->where('f_ev.tipo_firma', '=', 'CONCERTACION_EVALUADO');
                })
                ->leftJoin('firma as f_er', function ($join) {
                    $join->on('f_er.id_evaluacion', '=', 'ev.id_evaluacion')
                        ->where('f_er.tipo_firma', '=', 'CONCERTACION_EVALUADOR');
                })
                ->leftJoin('vinculacion as vs', 'vs.id_vinculacion', '=', 'ev.id_vinc_suplente')
                ->leftJoin('funcionario as fs', 'fs.id_funcionario', '=', 'vs.id_funcionario')
                ->select(
                    'ev.id_evaluacion',
                    'ev.id_vinc_evaluado',
                    'ev.estado',
                    'ev.categoria_final',
                    'ev.calificacion_final',
                    'p.anio',
                    'p.semestre',
                    'p.fecha_inicio',
                    'p.fecha_fin',
                    'ev.tipo_evaluacion as tipo_nombre',
                    'ev.referencia',
                    'ev.es_traslado',
                    'ev.id_vinc_suplente',
                    'fa.nombres as evaluador_nombres',
                    'fa.apellidos as evaluador_apellidos',
                    'fs.nombres as suplente_nombres',
                    'fs.apellidos as suplente_apellidos',
                    'p.sistema',
                    've.cargo as evaluado_cargo',
                    've.area as evaluado_area',
                    've.nivel_jerarquico as evaluado_nivel_jerarquico',
                    'ev.concertacion_firmada',
                    'ev.fase_actual',
                    'ev.desacuerdo_evaluado',
                    've.aplica_eje_misional',
                    DB::raw('IF(f_ev.id_firma IS NOT NULL, 1, 0) as evaluado_firmado'),
                    DB::raw('IF(f_er.id_firma IS NOT NULL, 1, 0) as evaluador_firmado'),
                    DB::raw('(SELECT COUNT(*) FROM concertacion_extratiempo ce WHERE ce.id_evaluacion = ev.id_evaluacion AND ce.activo = 1) as tiene_extratiempo')
                )
                ->orderByDesc('ev.id_evaluacion')
                ->get();

            // Marcar disponibilidad de informe anual.
            // El consolidado anual es el promedio de los dos semestres, así que
            // solo se ofrece en el segundo semestre y solo si ese semestre ya
            // tiene nota consolidada. La fila se evalúa individualmente: si se
            // marcara por grupo, la fila del semestre 1 del mismo año también
            // quedaría habilitada y aparecería un PDF anual que no aplica.
            if ($evaluacionesEvaluado->isNotEmpty()) {
                $gruposSemestres = DB::table('evaluacion as ev')
                    ->join('periodo as p', 'p.id_periodo', '=', 'ev.id_periodo')
                    ->join('vinculacion as ve', 've.id_vinculacion', '=', 'ev.id_vinc_evaluado')
                    ->where('ve.id_funcionario', $usuario['id_funcionario'])
                    ->where('p.semestre', 2)
                    ->where('ev.estado', 'CALIFICADA')
                    ->get(['p.anio', 'p.sistema', 'ev.id_vinc_evaluado'])
                    ->map(fn ($r) => "{$r->anio}|{$r->sistema}|{$r->id_vinc_evaluado}")
                    ->unique()
                    ->values();

                foreach ($evaluacionesEvaluado as $ev) {
                    $ev->tiene_informe_anual = (int) $ev->semestre === 2
                        && $gruposSemestres->contains("{$ev->anio}|{$ev->sistema}|{$ev->id_vinc_evaluado}");
                }
            }
        }

        // 4. Informes en PDF de las personas evaluadas por este evaluador,
        // agrupados por evaluado. Se listan solo las evaluaciones calificadas,
        // porque no hay informe que descargar de una evaluación sin nota.
        if ($rolActivo === 'evaluador' && $usuario['id_funcionario']) {
            $informesEvaluador = $evaluacionesEvaluador
                ->filter(fn ($e) => $e->estado === 'CALIFICADA')
                ->groupBy(fn ($e) => (int) $e->id_vinc_evaluado)
                ->map(function ($filas, $idVincEvaluado) {
                    $primera = $filas->first();

                    // El consolidado anual solo aplica en el segundo semestre y
                    // exige nota consolidada de ese semestre.
                    $gruposSem2 = $filas
                        ->filter(fn ($e) => (int) $e->semestre === 2)
                        ->map(fn ($e) => "{$e->anio}|{$e->sistema}|{$idVincEvaluado}")
                        ->unique();

                    $evaluaciones = $filas->map(function ($e) use ($gruposSem2, $idVincEvaluado) {
                        $e->tiene_informe_anual = (int) $e->semestre === 2
                            && $gruposSem2->contains("{$e->anio}|{$e->sistema}|{$idVincEvaluado}");
                        return $e;
                    })->values();

                    return [
                        'id_vinc_evaluado' => (int) $idVincEvaluado,
                        'nombres' => $primera->evaluado_nombres,
                        'apellidos' => $primera->evaluado_apellidos,
                        'cargo' => $primera->evaluado_cargo,
                        'area' => $primera->evaluado_area,
                        'evaluaciones' => $evaluaciones,
                    ];
                })
                ->sortBy(fn ($g) => $g['nombres'] . ' ' . $g['apellidos'])
                ->values();
        }



        // --- NOTIFICACIONES (Solo Admin) ---
        $notificaciones = collect();
        $notificacionesNoLeidas = 0;
        if ($rolActivo === 'admin' && Schema::hasTable('notificacion')) {
            // Generar notificaciones automáticas
            self::generarNotificacionesAdmin();

            $notificaciones = DB::table('notificacion')
                ->orderByDesc('created_at')
                ->limit(50)
                ->get();
            $notificacionesNoLeidas = DB::table('notificacion')->where('leida', false)->count();
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

        // La escala institucional se publica al frontend para que las etiquetas
        // de categoría y los topes de los campos de nota nunca se hardcodeen.
        $escalaCalificacion = escalaCalificacionConfig();

        // Fetch periodos for JavaScript config if not loaded
        if ($periodos->isEmpty()) {
            $periodos = DB::table('periodo')->orderByDesc('id_periodo')->get();
        }

        $viewData = compact(
            'usuario', 'rolActivo', 'usuarios', 'empleados', 'evaluaciones',
            'evaluacionesAdmin',
            'periodos', 'ponderaciones', 'evaluacionesEvaluador', 'evaluacionesEvaluado',
            'informesEvaluador', 'evaluadosDisponibles', 'miVinculacionEvaluador', 'acuerdosRL', 'acuerdosAG',
            'ponderacionesConfig', 'planesPendientesEvaluador', 'escalaCalificacion',
            'periodosParciales', 'funcionariosParaPeriodoParcial', 'vinculacionesReemplazo',
            'evaluadoresDelegacion', 'delegadosDisponibles', 'impedimentos',
            'cargosCatalogo', 'dependenciasCatalogo', 'funcionariosNoCalificados', 'evaluacionesExtratiempo', 'historialExtratiempo',
            'vinculacionesJerarquia', 'jefesDisponibles',
            'notificaciones', 'notificacionesNoLeidas'
        );

        return match ($rolActivo) {
            'admin' => view('dashboards.admin', $viewData),
            'evaluado' => view('dashboards.evaluado', $viewData),
            'evaluador' => view('dashboards.evaluador', $viewData),
            default => view('dashboards.evaluado', $viewData),
        };
    }

    /**
     * Genera notificaciones automáticas para el admin basadas en el estado actual del sistema.
     */
    private static function generarNotificacionesAdmin(): void
    {
        $now = now();

        // 1. Periodos a punto de cerrar (5 días o menos)
        if (Schema::hasTable('periodo')) {
            $periodosProximos = DB::table('periodo')
                ->where('estado', 'ABIERTO')
                ->whereBetween('fecha_fin', [$now, $now->copy()->addDays(5)])
                ->get();

            foreach ($periodosProximos as $p) {
                $diasRestantes = $now->diffInDays($p->fecha_fin, false);
                $dias = max(1, (int) ceil(abs($diasRestantes)));
                $existe = DB::table('notificacion')
                    ->where('tipo', 'PERIODO_CERCA')
                    ->where('titulo', "Periodo {$p->sistema} - {$p->anio}/{$p->semestre}")
                    ->where('created_at', '>=', $now->copy()->subDay()->toDateTimeString())
                    ->exists();

                if (!$existe) {
                    DB::table('notificacion')->insert([
                        'tipo' => 'PERIODO_CERCA',
                        'titulo' => "Periodo {$p->sistema} - {$p->anio}/{$p->semestre}",
                        'mensaje' => "Faltan {$dias} día(s) para que cierre el periodo de evaluación.",
                        'seccion' => 'periodos',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        // 2. Nuevos recursos (reposición o apelación)
        if (Schema::hasTable('recurso')) {
            $recursosNuevos = DB::table('recurso')
                ->where('fecha_recurso', '>=', $now->copy()->subDay()->toDateString())
                ->get();

            foreach ($recursosNuevos as $r) {
                $existe = DB::table('notificacion')
                    ->where('tipo', 'RECURSO_NUEVO')
                    ->where('titulo', "Recurso #{$r->id_recurso}")
                    ->exists();

                if (!$existe) {
                    $evaluacion = DB::table('evaluacion')->where('id_evaluacion', $r->id_evaluacion)->first();
                    $tipoLabel = $r->tipo_recurso === 'REPOSICION' ? 'reposición' : 'apelación';
                    $mensaje = "Se presentó un nuevo recurso de {$tipoLabel}.";
                    if ($evaluacion) {
                        $evaluado = DB::table('vinculacion as v')
                            ->join('funcionario as f', 'f.id_funcionario', '=', 'v.id_funcionario')
                            ->where('v.id_vinculacion', $evaluacion->id_vinc_evaluado)
                            ->first();
                        if ($evaluado) {
                            $mensaje = "{$evaluado->nombres} {$evaluado->apellidos} presentó un recurso de {$tipoLabel}.";
                        }
                    }
                    DB::table('notificacion')->insert([
                        'tipo' => 'RECURSO_NUEVO',
                        'titulo' => "Recurso #{$r->id_recurso}",
                        'mensaje' => $mensaje,
                        'seccion' => 'recursos-planes',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        // 3. Nuevos planes de mejoramiento
        if (Schema::hasTable('plan_mejoramiento')) {
            $planesNuevos = DB::table('plan_mejoramiento')
                ->where('fecha_creacion', '>=', $now->copy()->subDay())
                ->get();

            foreach ($planesNuevos as $pl) {
                $existe = DB::table('notificacion')
                    ->where('tipo', 'PLAN_NUEVO')
                    ->where('titulo', "Plan #{$pl->id_plan}")
                    ->exists();

                if (!$existe) {
                    DB::table('notificacion')->insert([
                        'tipo' => 'PLAN_NUEVO',
                        'titulo' => "Plan #{$pl->id_plan}",
                        'mensaje' => 'Se generó un nuevo plan de mejoramiento.',
                        'seccion' => 'recursos-planes',
                        'created_at' => $pl->fecha_creacion ?? $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        // 4. Nuevos impedimentos / recusaciones
        if (Schema::hasTable('impedimento_recusacion')) {
            $impedimentosRecientes = DB::table('impedimento_recusacion')
                ->where('created_at', '>=', $now->copy()->subDay()->toDateTimeString())
                ->get();

            foreach ($impedimentosRecientes as $ir) {
                $tipo = $ir->tipo === 'IMPEDIMENTO' ? 'Impedimento' : 'Recusación';
                $existe = DB::table('notificacion')
                    ->where('tipo', $ir->tipo === 'IMPEDIMENTO' ? 'IMPEDIMENTO_NUEVO' : 'RECUSACION_NUEVA')
                    ->where('titulo', "{$tipo} #{$ir->id_impedimento}")
                    ->exists();

                if (!$existe) {
                    $solicitante = DB::table('vinculacion as v')
                        ->join('funcionario as f', 'f.id_funcionario', '=', 'v.id_funcionario')
                        ->where('v.id_vinculacion', $ir->id_vinc_solicitante)
                        ->first();

                    $articulo = $ir->tipo === 'IMPEDIMENTO' ? 'un nuevo' : 'una nueva';
                    $mensaje = "Se registró {$articulo} {$tipo}.";
                    if ($solicitante) {
                        $mensaje = "{$solicitante->nombres} {$solicitante->apellidos} registró {$articulo} {$tipo}.";
                    }

                    DB::table('notificacion')->insert([
                        'tipo' => $ir->tipo === 'IMPEDIMENTO' ? 'IMPEDIMENTO_NUEVO' : 'RECUSACION_NUEVA',
                        'titulo' => "{$tipo} #{$ir->id_impedimento}",
                        'mensaje' => $mensaje,
                        'seccion' => 'impedimentos-admin',
                        'created_at' => $ir->created_at ?? $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        // 5. Delegaciones próximas a vencer (1 día o menos)
        if (Schema::hasTable('delegacion')) {
            $delegacionesProximas = DB::table('delegacion')
                ->where('estado', 'ACTIVA')
                ->whereBetween('fecha_fin', [$now->copy()->toDateString(), $now->copy()->addDay()->toDateString()])
                ->get();

            foreach ($delegacionesProximas as $d) {
                $existe = DB::table('notificacion')
                    ->where('tipo', 'DELEGACION_PROXIMA')
                    ->where('titulo', "Delegación #{$d->id_delegacion}")
                    ->where('created_at', '>=', $now->copy()->subDay()->toDateTimeString())
                    ->exists();

                if (!$existe) {
                    $delegado = DB::table('vinculacion as v')
                        ->join('funcionario as f', 'f.id_funcionario', '=', 'v.id_funcionario')
                        ->where('v.id_vinculacion', $d->id_vinc_delegado)
                        ->first();

                    $mensaje = 'Una delegación está por vencer.';
                    if ($delegado) {
                        $mensaje = "La delegación de {$delegado->nombres} {$delegado->apellidos} vence pronto.";
                    }

                    DB::table('notificacion')->insert([
                        'tipo' => 'DELEGACION_PROXIMA',
                        'titulo' => "Delegación #{$d->id_delegacion}",
                        'mensaje' => $mensaje,
                        'seccion' => 'delegaciones',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }
}
