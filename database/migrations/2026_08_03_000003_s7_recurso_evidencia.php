<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S7 - Evidencias de recursos (reposición / apelación) como links.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('recurso_evidencia')) {
            Schema::create('recurso_evidencia', function (Blueprint $table) {
                $table->unsignedInteger('id_recurso_evidencia', true);
                $table->unsignedInteger('id_recurso');
                $table->string('descripcion', 200)->nullable();
                $table->string('url', 1000);
                $table->dateTime('fecha_inclusion')->useCurrent();

                $table->index('id_recurso');
                $table->foreign('id_recurso')
                    ->references('id_recurso')
                    ->on('recurso')
                    ->onDelete('cascade')
                    ->onUpdate('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('recurso_evidencia');
    }
};
