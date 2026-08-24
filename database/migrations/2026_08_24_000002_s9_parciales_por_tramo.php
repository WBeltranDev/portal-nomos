<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Permite varios tramos PARCIAL para un mismo evaluador. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluacion', function (Blueprint $table) {
            $table->dropUnique('uq_evaluacion_periodo_ciclo_evaluador');
            $table->index(['id_periodo', 'id_vinc_evaluado', 'tipo_evaluacion'], 'idx_evaluacion_periodo_evaluado_tipo');
        });
    }

    public function down(): void
    {
        Schema::table('evaluacion', function (Blueprint $table) {
            $table->dropIndex('idx_evaluacion_periodo_evaluado_tipo');
            $table->unique(['id_periodo', 'id_vinc_evaluado', 'tipo_evaluacion', 'id_vinc_evaluador'], 'uq_evaluacion_periodo_ciclo_evaluador');
        });
    }
};
