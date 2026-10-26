<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S14 - El modo Observación también compara cada asignación de evaluador con la
 * jerarquía de plazas: quien evalúa debe ser el responsable de la plaza superior
 * de la plaza del evaluado. Esas diferencias no tienen evaluación: se guardan
 * con tipo JERARQUIA y el evaluado.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('puesto_discrepancia', 'tipo')) {
            return;
        }

        Schema::table('puesto_discrepancia', function (Blueprint $table) {
            $table->string('tipo', 20)->default('EVALUACION')->after('id_discrepancia');
            $table->unsignedInteger('id_vinc_evaluado')->nullable()->after('id_evaluacion');
            $table->foreign('id_vinc_evaluado', 'fk_discrepancia_evaluado')->references('id_vinculacion')->on('vinculacion');
        });
        DB::statement('ALTER TABLE puesto_discrepancia MODIFY id_evaluacion INT UNSIGNED NULL');
    }

    public function down(): void
    {
        if (! Schema::hasColumn('puesto_discrepancia', 'tipo')) {
            return;
        }

        DB::table('puesto_discrepancia')->where('tipo', 'JERARQUIA')->delete();
        DB::statement('ALTER TABLE puesto_discrepancia MODIFY id_evaluacion INT UNSIGNED NOT NULL');
        Schema::table('puesto_discrepancia', function (Blueprint $table) {
            $table->dropForeign('fk_discrepancia_evaluado');
            $table->dropColumn(['tipo', 'id_vinc_evaluado']);
        });
    }
};
