<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S8 - Edición de periodos por el administrador: se agrega una descripción
 * opcional al periodo para identificarlo mejor en el panel y reportes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('periodo', 'descripcion')) {
            Schema::table('periodo', function (Blueprint $table) {
                $table->string('descripcion', 200)->nullable()->after('fecha_fin');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('periodo', 'descripcion')) {
            Schema::table('periodo', function (Blueprint $table) {
                $table->dropColumn('descripcion');
            });
        }
    }
};
