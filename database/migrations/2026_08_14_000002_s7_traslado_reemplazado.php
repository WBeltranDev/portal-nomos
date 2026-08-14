<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S7 - Traslados: registra a quién reemplaza el funcionario trasladado
 * (por lo general un titular que ya no está activo). Permite heredar los
 * compromisos de la evaluación del reemplazado y deja trazabilidad.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('traslado', 'id_vinc_reemplazado')) {
            Schema::table('traslado', function (Blueprint $table) {
                $table->unsignedInteger('id_vinc_reemplazado')->nullable()->after('id_vinc_evaluador_nuevo');
                $table->foreign('id_vinc_reemplazado')
                    ->references('id_vinculacion')
                    ->on('vinculacion')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('traslado', 'id_vinc_reemplazado')) {
            Schema::table('traslado', function (Blueprint $table) {
                $table->dropForeign(['id_vinc_reemplazado']);
                $table->dropColumn('id_vinc_reemplazado');
            });
        }
    }
};
