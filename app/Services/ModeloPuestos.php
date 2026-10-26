<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S14 - Interruptor del modelo de puestos (plan, fase G).
 *
 * APAGADO: el sistema funciona por persona, como hasta ahora.
 * OBSERVACION: además calcula el responsable por plaza y solo registra las
 *   diferencias; no cambia nada.
 * ACTIVO: las plazas mandan. Exige catálogo completo y validado, haber pasado
 *   por Observación y ninguna diferencia pendiente.
 *
 * Talento Humano cambia el modo desde la plataforma; cada cambio se audita.
 */
class ModeloPuestos
{
    public const APAGADO = 'APAGADO';
    public const OBSERVACION = 'OBSERVACION';
    public const ACTIVO = 'ACTIVO';
    public const MODOS = [self::APAGADO, self::OBSERVACION, self::ACTIVO];

    private const CLAVE = 'modelo_puestos_modo';

    public function modo(): string
    {
        if (! Schema::hasTable('configuracion_sistema')) {
            return self::APAGADO;
        }

        $modo = DB::table('configuracion_sistema')->where('clave', self::CLAVE)->value('valor');

        return in_array($modo, self::MODOS, true) ? $modo : self::APAGADO;
    }

    public function observando(): bool
    {
        return in_array($this->modo(), [self::OBSERVACION, self::ACTIVO], true);
    }

    public function activo(): bool
    {
        return $this->modo() === self::ACTIVO;
    }

    /** Condiciones que faltan para pasar a $modo (vacío = se puede). */
    public function requisitosPendientes(string $modo): array
    {
        if ($modo === self::APAGADO || ! Schema::hasTable('puesto')) {
            return $modo === self::APAGADO ? [] : ['El catálogo de plazas aún no existe.'];
        }

        $plazas = DB::table('puesto')->where('estado', '!=', 'INACTIVA')->count();
        if ($plazas === 0) {
            return ['Aún no hay plazas cargadas en el catálogo.'];
        }
        if ($modo === self::OBSERVACION) {
            return [];
        }

        $faltan = [];
        if ($this->modo() !== self::OBSERVACION) {
            $faltan[] = 'Primero debe pasar por el modo Observación para comparar resultados.';
        }
        if ($borrador = DB::table('puesto')->where('estado', 'BORRADOR')->count()) {
            $faltan[] = "Hay {$borrador} plaza(s) sin validar.";
        }
        $sinPlaza = DB::table('vinculacion as v')
            ->where('v.activa', 1)
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('puesto_ocupacion as po')
                ->whereColumn('po.id_vinculacion', 'v.id_vinculacion')
                ->where('po.estado', 'VIGENTE'))
            ->count();
        if ($sinPlaza) {
            $faltan[] = "Hay {$sinPlaza} vinculación(es) activa(s) sin plaza.";
        }
        if (Schema::hasTable('puesto_discrepancia') && ($pendientes = DB::table('puesto_discrepancia')->where('resuelta', 0)->count())) {
            $faltan[] = "Hay {$pendientes} diferencia(s) de la observación sin resolver.";
        }

        return $faltan;
    }

    /** Cambia el modo si se cumplen los requisitos. Retorna null o el motivo que lo impide. */
    public function cambiar(string $nuevo, ?int $idUsuario): ?string
    {
        if (! in_array($nuevo, self::MODOS, true)) {
            return 'Modo no válido.';
        }

        $anterior = $this->modo();
        if ($anterior === $nuevo) {
            return null;
        }

        if ($faltan = $this->requisitosPendientes($nuevo)) {
            return 'No se puede pasar a ' . $nuevo . ': ' . implode(' ', $faltan);
        }

        DB::transaction(function () use ($anterior, $nuevo, $idUsuario) {
            DB::table('configuracion_sistema')->updateOrInsert(
                ['clave' => self::CLAVE],
                ['valor' => $nuevo, 'descripcion' => 'Modo del modelo de puestos: APAGADO, OBSERVACION o ACTIVO', 'updated_at' => now()]
            );
            DB::table('modelo_puestos_cambio')->insert([
                'modo_anterior' => $anterior,
                'modo_nuevo' => $nuevo,
                'id_usuario' => $idUsuario,
                'created_at' => now(),
            ]);
        });

        return null;
    }

    /** Últimos cambios de modo, para mostrar en la plataforma. */
    public function historial(int $limite = 10): array
    {
        if (! Schema::hasTable('modelo_puestos_cambio')) {
            return [];
        }

        return DB::table('modelo_puestos_cambio as c')
            ->leftJoin('usuario as u', 'u.id_usuario', '=', 'c.id_usuario')
            ->orderByDesc('c.id_cambio')
            ->limit($limite)
            ->get(['c.modo_anterior', 'c.modo_nuevo', 'c.created_at', 'u.username'])
            ->all();
    }
}
