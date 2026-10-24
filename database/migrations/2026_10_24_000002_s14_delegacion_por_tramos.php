<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S14 - Delegación por tramos. Una delegación de más de 30 días (o sin fecha
 * final) cambia de evaluador como un traslado: se programa, se califican los
 * tramos de todas las personas a cargo y del delegado, y solo entonces se
 * confirma. Al terminar se repite el ciclo hacia el sucesor que elija Talento
 * Humano. Cada cambio de evaluador queda como un traslado ligado a la
 * delegación (traslado.id_delegacion).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('delegacion', 'es_larga')) {
            return;
        }

        DB::statement("ALTER TABLE delegacion MODIFY estado ENUM('PROGRAMADA','ACTIVA','POR_FINALIZAR','FINALIZADA','CANCELADA') NOT NULL DEFAULT 'ACTIVA'");

        Schema::table('delegacion', function (Blueprint $table) {
            $table->boolean('es_larga')->default(false)->after('estado');
            $table->unsignedInteger('id_vinc_superior_delegado')->nullable()->after('es_larga');
            $table->unsignedInteger('id_vinc_evaluador_original_delegado')->nullable()->after('id_vinc_superior_delegado');
            $table->unsignedInteger('id_vinc_sucesor')->nullable()->after('id_vinc_evaluador_original_delegado');
            $table->date('fecha_regreso')->nullable()->after('fecha_fin');

            $table->foreign('id_vinc_superior_delegado', 'fk_delegacion_superior_delegado')->references('id_vinculacion')->on('vinculacion');
            $table->foreign('id_vinc_evaluador_original_delegado', 'fk_delegacion_eval_original')->references('id_vinculacion')->on('vinculacion');
            $table->foreign('id_vinc_sucesor', 'fk_delegacion_sucesor')->references('id_vinculacion')->on('vinculacion');
        });

        Schema::table('traslado', function (Blueprint $table) {
            $table->unsignedInteger('id_delegacion')->nullable()->after('id_periodo');
            $table->foreign('id_delegacion', 'fk_traslado_delegacion')->references('id_delegacion')->on('delegacion');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('delegacion', 'es_larga')) {
            return;
        }

        Schema::table('traslado', function (Blueprint $table) {
            $table->dropForeign('fk_traslado_delegacion');
            $table->dropColumn('id_delegacion');
        });

        Schema::table('delegacion', function (Blueprint $table) {
            $table->dropForeign('fk_delegacion_superior_delegado');
            $table->dropForeign('fk_delegacion_eval_original');
            $table->dropForeign('fk_delegacion_sucesor');
            $table->dropColumn(['es_larga', 'id_vinc_superior_delegado', 'id_vinc_evaluador_original_delegado', 'id_vinc_sucesor', 'fecha_regreso']);
        });

        // Los estados nuevos se llevan al equivalente previo antes de reducir el ENUM.
        DB::table('delegacion')->whereIn('estado', ['PROGRAMADA', 'CANCELADA'])->update(['estado' => 'FINALIZADA']);
        DB::table('delegacion')->where('estado', 'POR_FINALIZAR')->update(['estado' => 'ACTIVA']);
        DB::statement("ALTER TABLE delegacion MODIFY estado ENUM('ACTIVA','FINALIZADA') NOT NULL DEFAULT 'ACTIVA'");
    }
};
