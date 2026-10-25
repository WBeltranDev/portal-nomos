<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * S14 - Enlace de los registros existentes a su plaza (plan, fase C).
 *
 * Llena las referencias a plaza que la fase B dejó vacías, a partir de las
 * ocupaciones cargadas con la planta:
 *   - evaluador_asignacion: plaza vigente del evaluador.
 *   - evaluacion: abierta → plaza vigente del titular (el delegante si hay
 *     suplente); cerrada → la plaza que el evaluador ocupaba en el periodo.
 *   - delegacion: plaza del delegante durante la delegación.
 *   - periodo_parcial: la de su delegación o, si no, la del evaluador asignado.
 *   - traslado: plaza del trasladado antes de la fecha de traslado (origen).
 *
 * Solo enlaza cuando la plaza es inequívoca (una única ocupación cubre las
 * fechas); lo demás queda como excepción para Talento Humano. Nunca cambia
 * una referencia ya puesta ni toca las columnas de vinculación, así que se
 * puede ejecutar las veces que haga falta.
 */
class EnlacePuestos
{
    /** @var array<int, \Illuminate\Support\Collection> ocupaciones por vinculación */
    private array $ocupaciones = [];

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
                $clave = self::CLAVES[$tabla];
                foreach ($filas as $id => $valores) {
                    // whereNull: si alguien lo enlazó entre el cálculo y la escritura, se respeta.
                    $columna = array_key_first($valores);
                    DB::table($tabla)->where($clave, $id)->whereNull($columna)->update($valores);
                }
            }

            return $this->resultado();
        });
    }

    private const CLAVES = [
        'evaluador_asignacion' => 'id_asignacion',
        'evaluacion' => 'id_evaluacion',
        'delegacion' => 'id_delegacion',
        'periodo_parcial' => 'id_periodo_parcial',
        'traslado' => 'id_traslado',
    ];

    private function calcular(): void
    {
        $this->enlaces = array_fill_keys(array_keys(self::CLAVES), []);
        $this->excepciones = [];
        $this->ocupaciones = DB::table('puesto_ocupacion')
            ->orderBy('vigente_desde')
            ->get(['id_puesto_ocupacion', 'id_puesto', 'id_vinculacion', 'vigente_desde', 'vigente_hasta', 'estado'])
            ->groupBy('id_vinculacion')
            ->all();

        $this->asignaciones();
        $this->evaluaciones();
        $plazaDelegacion = $this->delegaciones();
        $this->periodosParciales($plazaDelegacion);
        $this->traslados();
    }

    private function asignaciones(): void
    {
        $filas = DB::table('evaluador_asignacion')->whereNull('id_puesto_evaluador')->get();
        foreach ($filas as $a) {
            $ocupacion = $this->vigente($a->id_vinc_evaluador);
            if ($ocupacion) {
                $this->enlaces['evaluador_asignacion'][$a->id_asignacion] = ['id_puesto_evaluador' => $ocupacion->id_puesto];
            } else {
                $this->excepcion('evaluador_asignacion', $a->id_asignacion, $a->id_vinc_evaluador, 'El evaluador no ocupa ninguna plaza vigente.');
            }
        }
    }

    private function evaluaciones(): void
    {
        $filas = DB::table('evaluacion as e')
            ->join('periodo as p', 'p.id_periodo', '=', 'e.id_periodo')
            ->whereNull('e.id_puesto_evaluador')
            ->get(['e.id_evaluacion', 'e.id_vinc_evaluador', 'e.id_vinc_suplente', 'e.estado', 'p.fecha_inicio', 'p.fecha_fin']);

        foreach ($filas as $e) {
            if ($e->estado === 'EN_PROCESO') {
                // El trabajo abierto pertenece a la plaza del titular (con delegación, el delegante).
                $titular = (int) ($e->id_vinc_suplente ?: $e->id_vinc_evaluador);
                $ocupacion = $this->vigente($titular);
                $motivo = 'El evaluador titular no ocupa ninguna plaza vigente.';
            } else {
                // Cerrada: la plaza que el evaluador ocupaba durante el periodo, sin suponer historia.
                $titular = (int) $e->id_vinc_evaluador;
                [$ocupacion, $motivo] = $this->enFechas($titular, $e->fecha_inicio, $e->fecha_fin);
            }

            if ($ocupacion) {
                $this->enlaces['evaluacion'][$e->id_evaluacion] = [
                    'id_puesto_evaluador' => $ocupacion->id_puesto,
                    'id_puesto_ocupacion_responsable' => $ocupacion->id_puesto_ocupacion,
                ];
            } else {
                $this->excepcion('evaluacion', $e->id_evaluacion, $titular, $motivo);
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
