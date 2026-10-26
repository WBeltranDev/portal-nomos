<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S14 - Modelo de puestos, fase B del plan: referencias opcionales a la plaza
 * en las tablas del flujo. Quedan vacías hasta cargar el catálogo validado
 * por Talento Humano; las columnas de vinculación existentes se conservan
 * (lectura y escritura dual durante la transición).
 */
return new class extends Migration
{
    /** Tabla => columnas nuevas (todas FK nullable a puesto o puesto_ocupacion). */
    private const COLUMNAS = [
        'delegacion' => ['id_puesto' => 'puesto'],
        'evaluador_asignacion' => ['id_puesto_evaluador' => 'puesto'],
        'evaluacion' => ['id_puesto_evaluador' => 'puesto', 'id_puesto_ocupacion_responsable' => 'puesto_ocupacion'],
        'periodo_parcial' => ['id_puesto_responsable' => 'puesto'],
        'traslado' => ['id_puesto_origen' => 'puesto', 'id_puesto_destino' => 'puesto'],
    ];

    public function up(): void
    {
        foreach (self::COLUMNAS as $tabla => $columnas) {
            Schema::table($tabla, function (Blueprint $table) use ($tabla, $columnas) {
                foreach ($columnas as $columna => $referencia) {
                    if (Schema::hasColumn($tabla, $columna)) {
                        continue;
                    }
                    $table->unsignedInteger($columna)->nullable();
                    $table->foreign($columna, "fk_{$tabla}_{$columna}")
                        ->references($referencia === 'puesto' ? 'id_puesto' : 'id_puesto_ocupacion')
                        ->on($referencia)
                        ->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNAS as $tabla => $columnas) {
            Schema::table($tabla, function (Blueprint $table) use ($tabla, $columnas) {
                foreach (array_keys($columnas) as $columna) {
                    if (! Schema::hasColumn($tabla, $columna)) {
                        continue;
                    }
                    $table->dropForeign("fk_{$tabla}_{$columna}");
                    $table->dropColumn($columna);
                }
            });
        }
    }
};
