<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S14 - Mantiene al día quién ocupa cada plaza (plan 3.1 y fase F).
 *
 * Una plaza tiene un único ocupante vigente y una persona ocupa una sola plaza
 * (la base lo refuerza con índices únicos). Retiro y vacancia cierran la
 * ocupación y la plaza queda vacante; un traslado cierra la de origen y abre
 * la de destino que eligió Talento Humano. Si la persona no tiene plaza (el
 * catálogo aún no se ha cargado) no se hace nada.
 */
class OcupacionPuestos
{
    private function disponible(): bool
    {
        static $disponible = null;

        return $disponible ??= Schema::hasTable('puesto_ocupacion') && Schema::hasColumn('puesto_ocupacion', 'motivo_fin');
    }

    public function plazaVigente(int $idVinculacion): ?object
    {
        if (! $this->disponible()) {
            return null;
        }

        return DB::table('puesto_ocupacion as po')
            ->join('puesto as p', 'p.id_puesto', '=', 'po.id_puesto')
            ->where('po.id_vinculacion', $idVinculacion)
            ->where('po.estado', 'VIGENTE')
            ->first(['po.id_puesto_ocupacion', 'po.id_puesto', 'p.codigo_plaza', 'p.id_dependencia', 'p.id_cargo']);
    }

    /** Ocupante vigente de una plaza, o null si está vacante. */
    public function ocupante(int $idPuesto): ?int
    {
        $id = DB::table('puesto_ocupacion')->where('id_puesto', $idPuesto)->where('estado', 'VIGENTE')->value('id_vinculacion');

        return $id ? (int) $id : null;
    }

    /** Cierra la ocupación vigente de la persona; su plaza queda vacante. */
    public function cerrar(int $idVinculacion, string $fechaFin, string $motivo): void
    {
        if (! $this->disponible()) {
            return;
        }

        DB::table('puesto_ocupacion')
            ->where('id_vinculacion', $idVinculacion)
            ->where('estado', 'VIGENTE')
            ->update([
                'estado' => 'CERRADA',
                'vigente_hasta' => $fechaFin,
                'motivo_fin' => mb_substr($motivo, 0, 255),
                'cerrado_por' => session('usuario_autenticado.id_usuario'),
                'updated_at' => now(),
            ]);
    }

    /**
     * Abre la ocupación de $idPuesto para la persona. Retorna null, o el motivo
     * si la plaza no existe, está inactiva o ya tiene ocupante.
     */
    public function ocupar(int $idPuesto, int $idVinculacion, string $fechaInicio, string $motivo): ?string
    {
        $plaza = DB::table('puesto')->where('id_puesto', $idPuesto)->first(['codigo_plaza', 'estado']);
        if (! $plaza || $plaza->estado === 'INACTIVA') {
            return 'La plaza destino no existe o está inactiva.';
        }
        if ($ocupante = $this->ocupante($idPuesto)) {
            return $ocupante === $idVinculacion
                ? null
                : "La plaza {$plaza->codigo_plaza} ya tiene un ocupante vigente: elige una plaza vacante.";
        }

        $tipo = DB::table('vinculacion')->where('id_vinculacion', $idVinculacion)->value('tipo_vinculacion');
        DB::table('puesto_ocupacion')->insert([
            'id_puesto' => $idPuesto,
            'id_vinculacion' => $idVinculacion,
            'tipo_ocupacion' => $tipo,
            'vigente_desde' => $fechaInicio,
            'estado' => 'VIGENTE',
            'motivo_inicio' => mb_substr($motivo, 0, 255),
            'registrado_por' => session('usuario_autenticado.id_usuario'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return null;
    }

    /**
     * Al reactivar una vinculación, vuelve a su última plaza si sigue vacante.
     * Si ya la ocupa otra persona, queda sin plaza para que TH se la asigne.
     */
    public function reactivar(int $idVinculacion, string $motivo): void
    {
        if (! $this->disponible() || $this->plazaVigente($idVinculacion)) {
            return;
        }

        $ultima = DB::table('puesto_ocupacion')->where('id_vinculacion', $idVinculacion)->orderByDesc('id_puesto_ocupacion')->value('id_puesto');
        if ($ultima && ! $this->ocupante((int) $ultima)) {
            $this->ocupar((int) $ultima, $idVinculacion, now()->toDateString(), $motivo);
        }
    }

    /** Plazas vacantes para elegir destino; primero las de la dependencia y cargo indicados. */
    public function vacantes(?int $idDependencia = null, ?int $idCargo = null): array
    {
        if (! $this->disponible()) {
            return [];
        }

        return DB::table('puesto as p')
            ->join('dependencia as d', 'd.id_dependencia', '=', 'p.id_dependencia')
            ->join('cargo as c', 'c.id_cargo', '=', 'p.id_cargo')
            ->where('p.estado', '!=', 'INACTIVA')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('puesto_ocupacion as po')
                ->whereColumn('po.id_puesto', 'p.id_puesto')->where('po.estado', 'VIGENTE'))
            ->orderByRaw('(p.id_dependencia = ?) DESC, (p.id_cargo = ?) DESC', [$idDependencia ?? 0, $idCargo ?? 0])
            ->orderBy('p.codigo_plaza')
            ->get(['p.id_puesto', 'p.codigo_plaza', 'p.estado', 'd.nombre as dependencia', 'c.nombre as cargo', 'p.grado'])
            ->all();
    }
}
