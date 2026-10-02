<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * S11 — Escala de calificación institucional de 1.0 a 5.0 con un (1) decimal.
 *
 * Hasta ahora las cuatro notas se manejaban en escala 0-100 (con 2 decimales):
 *   - calificación general  → evaluacion.calificacion_final / calificacion_parcial
 *   - nota de compromisos    → compromiso.calificacion_sem1 / sem2 / definitiva
 *   - nota de competencias   → competencia_evaluada.calificacion_sem1 / sem2 / definitiva
 *   - nota de eje misional   → eje_misional_calificacion.calificacion
 *
 * La nueva escala es 1.0 a 5.0 con un decimal, y sus cuatro bandas son también
 * las categorías finales:
 *   1.0 a 3.4  No satisfactorio
 *   3.5 a 4.0  Susceptible a plan de mejora (aplica plan de mejoramiento)
 *   4.1 a 4.5  Bueno
 *   4.6 a 5.0  Sobresaliente
 *
 * CONVERSIÓN: como 1.0 es el nuevo mínimo y solo hay un decimal, cada nota
 * vieja se convierte con  valor_nuevo = REDONDEO(valor_viejo / 20, 1)  y se
 * acota al rango [1.0, 5.0]. Consecuencias inevitables de la nueva escala:
 *   - Todo lo que estaba por debajo de 2.0 (es decir, menos de 40 sobre 100)
 *     queda en 1.0, porque ya no existe una nota menor a 1.0.
 *   - Un 4.6 no se distingue de un 4.58: la escala ya no tiene 2 decimales.
 *
 * La migración es idempotente: si el valor máximo guardado en las tablas ya es
 * <= 5.0, se asume que los datos ya están en la nueva escala y no se toca nada.
 */
class S11EscalaCalificacion1a5 extends Migration
{
    /** Tabla => columnas de calificación que viven en escala 0-100. */
    private const COLUMNAS = [
        'compromiso'               => ['calificacion_sem1', 'calificacion_sem2', 'calificacion_definitiva'],
        'competencia_evaluada'     => ['calificacion_sem1', 'calificacion_sem2', 'calificacion_definitiva'],
        'eje_misional_calificacion' => ['calificacion'],
        // Incluye las columnas de resultado ya calculadas, para que los
        // informes históricos no muestren notas fuera de la nueva escala.
        'evaluacion'               => ['calificacion_final', 'calificacion_parcial',
                                       'nota_compromisos', 'nota_competencias', 'nota_ejes_misionales'],
    ];

    public function up(): void
    {
        $maximoActual = $this->maximoAlmacenado();

        if ($maximoActual !== null && $maximoActual <= 5.0) {
            Log::info('S11 escala 1-5: los datos ya estaban en la nueva escala (máximo ' . $maximoActual . '). No se convirtió nada.');
            return;
        }

        $total = 0;
        foreach (self::COLUMNAS as $tabla => $columnas) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }

            foreach ($columnas as $columna) {
                if (! Schema::hasColumn($tabla, $columna)) {
                    continue;
                }

                // valor / 20, redondeado a un decimal y acotado a [1.0, 5.0].
                // LEAST/GREATEST se usan para que el acotado sea del motor y no
                // dependa de la función de redondeo de PHP.
                $afectadas = DB::table($tabla)
                    ->whereNotNull($columna)
                    ->update([
                        $columna => DB::raw(
                            'ROUND(LEAST(5.0, GREATEST(1.0, ' . $columna . ' / 20)), 1)'
                        ),
                    ]);

                $total += $afectadas;
            }
        }

        Log::info('S11 escala 1-5: ' . $total . ' calificaciones convertidas desde 0-100.');
    }

    public function down(): void
    {
        // AVISO: la reversión es una red de seguridad, no un retour exacto.
        // Al convertir de 2 decimales a 1 se pierde precisión, así que volver a
        // 0-100 no reconstruye la nota original: 71 → 3.6 → 72, 87.3 → 4.4 → 88.
        // Además, todo lo que quedó en 1.0 vuelve como 20, porque la escala
        // vieja sí admitía el 0 y la nueva no.
        //
        // Se usa el mismo criterio idempotente: si el máximo ya supera 5.0,
        // los datos ya están en 0-100 y no se toca nada.
        $maximoActual = $this->maximoAlmacenado();

        if ($maximoActual !== null && $maximoActual > 5.0) {
            Log::info('S11 escala 1-5 reversa: los datos ya estaban en 0-100 (máximo ' . $maximoActual . '). No se revirtió nada.');
            return;
        }

        $total = 0;
        foreach (self::COLUMNAS as $tabla => $columnas) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }

            foreach ($columnas as $columna) {
                if (! Schema::hasColumn($tabla, $columna)) {
                    continue;
                }

                $afectadas = DB::table($tabla)
                    ->whereNotNull($columna)
                    ->update([
                        $columna => DB::raw(
                            'ROUND(LEAST(' . $columna . ' * 20, 100), 2)'
                        ),
                    ]);

                $total += $afectadas;
            }
        }

        Log::info('S11 escala 1-5 reversa: ' . $total . ' calificaciones convertidas de vuelta a 0-100.');
    }

    /** Mayor nota guardada entre todas las columnas de calificación, o null si no hay datos. */
    private function maximoAlmacenado(): ?float
    {
        $maximo = null;

        foreach (self::COLUMNAS as $tabla => $columnas) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }

            foreach ($columnas as $columna) {
                if (! Schema::hasColumn($tabla, $columna)) {
                    continue;
                }

                $valor = DB::table($tabla)->whereNotNull($columna)->max($columna);
                if ($valor === null) {
                    continue;
                }

                $valor = (float) $valor;
                $maximo = $maximo === null ? $valor : max($maximo, $valor);
            }
        }

        return $maximo;
    }
}
