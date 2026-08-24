<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Tabla de Impedimentos y Recusaciones
        Schema::create('impedimento_recusacion', function (Blueprint $table) {
            $table->id('id_impedimento');
            $table->unsignedBigInteger('id_evaluacion');
            $table->unsignedBigInteger('id_vinc_solicitante'); // Quién lo solicita (evaluador o evaluado)
            $table->enum('tipo', ['IMPEDIMENTO', 'RECUSACION']);
            $table->text('motivo');
            $table->text('evidencia_url')->nullable();
            $table->enum('estado', ['PENDIENTE', 'APROBADO', 'RECHAZADO'])->default('PENDIENTE');
            $table->text('respuesta_admin')->nullable();
            $table->unsignedBigInteger('id_usuario_admin')->nullable();
            $table->timestamps();
        });

        // 2. Extender tabla firma / evaluacion para testigo y desacuerdo
        Schema::table('firma', function (Blueprint $table) {
            $table->string('testigo_nombre', 255)->nullable();
            $table->string('testigo_documento', 50)->nullable();
            $table->text('observacion_renuencia')->nullable();
        });

        Schema::table('evaluacion', function (Blueprint $table) {
            $table->text('desacuerdo_evaluado')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('impedimento_recusacion');

        Schema::table('firma', function (Blueprint $table) {
            $table->dropColumn(['testigo_nombre', 'testigo_documento', 'observacion_renuencia']);
        });

        Schema::table('evaluacion', function (Blueprint $table) {
            $table->dropColumn('desacuerdo_evaluado');
        });
    }
};
