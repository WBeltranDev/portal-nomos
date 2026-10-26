<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S14 (regla 11) - Bitácora de cada evaluación: quién hizo qué y cuándo, con
 * los valores clave antes y después (estado, fase, evaluador, nota,
 * decisión). Es de solo escritura y no copia textos completos: los
 * compromisos, observaciones y motivos se referencian por su id.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('evaluacion_evento')) {
            return;
        }

        Schema::create('evaluacion_evento', function (Blueprint $table) {
            $table->bigIncrements('id_evento');
            $table->unsignedInteger('id_evaluacion');
            $table->string('tipo_evento', 50);
            $table->unsignedInteger('id_usuario_actor')->nullable();
            $table->string('rol_actor', 20)->nullable();
            $table->string('estado_anterior', 30)->nullable();
            $table->string('estado_nuevo', 30)->nullable();
            $table->json('datos')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['id_evaluacion', 'created_at'], 'idx_evento_evaluacion_fecha');
            $table->index(['id_usuario_actor', 'created_at'], 'idx_evento_actor_fecha');
            $table->foreign('id_evaluacion', 'fk_evento_evaluacion')->references('id_evaluacion')->on('evaluacion');
            $table->foreign('id_usuario_actor', 'fk_evento_usuario')->references('id_usuario')->on('usuario');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluacion_evento');
    }
};
