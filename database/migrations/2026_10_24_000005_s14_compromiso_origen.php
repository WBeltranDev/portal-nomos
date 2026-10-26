<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S14 (plan 3.4) - Los compromisos copiados como borrador (p. ej. del
 * funcionario al que reemplaza un trasladado) conservan la referencia al
 * compromiso de origen y si se ajustaron antes de concertar. Si el original se
 * borra, la copia sigue marcada como ajena pero sin enlace.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('compromiso', 'id_compromiso_origen')) {
            return;
        }

        Schema::table('compromiso', function (Blueprint $table) {
            $table->unsignedInteger('id_compromiso_origen')->nullable()->after('id_evaluacion');
            $table->boolean('es_copia')->default(false)->after('id_compromiso_origen');
            $table->boolean('copia_ajustada')->default(false)->after('es_copia');

            $table->foreign('id_compromiso_origen', 'fk_compromiso_origen')
                ->references('id_compromiso')->on('compromiso')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('compromiso', 'id_compromiso_origen')) {
            return;
        }

        Schema::table('compromiso', function (Blueprint $table) {
            $table->dropForeign('fk_compromiso_origen');
            $table->dropColumn(['id_compromiso_origen', 'es_copia', 'copia_ajustada']);
        });
    }
};
