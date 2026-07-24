<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S4 - Fase 3: Evidencias por componente + aprobación, y módulo de Instancias Externas
 *
 * - evidencia: soporta árbol por componente (B/C/D/F, pliego pág. 5) y aprobación
 *   por el evaluador (pliego pág. 2: "Evaluadores: ... aprobación de evidencias").
 * - eje_misional_calificacion: permite registrar quién cargó la nota cuando quien
 *   la carga es una Instancia Externa (sin fila en `vinculacion`).
 * - Seed de prueba: marca como INSTANCIA_EXTERNA las cuentas institucionales de
 *   Vicerrectoría de Investigación y Vicerrectoría de Proyección Social, las dos
 *   instancias externas nombradas en el pliego que sí existen en el dump de datos.
 *   No hay una cuenta identificable de CEDP en los datos actuales (queda documentado
 *   en la bitácora S4).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('evidencia', 'componente')) {
            Schema::table('evidencia', function (Blueprint $table) {
                $table->enum('componente', ['B', 'C', 'D', 'F'])->default('B')->after('id_compromiso')
                    ->comment('Componente del pliego al que pertenece la evidencia: B=Compromisos, C=Comp. comunes, D=Comp. nivel jerárquico, F=Plan de formación');
            });
        }

        if (!Schema::hasColumn('evidencia', 'estado_aprobacion')) {
            Schema::table('evidencia', function (Blueprint $table) {
                $table->enum('estado_aprobacion', ['PENDIENTE', 'APROBADA', 'RECHAZADA'])->default('PENDIENTE')->after('id_vinc_registra');
                $table->unsignedInteger('id_vinc_aprueba')->nullable()->after('estado_aprobacion');
                $table->dateTime('fecha_aprobacion')->nullable()->after('id_vinc_aprueba');
                $table->text('observacion_aprobacion')->nullable()->after('fecha_aprobacion');
            });
        }

        if (Schema::hasColumn('eje_misional_calificacion', 'id_vinc_ingresador')) {
            Schema::table('eje_misional_calificacion', function (Blueprint $table) {
                $table->unsignedInteger('id_vinc_ingresador')->nullable()->change();
            });
        }

        if (!Schema::hasColumn('eje_misional_calificacion', 'id_usuario_ingresador')) {
            Schema::table('eje_misional_calificacion', function (Blueprint $table) {
                $table->unsignedInteger('id_usuario_ingresador')->nullable()->after('id_vinc_ingresador')
                    ->comment('usuario.id_usuario de quien registra, usado cuando no hay vinculacion (instancia externa)');
                $table->enum('origen', ['EVALUADOR', 'INSTANCIA_EXTERNA'])->nullable()->after('id_usuario_ingresador');
            });
        }

        // Seed de prueba: habilitar rol INSTANCIA_EXTERNA para las Vicerrectorías nombradas en el pliego.
        DB::table('usuario')
            ->whereIn('username', ['viceinvestigacion@unitropico.edu.co', 'viceproyeccion@unitropico.edu.co'])
            ->update(['rol' => 'INSTANCIA_EXTERNA']);
    }

    public function down(): void
    {
        Schema::table('evidencia', function (Blueprint $table) {
            foreach (['estado_aprobacion', 'id_vinc_aprueba', 'fecha_aprobacion', 'observacion_aprobacion', 'componente'] as $col) {
                if (Schema::hasColumn('evidencia', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('eje_misional_calificacion', function (Blueprint $table) {
            foreach (['id_usuario_ingresador', 'origen'] as $col) {
                if (Schema::hasColumn('eje_misional_calificacion', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
