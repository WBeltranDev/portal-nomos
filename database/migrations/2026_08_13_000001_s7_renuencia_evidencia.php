<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S7 - Evidencias de la renuencia (acta física digitalizada) como links,
 * de acuerdo al Sprint 6: la renuencia se resolverá mediante acta física
 * con testigo institucional y se subirá la evidencia digitalizada en PDF.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('renuencia_evidencia')) {
            Schema::create('renuencia_evidencia', function (Blueprint $table) {
                $table->unsignedInteger('id_renuncia_evidencia', true);
                $table->unsignedInteger('id_firma');
                $table->string('descripcion', 200)->nullable();
                $table->string('url', 1000);
                $table->dateTime('fecha_inclusion')->useCurrent();

                $table->index('id_firma');
                $table->foreign('id_firma')
                    ->references('id_firma')
                    ->on('firma')
                    ->onDelete('cascade')
                    ->onUpdate('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('renuencia_evidencia');
    }
};
