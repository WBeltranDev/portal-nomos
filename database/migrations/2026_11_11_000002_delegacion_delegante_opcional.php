<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Delegación de un cargo que nunca tuvo ocupante: no hay titular al cual ligarla
 * (la delegación sigue a la plaza, por id_puesto), así que el delegante es opcional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delegacion', function (Blueprint $table) {
            $table->unsignedInteger('id_vinc_delegante')->nullable()->change();
        });
    }

    public function down(): void
    {
        // No se revierte: volver a exigir el delegante invalidaría las delegaciones de plazas sin ocupante.
    }
};
