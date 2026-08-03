<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S7 - Catálogo de competencias (normalización 3FN).
 *
 * Extrae a una tabla `competencia_catalogo` el nombre/tipo/afirmación de las
 * competencias del pliego (antes repetidos como texto en `competencia_evaluada`).
 * `competencia_evaluada` pasa a referenciar el catálogo por FK (`id_competencia`),
 * eliminando la dependencia transitiva nombre -> competencia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competencia_catalogo', function (Blueprint $table) {
            $table->increments('id_competencia');
            $table->enum('sistema', ['RENDIMIENTO_LABORAL', 'ACUERDO_GESTION']);
            $table->enum('tipo', ['COMUN', 'NIVEL_JERARQUICO']);
            $table->enum('nivel_jerarquico', ['DIRECTIVO', 'ASESOR', 'PROFESIONAL', 'TECNICO', 'ASISTENCIAL'])->nullable();
            $table->string('nombre', 150);
            $table->text('afirmacion')->nullable();
            $table->unsignedSmallInteger('orden')->default(0);

            $table->unique(['sistema', 'tipo', 'nivel_jerarquico', 'nombre'], 'cc_sistema_tipo_nivel_nombre_unique');
        });

        // Seed desde el catálogo canónico (misma fuente que usa la app en runtime).
        $path = base_path('storage/app/competencias_catalogo.json');
        if (file_exists($path)) {
            $catalogo = json_decode(file_get_contents($path), true) ?? [];
            $rows = [];
            $orden = 0;
            foreach (['RENDIMIENTO_LABORAL', 'ACUERDO_GESTION'] as $sistema) {
                foreach ($catalogo[$sistema]['COMUN'] ?? [] as $item) {
                    $rows[] = [
                        'sistema' => $sistema,
                        'tipo' => 'COMUN',
                        'nivel_jerarquico' => null,
                        'nombre' => $item['nombre'],
                        'afirmacion' => $item['afirmacion'] ?? null,
                        'orden' => $orden++,
                    ];
                }
                foreach ($catalogo[$sistema]['NIVEL_JERARQUICO'] ?? [] as $nivel => $items) {
                    foreach ($items as $item) {
                        $rows[] = [
                            'sistema' => $sistema,
                            'tipo' => 'NIVEL_JERARQUICO',
                            'nivel_jerarquico' => $nivel,
                            'nombre' => $item['nombre'],
                            'afirmacion' => $item['afirmacion'] ?? null,
                            'orden' => $orden++,
                        ];
                    }
                }
            }
            DB::table('competencia_catalogo')->insert($rows);
        }

        // Vincula el catálogo en competencia_evaluada
        Schema::table('competencia_evaluada', function (Blueprint $table) {
            $table->unsignedInteger('id_competencia')->nullable()->after('id_evaluacion');
        });

        // Backfill (defensivo; la tabla está vacía en los ambientes actuales)
        DB::statement(
            "UPDATE competencia_evaluada ce
                JOIN evaluacion ev ON ev.id_evaluacion = ce.id_evaluacion
                JOIN periodo p ON p.id_periodo = ev.id_periodo
                JOIN vinculacion v ON v.id_vinculacion = ev.id_vinc_evaluado
                JOIN competencia_catalogo cc
                  ON cc.sistema = p.sistema
                 AND cc.tipo = ce.tipo
                 AND (ce.tipo = 'COMUN'
                        OR cc.nivel_jerarquico = v.nivel_jerarquico)
                 AND cc.nombre = ce.nombre_competencia
                SET ce.id_competencia = cc.id_competencia"
        );

        Schema::table('competencia_evaluada', function (Blueprint $table) {
            $table->foreign('id_competencia', 'ce_id_competencia_fk')
                ->references('id_competencia')->on('competencia_catalogo')
                ->onDelete('set null');
        });

        Schema::table('competencia_evaluada', function (Blueprint $table) {
            $table->dropColumn(['nombre_competencia', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::table('competencia_evaluada', function (Blueprint $table) {
            $table->string('nombre_competencia', 150)->nullable()->after('id_competencia');
            $table->enum('tipo', ['COMUN', 'NIVEL_JERARQUICO'])->nullable()->after('nombre_competencia');
        });

        DB::statement(
            "UPDATE competencia_evaluada ce
                JOIN competencia_catalogo cc ON cc.id_competencia = ce.id_competencia
                SET ce.nombre_competencia = cc.nombre, ce.tipo = cc.tipo"
        );

        Schema::table('competencia_evaluada', function (Blueprint $table) {
            $table->dropForeign('ce_id_competencia_fk');
            $table->dropColumn('id_competencia');
        });

        Schema::dropIfExists('competencia_catalogo');
    }
};
