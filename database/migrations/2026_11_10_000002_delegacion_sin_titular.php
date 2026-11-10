<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Delegación de un cargo vacante: el titular ya no está, así que no hay tramo
 * que calificar. Las personas a cargo pasan directamente al delegado.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('delegacion', 'sin_titular')) {
            Schema::table('delegacion', function (Blueprint $table) {
                $table->boolean('sin_titular')->default(false)->after('delegado_cambia_evaluador');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('delegacion', 'sin_titular')) {
            Schema::table('delegacion', fn (Blueprint $table) => $table->dropColumn('sin_titular'));
        }
    }
};
