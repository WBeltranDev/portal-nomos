<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Corrección VI/P6 del plan de pruebas 29-10-2026 (aclarada con Talento Humano):
 * en toda delegación el delegado asume las personas a cargo del titular. La
 * pregunta que se hace al delegar es si el DELEGADO cambia de evaluador. La regla
 * general es que no cambia (la delegación no es un traslado); solo en el caso
 * excepcional se programa su cambio de evaluador y se exige calificar su tramo.
 * Reemplaza la columna cambia_evaluador de la migración 2026_10_29_000002.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('delegacion', 'cambia_evaluador')) {
            Schema::table('delegacion', fn (Blueprint $table) => $table->dropColumn('cambia_evaluador'));
        }

        if (! Schema::hasColumn('delegacion', 'delegado_cambia_evaluador')) {
            Schema::table('delegacion', function (Blueprint $table) {
                $table->boolean('delegado_cambia_evaluador')->default(false)->after('es_larga');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('delegacion', 'delegado_cambia_evaluador')) {
            Schema::table('delegacion', fn (Blueprint $table) => $table->dropColumn('delegado_cambia_evaluador'));
        }
        if (! Schema::hasColumn('delegacion', 'cambia_evaluador')) {
            Schema::table('delegacion', fn (Blueprint $table) => $table->boolean('cambia_evaluador')->default(true)->after('es_larga'));
        }
    }
};
