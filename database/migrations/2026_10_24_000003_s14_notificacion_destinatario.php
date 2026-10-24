<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S14 - Avisos personales. Una notificación sin destinatario es de Talento
 * Humano (como hasta ahora); con id_usuario_destino es un aviso para ese
 * usuario, p. ej. al evaluado y al nuevo evaluador cuando cambia el
 * responsable de una evaluación.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('notificacion', 'id_usuario_destino')) {
            return;
        }

        Schema::table('notificacion', function (Blueprint $table) {
            $table->unsignedInteger('id_usuario_destino')->nullable()->after('seccion');
            $table->index(['id_usuario_destino', 'leida'], 'idx_notificacion_destino_leida');
            $table->foreign('id_usuario_destino', 'fk_notificacion_usuario_destino')->references('id_usuario')->on('usuario');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('notificacion', 'id_usuario_destino')) {
            return;
        }

        Schema::table('notificacion', function (Blueprint $table) {
            $table->dropForeign('fk_notificacion_usuario_destino');
            $table->dropIndex('idx_notificacion_destino_leida');
            $table->dropColumn('id_usuario_destino');
        });
    }
};
