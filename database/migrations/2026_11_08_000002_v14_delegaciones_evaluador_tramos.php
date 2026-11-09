<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fase 5 (v1.4) — Delegaciones, tramos de 31 días y notas ponderadas.
     * Acta de Acuerdos del 29-10-2026 y PLAN-CAMBIOS-SERAG.
     */
    public function up(): void
    {
        // 5.1 Evaluador del delegado (Regla de Oro):
        // Indica si el delegado necesita un nuevo evaluador mientras ejerce la delegación
        if (Schema::hasTable('delegacion') && !Schema::hasColumn('delegacion', 'necesita_nuevo_evaluador')) {
            Schema::table('delegacion', function (Blueprint $table) {
                $table->boolean('necesita_nuevo_evaluador')->nullable()->after('es_larga');
            });
        }

        // 5.5 Tramos desde 31 días:
        // Parámetro mínimo de días para que un tramo genere evaluación independiente
        if (Schema::hasTable('configuracion_sistema')) {
            DB::table('configuracion_sistema')->updateOrInsert(
                ['clave' => 'minimo_dias_tramo'],
                [
                    'valor' => '31',
                    'descripcion' => 'Mínimo de días laborados requeridos para que un tramo genere evaluación y calificación independiente',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('delegacion') && Schema::hasColumn('delegacion', 'necesita_nuevo_evaluador')) {
            Schema::table('delegacion', function (Blueprint $table) {
                $table->dropColumn('necesita_nuevo_evaluador');
            });
        }

        if (Schema::hasTable('configuracion_sistema')) {
            DB::table('configuracion_sistema')->where('clave', 'minimo_dias_tramo')->delete();
        }
    }
};
