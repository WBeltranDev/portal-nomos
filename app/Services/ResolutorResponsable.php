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
     * responsable por plaza, y cada asignación de evaluador contra la jerarquía
     * de plazas. Registra las diferencias nuevas y da por resueltas las que ya
     * no se presentan. No modifica evaluaciones ni asignaciones.
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
            $this->registrar(['tipo' => 'EVALUACION', 'id_evaluacion' => $evaluacion->id_evaluacion], $legado, $porPlaza, $nuevas, $vigentes);
        }

        $asignaciones = $this->asignacionesActivas();
        foreach ($asignaciones as $a) {
            $esperado = $this->evaluadorPorJerarquia((int) $a->id_vinc_evaluado);
            if (in_array((int) $a->id_vinc_evaluador, $esperado['aceptados'], true)) {
                continue;
            }
            $this->registrar(['tipo' => 'JERARQUIA', 'id_vinc_evaluado' => $a->id_vinc_evaluado], (int) $a->id_vinc_evaluador, $esperado, $nuevas, $vigentes);
        }

        // Lo que ya no difiere se da por resuelto (sin usuario: lo resolvió el sistema).
        $resueltas = DB::table('puesto_discrepancia')
            ->where('resuelta', 0)
            ->whereNotIn('id_discrepancia', $vigentes ?: [0])
            ->update(['resuelta' => 1, 'resuelta_at' => now(), 'updated_at' => now()]);

        return [
            'evaluaciones' => $abiertas->count(),
            'asignaciones' => $asignaciones->count(),
            'diferencias_nuevas' => $nuevas,
            'diferencias_pendientes' => count($vigentes),
            'resueltas_solas' => $resueltas,
        ];
    }

    /** Asignaciones de evaluador entre vinculaciones activas. */
    private function asignacionesActivas()
    {
        return DB::table('evaluador_asignacion as a')
            ->join('vinculacion as ve', 've.id_vinculacion', '=', 'a.id_vinc_evaluado')
            ->join('vinculacion as va', 'va.id_vinculacion', '=', 'a.id_vinc_evaluador')
            ->where('ve.activa', 1)
            ->where('va.activa', 1)
            ->get(['a.id_vinc_evaluado', 'a.id_vinc_evaluador']);
    }

    /**
     * Quién debe evaluar a una persona según la jerarquía de plazas: el
     * responsable de la plaza superior de su plaza. Se aceptan el ocupante y,
     * si hay delegación vigente, el delegado (durante una delegación corta ambos
     * tienen la asignación).
     */
    public function evaluadorPorJerarquia(int $idVincEvaluado): array
    {
        $plaza = DB::table('puesto_ocupacion as po')
            ->join('puesto as p', 'p.id_puesto', '=', 'po.id_puesto')
            ->where('po.id_vinculacion', $idVincEvaluado)
            ->where('po.estado', 'VIGENTE')
            ->first(['p.id_puesto', 'p.id_puesto_superior']);

        if (! $plaza) {
            return ['id_vinc' => null, 'id_puesto' => null, 'aceptados' => [], 'motivo' => 'El evaluado no tiene plaza en el catálogo.'];
        }
        if (! $plaza->id_puesto_superior) {
            return ['id_vinc' => null, 'id_puesto' => null, 'aceptados' => [], 'motivo' => 'La plaza del evaluado no tiene plaza superior.'];
        }

        $responsable = $this->responsableDePlaza((int) $plaza->id_puesto_superior);
        $ocupante = DB::table('puesto_ocupacion')->where('id_puesto', $plaza->id_puesto_superior)->where('estado', 'VIGENTE')->value('id_vinculacion');
        $responsable['aceptados'] = array_values(array_unique(array_filter([$responsable['id_vinc'], $ocupante ? (int) $ocupante : null])));
        $responsable['motivo'] = $responsable['id_vinc']
            ? 'Según la jerarquía de plazas lo evalúa el responsable de la plaza superior.'
            : 'La plaza superior del evaluado está vacante.';

        return $responsable;
    }

    /**
     * Registra una diferencia si no existe. La misma diferencia pendiente se
     * reutiliza; la ya revisada y aceptada por TH no se vuelve a abrir; las que
     * resolvió el sistema sí pueden reaparecer.
     */
    private function registrar(array $clave, int $legado, array $porPlaza, int &$nuevas, array &$vigentes): void
    {
        $existente = DB::table('puesto_discrepancia')
            ->where($clave)
            ->where('id_vinc_responsable_legado', $legado)
            ->where(fn ($q) => $porPlaza['id_vinc'] === null
                ? $q->whereNull('id_vinc_responsable_puesto')
                : $q->where('id_vinc_responsable_puesto', $porPlaza['id_vinc']))
            ->where(fn ($q) => $q->where('resuelta', 0)->orWhereNotNull('resuelta_por'))
            ->orderBy('resuelta')
            ->first(['id_discrepancia', 'resuelta']);

        if ($existente && $existente->resuelta) {
            return;
        }

        $id = $existente->id_discrepancia ?? null;
        if (! $id) {
            $id = DB::table('puesto_discrepancia')->insertGetId($clave + [
                'id_vinc_responsable_legado' => $legado,
                'id_vinc_responsable_puesto' => $porPlaza['id_vinc'],
                'motivo' => $porPlaza['motivo'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $nuevas++;
        }
        $vigentes[] = (int) $id;
    }
}
