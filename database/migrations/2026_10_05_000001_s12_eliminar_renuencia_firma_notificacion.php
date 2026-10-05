<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S12 - Eliminacion de la renuencia a la firma con testigos.
 *
 * El sprint anterior (commit a8ddeff) retiro la renuencia de la interfaz, pero
 * dejo el residuo en el esquema: la marca `firma.renuencia`, las columnas de
 * testigo y observacion agregadas en S9 y las tablas `testigo_renuencia` y
 * `renuencia_evidencia`.
 *
 * Desde este sprint el evaluado SIEMPRE firma la notificacion de la
 * calificacion, este o no de acuerdo, para dejar trazabilidad de que fue
 * notificado. Si no esta de acuerdo con la nota, el tramite es el recurso de
 * reposicion o apelacion con sus soportes, no la renuencia. Por tanto la
 * renuencia y los testigos dejan de tener lugar en el flujo y se eliminan.
 *
 * La columna `firma.tipo_firma` conserva el valor `NOTIFICACION_EVALUADO`, que
 * pasa a ser la unica forma de registrar la notificacion de la calificacion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('renuencia_evidencia');
        Schema::dropIfExists('testigo_renuencia');

        if (Schema::hasTable('firma')) {
            $columnas = array_values(array_filter(
                ['renuencia', 'testigo_nombre', 'testigo_documento', 'observacion_renuencia'],
                fn (string $columna) => Schema::hasColumn('firma', $columna)
            ));

            if ($columnas) {
                Schema::table('firma', function (Blueprint $table) use ($columnas) {
                    $table->dropColumn($columnas);
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('firma')) {
            $faltantes = array_values(array_filter(
                ['renuencia', 'testigo_nombre', 'testigo_documento', 'observacion_renuencia'],
                fn (string $columna) => ! Schema::hasColumn('firma', $columna)
            ));

            if ($faltantes) {
                Schema::table('firma', function (Blueprint $table) use ($faltantes) {
                    if (in_array('renuencia', $faltantes, true)) {
                        $table->boolean('renuencia')->default(false);
                    }
                    if (in_array('testigo_nombre', $faltantes, true)) {
                        $table->string('testigo_nombre', 255)->nullable();
                    }
                    if (in_array('testigo_documento', $faltantes, true)) {
                        $table->string('testigo_documento', 50)->nullable();
                    }
                    if (in_array('observacion_renuencia', $faltantes, true)) {
                        $table->text('observacion_renuencia')->nullable();
                    }
                });
            }
        }

        if (! Schema::hasTable('testigo_renuencia')) {
            Schema::create('testigo_renuencia', function (Blueprint $table) {
                $table->unsignedInteger('id_testigo', true);
                $table->unsignedInteger('id_firma');
                $table->string('nombre_testigo', 200);
                $table->string('cargo_testigo', 200);
                $table->dateTime('fecha_registro')->useCurrent();

                $table->index('id_firma');
            });
        }

        if (! Schema::hasTable('renuencia_evidencia')) {
            Schema::create('renuencia_evidencia', function (Blueprint $table) {
                $table->unsignedInteger('id_renuncia_evidencia', true);
                $table->unsignedInteger('id_firma');
                $table->string('descripcion', 200)->nullable();
                $table->string('url', 1000);
                $table->dateTime('fecha_inclusion')->useCurrent();

                $table->index('id_firma');
                $table->foreign('id_firma')
                    ->references('id_firma')
                    ->on('firma')
                    ->onDelete('cascade')
                    ->onUpdate('cascade');
            });
        }
    }
};
