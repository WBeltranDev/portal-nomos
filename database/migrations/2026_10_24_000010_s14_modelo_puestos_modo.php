<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S14 - Interruptor del modelo de puestos (plan, fase G). El modo vigente se
 * guarda en configuracion_sistema (clave modelo_puestos_modo); aquí quedan la
 * auditoría de cada cambio y las diferencias que el modo Observación detecta
 * entre el responsable por persona (actual) y el responsable por plaza.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('modelo_puestos_cambio')) {
            Schema::create('modelo_puestos_cambio', function (Blueprint $table) {
                $table->increments('id_cambio');
                $table->string('modo_anterior', 20);
                $table->string('modo_nuevo', 20);
                $table->unsignedInteger('id_usuario')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('id_usuario', 'fk_modelo_puestos_cambio_usuario')->references('id_usuario')->on('usuario');
            });
        }

        if (! Schema::hasTable('puesto_discrepancia')) {
            Schema::create('puesto_discrepancia', function (Blueprint $table) {
                $table->increments('id_discrepancia');
                $table->unsignedInteger('id_evaluacion');
                // Responsable según la lógica actual (vinculación) y según la plaza.
                $table->unsignedInteger('id_vinc_responsable_legado')->nullable();
                $table->unsignedInteger('id_vinc_responsable_puesto')->nullable();
                $table->string('motivo', 255);
                $table->boolean('resuelta')->default(false);
                $table->unsignedInteger('resuelta_por')->nullable();
                $table->timestamp('resuelta_at')->nullable();
                $table->timestamps();

                $table->index(['resuelta', 'id_evaluacion'], 'idx_discrepancia_pendiente');
                $table->foreign('id_evaluacion', 'fk_discrepancia_evaluacion')->references('id_evaluacion')->on('evaluacion');
                $table->foreign('resuelta_por', 'fk_discrepancia_resuelta_por')->references('id_usuario')->on('usuario');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('puesto_discrepancia');
        Schema::dropIfExists('modelo_puestos_cambio');
    }
};
