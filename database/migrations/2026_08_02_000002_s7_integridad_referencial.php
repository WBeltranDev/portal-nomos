<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S7 - Integridad referencial y unicidad (normalización).
 *
 * - `evaluacion`: única por (periodo, evaluado, ciclo) para evitar duplicados.
 * - `traslado`: agrega FKs a vinculacion/evaluacion/usuario (solo índices antes).
 * - `compromiso_observacion`: limpia registros huérfanos de prueba, alinea los
 *   tipos (bigint -> int unsigned) y agrega FKs hacia evaluacion/compromiso/vinculacion.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Unicidad de evaluación por periodo + evaluado + ciclo
        Schema::table('evaluacion', function (Blueprint $table) {
            $table->unique(['id_periodo', 'id_vinc_evaluado', 'tipo_evaluacion'], 'uq_evaluacion_periodo_ciclo');
        });

        // 2. FKs de traslado (tabla vacía en ambientes actuales)
        Schema::table('traslado', function (Blueprint $table) {
            $table->foreign('id_vinc_funcionario', 'traslado_funcionario_fk')
                ->references('id_vinculacion')->on('vinculacion')->onDelete('cascade');
            $table->foreign('id_vinc_evaluador_origen', 'traslado_eval_origen_fk')
                ->references('id_vinculacion')->on('vinculacion')->onDelete('set null');
            $table->foreign('id_vinc_evaluador_nuevo', 'traslado_eval_nuevo_fk')
                ->references('id_vinculacion')->on('vinculacion')->onDelete('cascade');
            $table->foreign('id_evaluacion_parcial', 'traslado_parcial_fk')
                ->references('id_evaluacion')->on('evaluacion')->onDelete('set null');
            $table->foreign('id_usuario_registra', 'traslado_usuario_fk')
                ->references('id_usuario')->on('usuario')->onDelete('set null');
        });

        // 3. compromiso_observacion: limpiar huérfanos, tipos y FKs
        DB::table('compromiso_observacion')
            ->whereNotIn('id_evaluacion', DB::table('evaluacion')->select('id_evaluacion'))
            ->orWhereNotIn('id_compromiso', DB::table('compromiso')->select('id_compromiso'))
            ->orWhereNotIn('id_vinc_evaluador', DB::table('vinculacion')->select('id_vinculacion'))
            ->delete();

        Schema::table('compromiso_observacion', function (Blueprint $table) {
            $table->unsignedInteger('id_evaluacion')->change();
            $table->unsignedInteger('id_compromiso')->change();
            $table->unsignedInteger('id_vinc_evaluador')->nullable()->change();
        });

        Schema::table('compromiso_observacion', function (Blueprint $table) {
            $table->foreign('id_evaluacion', 'obs_evaluacion_fk')
                ->references('id_evaluacion')->on('evaluacion')->onDelete('cascade');
            $table->foreign('id_compromiso', 'obs_compromiso_fk')
                ->references('id_compromiso')->on('compromiso')->onDelete('cascade');
            $table->foreign('id_vinc_evaluador', 'obs_evaluador_fk')
                ->references('id_vinculacion')->on('vinculacion')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('compromiso_observacion', function (Blueprint $table) {
            $table->dropForeign('obs_evaluacion_fk');
            $table->dropForeign('obs_compromiso_fk');
            $table->dropForeign('obs_evaluador_fk');
            $table->unsignedBigInteger('id_evaluacion')->change();
            $table->unsignedBigInteger('id_compromiso')->change();
            $table->unsignedBigInteger('id_vinc_evaluador')->nullable()->change();
        });

        Schema::table('traslado', function (Blueprint $table) {
            $table->dropForeign('traslado_funcionario_fk');
            $table->dropForeign('traslado_eval_origen_fk');
            $table->dropForeign('traslado_eval_nuevo_fk');
            $table->dropForeign('traslado_parcial_fk');
            $table->dropForeign('traslado_usuario_fk');
        });

        Schema::table('evaluacion', function (Blueprint $table) {
            $table->dropUnique('uq_evaluacion_periodo_ciclo');
        });
    }
};
