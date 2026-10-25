<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S14 - Mantiene alineadas las columnas por persona (que siguen leyendo las
 * bandejas y permisos) con el modelo de plazas (plan, fase E).
 *
 * 1. Enlaza a su plaza lo nuevo y lo que el flujo actual reasignó
 *    (EnlacePuestos). En este paso manda la persona.
 * 2. Solo con el modelo ACTIVO, relevo: si cambió el ocupante de una plaza, sus
 *    asignaciones y evaluaciones abiertas pasan al ocupante vigente, con evento
 *    en la bitácora y aviso al evaluado y al nuevo evaluador. En este paso
 *    manda la plaza. No se toca:
 *      - una plaza con delegación vigente (la delegación ya decide quién atiende);
 *      - una plaza vacante (nunca se devuelve trabajo a quien ya no la ocupa);
 *      - una evaluación con evidencias confirmadas (fase 4 o más), hasta que
 *        Talento Humano defina quién termina una calificación iniciada.
 *    Lo que no se mueve queda como diferencia del modo Observación para TH.
 */
class SincronizadorPlazas
{
    public function __construct(private EnlacePuestos $enlace, private ModeloPuestos $modelo)
    {
    }

    public function disponible(): bool
    {
        return Schema::hasColumn('evaluacion', 'id_vinc_enlazado') && DB::table('puesto_ocupacion')->exists();
    }

    public function sincronizar(): array
    {
        if (! $this->disponible()) {
            return ['omitido' => 'El catálogo de plazas aún no tiene ocupaciones.'];
        }

        return DB::transaction(function () {
            $enlace = $this->enlace->aplicar();
            $relevo = $this->modelo->activo() ? $this->relevar() : null;

            return ['enlace' => $enlace['resumen'], 'excepciones' => $enlace['excepciones'], 'relevo' => $relevo];
        });
    }

    private function relevar(): array
    {
        $ocupacion = DB::table('puesto_ocupacion')->where('estado', 'VIGENTE')->get(['id_puesto', 'id_vinculacion', 'id_puesto_ocupacion'])->keyBy('id_puesto');
        $delegadas = DB::table('delegacion')->whereIn('estado', ['ACTIVA', 'POR_FINALIZAR'])->whereNotNull('id_puesto')->pluck('id_puesto')->all();
        $resultado = ['asignaciones' => 0, 'evaluaciones' => 0, 'en_calificacion' => 0];

        // Solo lo que sigue con la persona con que se enlazó: si el flujo actual lo
        // reasignó, el paso de enlace ya lo movió a la plaza de la nueva persona.
        $asignaciones = DB::table('evaluador_asignacion')
            ->whereNotNull('id_puesto_evaluador')
            ->whereColumn('id_vinc_enlazado', 'id_vinc_evaluador')
            ->whereNotIn('id_puesto_evaluador', $delegadas ?: [0])
            ->get();

        foreach ($asignaciones as $a) {
            $ocupante = (int) ($ocupacion[$a->id_puesto_evaluador]->id_vinculacion ?? 0);
            if (! $ocupante || $ocupante === (int) $a->id_vinc_evaluador || $ocupante === (int) $a->id_vinc_evaluado) {
                continue;
            }

            $yaLaTiene = DB::table('evaluador_asignacion')->where('id_vinc_evaluador', $ocupante)->where('id_vinc_evaluado', $a->id_vinc_evaluado)->exists();
            $fila = DB::table('evaluador_asignacion')->where('id_asignacion', $a->id_asignacion);
            $yaLaTiene
                ? $fila->delete()
                : $fila->update(['id_vinc_evaluador' => $ocupante, 'id_vinc_enlazado' => $ocupante, 'fecha_asignacion' => now()]);
            $resultado['asignaciones']++;
        }

        $evaluaciones = DB::table('evaluacion')
            ->where('estado', 'EN_PROCESO')
            ->whereNull('id_vinc_suplente')
            ->whereNotNull('id_puesto_evaluador')
            ->whereColumn('id_vinc_enlazado', 'id_vinc_evaluador')
            ->whereNotIn('id_puesto_evaluador', $delegadas ?: [0])
            ->get();

        foreach ($evaluaciones as $e) {
            $vigente = $ocupacion[$e->id_puesto_evaluador] ?? null;
            $ocupante = (int) ($vigente->id_vinculacion ?? 0);
            if (! $ocupante || $ocupante === (int) $e->id_vinc_evaluador || $ocupante === (int) $e->id_vinc_evaluado) {
                continue;
            }
            if ((int) $e->fase_actual >= 4) {
                $resultado['en_calificacion']++;
                continue;
            }

            DB::table('evaluacion')->where('id_evaluacion', $e->id_evaluacion)->update([
                'id_vinc_evaluador' => $ocupante,
                'id_vinc_enlazado' => $ocupante,
                'id_puesto_ocupacion_responsable' => $vigente->id_puesto_ocupacion,
            ]);
            $resultado['evaluaciones']++;
            $this->avisar($e, $ocupante);
        }

        return $resultado;
    }

    private function avisar(object $evaluacion, int $nuevo): void
    {
        if (function_exists('registrarEventoEvaluacion')) {
            registrarEventoEvaluacion((int) $evaluacion->id_evaluacion, 'CAMBIO_EVALUADOR', [
                'origen' => 'RELEVO_PLAZA',
                'id_puesto' => (int) $evaluacion->id_puesto_evaluador,
                'id_vinc_evaluador_anterior' => (int) $evaluacion->id_vinc_evaluador,
                'id_vinc_evaluador_nuevo' => $nuevo,
            ]);
        }

        if (function_exists('notificarVinculacion') && function_exists('nombreFuncionarioVinc')) {
            notificarVinculacion((int) $evaluacion->id_vinc_evaluado, 'CAMBIO_EVALUADOR', 'Cambio de evaluador',
                'Por cambio del ocupante del cargo, tu evaluador es ' . nombreFuncionarioVinc($nuevo) . '. Lo realizado hasta hoy se conserva.', 'evaluaciones');
            notificarVinculacion($nuevo, 'NUEVO_EVALUADO', 'Nueva persona a cargo',
                'Al ocupar el cargo, evalúas a ' . nombreFuncionarioVinc((int) $evaluacion->id_vinc_evaluado) . '. La evaluación continúa desde donde quedó.', 'evaluaciones-evaluador');
        }
    }
}
