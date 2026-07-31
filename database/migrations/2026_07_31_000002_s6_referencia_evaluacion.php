<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S6 - Campo de nombre o referencia en la evaluación parcial.
 *
 * Permite identificar el funcionario asociado a la evaluación PARCIAL que se
 * genera desde el formulario de traslados (campo "nombre o referencia").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluacion', function (Blueprint $table) {
            $table->string('referencia', 200)->nullable()->after('dias_laborados');
        });
    }

    public function down(): void
    {
        Schema::table('evaluacion', function (Blueprint $table) {
            $table->dropColumn('referencia');
        });
    }
};
