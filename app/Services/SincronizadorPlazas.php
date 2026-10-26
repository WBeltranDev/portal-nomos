<?php

namespace App\Services;

use Illuminate\Support\Collection;
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
 *      - una evaluación en calificación (fase 4 o más): TH decide caso por caso
 *        en «Relevos por decidir» si pasa al nuevo ocupante (por defecto) o si
 *        la termina quien la empezó.
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

        foreach ($this->evaluacionesPorRelevar() as $e) {
            if ((int) $e->fase_actual >= 4) {
                $resultado['en_calificacion']++;
                continue;
            }
            $this->moverEvaluacion($e);
            $resultado['evaluaciones']++;
        }

        return $resultado;
    }

    /**
     * Evaluaciones abiertas que siguen con la persona con que se enlazaron, cuya
     * plaza tiene hoy otro ocupante: candidatas a relevo. Cada fila trae el
     * ocupante vigente y su ocupación. Se excluyen las plazas con delegación
     * vigente, las vacantes y las que TH decidió dejar con quien las empezó.
     */
    private function evaluacionesPorRelevar(): Collection
    {
        $ocupacion = DB::table('puesto_ocupacion')->where('estado', 'VIGENTE')->get(['id_puesto', 'id_vinculacion', 'id_puesto_ocupacion'])->keyBy('id_puesto');
        $delegadas = DB::table('delegacion')->whereIn('estado', ['ACTIVA', 'POR_FINALIZAR'])->whereNotNull('id_puesto')->pluck('id_puesto')->all();

        return DB::table('evaluacion')
            ->where('estado', 'EN_PROCESO')
            ->whereNull('id_vinc_suplente')
            ->whereNotNull('id_puesto_evaluador')
            ->whereColumn('id_vinc_enlazado', 'id_vinc_evaluador')
            ->whereNotIn('id_puesto_evaluador', $delegadas ?: [0])
            ->get()
            ->map(function ($e) use ($ocupacion) {
                $vigente = $ocupacion[$e->id_puesto_evaluador] ?? null;
                $e->ocupante = (int) ($vigente->id_vinculacion ?? 0);
                $e->id_ocupacion_vigente = (int) ($vigente->id_puesto_ocupacion ?? 0);

                return $e;
            })
            ->filter(fn ($e) => $e->ocupante
                && $e->ocupante !== (int) $e->id_vinc_evaluador
                && $e->ocupante !== (int) $e->id_vinc_evaluado
                && (int) ($e->id_ocupacion_relevo_conservado ?? 0) !== $e->id_ocupacion_vigente)
            ->values();
    }

    private function moverEvaluacion(object $e): void
    {
        DB::table('evaluacion')->where('id_evaluacion', $e->id_evaluacion)->update([
            'id_vinc_evaluador' => $e->ocupante,
            'id_vinc_enlazado' => $e->ocupante,
            'id_puesto_ocupacion_responsable' => $e->id_ocupacion_vigente,
            'id_ocupacion_relevo_conservado' => null,
        ]);
        $this->avisar($e, $e->ocupante);
    }

    /**
     * Relevos que esperan decisión de TH: evaluaciones en calificación cuya plaza
     * cambió de ocupante. «Conservable» indica si quien la empezó sigue activo y
     * puede terminarla (nunca se deja trabajo a una persona retirada).
     */
    public function relevosPorDecidir(): array
    {
        if (! $this->disponible()) {
            return [];
        }

        $casos = $this->evaluacionesPorRelevar()->filter(fn ($e) => (int) $e->fase_actual >= 4);
        if ($casos->isEmpty()) {
            return [];
        }

        $personas = DB::table('vinculacion as v')
            ->leftJoin('funcionario as f', 'f.id_funcionario', '=', 'v.id_funcionario')
            ->whereIn('v.id_vinculacion', $casos->flatMap(fn ($e) => [$e->id_vinc_evaluado, $e->id_vinc_evaluador, $e->ocupante])->unique()->all())
            ->get(['v.id_vinculacion', 'v.activa', DB::raw("TRIM(CONCAT(COALESCE(f.nombres, ''), ' ', COALESCE(f.apellidos, ''))) as nombre")])
            ->keyBy('id_vinculacion');
        $plazas = DB::table('puesto')->whereIn('id_puesto', $casos->pluck('id_puesto_evaluador')->unique()->all())->pluck('codigo_plaza', 'id_puesto');
        $periodos = DB::table('periodo')->whereIn('id_periodo', $casos->pluck('id_periodo')->unique()->all())->get()->keyBy('id_periodo');

        return $casos->map(fn ($e) => [
            'id_evaluacion' => (int) $e->id_evaluacion,
            'fase' => (int) $e->fase_actual,
            'tipo_evaluacion' => $e->tipo_evaluacion,
            'periodo' => isset($periodos[$e->id_periodo]) ? "{$periodos[$e->id_periodo]->anio}-{$periodos[$e->id_periodo]->semestre}" : null,
            'codigo_plaza' => $plazas[$e->id_puesto_evaluador] ?? null,
            'evaluado' => $personas[$e->id_vinc_evaluado]->nombre ?? null,
            'evaluador_actual' => $personas[$e->id_vinc_evaluador]->nombre ?? null,
            'nuevo_ocupante' => $personas[$e->ocupante]->nombre ?? null,
            'conservable' => (bool) ($personas[$e->id_vinc_evaluador]->activa ?? false),
        ])->values()->all();
    }

    /**
     * TH decide un relevo en calificación: NUEVO_OCUPANTE (continúa desde lo
     * guardado) o CONSERVAR (la termina quien la empezó). Devuelve el error o null.
     */
    public function decidirRelevo(int $idEvaluacion, string $decision): ?string
    {
        if (! $this->modelo->activo()) {
            return 'Los relevos se deciden con el modelo de puestos en modo Activo.';
        }

        return DB::transaction(function () use ($idEvaluacion, $decision) {
            DB::table('evaluacion')->where('id_evaluacion', $idEvaluacion)->lockForUpdate()->first();
            $e = $this->evaluacionesPorRelevar()->first(fn ($x) => (int) $x->id_evaluacion === $idEvaluacion && (int) $x->fase_actual >= 4);
            if (! $e) {
                return 'Esta evaluación ya no tiene un relevo por decidir.';
            }

            if ($decision === 'NUEVO_OCUPANTE') {
                $this->moverEvaluacion($e);

                return null;
            }

            if (! DB::table('vinculacion')->where('id_vinculacion', $e->id_vinc_evaluador)->where('activa', 1)->exists()) {
                return 'Quien empezó la calificación ya no está activo: la evaluación debe pasar al nuevo ocupante.';
            }

            DB::table('evaluacion')->where('id_evaluacion', $idEvaluacion)->update(['id_ocupacion_relevo_conservado' => $e->id_ocupacion_vigente]);
            if (function_exists('registrarEventoEvaluacion')) {
                registrarEventoEvaluacion($idEvaluacion, 'RELEVO_CONSERVADO', [
                    'id_puesto' => (int) $e->id_puesto_evaluador,
                    'id_vinc_evaluador' => (int) $e->id_vinc_evaluador,
                    'id_vinc_nuevo_ocupante' => $e->ocupante,
                ]);
            }

            return null;
        });
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
