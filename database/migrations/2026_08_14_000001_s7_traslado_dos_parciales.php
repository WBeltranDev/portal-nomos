<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S7 - Traslados: permite dos evaluaciones PARCIAL por (periodo, evaluado)
 * cuando corresponden a evaluadores distintos (dependencia origen y nuevo
 * cargo). Se relaja uq_evaluacion_periodo_ciclo incluyendo id_vinc_evaluador.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluacion', function (Blueprint $table) {
            $table->dropUnique('uq_evaluacion_periodo_ciclo');
            $table->unique(
                ['id_periodo', 'id_vinc_evaluado', 'tipo_evaluacion', 'id_vinc_evaluador'],
                'uq_evaluacion_periodo_ciclo_evaluador'
            );
        });
    }

    public function down(): void
    {
        Schema::table('evaluacion', function (Blueprint $table) {
            $table->dropUnique('uq_evaluacion_periodo_ciclo_evaluador');
            $table->unique(
                ['id_periodo', 'id_vinc_evaluado', 'tipo_evaluacion'],
                'uq_evaluacion_periodo_ciclo'
            );
        });
    }
};
