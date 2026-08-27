<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificacion', function (Blueprint $table) {
            $table->id('id_notificacion');
            $table->string('tipo', 50);          // PERIODO_CERCA, RECURSO_NUEVO, PLAN_NUEVO, IMPEDIMENTO_NUEVO, RECUSACION_NUEVA, DELEGACION_PROXIMA
            $table->string('titulo', 255);
            $table->text('mensaje');
            $table->string('seccion', 50);        // section del admin: periodos, recursos-planes, impedimentos-admin, delegaciones
            $table->boolean('leida')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificacion');
    }
};
