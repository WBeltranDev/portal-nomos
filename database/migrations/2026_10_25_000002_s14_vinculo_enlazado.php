<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S14 - Modelo de puestos, fase E: la persona responsable en el momento en que
 * el registro se enlazó a su plaza. Permite distinguir, al sincronizar, si el
 * responsable lo cambió el flujo actual (traslado, delegación, recurso: manda
 * la persona y se vuelve a enlazar) o si cambió el ocupante de la plaza
 * (relevo: manda la plaza y el trabajo abierto pasa al nuevo ocupante).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['evaluador_asignacion' => 'id_puesto_evaluador', 'evaluacion' => 'id_puesto_evaluador'] as $tabla => $plaza) {
            if (Schema::hasColumn($tabla, 'id_vinc_enlazado')) {
                continue;
            }
            Schema::table($tabla, function (Blueprint $table) use ($tabla, $plaza) {
                $table->unsignedInteger('id_vinc_enlazado')->nullable()->after($plaza);
                $table->foreign('id_vinc_enlazado', "fk_{$tabla}_vinc_enlazado")->references('id_vinculacion')->on('vinculacion')->nullOnDelete();
            });
        }

        // Lo que ya estaba enlazado (fase C) se enlazó con su responsable actual.
        DB::table('evaluador_asignacion')->whereNotNull('id_puesto_evaluador')->whereNull('id_vinc_enlazado')
            ->update(['id_vinc_enlazado' => DB::raw('id_vinc_evaluador')]);
        DB::table('evaluacion')->whereNotNull('id_puesto_evaluador')->whereNull('id_vinc_enlazado')
            ->update(['id_vinc_enlazado' => DB::raw('COALESCE(id_vinc_suplente, id_vinc_evaluador)')]);
    }

    public function down(): void
    {
        foreach (['evaluador_asignacion', 'evaluacion'] as $tabla) {
            if (! Schema::hasColumn($tabla, 'id_vinc_enlazado')) {
                continue;
            }
            Schema::table($tabla, function (Blueprint $table) use ($tabla) {
                $table->dropForeign("fk_{$tabla}_vinc_enlazado");
                $table->dropColumn('id_vinc_enlazado');
            });
        }
    }
};
