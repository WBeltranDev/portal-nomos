<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S6 - Recursos en línea (Reposición / Apelación), Renuencia con testigos y
 * Plan de Mejoramiento condicionado.
 *
 * Las tablas ya existen en el dump base de la BD institucional; esta migración
 * solo las crea si un ambiente fresco no las tiene todavía.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('recurso')) {
            Schema::create('recurso', function (Blueprint $table) {
                $table->unsignedInteger('id_recurso', true);
                $table->unsignedInteger('id_evaluacion');
                $table->enum('tipo_recurso', ['REPOSICION', 'APELACION']);
                $table->unsignedInteger('id_vinc_receptor');
                $table->string('numero_radicado', 50)->nullable();
                $table->unsignedSmallInteger('numero_folios')->nullable();
                $table->date('fecha_recurso');
                $table->enum('decision', ['PENDIENTE', 'APROBADO', 'NEGADO'])->default('PENDIENTE');
                $table->text('motivacion')->nullable();
                $table->date('fecha_decision')->nullable();

                $table->index('id_evaluacion');
                $table->index('id_vinc_receptor');
                $table->index('decision');
            });
        }

        if (!Schema::hasTable('plan_mejoramiento')) {
            Schema::create('plan_mejoramiento', function (Blueprint $table) {
                $table->unsignedInteger('id_plan', true);
                $table->unsignedInteger('id_evaluacion')->unique();
                $table->text('descripcion_temas');
                $table->dateTime('fecha_creacion')->useCurrent();
                $table->boolean('firmado_evaluado')->default(false);
                $table->boolean('firmado_evaluador')->default(false);
                $table->dateTime('fecha_firma_evaluado')->nullable();
                $table->dateTime('fecha_firma_evaluador')->nullable();
                $table->enum('estado', ['PENDIENTE', 'CONCERTADO', 'CERRADO'])->default('PENDIENTE');
            });
        }

        if (!Schema::hasTable('testigo_renuencia')) {
            Schema::create('testigo_renuencia', function (Blueprint $table) {
                $table->unsignedInteger('id_testigo', true);
                $table->unsignedInteger('id_firma');
                $table->string('nombre_testigo', 200);
                $table->string('cargo_testigo', 200);
                $table->dateTime('fecha_registro')->useCurrent();

                $table->index('id_firma');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('testigo_renuencia');
        Schema::dropIfExists('plan_mejoramiento');
        Schema::dropIfExists('recurso');
    }
};
