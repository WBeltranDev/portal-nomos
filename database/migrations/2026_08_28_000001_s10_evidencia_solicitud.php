<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitud_modificacion_compromiso', function (Blueprint $table) {
            $table->string('evidencia_url', 1000)->nullable()
                ->after('motivo')
                ->comment('Enlace/URL de la evidencia que justifica la solicitud (incapacidad, comisión, etc.)');
        });
    }

    public function down(): void
    {
        Schema::table('solicitud_modificacion_compromiso', function (Blueprint $table) {
            $table->dropColumn('evidencia_url');
        });
    }
};
