<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S14 - Enlace de los registros a su plaza (plan, fases C y E).
 *
 * Llena las referencias a plaza a partir de las ocupaciones cargadas con la
 * planta:
 *   - evaluador_asignacion: plaza vigente del evaluador.
 *   - evaluacion: abierta → plaza vigente del titular (el delegante si hay
 *     suplente); cerrada → la plaza que el evaluador ocupaba en el periodo.
 *   - delegacion: plaza del delegante durante la delegación.
 *   - periodo_parcial: la de su delegación o, si no, la del evaluador asignado.
 *   - traslado: plaza del trasladado antes de la fecha de traslado (origen).
 * Lo que una delegación vigente puso a cargo del delegado (copias de la
 * delegación corta, traslados de la larga) queda en la plaza delegada.
 *
 * Asignaciones y evaluaciones abiertas guardan además la persona con la que se
 * enlazaron (id_vinc_enlazado). Si el flujo actual cambia esa persona
 * (traslado, recurso, fin de delegación), se vuelven a enlazar a la plaza de
 * la nueva; las evaluaciones cerradas no se tocan una vez enlazadas.
 *
 * Solo enlaza cuando la plaza es inequívoca (una única ocupación cubre las
 * fechas); lo demás queda como excepción para Talento Humano. Se puede
 * ejecutar las veces que haga falta.
 */
class EnlacePuestos
{
    private const CLAVES = [
        'evaluador_asignacion' => 'id_asignacion',
        'evaluacion' => 'id_evaluacion',
        'delegacion' => 'id_delegacion',
        'periodo_parcial' => 'id_periodo_parcial',
        'traslado' => 'id_traslado',
    ];

    /** @var array<int, \Illuminate\Support\Collection> ocupaciones por vinculación */
    private array $ocupaciones = [];

    /** "delegado-evaluado" => plaza delegada, para lo que una delegación vigente puso a cargo del delegado. */
    private array $aCargoDeDelegado = [];

    private array $enlaces = [];

    private array $excepciones = [];

    /** Calcula los enlaces sin escribir nada. */
    public function ensayar(): array
    {
        $this->calcular();

        return $this->resultado();
    }

    /** Calcula y escribe los enlaces en una sola transacción. */
    public function aplicar(): array
    {
        return DB::transaction(function () {
            $this->calcular();

            foreach ($this->enlaces as $tabla => $filas) {
                foreach ($filas as $id => $valores) {
                    DB::table($tabla)->where(self::CLAVES[$tabla], $id)->update($valores);
                }
            }

            return $this->resultado();
        });
    }

    private function calcular(): void
    {
        $this->enlaces = array_fill_keys(array_keys(self::CLAVES), []);
        $this->excepciones = [];
        $this->ocupaciones = DB::table('puesto_ocupacion')
            ->orderBy('vigente_desde')
            ->get(['id_puesto_ocupacion', 'id_puesto', 'id_vinculacion', 'vigente_desde', 'vigente_hasta', 'estado'])
            ->groupBy('id_vinculacion')
            ->all();

        $plazaDelegacion = $this->delegaciones();
        $this->aCargoDeDelegado = $this->aCargoDeDelegado($plazaDelegacion);
        $this->asignaciones();
        $this->evaluaciones();
        $this->periodosParciales($plazaDelegacion);
        $this->traslados();
    }

    private function asignaciones(): void
    {
        $conEnlazado = Schema::hasColumn('evaluador_asignacion', 'id_vinc_enlazado');
        $filas = DB::table('evaluador_asignacion')
            ->where(fn ($q) => $conEnlazado
                ? $q->whereNull('id_puesto_evaluador')->orWhereNull('id_vinc_enlazado')->orWhereColumn('id_vinc_enlazado', '!=', 'id_vinc_evaluador')
                : $q->whereNull('id_puesto_evaluador'))
            ->get();

        foreach ($filas as $a) {
            $persona = (int) $a->id_vinc_evaluador;

            // Ya enlazada a mano o antes de guardar la persona: se adopta la persona actual.
            if ($a->id_puesto_evaluador && $conEnlazado && $a->id_vinc_enlazado === null) {
                $this->enlaces['evaluador_asignacion'][$a->id_asignacion] = ['id_vinc_enlazado' => $persona];
                continue;
            }

            $idPuesto = $this->aCargoDeDelegado[$persona . '-' . $a->id_vinc_evaluado] ?? $this->vigente($persona)?->id_puesto;
            if ($idPuesto) {
                $this->enlaces['evaluador_asignacion'][$a->id_asignacion] = ['id_puesto_evaluador' => $idPuesto]
                    + ($conEnlazado ? ['id_vinc_enlazado' => $persona] : []);
            } else {
                $this->excepcion('evaluador_asignacion', $a->id_asignacion, $persona, 'El evaluador no ocupa ninguna plaza vigente.');
            }
        }
    }

