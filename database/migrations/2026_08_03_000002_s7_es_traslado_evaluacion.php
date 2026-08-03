<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S7 - Bloqueo de evaluación por traslado.
 *
 * Cuando un funcionario es trasladado y su evaluación SEMESTRE ya quedó
 * concertada y firmada por ambas partes, esa evaluación queda etiquetada como
 * "traslado" y se bloquea a solo lectura (no se puede modificar ni calificar).
 * El evaluador de origen evalúa el periodo trabajado a través de la PARCIAL
 * que se genera automáticamente en el flujo de traslado.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('evaluacion', 'es_traslado')) {
            Schema::table('evaluacion', function (Blueprint $table) {
                $table->tinyInteger('es_traslado')->default(0)->after('es_parcial');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('evaluacion', 'es_traslado')) {
            Schema::table('evaluacion', function (Blueprint $table) {
                $table->dropColumn('es_traslado');
            });
        }
    }
};
