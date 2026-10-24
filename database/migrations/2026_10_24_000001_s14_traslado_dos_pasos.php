<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S14 - Traslado en dos pasos: Talento Humano programa el traslado, el
 * evaluador de origen califica la evaluación vigente (tramo) y solo con esa
 * nota se confirma el traslado y se abre la evaluación del cargo nuevo.
 * Los traslados existentes quedan CONFIRMADOS y sin referencias de tramo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('traslado', 'estado')) {
            return;
        }

        Schema::table('traslado', function (Blueprint $table) {
            $table->enum('estado', ['PROGRAMADO', 'CONFIRMADO', 'CANCELADO'])->default('CONFIRMADO')->after('id_vinc_funcionario');
            $table->unsignedInteger('id_periodo')->nullable()->after('estado');
            $table->unsignedInteger('id_evaluacion_origen')->nullable()->after('id_evaluacion_parcial');
            $table->unsignedInteger('id_evaluacion_destino')->nullable()->after('id_evaluacion_origen');
            $table->unsignedSmallInteger('dias_origen_previos')->nullable()->after('dias_laborados');
            $table->string('referencia', 200)->nullable()->after('motivo');
            $table->unsignedInteger('id_usuario_resuelve')->nullable()->after('id_usuario_registra');
            $table->timestamp('resuelto_at')->nullable()->after('id_usuario_resuelve');

            $table->index(['id_vinc_funcionario', 'estado'], 'idx_traslado_funcionario_estado');
            $table->foreign('id_periodo', 'fk_traslado_periodo')->references('id_periodo')->on('periodo');
            $table->foreign('id_evaluacion_origen', 'fk_traslado_eval_origen')->references('id_evaluacion')->on('evaluacion');
            $table->foreign('id_evaluacion_destino', 'fk_traslado_eval_destino')->references('id_evaluacion')->on('evaluacion');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('traslado', 'estado')) {
            return;
        }

        Schema::table('traslado', function (Blueprint $table) {
            $table->dropForeign('fk_traslado_periodo');
            $table->dropForeign('fk_traslado_eval_origen');
            $table->dropForeign('fk_traslado_eval_destino');
            $table->dropIndex('idx_traslado_funcionario_estado');
            $table->dropColumn([
                'estado',
                'id_periodo',
                'id_evaluacion_origen',
                'id_evaluacion_destino',
                'dias_origen_previos',
                'referencia',
                'id_usuario_resuelve',
                'resuelto_at',
            ]);
        });
    }
};
