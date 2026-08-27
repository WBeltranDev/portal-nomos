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

        // Default empty collections
        $usuarios = collect();
        $empleados = collect();
        $evaluaciones = collect();
        $periodos = collect();
        $ponderaciones = collect();
        $periodosParciales = collect();
        $funcionariosParaPeriodoParcial = collect();
        $evaluacionesEvaluador = collect();
        $evaluacionesEvaluado = collect();
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
        }

        // 2. Data for Evaluador or Instancia Externa
        if (in_array($rolActivo, ['evaluador', 'instancia_externa'], true) && $usuario['id_funcionario']) {
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
                    DB::raw('IF(f_no.id_firma IS NOT NULL, 1, 0) as notificacion_firmada'),
                    DB::raw('IF(f_no.renuencia = 1, 1, 0) as notificacion_renuencia'),
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
                ->leftJoin('firma as f_no', function ($join) {
                    $join->on('f_no.id_evaluacion', '=', 'ev.id_evaluacion')
                        ->where('f_no.tipo_firma', '=', 'NOTIFICACION_EVALUADO');
                })
                ->where('va.id_funcionario', $usuario['id_funcionario'])
                ->where('ev.estado', 'CALIFICADA')
                // Plan solo se habilita si no hubo renuencia y fue notificada/firmada
                ->where(function ($q) {
                    $q->whereNull('f_no.renuencia')->orWhere('f_no.renuencia', 0);
                })
                ->where(function ($q) {
                    $q->where('ev.categoria_final', 'NO_SATISFACTORIO')
                      ->orWhere(function ($q2) {
                          $q2->whereNotNull('ev.calificacion_final')
                             ->where('ev.calificacion_final', '<=', 70)
                             ->where('ev.calificacion_final', '>', 0);
                      })
                      ->orWhere(function ($q3) {
                          $q3->whereNotNull('ev.calificacion_parcial')
                             ->where('ev.calificacion_parcial', '<=', 70)
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

                    $idsConPeriodoParcialAbierto = DB::table('periodo_parcial')
                        ->where('estado', 'ABIERTO')
                        ->pluck('id_vinc_funcionario')
                        ->map(fn ($id) => (int) $id)
                        ->all();

                    foreach ($evaluadosDisponibles as $evaluado) {
                        $evaluado->tiene_periodo_parcial = in_array((int) $evaluado->id_vinculacion, $idsConPeriodoParcialAbierto, true);
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
                    DB::raw('IF(f_no.id_firma IS NOT NULL, 1, 0) as notificacion_firmada'),
                    DB::raw('IF(f_no.renuencia = 1, 1, 0) as notificacion_renuencia'),
                    DB::raw('(SELECT COUNT(*) FROM concertacion_extratiempo ce WHERE ce.id_evaluacion = ev.id_evaluacion AND ce.activo = 1) as tiene_extratiempo'),
                    'f_no.fecha_firma as notificacion_fecha',
                    'f_no.testigo_nombre',
                    'f_no.testigo_documento',
                    'f_no.observacion_renuencia'
                )
                ->orderByDesc('ev.id_evaluacion')
                ->get();

            // Marcar disponibilidad de informe anual
            $clavesConInformeAnual = [];
            if ($evaluacionesEvaluado->isNotEmpty()) {
                $gruposSemestres = DB::table('evaluacion as ev')
                    ->join('periodo as p', 'p.id_periodo', '=', 'ev.id_periodo')
                    ->join('vinculacion as ve', 've.id_vinculacion', '=', 'ev.id_vinc_evaluado')
                    ->where('ve.id_funcionario', $usuario['id_funcionario'])
                    ->whereIn('ev.tipo_evaluacion', ['SEMESTRE_1', 'SEMESTRE_2'])
                    ->where('ev.estado', 'CALIFICADA')
                    ->get(['p.anio', 'p.sistema', 'ev.id_vinc_evaluado', 'ev.tipo_evaluacion'])
                    ->groupBy(fn ($r) => "{$r->anio}|{$r->sistema}|{$r->id_vinc_evaluado}");

                foreach ($gruposSemestres as $clave => $filas) {
                    $tipos = $filas->pluck('tipo_evaluacion');
                    if (($tipos->contains('SEMESTRE_1') && $tipos->contains('SEMESTRE_2')) || $tipos->contains('SEMESTRE_2')) {
                        $clavesConInformeAnual[] = $clave;
                    }
                }

                foreach ($evaluacionesEvaluado as $ev) {
                    $ev->tiene_informe_anual = in_array(
                        "{$ev->anio}|{$ev->sistema}|{$ev->id_vinc_evaluado}",
                        $clavesConInformeAnual,
                        true
                    );
                }
            }
        }

        // 4. Data for Instancia Externa
        if ($rolActivo === 'instancia_externa') {
            $evaluacionesInstanciaExterna = obtenerEvaluacionesAgConEjesMisionales();
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

        // Fetch periodos for JavaScript config if not loaded
        if ($periodos->isEmpty()) {
            $periodos = DB::table('periodo')->orderByDesc('id_periodo')->get();
        }

        $viewData = compact(
            'usuario', 'rolActivo', 'usuarios', 'empleados', 'evaluaciones',
            'periodos', 'ponderaciones', 'evaluacionesEvaluador', 'evaluacionesEvaluado',
            'evaluadosDisponibles', 'miVinculacionEvaluador', 'acuerdosRL', 'acuerdosAG',
            'ponderacionesConfig', 'evaluacionesInstanciaExterna', 'planesPendientesEvaluador',
            'periodosParciales', 'funcionariosParaPeriodoParcial', 'vinculacionesReemplazo',
            'evaluadoresDelegacion', 'delegadosDisponibles', 'impedimentos',
            'cargosCatalogo', 'dependenciasCatalogo', 'funcionariosNoCalificados', 'evaluacionesExtratiempo', 'historialExtratiempo',
            'vinculacionesJerarquia', 'jefesDisponibles',
            'notificaciones', 'notificacionesNoLeidas'
        );

        return match ($rolActivo) {
            'admin' => view('dashboards.admin', $viewData),
            'evaluado' => view('dashboards.evaluado', $viewData),
            'evaluador', 'instancia_externa' => view('dashboards.evaluador', $viewData),
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
                ->where('estado', 'ACTIVO')
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

        // 2. Nuevos recursos de apelación
        if (Schema::hasTable('recurso_apelacion')) {
            $recursosNuevos = DB::table('recurso_apelacion')
                ->where('created_at', '>=', $now->copy()->subDay()->toDateTimeString())
                ->get();

            foreach ($recursosNuevos as $r) {
                $existe = DB::table('notificacion')
                    ->where('tipo', 'RECURSO_NUEVO')
                    ->where('titulo', "Recurso #{$r->id_recurso}")
                    ->exists();

                if (!$existe) {
                    $evaluacion = DB::table('evaluacion')->where('id_evaluacion', $r->id_evaluacion)->first();
                    $mensaje = "Se presentó un nuevo recurso de apelación.";
                    if ($evaluacion) {
                        $evaluado = DB::table('vinculacion as v')
                            ->join('funcionario as f', 'f.id_funcionario', '=', 'v.id_funcionario')
                            ->where('v.id_vinculacion', $evaluacion->id_vinc_evaluado)
                            ->first();
                        if ($evaluado) {
                            $mensaje = "{$evaluado->nombres} {$evaluado->apellidos} presentó un recurso de apelación.";
                        }
                    }
                    DB::table('notificacion')->insert([
                        'tipo' => 'RECURSO_NUEVO',
                        'titulo' => "Recurso #{$r->id_recurso}",
                        'mensaje' => $mensaje,
                        'seccion' => 'recursos-planes',
                        'created_at' => $r->created_at ?? $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        // 3. Nuevos planes de mejoramiento
        if (Schema::hasTable('plan_mejoramiento')) {
            $planesNuevos = DB::table('plan_mejoramiento')
                ->where('created_at', '>=', $now->copy()->subDay()->toDateTimeString())
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
                        'created_at' => $pl->created_at ?? $now,
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

                    $mensaje = "Se registró un nuevo {$tipo}.";
                    if ($solicitante) {
                        $mensaje = "{$solicitante->nombres} {$solicitante->apellidos} registró un nuevo {$tipo}.";
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
                ->whereNull('fecha_fin_real')
                ->whereBetween('fecha_fin', [$now, $now->copy()->addDay()])
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
