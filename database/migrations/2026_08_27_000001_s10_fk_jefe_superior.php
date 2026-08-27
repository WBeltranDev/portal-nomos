<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S10 - Integridad referencial para el superior jerárquico.
 *
 * Convierte `vinculacion.id_vinc_jefe` (agregado en S6 como simple índice)
 * en una FK real hacia `vinculacion.id_vinculacion`. Antes de crear la FK
 * se limpian defensivamente referencias huérfanas para no fallar en migraciones.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('vinculacion', 'id_vinc_jefe')) {
            return;
        }

        $idsValidos = DB::table('vinculacion')->pluck('id_vinculacion')->all();

        DB::table('vinculacion')
            ->whereNotNull('id_vinc_jefe')
            ->whereNotIn('id_vinc_jefe', $idsValidos)
            ->update(['id_vinc_jefe' => null]);

        Schema::table('vinculacion', function (Blueprint $table) {
            $table->foreign('id_vinc_jefe', 'vinculacion_id_vinc_jefe_fk')
                ->references('id_vinculacion')
                ->on('vinculacion')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('vinculacion', 'id_vinc_jefe')) {
            return;
        }

        Schema::table('vinculacion', function (Blueprint $table) {
            $table->dropForeign('vinculacion_id_vinc_jefe_fk');
        });
    }
};