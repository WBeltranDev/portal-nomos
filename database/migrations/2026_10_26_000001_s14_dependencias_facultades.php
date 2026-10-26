<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * S14 - Las decanaturas dependen de la facultad, no de un programa. El catálogo
 * solo tenía dependencias por programa («FACULTAD X - PROGRAMA Y»), así que la
 * carga de planta no encontraba dónde crear las vacantes de DECANO. Con estas
 * dependencias la carga las crea directamente.
 */
return new class extends Migration
{
    private const FACULTADES = [
        'FACULTAD DE ARTES',
        'FACULTAD DE CIENCIAS',
        'FACULTAD DE CIENCIAS ECONOMICAS Y ADMINISTRATIVAS',
        'FACULTAD DE DERECHO Y CIENCIAS POLITICAS',
        'FACULTAD DE INGENIERIAS',
    ];

    public function up(): void
    {
        foreach (self::FACULTADES as $nombre) {
            if (! DB::table('dependencia')->where('nombre', $nombre)->exists()) {
                DB::table('dependencia')->insert(['nombre' => $nombre, 'activa' => 1, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        // Solo se quitan las que no tengan plazas.
        DB::table('dependencia')->whereIn('nombre', self::FACULTADES)
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('puesto')->whereColumn('puesto.id_dependencia', 'dependencia.id_dependencia'))
            ->delete();
    }
};
