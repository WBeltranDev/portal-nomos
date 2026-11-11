<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Traslados en bloque (rotación): un lote agrupa traslados normales para
 * programarlos y confirmarlos todos juntos. Cada persona sigue teniendo su
 * registro en `traslado`; el lote guarda quién lo hizo, cuándo y con qué archivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('traslado_lote')) {
            Schema::create('traslado_lote', function (Blueprint $table) {
                $table->increments('id_lote');
                $table->string('descripcion', 300)->nullable();
                $table->date('fecha_traslado');
                $table->enum('estado', ['PROGRAMADO', 'CONFIRMADO', 'CANCELADO'])->default('PROGRAMADO');
                $table->unsignedSmallInteger('total')->default(0);
                $table->string('archivo', 255)->nullable();
                $table->unsignedInteger('id_usuario_crea')->nullable();
                $table->unsignedInteger('id_usuario_resuelve')->nullable();
                $table->timestamp('resuelto_at')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (! Schema::hasColumn('traslado', 'id_lote')) {
            Schema::table('traslado', function (Blueprint $table) {
                $table->unsignedInteger('id_lote')->nullable()->after('id_delegacion');
                $table->index('id_lote', 'traslado_id_lote_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('traslado', 'id_lote')) {
            Schema::table('traslado', function (Blueprint $table) {
                $table->dropIndex('traslado_id_lote_idx');
                $table->dropColumn('id_lote');
            });
        }
        Schema::dropIfExists('traslado_lote');
    }
};
