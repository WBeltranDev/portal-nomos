<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('configuracion_sistema')) {
            Schema::create('configuracion_sistema', function (Blueprint $table) {
                $table->string('clave', 100)->primary();
                $table->text('valor')->nullable();
                $table->string('descripcion', 255)->nullable();
                $table->timestamps();
            });

            DB::table('configuracion_sistema')->insert([
                [
                    'clave' => 'encuesta_capacitacion_url',
                    'valor' => null,
                    'descripcion' => 'Enlace a la encuesta externa de necesidades de capacitación (Google Forms u otro)',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'clave' => 'encuesta_capacitacion_activa',
                    'valor' => '1',
                    'descripcion' => 'Indica si la encuesta de capacitación está habilitada para los evaluados',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_sistema');
    }
};
