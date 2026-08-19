<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delegacion', function (Blueprint $table) {
            $table->date('fecha_fin')->nullable()->change();
            $table->string('acto_administrativo', 255)->nullable()->after('motivo');
            $table->string('acto_administrativo_numero', 100)->nullable()->after('acto_administrativo');
            $table->date('acto_administrativo_fecha')->nullable()->after('acto_administrativo_numero');
            $table->string('acto_administrativo_url', 1000)->nullable()->after('acto_administrativo_fecha');
        });
    }

    public function down(): void
    {
        Schema::table('delegacion', function (Blueprint $table) {
            $table->dropColumn([
                'acto_administrativo',
                'acto_administrativo_numero',
                'acto_administrativo_fecha',
                'acto_administrativo_url',
            ]);
            $table->date('fecha_fin')->nullable(false)->change();
        });
    }
};
