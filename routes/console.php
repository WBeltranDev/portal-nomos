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

// Limpieza de los datos de prueba antes de operar (ver App\Services\LimpiezaPruebas).
Artisan::command('serag:limpiar-pruebas
    {--ejecutar : Ejecuta la limpieza. Sin esta opción solo muestra qué se borraría}
    {--base= : Nombre de la base de datos, como confirmación (obligatorio con --ejecutar)}
    {--sin-evaluadores : No restablece los evaluadores al jefe inmediato de la planta}
    {--sin-reiniciar-ids : No reinicia los contadores de id de las tablas vaciadas}', function (\App\Services\LimpiezaPruebas $limpieza) {
    $base = \Illuminate\Support\Facades\DB::connection()->getDatabaseName();
    $plan = $limpieza->plan();

    $this->info("Base de datos: {$base}");
    $this->newLine();
    $this->line('SE VACÍAN (movimiento y derivados):');
    foreach ($plan['vaciar'] as $tabla => $n) {
        $this->line(sprintf('  %-38s %6d registro(s)', $tabla, $n));
    }
    $this->newLine();
    $this->line('SE CONSERVAN:');
    foreach ($plan['conservar'] as $tabla => $n) {
        $this->line(sprintf('  %-38s %6d registro(s)', $tabla, $n));
    }
    $this->line(sprintf('  evaluador_asignacion se restablece: %d hoy → %d (cada persona activa con su jefe inmediato)', $plan['asignaciones_hoy'], $plan['asignaciones_base']));
    if ($plan['sin_clasificar']) {
        $this->newLine();
        $this->warn('Tablas sin clasificar (NO se tocan): ' . implode(', ', $plan['sin_clasificar']));
    }
    if ($plan['inactivas_no_vacante']) {
        $this->newLine();
        $this->warn('Funcionarios inactivos (no vacantes) que conviene revisar o reactivar:');
        foreach ($plan['inactivas_no_vacante'] as $p) {
            $this->line("  {$p['numero_doc']}  {$p['nombres']} {$p['apellidos']} · {$p['cargo']} · {$p['area']}");
        }
    }

    if (! $this->option('ejecutar')) {
        $this->newLine();
        $this->comment('Simulación: no se cambió nada. Para ejecutar: php artisan serag:limpiar-pruebas --ejecutar --base=' . $base);
        return 0;
    }

    if ($this->option('base') !== $base) {
        $this->error("Para ejecutar hay que confirmar la base: agrega --base={$base}");
        return 1;
    }

    $resultado = $limpieza->ejecutar(! $this->option('sin-evaluadores'), ! $this->option('sin-reiniciar-ids'));
    $this->newLine();
    $this->info('Limpieza terminada: ' . array_sum($resultado['vaciadas']) . ' registro(s) borrados en ' . count($resultado['vaciadas']) . ' tabla(s).');
    if ($resultado['asignaciones'] !== null) {
        $this->info("Evaluadores restablecidos: {$resultado['asignaciones']} asignaciones (jefe inmediato).");
    }
    \Illuminate\Support\Facades\Log::warning('serag:limpiar-pruebas ejecutado', ['base' => $base, 'borrados' => $resultado['vaciadas'], 'asignaciones' => $resultado['asignaciones']]);

    return 0;
})->purpose('Vacía los datos de prueba (periodos, evaluaciones, delegaciones, traslados, recursos…) y conserva los datos maestros');
