<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S12 - Enlace al documento PDF firmado de la notificacion de calificacion.
 *
 * La notificacion de la calificacion se firma en linea con un boton, pero el
 * tramite se completa fuera del sistema: el evaluado imprime el documento, lo
 * firma en original y lo radica en la Oficina de Talento Humano. Hasta ahora
 * ese paso solo quedaba registrado de forma fisica en la historia laboral.
 *
 * Esta tabla guarda el enlace donde el evaluado almaceno el PDF firmado, para
 * que el documento quede tambien cargado en la plataforma: quien lo registro,
 * cuando y donde. Se permite un unico enlace por evaluacion, garantizado por
 * el indice unico sobre `id_evaluacion`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notificacion_documento')) {
            return;
        }

        Schema::create('notificacion_documento', function (Blueprint $table) {
            $table->unsignedInteger('id_notificacion_documento', true);
            $table->unsignedInteger('id_evaluacion')->unique();
            $table->string('url', 1000);
            $table->string('descripcion', 200)->nullable();
            $table->unsignedInteger('id_vinc_registra')->nullable();
            $table->dateTime('fecha_inclusion')->useCurrent();
            $table->dateTime('fecha_actualizacion')->nullable();

            $table->foreign('id_evaluacion')
                ->references('id_evaluacion')
                ->on('evaluacion')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('id_vinc_registra')
                ->references('id_vinculacion')
                ->on('vinculacion')
                ->onDelete('set null')
                ->onUpdate('cascade');

            $table->index('fecha_inclusion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificacion_documento');
    }
};
