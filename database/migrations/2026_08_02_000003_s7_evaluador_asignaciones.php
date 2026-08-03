<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S7 - Asignaciones evaluador-evaluado en base de datos.
 *
 * Antes se guardaban en `storage/app/evaluador_asignaciones.json` (capa
 * temporal del contenedor) y se perdían en cada rebuild. Ahora viven en
 * MySQL, que es persistente a través de `docker compose up -d --build`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluador_asignacion', function (Blueprint $table) {
            $table->increments('id_asignacion');
            $table->unsignedInteger('id_vinc_evaluador');
            $table->unsignedInteger('id_vinc_evaluado');
            $table->timestamp('fecha_asignacion')->nullable();
            $table->unique(['id_vinc_evaluador', 'id_vinc_evaluado'], 'uq_asignacion_evaluador_evaluado');
            $table->foreign('id_vinc_evaluador', 'asignacion_evaluador_fk')
                ->references('id_vinculacion')->on('vinculacion')->onDelete('cascade');
            $table->foreign('id_vinc_evaluado', 'asignacion_evaluado_fk')
                ->references('id_vinculacion')->on('vinculacion')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluador_asignacion');
    }
};