    private function evaluaciones(): void
    {
        $conEnlazado = Schema::hasColumn('evaluacion', 'id_vinc_enlazado');
        $titular = 'COALESCE(e.id_vinc_suplente, e.id_vinc_evaluador)';
        $filas = DB::table('evaluacion as e')
            ->join('periodo as p', 'p.id_periodo', '=', 'e.id_periodo')
            ->where(fn ($q) => $q->whereNull('e.id_puesto_evaluador')
                ->when($conEnlazado, fn ($q2) => $q2->orWhere(fn ($q3) => $q3->where('e.estado', 'EN_PROCESO')
                    ->where(fn ($q4) => $q4->whereNull('e.id_vinc_enlazado')->orWhereRaw("e.id_vinc_enlazado <> {$titular}")))))
            ->get(['e.id_evaluacion', 'e.id_vinc_evaluado', 'e.id_vinc_evaluador', 'e.id_vinc_suplente', 'e.estado', 'e.id_puesto_evaluador',
                $conEnlazado ? 'e.id_vinc_enlazado' : DB::raw('NULL as id_vinc_enlazado'), 'p.fecha_inicio', 'p.fecha_fin']);

        foreach ($filas as $e) {
            // El trabajo pertenece a la plaza del titular (con delegación corta, el delegante).
            $persona = (int) ($e->id_vinc_suplente ?: $e->id_vinc_evaluador);

            if ($e->id_puesto_evaluador && $e->id_vinc_enlazado === null) {
                $this->enlaces['evaluacion'][$e->id_evaluacion] = ['id_vinc_enlazado' => $persona];
                continue;
            }

            $delegada = $this->aCargoDeDelegado[$persona . '-' . $e->id_vinc_evaluado] ?? null;
            if ($e->estado === 'EN_PROCESO') {
                $ocupacion = $delegada ? $this->ocupacionDePlaza($delegada) : $this->vigente($persona);
                $motivo = 'El evaluador titular no ocupa ninguna plaza vigente.';
            } else {
                // Cerrada: la plaza que el evaluador ocupaba durante el periodo, sin suponer historia.
                $persona = (int) $e->id_vinc_evaluador;
                [$ocupacion, $motivo] = $this->enFechas($persona, $e->fecha_inicio, $e->fecha_fin);
            }

            if ($ocupacion || $delegada) {
                $this->enlaces['evaluacion'][$e->id_evaluacion] = [
                    'id_puesto_evaluador' => $ocupacion->id_puesto ?? $delegada,
                    'id_puesto_ocupacion_responsable' => $ocupacion->id_puesto_ocupacion ?? null,
                ] + ($conEnlazado ? ['id_vinc_enlazado' => $persona] : []);
            } else {
                $this->excepcion('evaluacion', $e->id_evaluacion, $persona, $motivo);
            }
        }
    }

    /** Enlaza las delegaciones y retorna id_delegacion => id_puesto (también las ya enlazadas). */
    private function delegaciones(): array
    {
        $plazas = [];
        foreach (DB::table('delegacion')->get() as $d) {
            if ($d->id_puesto) {
                $plazas[$d->id_delegacion] = (int) $d->id_puesto;
                continue;
            }

            [$ocupacion, $motivo] = $this->enFechas((int) $d->id_vinc_delegante, $d->fecha_inicio, $d->fecha_fin ?: now()->toDateString());
            if ($ocupacion) {
                $plazas[$d->id_delegacion] = (int) $ocupacion->id_puesto;
                $this->enlaces['delegacion'][$d->id_delegacion] = ['id_puesto' => $ocupacion->id_puesto];
            } else {
                $this->excepcion('delegacion', $d->id_delegacion, (int) $d->id_vinc_delegante, $motivo);
            }
        }

        return $plazas;
    }

    /** Personas que una delegación vigente dejó a cargo del delegado: copias (corta) y traslados (larga). */
    private function aCargoDeDelegado(array $plazaDelegacion): array
    {
        $mapa = [];
        $vigentes = DB::table('delegacion')->whereIn('estado', ['ACTIVA', 'POR_FINALIZAR'])->get();
        foreach ($vigentes as $d) {
            $idPuesto = $plazaDelegacion[$d->id_delegacion] ?? null;
            if (! $idPuesto) {
                continue;
            }

            $detalle = $d->detalle_transferencia ? json_decode($d->detalle_transferencia, true) : [];
            $evaluados = array_merge(
                $detalle['evaluados_transferidos'] ?? [],
                DB::table('traslado')->where('id_delegacion', $d->id_delegacion)->where('id_vinc_evaluador_nuevo', $d->id_vinc_delegado)
                    ->where('estado', 'CONFIRMADO')->pluck('id_vinc_funcionario')->all(),
            );
            foreach ($evaluados as $idEvaluado) {
                $mapa[$d->id_vinc_delegado . '-' . $idEvaluado] = $idPuesto;
            }
        }

        return $mapa;
    }

