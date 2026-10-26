<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S14 - Ausencia larga del titular de una delegación. Además del cambio de
 * evaluador (CAMBIO), un traslado puede ser una PAUSA (se cierra el tramo del
 * titular sin cambiar de evaluador ni abrir otra evaluación) o una
 * REANUDACION (al regresar se abre una evaluación nueva con el mismo
 * evaluador). Los días de ausencia no entran al promedio del semestre.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('traslado', 'tipo')) {
            return;
        }

        Schema::table('traslado', function (Blueprint $table) {
            $table->enum('tipo', ['CAMBIO', 'PAUSA', 'REANUDACION'])->default('CAMBIO')->after('estado');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('traslado', 'tipo')) {
            return;
        }

        Schema::table('traslado', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
    }
};
