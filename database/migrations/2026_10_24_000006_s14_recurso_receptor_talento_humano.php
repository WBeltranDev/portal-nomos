<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S14 - Una apelación sin superior activo del evaluador la decide Talento
 * Humano. Los usuarios de TH no tienen vinculación, así que el receptor queda
 * vacío (NULL = Talento Humano) en lugar de recaer en el propio evaluador.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('recurso', 'id_vinc_receptor')) {
            return;
        }

        DB::statement('ALTER TABLE recurso MODIFY id_vinc_receptor INT UNSIGNED NULL');
    }

    public function down(): void
    {
        if (DB::table('recurso')->whereNull('id_vinc_receptor')->exists()) {
            throw new \RuntimeException('Hay recursos asignados a Talento Humano (receptor vacío); asígnales un receptor antes de revertir.');
        }

        DB::statement('ALTER TABLE recurso MODIFY id_vinc_receptor INT UNSIGNED NOT NULL');
    }
};
