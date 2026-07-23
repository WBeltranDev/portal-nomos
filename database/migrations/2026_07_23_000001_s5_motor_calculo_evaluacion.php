<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S5 - Motor de Cálculo RL (70/15/15) y AG (85/7.5/7.5)
 *
 * Agrega columnas necesarias para soportar:
 * - calificacion_sem1 / calificacion_sem2 / calificacion_definitiva por compromiso
 * - calificacion por competencia evaluada
 * - nota total de compromisos y competencias a nivel de evaluacion
 * - calificacion_final y categoria_final en evaluacion (ya existe en el dump)
 * - calificacion_parcial para evaluaciones parciales con prorrateo
 */
return new class extends Migration
{
    public function up(): void
    {
        // Asegura que evaluacion tenga columna calificacion_parcial para prorrateo
        if (!Schema::hasColumn('evaluacion', 'calificacion_parcial')) {
            Schema::table('evaluacion', function (Blueprint $table) {
                $table->decimal('calificacion_parcial', 5, 2)->nullable()->after('calificacion_final')
                    ->comment('Nota ajustada por días laborados (prorrateo RF3)');
            });
        }

        // Asegura que evaluacion tenga nota_compromisos y nota_competencias
        if (!Schema::hasColumn('evaluacion', 'nota_compromisos')) {
            Schema::table('evaluacion', function (Blueprint $table) {
                $table->decimal('nota_compromisos', 5, 2)->nullable()->after('calificacion_parcial')
                    ->comment('Subtotal ponderado de compromisos');
                $table->decimal('nota_competencias', 5, 2)->nullable()->after('nota_compromisos')
                    ->comment('Subtotal ponderado de competencias');
                $table->decimal('nota_ejes_misionales', 5, 2)->nullable()->after('nota_competencias')
                    ->comment('Subtotal ponderado de ejes misionales (solo AG con ejes)');
            });
        }
    }

    public function down(): void
    {
        Schema::table('evaluacion', function (Blueprint $table) {
            foreach (['calificacion_parcial', 'nota_compromisos', 'nota_competencias', 'nota_ejes_misionales'] as $col) {
                if (Schema::hasColumn('evaluacion', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
