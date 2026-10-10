<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (!function_exists('defaultPonderacionesConfig')) {
    function defaultPonderacionesConfig(): array {
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
    function getPonderacionesConfig(): array {
        $configData = defaultPonderacionesConfig();
        if (Schema::hasTable('ponderacion')) {
            foreach (DB::table('ponderacion')->get() as $row) {
                if (!isset($configData[$row->sistema])) continue;
                foreach (['peso_compromisos','peso_competencias','peso_docencia','peso_investigacion','peso_proyeccion_social'] as $campo) {
                    if ($row->$campo !== null) $configData[$row->sistema][$campo] = (float)$row->$campo;
                }
            }
        }
        return $configData;
    }
}

if (!function_exists('escalaCalificacionConfig')) {
    function escalaCalificacionConfig(): array {
        return [
            'minimo' => 1.0, 'maximo' => 5.0, 'decimales' => 1, 'step' => '0.1',
            // Las cuatro bandas son también las categorías finales. Las dos
            // primeras exigen plan de mejoramiento: "No satisfactorio" es el peor
            // resultado y "Susceptible a plan de mejora" lo dice explícitamente.
            // Bueno y Sobresaliente no lo exigen. Antes este dato estaba
            // contradictorio (la bandera decía false para No satisfactorio
            // mientras el flujo sí exigía plan) y provocaba que una nota buena
            // heredara el umbral numérico de la escala 0-100.
            'bandas' => [
                ['desde'=>1.0,'hasta'=>3.4,'nivel'=>'NO_SATISFACTORIO','etiqueta'=>'No satisfactorio','aplica_plan_mejoramiento'=>true],
                ['desde'=>3.5,'hasta'=>4.0,'nivel'=>'APROBADO_MEJORA','etiqueta'=>'Susceptible a plan de mejora','aplica_plan_mejoramiento'=>true],
                ['desde'=>4.1,'hasta'=>4.5,'nivel'=>'BUENO','etiqueta'=>'Bueno','aplica_plan_mejoramiento'=>false],
                ['desde'=>4.6,'hasta'=>5.0,'nivel'=>'SOBRESALIENTE','etiqueta'=>'Sobresaliente','aplica_plan_mejoramiento'=>false],
            ],
        ];
    }
}

if (!function_exists('redondearEscala')) {
    function redondearEscala($valor): float {
        $escala = escalaCalificacionConfig(); $valor = (float)$valor;
        $valor = round($valor, $escala['decimales']);
        if ($valor < $escala['minimo']) return (float)$escala['minimo'];
        if ($valor > $escala['maximo']) return (float)$escala['maximo'];
        return $valor;
    }
}

if (!function_exists('redondearSubtotal')) {
    function redondearSubtotal($valor): float {
        $escala = escalaCalificacionConfig(); 
        // Subtotals are usually rounded to the same or +1 decimal places but NOT clamped
        return round((float)$valor, $escala['decimales'] + 1);
    }
}

if (!function_exists('nivelEscalaCalificacion')) {
    function nivelEscalaCalificacion($valor): string {
        $valor = (float)$valor;
        foreach (escalaCalificacionConfig()['bandas'] as $banda) {
            if ($valor >= $banda['desde'] && $valor <= $banda['hasta']) return $banda['nivel'];
        }
        return $valor > escalaCalificacionConfig()['bandas'][count(escalaCalificacionConfig()['bandas'])-1]['hasta'] ? 'SOBRESALIENTE' : 'NO_SATISFACTORIO';
    }
}

if (!function_exists('nivelEscalaAplicaPlanMejoramiento')) {
    /**
     * Indica si un nivel de la escala institucional exige plan de
     * mejoramiento. Se deriva de la bandera `aplica_plan_mejoramiento` de las
     * bandas configuradas, para que cambiar la escala no obligue a tocar
     * umbrales numéricos escritos a mano.
     */
    function nivelEscalaAplicaPlanMejoramiento(string $nivel): bool {
        foreach (escalaCalificacionConfig()['bandas'] as $banda) {
            if ($banda['nivel'] === $nivel) {
                return (bool) $banda['aplica_plan_mejoramiento'];
            }
        }
        return false;
    }
}

if (!function_exists('notaEscalaAplicaPlanMejoramiento')) {
    /**
     * ¿La nota, en la escala vigente, obliga a concertar plan de mejoramiento?
     * Solo aplica a las bandas marcadas como tal en la configuración.
     */
    function notaEscalaAplicaPlanMejoramiento($valor): bool {
        return nivelEscalaAplicaPlanMejoramiento(nivelEscalaCalificacion($valor));
    }
}

if (!function_exists('escalaCalificacionCatalogo')) {
    function escalaCalificacionCatalogo(): array {
        $escala = escalaCalificacionConfig(); $dec = $escala['decimales'];
        $formato = fn(float $v) => number_format($v, $dec, '.', '');
        $rangos = [];
        foreach ($escala['bandas'] as $i => $banda) {
            $rangos[] = ['rango'=>$formato($banda['desde']).' a '.$formato($banda['hasta']),'nivel'=>$banda['etiqueta'],'codigo'=>$i+1,'desde'=>$banda['desde'],'hasta'=>$banda['hasta'],'aplica_plan_mejoramiento'=>$banda['aplica_plan_mejoramiento']];
        }
        return ['descripcion'=>'Escala de calificación institucional de '.$formato($escala['minimo']).' a '.$formato($escala['maximo']).' con '.($escala['decimales']===1?'un (1) decimal':$escala['decimales'].' decimales').', para compromisos, competencias y ejes misionales de los dos sistemas','minimo'=>$escala['minimo'],'maximo'=>$escala['maximo'],'decimales'=>$escala['decimales'],'step'=>$escala['step'],'rangos'=>$rangos];
    }
}

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
            // Sin nota cargada se usa el mínimo de la escala (1.0), no 0.0:
            // un 0.0 ya no es un valor válido y hundiría la nota final por
            // debajo del mínimo institucional.
            $notasPorEje[$tipoEje] = isset($ejeCals[$tipoEje]) ? (float)$ejeCals[$tipoEje] : 1.0;
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
    // 1. NOTA COMPROMISOS — suma ponderada (1.0 a 5.0 cada uno)
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
    // 2. NOTA COMPETENCIAS COMUNES (promedio escala 1.0 a 5.0)
    // -------------------------------------------------------
    $compComun = DB::table('competencia_evaluada as ce')
        ->join('competencia_catalogo as cc', 'cc.id_competencia', '=', 'ce.id_competencia')
        ->where('ce.id_evaluacion', $idEvaluacion)
        ->where('cc.tipo', 'COMUN')
        ->whereNotNull('ce.calificacion_definitiva')
        ->avg('ce.calificacion_definitiva');
    $notaCompComun = is_null($compComun) ? 1.0 : (float)$compComun;

    // -------------------------------------------------------
    // 3. NOTA COMPETENCIAS NIVEL JERÁRQUICO (promedio 1.0 a 5.0)
    // -------------------------------------------------------
    $compNivel = DB::table('competencia_evaluada as ce')
        ->join('competencia_catalogo as cc', 'cc.id_competencia', '=', 'ce.id_competencia')
        ->where('ce.id_evaluacion', $idEvaluacion)
        ->where('cc.tipo', 'NIVEL_JERARQUICO')
        ->whereNotNull('ce.calificacion_definitiva')
        ->avg('ce.calificacion_definitiva');
    $notaCompNivel = is_null($compNivel) ? 1.0 : (float)$compNivel;

    // -------------------------------------------------------
    // 4. NOTA FINAL (antes de prorrateo)
    // -------------------------------------------------------
    $subtotalCompromisos = $notaCompromisos * ($pesoCompromisos / 100.0);
    $subtotalComun       = $notaCompComun   * ($pesoCompComun   / 100.0);
    $subtotalNivel       = $notaCompNivel   * ($pesoCompNivel   / 100.0);

    $subtotalesEjes = [];
    $subtotalEjesTotal = 0.0;
    foreach ($pesoEjes as $tipoEje => $pesoEje) {
        $subtotalEje = ($notasPorEje[$tipoEje] ?? 1.0) * ($pesoEje / 100.0);
        $subtotalesEjes[$tipoEje] = redondearSubtotal($subtotalEje);
        $subtotalEjesTotal += $subtotalEje;
    }

    $notaFinal = redondearEscala($subtotalCompromisos + $subtotalComun + $subtotalNivel + $subtotalEjesTotal);

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
            $notaProrrateo   = redondearEscala($notaFinal * $factorProrrateo);
        }
    }

    // -------------------------------------------------------
    // 6. CATEGORÍA FINAL
    // -------------------------------------------------------
    $notaParaCategoria = $notaProrrateo ?? $notaFinal;
    $categoria = nivelEscalaCalificacion($notaParaCategoria);

    // -------------------------------------------------------
    // 7. PLAN DE MEJORAMIENTO (1er semestre)
    // -------------------------------------------------------
    $requierePlanMejoramiento = false;
    $tipoEval = $evaluacion->tipo_evaluacion ?? $evaluacion->tipo ?? 'SEMESTRE_1';
    if ($tipoEval === 'SEMESTRE_1') {
        if (in_array($sistema, ['RENDIMIENTO_LABORAL', 'ACUERDO_GESTION']) && in_array($categoria, ['NO_SATISFACTORIO', 'APROBADO_MEJORA'])) {
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
        'nota_compromisos_raw'      => redondearEscala($notaCompromisos),
        'nota_comp_comun_raw'       => redondearEscala($notaCompComun),
        'nota_comp_nivel_raw'       => redondearEscala($notaCompNivel),
        'subtotal_compromisos'      => redondearSubtotal($subtotalCompromisos),
        'subtotal_comun'            => redondearSubtotal($subtotalComun),
        'subtotal_nivel'            => redondearSubtotal($subtotalNivel),
        'subtotales_ejes'           => $subtotalesEjes,
        'subtotal_ejes_total'       => redondearSubtotal($subtotalEjesTotal),
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
