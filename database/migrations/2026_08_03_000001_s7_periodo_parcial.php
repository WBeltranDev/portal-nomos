<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S7 - Periodo parcial por funcionario.
 *
 * El admin registra un periodo PARCIAL para un funcionario que no estuvo desde
 * el inicio del semestre (ingreso a mitad de periodo o traslado). Almacena el
 * funcionario asociado y la referencia que lo identifica. El evaluador solo
 * podrá abrir una evaluación PARCIAL cuando exista un periodo parcial abierto
 * para el funcionario.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periodo_parcial', function (Blueprint $table) {
            $table->increments('id_periodo_parcial');
            $table->unsignedInteger('id_periodo');
            $table->unsignedInteger('id_vinc_funcionario');
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->string('referencia', 200)->nullable();
            $table->enum('estado', ['ABIERTO', 'CERRADO'])->default('ABIERTO');
            $table->unsignedInteger('id_usuario_apertura')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('id_periodo');
            $table->index('id_vinc_funcionario');
            $table->index('estado');

            $table->foreign('id_periodo', 'pp_periodo_fk')
                ->references('id_periodo')->on('periodo')->onDelete('cascade');
            $table->foreign('id_vinc_funcionario', 'pp_funcionario_fk')
                ->references('id_vinculacion')->on('vinculacion')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periodo_parcial');
    }
};
