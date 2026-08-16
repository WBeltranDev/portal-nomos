<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S8 - Gestión de delegaciones de funciones del cargo entre evaluadores.
 *
 * Una delegación es temporal: el titular (delegante) cede el ejercicio de su
 * rol de evaluador al delegado durante una vigencia, sin alterar la titularidad
 * del cargo. Al finalizar, el titular retoma y conserva la responsabilidad de
 * la firma final de las evaluaciones pendientes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delegacion', function (Blueprint $table) {
            $table->increments('id_delegacion');
            $table->unsignedInteger('id_vinc_delegante');
            $table->unsignedInteger('id_vinc_delegado');
            $table->string('motivo', 500)->nullable();
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->enum('estado', ['ACTIVA', 'FINALIZADA'])->default('ACTIVA');
            $table->unsignedInteger('id_usuario_registra')->nullable();
            $table->json('detalle_transferencia')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->index('id_vinc_delegante');
            $table->index('id_vinc_delegado');
            $table->index(['id_vinc_delegante', 'estado']);
            $table->index(['id_vinc_delegado', 'estado']);

            $table->foreign('id_vinc_delegante', 'delg_delegante_fk')
                ->references('id_vinculacion')->on('vinculacion')->onDelete('cascade');
            $table->foreign('id_vinc_delegado', 'delg_delegado_fk')
                ->references('id_vinculacion')->on('vinculacion')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delegacion');
    }
};
