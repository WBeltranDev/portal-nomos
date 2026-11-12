<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** La resolución de la delegación lleva número y fecha: 255 caracteres se quedan cortos. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delegacion', function (Blueprint $table) {
            $table->string('acto_administrativo', 500)->nullable()->change();
        });
    }

    public function down(): void
    {
        // No se revierte: acortar la columna truncaría resoluciones ya registradas.
    }
};
