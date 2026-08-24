<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Tabla de Cargos
        if (!Schema::hasTable('cargo')) {
            Schema::create('cargo', function (Blueprint $table) {
                $table->increments('id_cargo');
                $table->string('nombre', 255)->unique();
                $table->integer('codigo_cargo')->nullable()->default(0);
                $table->integer('grado_cargo')->nullable()->default(0);
                $table->string('nivel_jerarquico', 50)->nullable()->default('PROFESIONAL');
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        }

        // 2. Tabla de Dependencias (Áreas)
        if (!Schema::hasTable('dependencia')) {
            Schema::create('dependencia', function (Blueprint $table) {
                $table->increments('id_dependencia');
                $table->string('nombre', 255)->unique();
                $table->boolean('activa')->default(true);
                $table->timestamps();
            });
        }

        // 3. Ajustes en Vinculación (vacancias y referencias)
        Schema::table('vinculacion', function (Blueprint $table) {
            if (!Schema::hasColumn('vinculacion', 'es_vacante')) {
                $table->boolean('es_vacante')->default(false)->after('activa');
            }
        });

        // 4. Ajustes en Delegación (área para trazabilidad)
        if (Schema::hasTable('delegacion')) {
            Schema::table('delegacion', function (Blueprint $table) {
                if (!Schema::hasColumn('delegacion', 'area')) {
                    $table->string('area', 255)->nullable()->after('motivo');
                }
            });
        }

        // 5. Poblar datos existentes de vinculacion si existen
        try {
            if (Schema::hasTable('vinculacion')) {
                $cargosExistentes = DB::table('vinculacion')
                    ->whereNotNull('cargo')
                    ->where('cargo', '!=', '')
                    ->select('cargo', 'codigo_cargo', 'grado_cargo', 'nivel_jerarquico')
                    ->distinct()
                    ->get();

                foreach ($cargosExistentes as $c) {
                    $nombreCargo = trim($c->cargo);
                    if (!empty($nombreCargo) && !DB::table('cargo')->where('nombre', $nombreCargo)->exists()) {
                        DB::table('cargo')->insert([
                            'nombre' => $nombreCargo,
                            'codigo_cargo' => $c->codigo_cargo ?? 0,
                            'grado_cargo' => $c->grado_cargo ?? 0,
                            'nivel_jerarquico' => $c->nivel_jerarquico ?? 'PROFESIONAL',
                            'activo' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                $areasExistentes = DB::table('vinculacion')
                    ->whereNotNull('area')
                    ->where('area', '!=', '')
                    ->pluck('area')
                    ->unique();

                foreach ($areasExistentes as $area) {
                    $nombreArea = trim($area);
                    if (!empty($nombreArea) && !DB::table('dependencia')->where('nombre', $nombreArea)->exists()) {
                        DB::table('dependencia')->insert([
                            'nombre' => $nombreArea,
                            'activa' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        // 6. Tabla de Concertaciones en Extratiempo (S9)
        if (!Schema::hasTable('concertacion_extratiempo')) {
            Schema::create('concertacion_extratiempo', function (Blueprint $table) {
                $table->increments('id_extratiempo');
                $table->unsignedInteger('id_evaluacion');
                $table->text('justificacion');
                $table->string('autorizado_por', 255)->nullable();
                $table->date('fecha_limite');
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('delegacion') && Schema::hasColumn('delegacion', 'area')) {
            Schema::table('delegacion', function (Blueprint $table) {
                $table->dropColumn('area');
            });
        }

        if (Schema::hasTable('vinculacion') && Schema::hasColumn('vinculacion', 'es_vacante')) {
            Schema::table('vinculacion', function (Blueprint $table) {
                $table->dropColumn('es_vacante');
            });
        }

        Schema::dropIfExists('dependencia');
        Schema::dropIfExists('cargo');
    }
};
