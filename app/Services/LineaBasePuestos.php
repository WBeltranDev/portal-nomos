<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S14 - Línea base para conciliar la migración al modelo de puestos (plan,
 * fase A): conteos por estado y una huella de cada tabla afectada, tomados
 * antes de ligar datos a plazas. Después de la migración se compara contra
 * esta foto para comprobar que no se perdieron ni cambiaron registros.
 *
 * Solo guarda ids, estados y conteos (muestras anonimizadas): ningún nombre,
 * documento ni texto.
 */
class LineaBasePuestos
{
    /** Tabla => [columnas de la huella, columna de estado para el conteo]. */
    private const TABLAS = [
        'vinculacion' => [['id_vinculacion', 'id_funcionario', 'activa', 'id_vinc_jefe'], 'activa'],
        'evaluador_asignacion' => [['id_asignacion', 'id_vinc_evaluador', 'id_vinc_evaluado'], null],
        'delegacion' => [['id_delegacion', 'id_vinc_delegante', 'id_vinc_delegado', 'estado'], 'estado'],
        'evaluacion' => [['id_evaluacion', 'id_periodo', 'id_vinc_evaluado', 'id_vinc_evaluador', 'tipo_evaluacion', 'estado', 'calificacion_final'], 'estado'],
        'periodo_parcial' => [['id_periodo_parcial', 'id_periodo', 'id_vinc_funcionario', 'estado'], 'estado'],
        'traslado' => [['id_traslado', 'id_vinc_funcionario', 'id_vinc_evaluador_origen', 'id_vinc_evaluador_nuevo', 'estado'], 'estado'],
        'firma' => [['id_evaluacion', 'tipo_firma', 'id_vinc_firmante'], 'tipo_firma'],
        'evidencia' => [['id_evidencia', 'id_evaluacion', 'estado_aprobacion'], 'estado_aprobacion'],
        'recurso' => [['id_recurso', 'id_evaluacion', 'tipo_recurso', 'decision', 'id_vinc_receptor'], 'decision'],
        'compromiso' => [['id_compromiso', 'id_evaluacion', 'porcentaje_peso', 'calificacion_definitiva'], null],
        'puesto' => [['id_puesto', 'codigo_plaza', 'estado', 'id_puesto_superior'], 'estado'],
        'puesto_ocupacion' => [['id_puesto_ocupacion', 'id_puesto', 'id_vinculacion', 'estado'], 'estado'],
    ];

    private const MUESTRA = 10;

    public function capturar(): array
    {
        $tablas = [];
        foreach (self::TABLAS as $tabla => [$columnas, $estado]) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }

            $filas = DB::table($tabla)->orderBy($columnas[0])->get($columnas);

            $tablas[$tabla] = [
                'total' => $filas->count(),
                'por_estado' => $estado ? $filas->countBy(fn ($f) => (string) $f->{$estado})->sortKeys()->all() : null,
                // Cambia si se agrega, borra o modifica cualquier valor clave de la tabla.
                'huella' => md5($filas->map(fn ($f) => implode('|', array_map(fn ($c) => (string) $f->{$c}, $columnas)))->implode("\n")),
                // Muestra fija (los primeros ids) para revisar a mano registro por registro.
                'muestra' => $filas->take(self::MUESTRA)->map(fn ($f) => (array) $f)->values()->all(),
            ];
        }

        return [
            'generada_el' => now()->toIso8601String(),
            'base_de_datos' => DB::connection()->getDatabaseName(),
            'tablas' => $tablas,
        ];
    }

    /**
     * Diferencias entre una línea base guardada y el estado actual. Un total o
     * una huella distintos indican que la tabla cambió desde la foto.
     */
    public function comparar(array $anterior): array
    {
        $actual = $this->capturar()['tablas'];
        $diferencias = [];

        foreach ($anterior['tablas'] ?? [] as $tabla => $antes) {
            $ahora = $actual[$tabla] ?? null;
            if (! $ahora) {
                $diferencias[$tabla] = ['detalle' => 'La tabla ya no existe.'];
                continue;
            }
            if ($antes['huella'] === $ahora['huella']) {
                continue;
            }

            $estados = array_unique(array_merge(array_keys($antes['por_estado'] ?? []), array_keys($ahora['por_estado'] ?? [])));
            $cambiosEstado = [];
            foreach ($estados as $estado) {
                $a = $antes['por_estado'][$estado] ?? 0;
                $b = $ahora['por_estado'][$estado] ?? 0;
                if ($a !== $b) {
                    $cambiosEstado[$estado] = ['antes' => $a, 'despues' => $b];
                }
            }

            $diferencias[$tabla] = [
                'total' => ['antes' => $antes['total'], 'despues' => $ahora['total']],
                'por_estado' => $cambiosEstado,
            ];
        }

        return $diferencias;
    }
}
