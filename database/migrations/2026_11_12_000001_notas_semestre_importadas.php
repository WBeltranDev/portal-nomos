<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contingencia: notas finales de un semestre cargadas desde Excel (los soportes
 * reposan en físico). Cada nota pertenece a un lote, que se puede revertir.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nota_importada_lote')) {
            Schema::create('nota_importada_lote', function (Blueprint $table) {
                $table->increments('id_lote');
                $table->unsignedSmallInteger('anio');
                $table->unsignedTinyInteger('semestre');
                $table->string('descripcion', 300)->nullable();
                $table->string('archivo', 255)->nullable();
                $table->unsignedInteger('total')->default(0);
                $table->string('estado', 20)->default('APLICADO');
                $table->unsignedInteger('id_usuario')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('revertido_at')->nullable();
            });
        }

        if (! Schema::hasTable('nota_semestre_importada')) {
            Schema::create('nota_semestre_importada', function (Blueprint $table) {
                $table->increments('id_nota_importada');
                $table->unsignedInteger('id_lote');
                $table->unsignedInteger('id_vinculacion');
                $table->unsignedSmallInteger('anio');
                $table->unsignedTinyInteger('semestre');
                $table->string('sistema', 30);
                $table->decimal('nota', 3, 1);
                $table->date('fecha_calificacion')->nullable();
                $table->string('observacion', 500)->nullable();
                $table->timestamp('created_at')->nullable();
                $table->unique(['id_vinculacion', 'anio', 'semestre', 'sistema'], 'nota_importada_unica');
                $table->index('id_lote');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('nota_semestre_importada');
        Schema::dropIfExists('nota_importada_lote');
    }
};
