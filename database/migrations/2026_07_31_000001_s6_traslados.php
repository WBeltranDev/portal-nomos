<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S6 - Gestión de traslados.
 *
 * Registra el traslado de un funcionario a otra dependencia/cargo con cambio
 * de evaluador. Cuando hay un periodo abierto se genera automáticamente una
 * evaluación PARCIAL prorrateada por los días laborados en la dependencia
 * origen (RF3: traslado / cambio de evaluador).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('traslado', function (Blueprint $table) {
            $table->increments('id_traslado');
            $table->unsignedInteger('id_vinc_funcionario');
            $table->unsignedInteger('id_vinc_evaluador_origen')->nullable();
            $table->unsignedInteger('id_vinc_evaluador_nuevo');
            $table->string('area_origen', 250)->nullable();
            $table->string('cargo_origen', 200)->nullable();
            $table->string('area_nuevo', 250)->nullable();
            $table->string('cargo_nuevo', 200)->nullable();
            $table->date('fecha_traslado');
            $table->unsignedSmallInteger('dias_laborados')->nullable();
            $table->unsignedInteger('id_evaluacion_parcial')->nullable();
            $table->string('resolucion', 200)->nullable();
            $table->string('motivo', 500)->nullable();
            $table->unsignedInteger('id_usuario_registra')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('id_vinc_funcionario');
            $table->index('id_vinc_evaluador_nuevo');
            $table->index('fecha_traslado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traslado');
    }
};
