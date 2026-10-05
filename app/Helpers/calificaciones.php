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
        $evaluacion = DB::table('evaluacion')->where('id_evaluacion', $idEvaluacion)->first();
        if (!$evaluacion) return ['error'=>'Evaluación no encontrada'];

        $sistema = $evaluacion->sistema ?? 'RENDIMIENTO_LABORAL';
        $configSistema = getPonderacionesConfig()[$sistema] ?? getPonderacionesConfig()['RENDIMIENTO_LABORAL'];

        $ejesActivos = []; $pesoEjes = [];
        if ($sistema === 'ACUERDO_GESTION' && ($evaluacion->aplica_eje_misional ?? false)) {
            foreach (['DOCENCIA','INVESTIGACION','PROYECCION_SOCIAL'] as $tipoEje) {
                $campoPeso = 'peso_'.strtolower($tipoEje);
                $peso = (float)($configSistema[$campoPeso] ?? 0.0);
                if ($peso > 0) { $ejesActivos[] = $tipoEje; $pesoEjes[$tipoEje] = $peso; }
            }
        }

        $notasPorEje = [];
        if (!empty($ejesActivos)) {
            $ejeCals = DB::table('eje_misional_calificacion')->where('id_evaluacion',$idEvaluacion)->whereIn('eje',$ejesActivos)->pluck('calificacion','eje')->toArray();
            foreach ($ejesActivos as $tipoEje) $notasPorEje[$tipoEje] = isset($ejeCals[$tipoEje]) ? (float)$ejeCals[$tipoEje] : 1.0;
            $pesoCompromisos = (float)$configSistema['peso_compromisos'] + (float)($configSistema['peso_docencia']??0) + (float)($configSistema['peso_investigacion']??0) + (float)($configSistema['peso_proyeccion_social']??0) - array_sum($pesoEjes);
        } elseif ($sistema === 'ACUERDO_GESTION' && !($evaluacion->aplica_eje_misional ?? false)) {
            $pesoCompromisos = (float)$configSistema['peso_compromisos'] + (float)($configSistema['peso_docencia']??0) + (float)($configSistema['peso_investigacion']??0) + (float)($configSistema['peso_proyeccion_social']??0);
            $pesoEjes = [];
        } else {
            $pesoCompromisos = (float)$configSistema['peso_compromisos'];
            $pesoEjes = [];
        }

        $pesoCompComun = (float)$configSistema['peso_competencias'];
        $pesoCompNivel = (float)$configSistema['peso_competencias'];

        $compromisos = DB::table('compromiso')->where('id_evaluacion',$idEvaluacion)->whereNotNull('calificacion_definitiva')->get(['calificacion_definitiva','porcentaje_peso']);
        $totalPesoCompromisos = 0.0; $notaCompromisos = 0.0;
        foreach ($compromisos as $c) { $totalPesoCompromisos += (float)$c->porcentaje_peso; $notaCompromisos += (float)$c->calificacion_definitiva * (float)$c->porcentaje_peso; }
        if ($totalPesoCompromisos > 0) $notaCompromisos = $notaCompromisos / $totalPesoCompromisos;

        $compComun = DB::table('competencia_evaluada as ce')->join('competencia_catalogo as cc','cc.id_competencia','=','ce.id_competencia')->where('ce.id_evaluacion',$idEvaluacion)->where('cc.tipo','COMUN')->whereNotNull('ce.calificacion_definitiva')->avg('ce.calificacion_definitiva');
        $notaCompComun = $compComun ? (float)$compComun : 1.0;

        $compNivel = DB::table('competencia_evaluada as ce')->join('competencia_catalogo as cc','cc.id_competencia','=','ce.id_competencia')->where('ce.id_evaluacion',$idEvaluacion)->where('cc.tipo','NIVEL_JERARQUICO')->whereNotNull('ce.calificacion_definitiva')->avg('ce.calificacion_definitiva');
        $notaCompNivel = $compNivel ? (float)$compNivel : 1.0;

        $subtotalCompromisos = $notaCompromisos * ($pesoCompromisos/100.0);
        $subtotalComun = $notaCompComun * ($pesoCompComun/100.0);
        $subtotalNivel = $notaCompNivel * ($pesoCompNivel/100.0);

        $subtotalesEjes = []; $subtotalEjesTotal = 0.0;
        foreach ($pesoEjes as $tipoEje => $pesoEje) {
            $subtotalEje = ($notasPorEje[$tipoEje] ?? 1.0) * ($pesoEje/100.0);
            $subtotalesEjes[$tipoEje] = redondearEscala($subtotalEje);
            $subtotalEjesTotal += $subtotalEje;
        }
        $notaFinal = redondearEscala($subtotalCompromisos + $subtotalComun + $subtotalNivel + $subtotalEjesTotal);

        $notaProrrateo = null; $factorProrrateo = null;
        if ($evaluacion->dias_laborados && (int)$evaluacion->dias_laborados > 0) {
            $fechaInicio = new \DateTime($evaluacion->fecha_inicio);
            $fechaFin = new \DateTime($evaluacion->fecha_fin);
            $diasPeriodo = $fechaInicio->diff($fechaFin)->days + 1;
            if ($diasPeriodo > 0 && (int)$evaluacion->dias_laborados < $diasPeriodo) {
                $factorProrrateo = (int)$evaluacion->dias_laborados / $diasPeriodo;
                $notaProrrateo = redondearEscala($notaFinal * $factorProrrateo);
            }
        }

        $notaParaCategoria = $notaProrrateo ?? $notaFinal;
        $categoria = nivelEscalaCalificacion($notaParaCategoria);

        $requierePlanMejoramiento = false;
        $tipoEval = $evaluacion->tipo_evaluacion ?? $evaluacion->tipo ?? 'SEMESTRE_1';
        if ($tipoEval === 'SEMESTRE_1' && in_array($sistema,['RENDIMIENTO_LABORAL','ACUERDO_GESTION']) && in_array($categoria,['NO_SATISFACTORIO','APROBADO_MEJORA'])) $requierePlanMejoramiento = true;

        $totalCompromisos = DB::table('compromiso')->where('id_evaluacion',$idEvaluacion)->count();
        $compromisosSinCalificar = DB::table('compromiso')->where('id_evaluacion',$idEvaluacion)->whereNull('calificacion_definitiva')->count();

        $catalogoPath = storage_path('app/competencias_catalogo.json');
        $catalogo = file_exists($catalogoPath) ? (json_decode(file_get_contents($catalogoPath), true) ?? []) : [];
        // El nivel jerarquico no es una columna de la evaluacion: pertenece a la
        // vinculacion del evaluado. Sin este fallback se generaba un aviso que
        // Laravel convertia en excepcion y hacia fallar el calculo.
        $nivelJerarquico = strtoupper(trim((string)($evaluacion->nivel_jerarquico ?? '')));
        if ($nivelJerarquico === '') {
            $nivelVinculacion = DB::table('vinculacion')
                ->where('id_vinculacion', $evaluacion->id_vinc_evaluado)
                ->value('nivel_jerarquico');
            $nivelJerarquico = strtoupper(trim((string)$nivelVinculacion));
        }

        $comunesEsperadas = collect($catalogo[$sistema]['COMUN']??[])->pluck('nombre')->all();
        $nivelEsperadas = collect($catalogo[$sistema]['NIVEL_JERARQUICO'][$nivelJerarquico]??[])->pluck('nombre')->all();

        $comunesCalificadas = DB::table('competencia_evaluada as ce')->join('competencia_catalogo as cc','cc.id_competencia','=','ce.id_competencia')->where('ce.id_evaluacion',$idEvaluacion)->where('cc.tipo','COMUN')->whereNotNull('ce.calificacion_definitiva')->pluck('cc.nombre')->all();
        $nivelCalificadas = DB::table('competencia_evaluada as ce')->join('competencia_catalogo as cc','cc.id_competencia','=','ce.id_competencia')->where('ce.id_evaluacion',$idEvaluacion)->where('cc.tipo','NIVEL_JERARQUICO')->whereNotNull('ce.calificacion_definitiva')->pluck('cc.nombre')->all();
        $ejesActivosConNota = DB::table('eje_misional_calificacion')->where('id_evaluacion',$idEvaluacion)->whereNotNull('calificacion')->pluck('eje')->all();

        return [
            'sistema'=>$sistema,
            'pesos'=>['compromisos'=>$pesoCompromisos,'comun'=>$pesoCompComun,'nivel_jerarquico'=>$pesoCompNivel,'ejes'=>$pesoEjes],
            'ejes_activos'=>$ejesActivos,
            'notas_ejes_raw'=>$notasPorEje,
            'nota_compromisos_raw'=>redondearEscala($notaCompromisos),
            'nota_comp_comun_raw'=>redondearEscala($notaCompComun),
            'nota_comp_nivel_raw'=>redondearEscala($notaCompNivel),
            'subtotal_compromisos'=>redondearEscala($notaCompromisos * ($pesoCompromisos/100.0)),
            'subtotal_comun'=>redondearEscala($notaCompComun * ($pesoCompComun/100.0)),
            'subtotal_nivel'=>redondearEscala($notaCompNivel * ($pesoCompNivel/100.0)),
            'subtotales_ejes'=>$subtotalesEjes,
            'subtotal_ejes_total'=>redondearEscala($subtotalEjesTotal ?? 0),
            'nota_final'=>redondearEscala($notaFinal),
            'dias_laborados'=>$evaluacion->dias_laborados,
            'factor_prorrateo'=>$factorProrrateo ? round($factorProrrateo,6) : null,
            'nota_prorrateo'=>$notaProrrateo,
            'nota_definitiva'=>$notaProrrateo ?? $notaFinal,
            'categoria'=>$categoria,
            'requiere_plan_mejoramiento'=>$requierePlanMejoramiento,
            'calificacion_completa'=>$compromisosSinCalificar===0 && count($comunesCalificadas)===count($comunesEsperadas) && count($nivelCalificadas)===count($nivelEsperadas) && (empty($ejesActivos) || count($ejesActivosConNota)===count($ejesActivos)),
            'pendientes'=>['compromisos'=>$compromisosSinCalificar,'comunes'=>count($comunesEsperadas)-count($comunesCalificadas),'nivel'=>count($nivelEsperadas)-count($nivelCalificadas),'ejes'=>count($ejesActivos)-count($ejesActivosConNota)],
            'estado'=>$evaluacion->estado,
        ];
    }
}
