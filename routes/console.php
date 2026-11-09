<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// S14 - Línea base para conciliar la migración al modelo de puestos (plan, fase A).
Artisan::command('puestos:linea-base {--comparar= : Ruta de una línea base guardada para comparar contra el estado actual}', function (\App\Services\LineaBasePuestos $lineaBase) {
    if ($archivo = $this->option('comparar')) {
        if (! is_file($archivo)) {
            $this->error("No existe el archivo {$archivo}.");
            return 1;
        }

        $diferencias = $lineaBase->comparar(json_decode(file_get_contents($archivo), true) ?: []);
        if (! $diferencias) {
            $this->info('Sin diferencias: ninguna tabla cambió desde la línea base.');
            return 0;
        }

        foreach ($diferencias as $tabla => $d) {
            $this->warn($tabla . ': ' . json_encode($d, JSON_UNESCAPED_UNICODE));
        }
        return 2;
    }

    $foto = $lineaBase->capturar();
    $ruta = storage_path('app/conciliacion/linea-base-' . now()->format('Ymd-His') . '.json');
    if (! is_dir(dirname($ruta))) {
        mkdir(dirname($ruta), 0775, true);
    }
    file_put_contents($ruta, json_encode($foto, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    foreach ($foto['tablas'] as $tabla => $t) {
        $this->line(sprintf('%-22s %6d registros', $tabla, $t['total']));
    }
    $this->info("Línea base guardada en {$ruta}");
    return 0;
})->purpose('Guarda (o compara) la línea base de conteos para conciliar la migración a puestos');

// S14 - Enlace de asignaciones, evaluaciones, delegaciones, tramos y traslados a su plaza (plan, fase C).
Artisan::command('puestos:enlazar {--aplicar : Escribe los enlaces; sin esta opción solo es un ensayo}', function (\App\Services\EnlacePuestos $enlace) {
    $aplicar = (bool) $this->option('aplicar');
    $r = $aplicar ? $enlace->aplicar() : $enlace->ensayar();

    $this->table(['Tabla', 'Enlazados', 'Excepciones'], collect($r['resumen'])->map(fn ($v, $t) => [$t, $v['enlazados'], $v['excepciones']])->values());

    if ($r['excepciones']) {
        $nombres = \Illuminate\Support\Facades\DB::table('vinculacion as v')
            ->join('funcionario as f', 'f.id_funcionario', '=', 'v.id_funcionario')
            ->whereIn('v.id_vinculacion', array_filter(array_column($r['excepciones'], 'id_vinculacion')))
            ->pluck(\Illuminate\Support\Facades\DB::raw("CONCAT(f.nombres, ' ', f.apellidos)"), 'v.id_vinculacion');
        $this->warn('Excepciones para Talento Humano (no se enlazaron):');
        $this->table(['Tabla', 'Id', 'Persona', 'Motivo'], array_map(fn ($e) => [$e['tabla'], $e['id'], $nombres[$e['id_vinculacion']] ?? '-', $e['motivo']], $r['excepciones']));
    }

    // El reporte guardado solo lleva ids (sin nombres), igual que la línea base.
    $ruta = storage_path('app/conciliacion/enlace-' . ($aplicar ? 'aplicado' : 'ensayo') . '-' . now()->format('Ymd-His') . '.json');
    if (! is_dir(dirname($ruta))) {
        mkdir(dirname($ruta), 0775, true);
    }
    file_put_contents($ruta, json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    $this->info(($aplicar ? 'Enlaces escritos.' : 'Ensayo: no se escribió nada. Use --aplicar para escribir.') . " Reporte en {$ruta}");
    return 0;
})->purpose('Enlaza los registros existentes a su plaza (ensayo por defecto)');

// S14 - Enlace de lo nuevo y relevo por plaza (plan, fase E). También corre después de cada operación web.
Artisan::command('puestos:sincronizar', function (\App\Services\SincronizadorPlazas $sincronizador) {
    $r = $sincronizador->sincronizar();
    if (isset($r['omitido'])) {
        $this->info($r['omitido']);
        return 0;
    }

    $enlazados = array_sum(array_column($r['enlace'], 'enlazados'));
    $this->info("Enlaces: {$enlazados} · excepciones: " . count($r['excepciones']));
    $this->info($r['relevo'] === null
        ? 'Relevo: el modelo de plazas no está activo.'
        : "Relevo: {$r['relevo']['asignaciones']} asignaciones y {$r['relevo']['evaluaciones']} evaluaciones pasaron al ocupante vigente · {$r['relevo']['en_calificacion']} en calificación esperan decisión de TH");
    return 0;
})->purpose('Enlaza a su plaza lo nuevo y, con el modelo activo, pasa el trabajo abierto al ocupante vigente');

\Illuminate\Support\Facades\Schedule::command('puestos:sincronizar')->dailyAt('01:50')->withoutOverlapping();

// S14 - Modo Observación: compara el responsable actual con el responsable por plaza.
Artisan::command('puestos:observar', function (\App\Services\ModeloPuestos $modelo, \App\Services\ResolutorResponsable $resolutor) {
    if (! $modelo->observando()) {
        $this->info('El modelo de puestos está apagado: no hay nada que comparar.');
        return 0;
    }

    $r = $resolutor->observar();
    $this->info("Evaluaciones abiertas: {$r['evaluaciones']} · asignaciones: {$r['asignaciones']} · diferencias nuevas: {$r['diferencias_nuevas']} · pendientes: {$r['diferencias_pendientes']} · resueltas solas: {$r['resueltas_solas']}");
    return 0;
})->purpose('Compara el responsable por persona contra el responsable por plaza (modo Observación)');

\Illuminate\Support\Facades\Schedule::command('puestos:observar')->dailyAt('02:00')->withoutOverlapping();

// Fase 5 (5.3) — Delegación sin fecha de fin (corrección caso Gledy):
// Si una delegación sin fecha final llega al día 31 activa, se convierte automáticamente
// a LARGA, genera tramos con fecha = fecha_inicio real y notifica a TH y al titular.
Artisan::command('delegaciones:revisar-abiertas', function () {
    $minimoDias = function_exists('getConfiguracion') ? (int) getConfiguracion('minimo_dias_tramo', 31) : 31;
    $hoy = now()->startOfDay();

    $abiertas = \Illuminate\Support\Facades\DB::table('delegacion')
        ->where('estado', 'ACTIVA')
        ->whereNull('fecha_fin')
        ->where(fn ($q) => $q->whereNull('es_larga')->orWhere('es_larga', 0))
        ->get();

    $convertidas = 0;

    foreach ($abiertas as $del) {
        $inicio = \Carbon\Carbon::parse($del->fecha_inicio)->startOfDay();
        $diasTranscurridos = $inicio->diffInDays($hoy) + 1;

        if ($diasTranscurridos >= $minimoDias) {
            \Illuminate\Support\Facades\DB::transaction(function () use ($del, &$convertidas) {
                $delegante = \Illuminate\Support\Facades\DB::table('vinculacion')->where('id_vinculacion', $del->id_vinc_delegante)->first();
                $delegado = \Illuminate\Support\Facades\DB::table('vinculacion')->where('id_vinculacion', $del->id_vinc_delegado)->first();
                if (! $delegante || ! $delegado) {
                    return;
                }

                // Subordinados del delegante
                $evaluados = \Illuminate\Support\Facades\DB::table('evaluador_asignacion')
                    ->where('id_vinc_evaluador', $del->id_vinc_delegante)
                    ->where('id_vinc_evaluado', '!=', $del->id_vinc_delegado)
                    ->pluck('id_vinc_evaluado')
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all();

                // Revertir asignaciones cortas temporales para que queden con el titular
                $detalle = json_decode($del->detalle_transferencia ?? '{}', true);
                $transferidos = $detalle['evaluados_transferidos'] ?? [];
                if (! empty($transferidos)) {
                    \Illuminate\Support\Facades\DB::table('evaluador_asignacion')
                        ->where('id_vinc_evaluador', $del->id_vinc_delegado)
                        ->whereIn('id_vinc_evaluado', $transferidos)
                        ->delete();

                    \Illuminate\Support\Facades\DB::table('evaluacion')
                        ->where('id_vinc_evaluador', $del->id_vinc_delegado)
                        ->where('id_vinc_suplente', $del->id_vinc_delegante)
                        ->where('estado', 'EN_PROCESO')
                        ->update([
                            'id_vinc_evaluador' => $del->id_vinc_delegante,
                            'id_vinc_suplente' => null,
                        ]);
                }

                $evaluadorOriginal = (int) \Illuminate\Support\Facades\DB::table('evaluador_asignacion')
                    ->where('id_vinc_evaluado', $del->id_vinc_delegado)
                    ->orderBy('id_asignacion')
                    ->value('id_vinc_evaluador');

                $esTitularJefe = $evaluadorOriginal === (int) $del->id_vinc_delegante;
                $esVacante = ! empty($delegante->es_vacante);
                $necesitaNuevo = $esTitularJefe || $esVacante || ! empty($del->id_vinc_superior_delegado);

                $idSuperior = $del->id_vinc_superior_delegado;
                if ($necesitaNuevo && ! $idSuperior) {
                    $idSuperior = (int) ($delegado->id_vinc_jefe ?? 0);
                    if (! $idSuperior || $idSuperior === (int) $del->id_vinc_delegado) {
                        $idSuperior = (int) (\Illuminate\Support\Facades\DB::table('vinculacion')->where('es_evaluador', 1)->where('activa', 1)->where('id_vinculacion', '!=', $del->id_vinc_delegado)->value('id_vinculacion') ?? 0);
                    }
                }

                \Illuminate\Support\Facades\DB::table('delegacion')->where('id_delegacion', $del->id_delegacion)->update([
                    'es_larga' => 1,
                    'estado' => 'PROGRAMADA',
                    'necesita_nuevo_evaluador' => $necesitaNuevo ? 1 : 0,
                    'id_vinc_superior_delegado' => $idSuperior ?: null,
                    'id_vinc_evaluador_original_delegado' => $evaluadorOriginal ?: null,
                    'updated_at' => now(),
                ]);

                $delActualizada = \Illuminate\Support\Facades\DB::table('delegacion')->where('id_delegacion', $del->id_delegacion)->first();
                programarCambiosDelegacion($delActualizada, $evaluados, (int) $del->id_vinc_delegante, (int) $del->id_vinc_delegado, $idSuperior ?: null, (string) $del->fecha_inicio);

                // Titular pausa si aplica
                if (empty($delegante->es_vacante)) {
                    $evaluadorTitular = (int) \Illuminate\Support\Facades\DB::table('evaluador_asignacion')
                        ->where('id_vinc_evaluado', $del->id_vinc_delegante)
                        ->orderBy('id_asignacion')
                        ->value('id_vinc_evaluador');
                    if ($evaluadorTitular) {
                        programarTraslado([
                            'id_vinc_funcionario' => (int) $del->id_vinc_delegante,
                            'id_vinc_evaluador_nuevo' => $evaluadorTitular,
                            'fecha_traslado' => $del->fecha_inicio,
                            'motivo' => 'Ausencia del titular - Delegación #' . $del->id_delegacion,
                        ], $evaluadorTitular, (int) $del->id_delegacion, false, 'PAUSA');
                    }
                }

                // Notificar a Talento Humano
                \Illuminate\Support\Facades\DB::table('notificacion')->insert([
                    'tipo' => 'DELEGACION_CONVERTIDA_A_LARGA',
                    'titulo' => 'Delegación convertida a delegación por tramos',
                    'mensaje' => "La delegación #{$del->id_delegacion} cumplió 31 días activa y se convirtió a delegación por tramos. Se programaron los tramos con fecha {$del->fecha_inicio}.",
                    'seccion' => 'delegaciones',
                    'id_usuario_destino' => null,
                    'leida' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Notificar al titular
                if ($delegante && $delegante->id_funcionario) {
                    $idUsuarioTitular = \Illuminate\Support\Facades\DB::table('funcionario')->where('id_funcionario', $delegante->id_funcionario)->value('id_usuario');
                    \Illuminate\Support\Facades\DB::table('notificacion')->insert([
                        'tipo' => 'DELEGACION_CONVERTIDA_A_LARGA',
                        'titulo' => 'Delegación convertida a delegación por tramos',
                        'mensaje' => "Tu delegación #{$del->id_delegacion} cumplió 31 días. Se programaron tramos para calificar a tus personas a cargo con fecha {$del->fecha_inicio}.",
                        'seccion' => 'evaluaciones',
                        'id_usuario_destino' => $idUsuarioTitular,
                        'leida' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $convertidas++;
            });
        }
    }

    $this->info("Revisión completada: {$convertidas} delegación(es) convertida(s) a larga.");
    return 0;
})->purpose('Revisa delegaciones activas sin fecha final y las convierte a largas al llegar al día 31');

\Illuminate\Support\Facades\Schedule::command('delegaciones:revisar-abiertas')->dailyAt('02:05')->withoutOverlapping();

