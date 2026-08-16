<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S8 - Auditoría de periodos.
 *
 * Registra cada cambio realizado por el administrador sobre un periodo:
 * CREAR (apertura), EDITAR (fechas/descripción/estado), ABRIR y CERRAR.
 * El detalle antes->después de cada cambio se guarda en JSON.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periodo_auditoria', function (Blueprint $table) {
            $table->increments('id_periodo_auditoria');
            $table->unsignedInteger('id_periodo');
            $table->unsignedInteger('id_usuario')->nullable();
            $table->enum('accion', ['CREAR', 'EDITAR', 'ABRIR', 'CERRAR']);
            $table->json('cambios')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('id_periodo');
            $table->index('accion');

            $table->foreign('id_periodo', 'pa_periodo_fk')
                ->references('id_periodo')->on('periodo')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periodo_auditoria');
    }
};