    private function periodosParciales(array $plazaDelegacion): void
    {
        // Tramos que abrió una delegación: responde la plaza delegada.
        $deDelegacion = [];
        foreach (DB::table('delegacion')->whereNotNull('detalle_transferencia')->get(['id_delegacion', 'detalle_transferencia']) as $d) {
            foreach (json_decode($d->detalle_transferencia, true)['periodo_parcial_ids'] ?? [] as $idPp) {
                $deDelegacion[(int) $idPp] = (int) $d->id_delegacion;
            }
        }

        $filas = DB::table('periodo_parcial')->whereNull('id_puesto_responsable')->get();
        foreach ($filas as $pp) {
            if (isset($deDelegacion[$pp->id_periodo_parcial])) {
                $idPuesto = $plazaDelegacion[$deDelegacion[$pp->id_periodo_parcial]] ?? null;
                if ($idPuesto) {
                    $this->enlaces['periodo_parcial'][$pp->id_periodo_parcial] = ['id_puesto_responsable' => $idPuesto];
                } else {
                    $this->excepcion('periodo_parcial', $pp->id_periodo_parcial, (int) $pp->id_vinc_funcionario, 'Su delegación no tiene plaza.');
                }
                continue;
            }

            // Tramo suelto: responde la plaza del evaluador asignado, si la ocupaba en esas fechas.
            $evaluador = DB::table('evaluador_asignacion')->where('id_vinc_evaluado', $pp->id_vinc_funcionario)->value('id_vinc_evaluador');
            [$ocupacion, $motivo] = $evaluador
                ? $this->enFechas((int) $evaluador, $pp->fecha_inicio, $pp->fecha_fin)
                : [null, 'El funcionario no tiene evaluador asignado.'];

            if ($ocupacion) {
                $this->enlaces['periodo_parcial'][$pp->id_periodo_parcial] = ['id_puesto_responsable' => $ocupacion->id_puesto];
            } else {
                $this->excepcion('periodo_parcial', $pp->id_periodo_parcial, (int) $pp->id_vinc_funcionario, $motivo);
            }
        }
    }

    private function traslados(): void
    {
        $filas = DB::table('traslado')->whereNull('id_puesto_origen')->get(['id_traslado', 'id_vinc_funcionario', 'fecha_traslado']);
        foreach ($filas as $t) {
            // La plaza que tenía el día anterior al traslado.
            $dia = date('Y-m-d', strtotime($t->fecha_traslado . ' -1 day'));
            [$ocupacion, $motivo] = $this->enFechas((int) $t->id_vinc_funcionario, $dia, $dia);
            if ($ocupacion) {
                $this->enlaces['traslado'][$t->id_traslado] = ['id_puesto_origen' => $ocupacion->id_puesto];
            } else {
                $this->excepcion('traslado', $t->id_traslado, (int) $t->id_vinc_funcionario, $motivo);
            }
        }
    }

    private function vigente(int $idVinc): ?object
    {
        return ($this->ocupaciones[$idVinc] ?? collect())->firstWhere('estado', 'VIGENTE');
    }

    private function ocupacionDePlaza(int $idPuesto): ?object
    {
        foreach ($this->ocupaciones as $deLaPersona) {
            if ($o = $deLaPersona->first(fn ($o) => (int) $o->id_puesto === $idPuesto && $o->estado === 'VIGENTE')) {
                return $o;
            }
        }

        return null;
    }

    /**
     * La única ocupación de la persona que se cruza con [desde, hasta].
     * Retorna [ocupación|null, motivo de la excepción].
     */
    private function enFechas(int $idVinc, string $desde, string $hasta): array
    {
        $cruzan = ($this->ocupaciones[$idVinc] ?? collect())->filter(fn ($o) => $o->vigente_desde <= $hasta
            && ($o->vigente_hasta === null || $o->vigente_hasta >= $desde));

        if ($cruzan->count() === 1) {
            return [$cruzan->first(), ''];
        }
        if ($cruzan->isEmpty()) {
            return [null, isset($this->ocupaciones[$idVinc])
                ? "Su ocupación registrada no cubre las fechas {$desde} a {$hasta}."
                : 'No ocupa ninguna plaza en el catálogo.'];
        }

        return [null, "Ocupó {$cruzan->count()} plazas entre {$desde} y {$hasta}; no se sabe cuál corresponde."];
    }

    private function excepcion(string $tabla, int $id, ?int $idVinc, string $motivo): void
    {
        $this->excepciones[] = ['tabla' => $tabla, 'id' => $id, 'id_vinculacion' => $idVinc, 'motivo' => $motivo];
    }

    private function resultado(): array
    {
        $resumen = [];
        foreach (array_keys(self::CLAVES) as $tabla) {
            $resumen[$tabla] = [
                'enlazados' => count($this->enlaces[$tabla]),
                'excepciones' => count(array_filter($this->excepciones, fn ($e) => $e['tabla'] === $tabla)),
            ];
        }

        return ['resumen' => $resumen, 'excepciones' => $this->excepciones];
    }
}
