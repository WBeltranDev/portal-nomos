<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La plataforma pasa a hora de Colombia (UTC-5). Las columnas TIMESTAMP no se
 * tocan: MySQL las guarda en UTC y las entrega en la zona de la conexión. Las
 * DATETIME guardan la hora tal cual y hasta hoy se escribieron en UTC (la app
 * estaba en UTC y la base también), así que se les restan 5 horas una sola vez.
 *
 * Si el servidor de base de datos no estaba en UTC, los valores por defecto de
 * esas columnas ya se habrían escrito en su hora local y la conversión no sería
 * segura: no se convierte nada y se avisa para revisarlo a mano.
 */
return new class extends Migration
{
    private function columnasDatetime(): array
    {
        return DB::select("SELECT TABLE_NAME AS tabla, COLUMN_NAME AS columna FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND DATA_TYPE = 'datetime' AND TABLE_NAME <> 'migrations'");
    }

    /** Diferencia en minutos entre la hora del servidor de base de datos y UTC. */
    private function desfaseServidor(): int
    {
        $zona = DB::selectOne('SELECT @@session.time_zone AS z')->z;
        DB::statement("SET time_zone = 'SYSTEM'");
        $minutos = (int) DB::selectOne('SELECT TIMESTAMPDIFF(MINUTE, UTC_TIMESTAMP(), NOW()) AS m')->m;
        DB::statement('SET time_zone = ?', [$zona]);

        return $minutos;
    }

    private function mover(string $intervalo): void
    {
        foreach ($this->columnasDatetime() as $c) {
            DB::statement("UPDATE `{$c->tabla}` SET `{$c->columna}` = `{$c->columna}` {$intervalo} WHERE `{$c->columna}` IS NOT NULL");
        }
    }

    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        $desfase = $this->desfaseServidor();
        if ($desfase !== 0) {
            fwrite(STDERR, "AVISO hora_colombia: el servidor de base de datos está en UTC{$desfase}min, no en UTC. No se convirtieron las columnas DATETIME existentes; revísalas antes de usar la plataforma.\n");

            return;
        }

        $this->mover('- INTERVAL 5 HOUR');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite' || $this->desfaseServidor() !== 0) {
            return;
        }

        $this->mover('+ INTERVAL 5 HOUR');
    }
};
