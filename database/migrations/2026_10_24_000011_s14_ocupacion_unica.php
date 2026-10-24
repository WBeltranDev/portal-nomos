<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S14 - Plan 3.1: una plaza tiene un único ocupante vigente y una persona
 * ocupa una sola plaza a la vez. La cobertura temporal se hace con
 * delegaciones. Se refuerza en la base con índices únicos sobre columnas
 * generadas que solo tienen valor mientras la ocupación está VIGENTE (MySQL
 * admite varios NULL en un índice único). Además, cada ocupación registra el
 * motivo de inicio y de fin y quién la cerró (plan 3.2, regla 11).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('puesto_ocupacion', 'vigente_puesto')) {
            return;
        }

        Schema::table('puesto_ocupacion', function (Blueprint $table) {
            $table->string('motivo_inicio', 255)->nullable()->after('estado');
            $table->string('motivo_fin', 255)->nullable()->after('motivo_inicio');
            $table->unsignedInteger('cerrado_por')->nullable()->after('registrado_por');
            $table->foreign('cerrado_por', 'fk_ocupacion_cerrado_por')->references('id_usuario')->on('usuario');
        });

        DB::statement("ALTER TABLE puesto_ocupacion
            ADD COLUMN vigente_puesto INT UNSIGNED GENERATED ALWAYS AS (IF(estado = 'VIGENTE', id_puesto, NULL)) STORED,
            ADD COLUMN vigente_vinculacion INT UNSIGNED GENERATED ALWAYS AS (IF(estado = 'VIGENTE', id_vinculacion, NULL)) STORED,
            ADD UNIQUE INDEX uq_ocupacion_vigente_puesto (vigente_puesto),
            ADD UNIQUE INDEX uq_ocupacion_vigente_vinculacion (vigente_vinculacion)");
    }

    public function down(): void
    {
        if (! Schema::hasColumn('puesto_ocupacion', 'vigente_puesto')) {
            return;
        }

        DB::statement('ALTER TABLE puesto_ocupacion
            DROP INDEX uq_ocupacion_vigente_puesto,
            DROP INDEX uq_ocupacion_vigente_vinculacion,
            DROP COLUMN vigente_puesto,
            DROP COLUMN vigente_vinculacion');

        Schema::table('puesto_ocupacion', function (Blueprint $table) {
            $table->dropForeign('fk_ocupacion_cerrado_por');
            $table->dropColumn(['motivo_inicio', 'motivo_fin', 'cerrado_por']);
        });
    }
};
