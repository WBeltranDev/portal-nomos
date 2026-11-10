<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Corrección VI del plan de pruebas 29-10-2026: al delegar, Talento Humano elige
 * si el evaluador de las personas a cargo CAMBIA (el delegado las evalúa, como
 * hasta ahora) o PERMANECE (siguen con su evaluador y el delegado solo cubre la
 * delegación). Las delegaciones existentes cambian el evaluador.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('delegacion', 'cambia_evaluador')) {
            Schema::table('delegacion', function (Blueprint $table) {
                $table->boolean('cambia_evaluador')->default(true)->after('es_larga');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('delegacion', 'cambia_evaluador')) {
            Schema::table('delegacion', fn (Blueprint $table) => $table->dropColumn('cambia_evaluador'));
        }
    }
};
