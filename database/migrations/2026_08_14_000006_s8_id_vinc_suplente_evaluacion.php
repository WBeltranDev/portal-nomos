<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S8 - Delegaciones: referencia al titular (delegante) en la evaluación.
 *
 * Cuando una delegación está activa, las evaluaciones pendientes del delegante
 * pasan al delegado y se marca al titular en id_vinc_suplente para poder
 * devolverlas (con su responsabilidad de firma final) cuando la delegación
 * finaliza.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('evaluacion', 'id_vinc_suplente')) {
            Schema::table('evaluacion', function (Blueprint $table) {
                $table->unsignedInteger('id_vinc_suplente')->nullable()->after('id_vinc_evaluador');
                $table->index('id_vinc_suplente', 'ev_suplente_idx');
                $table->foreign('id_vinc_suplente', 'ev_suplente_fk')
                    ->references('id_vinculacion')->on('vinculacion')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('evaluacion', 'id_vinc_suplente')) {
            Schema::table('evaluacion', function (Blueprint $table) {
                $table->dropForeign('ev_suplente_fk');
                $table->dropIndex('ev_suplente_idx');
                $table->dropColumn('id_vinc_suplente');
            });
        }
    }
};
