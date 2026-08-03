<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S7 - Configuración parametrizada en base de datos.
 *
 * - `ponderacion`: pesos de compromisos/competencias/ejes por sistema
 *   (antes: storage/app/ponderaciones.json).
 * - `evaluacion_eje`: ejes misionales activos por evaluación
 *   (antes: storage/app/evaluacion_ejes.json).
 *
 * Ambos archivos vivían en la capa temporal del contenedor y se perdían en
 * cada rebuild; ahora son persistentes en MySQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ponderacion', function (Blueprint $table) {
            $table->increments('id_ponderacion');
            $table->string('sistema')->unique();
            $table->decimal('peso_compromisos', 5, 2)->default(0);
            $table->decimal('peso_competencias', 5, 2)->default(0);
            $table->decimal('peso_docencia', 5, 2)->default(0);
            $table->decimal('peso_investigacion', 5, 2)->default(0);
            $table->decimal('peso_proyeccion_social', 5, 2)->default(0);
        });

        DB::table('ponderacion')->insert([
            [
                'sistema' => 'RENDIMIENTO_LABORAL',
                'peso_compromisos' => 80.0,
                'peso_competencias' => 20.0,
                'peso_docencia' => 0.0,
                'peso_investigacion' => 0.0,
                'peso_proyeccion_social' => 0.0,
            ],
            [
                'sistema' => 'ACUERDO_GESTION',
                'peso_compromisos' => 50.0,
                'peso_competencias' => 20.0,
                'peso_docencia' => 10.0,
                'peso_investigacion' => 10.0,
                'peso_proyeccion_social' => 10.0,
            ],
        ]);

        Schema::create('evaluacion_eje', function (Blueprint $table) {
            $table->unsignedInteger('id_evaluacion')->primary();
            $table->boolean('investigacion')->default(false);
            $table->boolean('proyeccion_social')->default(false);
            $table->foreign('id_evaluacion', 'eje_evaluacion_fk')
                ->references('id_evaluacion')->on('evaluacion')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluacion_eje');
        Schema::dropIfExists('ponderacion');
    }
};
