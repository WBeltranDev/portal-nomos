<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * S14 - Resolvedor único de responsable (plan, fase D): plaza → delegación
 * vigente → ocupante vigente.
 *
 * Durante la transición lee primero las referencias a plaza y usa las de
 * vinculación como respaldo (lectura dual). En modo Observación solo compara
 * contra el responsable actual (evaluacion.id_vinc_evaluador) y registra las
 * diferencias para que Talento Humano las revise; no reasigna nada.
 */
class ResolutorResponsable
{
    /** Plaza vigente que ocupa una vinculación (o la última que ocupó). */
    public function plazaDe(int $idVinc): ?int
    {
        $ocupacion = DB::table('puesto_ocupacion')
            ->where('id_vinculacion', $idVinc)
            ->orderByRaw("estado = 'VIGENTE' DESC")
            ->orderByDesc('id_puesto_ocupacion')
            ->first(['id_puesto']);

        return $ocupacion ? (int) $ocupacion->id_puesto : null;
    }

    /**
     * Responsable por plaza de una evaluación. Retorna ['id_vinc' => ?int,
     * 'id_puesto' => ?int, 'motivo' => string] donde id_vinc es null si no se
     * puede determinar (sin plaza o plaza vacante).
     */
    public function responsablePorPlaza(object $evaluacion): array
    {
        // La plaza responsable: la registrada en la evaluación o, mientras no se
        // haya cargado, la del titular (si hay delegación) o la del evaluador.
        $idPuesto = $evaluacion->id_puesto_evaluador ?? null;
        $titular = (int) ($evaluacion->id_vinc_suplente ?: $evaluacion->id_vinc_evaluador);
        $idPuesto = $idPuesto ? (int) $idPuesto : $this->plazaDe($titular);

        if (! $idPuesto) {
            return ['id_vinc' => null, 'id_puesto' => null, 'motivo' => 'El evaluador no tiene plaza en el catálogo.'];
        }

        return $this->responsableDePlaza($idPuesto);
    }

    /** Quién responde hoy por una plaza: su delegación vigente o, si no hay, su ocupante. */
    public function responsableDePlaza(int $idPuesto): array
    {
        $ocupante = DB::table('puesto_ocupacion')
            ->where('id_puesto', $idPuesto)
            ->where('estado', 'VIGENTE')
            ->value('id_vinculacion');

        // Delegación vigente sobre la plaza (o, antes de la carga, sobre su ocupante).
        $hoy = now()->toDateString();
        $delegado = DB::table('delegacion')
            ->where('estado', 'ACTIVA')
            ->whereDate('fecha_inicio', '<=', $hoy)
            ->where(fn ($q) => $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $hoy))
            ->where(function ($q) use ($idPuesto, $ocupante) {
                $q->where('id_puesto', $idPuesto);
                if ($ocupante) {
                    $q->orWhere(fn ($q2) => $q2->whereNull('id_puesto')->where('id_vinc_delegante', $ocupante));
                }
            })
            ->orderByDesc('id_delegacion')
            ->value('id_vinc_delegado');

        if ($delegado) {
            return ['id_vinc' => (int) $delegado, 'id_puesto' => $idPuesto, 'motivo' => 'Delegación vigente sobre la plaza.'];
        }
        if ($ocupante) {
            return ['id_vinc' => (int) $ocupante, 'id_puesto' => $idPuesto, 'motivo' => 'Ocupante vigente de la plaza.'];
        }

        return ['id_vinc' => null, 'id_puesto' => $idPuesto, 'motivo' => 'La plaza está vacante.'];
    }

    /**
     * Compara el responsable actual de cada evaluación abierta contra el
     * responsable por plaza. Registra las diferencias nuevas y da por
     * resueltas las que ya no se presentan. No modifica evaluaciones.
     */
    public function observar(): array
    {
        $abiertas = DB::table('evaluacion')->where('estado', 'EN_PROCESO')->where('es_traslado', 0)->get();
        $nuevas = 0;
        $vigentes = [];

        foreach ($abiertas as $evaluacion) {
            $porPlaza = $this->responsablePorPlaza($evaluacion);
            $legado = (int) $evaluacion->id_vinc_evaluador;
            if ($porPlaza['id_vinc'] === $legado) {
                continue;
            }

            // La misma diferencia pendiente, o ya revisada y aceptada por TH (esa no
            // se vuelve a abrir); las que resolvió el sistema sí pueden reaparecer.
            $existente = DB::table('puesto_discrepancia')
                ->where('id_evaluacion', $evaluacion->id_evaluacion)
                ->where('id_vinc_responsable_legado', $legado)
                ->where(fn ($q) => $porPlaza['id_vinc'] === null
                    ? $q->whereNull('id_vinc_responsable_puesto')
                    : $q->where('id_vinc_responsable_puesto', $porPlaza['id_vinc']))
                ->where(fn ($q) => $q->where('resuelta', 0)->orWhereNotNull('resuelta_por'))
                ->orderBy('resuelta')
                ->first(['id_discrepancia', 'resuelta']);

            if ($existente && $existente->resuelta) {
                continue;
            }

            $existente = $existente->id_discrepancia ?? null;
            if (! $existente) {
                $existente = DB::table('puesto_discrepancia')->insertGetId([
                    'id_evaluacion' => $evaluacion->id_evaluacion,
                    'id_vinc_responsable_legado' => $legado,
                    'id_vinc_responsable_puesto' => $porPlaza['id_vinc'],
                    'motivo' => $porPlaza['motivo'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $nuevas++;
            }
            $vigentes[] = (int) $existente;
        }

        // Lo que ya no difiere se da por resuelto (sin usuario: lo resolvió el sistema).
        $resueltas = DB::table('puesto_discrepancia')
            ->where('resuelta', 0)
            ->whereNotIn('id_discrepancia', $vigentes ?: [0])
            ->update(['resuelta' => 1, 'resuelta_at' => now(), 'updated_at' => now()]);

        return [
            'evaluaciones' => $abiertas->count(),
            'diferencias_nuevas' => $nuevas,
            'diferencias_pendientes' => count($vigentes),
            'resueltas_solas' => $resueltas,
        ];
    }
}
