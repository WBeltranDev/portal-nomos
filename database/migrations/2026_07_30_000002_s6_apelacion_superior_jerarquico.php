<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S6 - Apelación dirigida al superior jerárquico del evaluador.
 *
 * Se agrega `vinculacion.id_vinc_jefe`: la vinculación del jefe inmediato del
 * cargo. El receptor de una apelación se resuelve como el jefe del evaluador
 * (fallback a Talento Humano si no está definido).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vinculacion', function (Blueprint $table) {
            if (!Schema::hasColumn('vinculacion', 'id_vinc_jefe')) {
                $table->unsignedInteger('id_vinc_jefe')->nullable()->after('es_evaluador');
                $table->index('id_vinc_jefe');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vinculacion', function (Blueprint $table) {
            $table->dropIndex(['id_vinc_jefe']);
            $table->dropColumn('id_vinc_jefe');
        });
    }
};
