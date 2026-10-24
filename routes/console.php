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

// S14 - Modo Observación: compara el responsable actual con el responsable por plaza.
Artisan::command('puestos:observar', function (\App\Services\ModeloPuestos $modelo, \App\Services\ResolutorResponsable $resolutor) {
    if (! $modelo->observando()) {
        $this->info('El modelo de puestos está apagado: no hay nada que comparar.');
        return 0;
    }

    $r = $resolutor->observar();
    $this->info("Evaluaciones abiertas: {$r['evaluaciones']} · diferencias nuevas: {$r['diferencias_nuevas']} · pendientes: {$r['diferencias_pendientes']} · resueltas solas: {$r['resueltas_solas']}");
    return 0;
})->purpose('Compara el responsable por persona contra el responsable por plaza (modo Observación)');

\Illuminate\Support\Facades\Schedule::command('puestos:observar')->dailyAt('02:00')->withoutOverlapping();
