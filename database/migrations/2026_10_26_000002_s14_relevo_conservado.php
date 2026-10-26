<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S14 - Relevo con calificación iniciada (plan, fase E punto 21; decisión de TH:
 * caso por caso). Cuando TH decide que termina quien empezó la calificación, se
 * guarda la ocupación del nuevo titular frente a la cual se tomó la decisión:
 * el relevo automático la respeta mientras ese titular siga en la plaza.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('evaluacion', 'id_ocupacion_relevo_conservado')) {
            return;
        }

        Schema::table('evaluacion', function (Blueprint $table) {
            $table->unsignedInteger('id_ocupacion_relevo_conservado')->nullable()->after('id_vinc_enlazado');
            $table->foreign('id_ocupacion_relevo_conservado', 'fk_evaluacion_relevo_conservado')
                ->references('id_puesto_ocupacion')->on('puesto_ocupacion')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('evaluacion', 'id_ocupacion_relevo_conservado')) {
            return;
        }

        Schema::table('evaluacion', function (Blueprint $table) {
            $table->dropForeign('fk_evaluacion_relevo_conservado');
            $table->dropColumn('id_ocupacion_relevo_conservado');
        });
    }
};
