<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S10 - Auditoría del ajuste de días laborados en evaluaciones parciales.
 *
 * Cuando el evaluador ajusta manualmente los días laborados de un tramo PARCIAL
 * (que afectan la nota por prorrateo RF3), se deja constancia de quién
 * (id_usuario) y cuándo (timestamp) se hizo el ajuste.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('evaluacion', 'dias_laborados_ajustado_por')) {
            Schema::table('evaluacion', function (Blueprint $table) {
                $table->unsignedBigInteger('dias_laborados_ajustado_por')->nullable()->after('dias_laborados');
                $table->timestamp('dias_laborados_ajustado_el')->nullable()->after('dias_laborados_ajustado_por');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('evaluacion', 'dias_laborados_ajustado_el')) {
            Schema::table('evaluacion', function (Blueprint $table) {
                $table->dropColumn('dias_laborados_ajustado_el');
            });
        }
        if (Schema::hasColumn('evaluacion', 'dias_laborados_ajustado_por')) {
            Schema::table('evaluacion', function (Blueprint $table) {
                $table->dropColumn('dias_laborados_ajustado_por');
            });
        }
    }
};