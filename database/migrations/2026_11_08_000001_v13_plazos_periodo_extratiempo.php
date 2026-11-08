<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Fase 4 (v1.3) — Plazos del período y extratiempo:
     * - Fechas límite en periodo: fecha_fin_concertacion y fecha_fin_calificacion.
     * - Tipo de extratiempo en concertacion_extratiempo: CONCERTACION o CALIFICACION.
     */
    public function up(): void
    {
        if (Schema::hasTable('periodo')) {
            Schema::table('periodo', function (Blueprint $table) {
                if (!Schema::hasColumn('periodo', 'fecha_fin_concertacion')) {
                    $table->date('fecha_fin_concertacion')->nullable()->after('fecha_fin');
                }
                if (!Schema::hasColumn('periodo', 'fecha_fin_calificacion')) {
                    $table->date('fecha_fin_calificacion')->nullable()->after('fecha_fin_concertacion');
                }
            });
        }

        if (Schema::hasTable('concertacion_extratiempo')) {
            Schema::table('concertacion_extratiempo', function (Blueprint $table) {
                if (!Schema::hasColumn('concertacion_extratiempo', 'tipo')) {
                    $table->string('tipo', 20)->default('CONCERTACION')->after('id_evaluacion');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('concertacion_extratiempo')) {
            Schema::table('concertacion_extratiempo', function (Blueprint $table) {
                if (Schema::hasColumn('concertacion_extratiempo', 'tipo')) {
                    $table->dropColumn('tipo');
                }
            });
        }

        if (Schema::hasTable('periodo')) {
            Schema::table('periodo', function (Blueprint $table) {
                $columnas = [];
                if (Schema::hasColumn('periodo', 'fecha_fin_calificacion')) {
                    $columnas[] = 'fecha_fin_calificacion';
                }
                if (Schema::hasColumn('periodo', 'fecha_fin_concertacion')) {
                    $columnas[] = 'fecha_fin_concertacion';
                }
                if (!empty($columnas)) {
                    $table->dropColumn($columnas);
                }
            });
        }
    }
};
