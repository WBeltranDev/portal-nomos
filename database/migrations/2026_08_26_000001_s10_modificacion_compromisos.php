<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitud_modificacion_compromiso', function (Blueprint $table) {
            $table->id('id_solicitud');
            $table->unsignedBigInteger('id_evaluacion');
            $table->unsignedBigInteger('id_vinc_solicitante'); // evaluador o evaluado
            $table->text('motivo');                            // justificación (incapacidad, etc.)
            $table->json('detalle_cambio');                    // { id_compromiso, descripcion, porcentaje_peso, metas: [] }
            $table->enum('estado', ['PENDIENTE', 'APROBADO', 'RECHAZADO'])->default('PENDIENTE');
            $table->text('respuesta_admin')->nullable();
            $table->unsignedBigInteger('id_usuario_admin')->nullable();
            $table->timestamps();

            $table->foreign('id_evaluacion', 'solmod_evaluacion_fk')
                ->references('id_evaluacion')->on('evaluacion')
                ->onDelete('cascade');
            $table->foreign('id_vinc_solicitante', 'solmod_solicitante_fk')
                ->references('id_vinculacion')->on('vinculacion')
                ->onDelete('cascade');

            $table->index('id_evaluacion', 'solmod_eval_idx');
        });

        Schema::create('bitacora_modificacion_compromiso', function (Blueprint $table) {
            $table->id('id_bitacora');
            $table->unsignedBigInteger('id_evaluacion');
            $table->unsignedBigInteger('id_compromiso');
            $table->text('motivo');
            $table->json('detalle_anterior');
            $table->json('detalle_nuevo');
            $table->string('creado_por', 100)->nullable(); // nombre del admin/aprobador
            $table->timestamps();

            $table->foreign('id_evaluacion', 'bitmod_evaluacion_fk')
                ->references('id_evaluacion')->on('evaluacion')
                ->onDelete('cascade');
            $table->foreign('id_compromiso', 'bitmod_compromiso_fk')
                ->references('id_compromiso')->on('compromiso')
                ->onDelete('cascade');

            $table->index('id_evaluacion', 'bitmod_eval_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitacora_modificacion_compromiso');
        Schema::dropIfExists('solicitud_modificacion_compromiso');
    }
};