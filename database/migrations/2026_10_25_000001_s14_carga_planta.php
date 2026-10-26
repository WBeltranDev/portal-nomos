<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S14 - Carga de la planta oficial (Excel de cargos y vacantes de Talento
 * Humano). El correo del cargo pertenece a la plaza y la identifica en cargas
 * futuras aunque cambie la persona. Cada carga queda registrada (archivo,
 * huella y resumen) y lo que no se puede resolver solo queda pendiente para
 * que TH lo resuelva desde el panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('puesto', 'correo_cargo')) {
            Schema::table('puesto', function (Blueprint $table) {
                $table->string('correo_cargo', 255)->nullable()->unique()->after('codigo_plaza');
            });
        }

        if (! Schema::hasTable('carga_planta')) {
            Schema::create('carga_planta', function (Blueprint $table) {
                $table->increments('id_carga');
                $table->string('archivo', 255);
                $table->char('huella_archivo', 64);
                $table->json('resumen')->nullable();
                $table->unsignedInteger('id_usuario')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('id_usuario', 'fk_carga_planta_usuario')->references('id_usuario')->on('usuario');
            });
        }

        if (! Schema::hasTable('carga_planta_pendiente')) {
            Schema::create('carga_planta_pendiente', function (Blueprint $table) {
                $table->increments('id_pendiente');
                $table->unsignedInteger('id_carga');
                // VACANTE_SIN_DEPENDENCIA, PERSONA_SIN_PLAZA, PERSONA_NO_REGISTRADA, REQUIERE_TRASLADO
                $table->string('tipo', 40);
                $table->string('descripcion', 500);
                $table->json('datos')->nullable();
                $table->enum('estado', ['PENDIENTE', 'RESUELTO', 'DESCARTADO'])->default('PENDIENTE');
                $table->unsignedInteger('resuelto_por')->nullable();
                $table->timestamp('resuelto_at')->nullable();
                $table->timestamps();

                $table->index(['estado', 'tipo'], 'idx_carga_pendiente_estado');
                $table->foreign('id_carga', 'fk_carga_pendiente_carga')->references('id_carga')->on('carga_planta');
                $table->foreign('resuelto_por', 'fk_carga_pendiente_resuelto_por')->references('id_usuario')->on('usuario');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('carga_planta_pendiente');
        Schema::dropIfExists('carga_planta');

        if (Schema::hasColumn('puesto', 'correo_cargo')) {
            Schema::table('puesto', function (Blueprint $table) {
                $table->dropUnique(['correo_cargo']);
                $table->dropColumn('correo_cargo');
            });
        }
    }
};
