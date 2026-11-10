<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Corrección X del plan de pruebas 29-10-2026: plazos reales por periodo para
 * concertar y para calificar, y extratiempo para cualquiera de los dos. Los
 * periodos existentes quedan sin fecha límite (no se bloquea nada) hasta que
 * Talento Humano las defina.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['fecha_fin_concertacion', 'fecha_fin_calificacion'] as $columna) {
            if (! Schema::hasColumn('periodo', $columna)) {
                Schema::table('periodo', fn (Blueprint $table) => $table->date($columna)->nullable()->after('fecha_fin'));
            }
        }

        if (Schema::hasTable('concertacion_extratiempo') && ! Schema::hasColumn('concertacion_extratiempo', 'tipo')) {
            Schema::table('concertacion_extratiempo', function (Blueprint $table) {
                $table->enum('tipo', ['CONCERTACION', 'CALIFICACION'])->default('CONCERTACION')->after('id_evaluacion');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('concertacion_extratiempo', 'tipo')) {
            Schema::table('concertacion_extratiempo', fn (Blueprint $table) => $table->dropColumn('tipo'));
        }
        foreach (['fecha_fin_concertacion', 'fecha_fin_calificacion'] as $columna) {
            if (Schema::hasColumn('periodo', $columna)) {
                Schema::table('periodo', fn (Blueprint $table) => $table->dropColumn($columna));
            }
        }
    }
};
