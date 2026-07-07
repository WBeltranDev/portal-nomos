<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compromiso_observacion', function (Blueprint $table) {
            $table->id('id_observacion');
            $table->unsignedBigInteger('id_evaluacion');
            $table->unsignedBigInteger('id_compromiso');
            $table->unsignedBigInteger('id_vinc_evaluador')->nullable();
            $table->text('texto');
            $table->boolean('confirmada')->default(false);
            $table->dateTime('fecha_inclusion');
            $table->dateTime('fecha_actualizacion')->nullable();
            $table->dateTime('fecha_confirmacion')->nullable();

            $table->unique(['id_evaluacion', 'id_compromiso'], 'comp_obs_eval_comp_unique');
            $table->index('id_compromiso', 'comp_obs_compromiso_index');
            $table->index('id_vinc_evaluador', 'comp_obs_evaluador_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compromiso_observacion');
    }
};
