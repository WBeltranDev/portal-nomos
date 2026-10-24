<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S14 - Modelo de puestos, fases A-B del plan: catálogo de plazas concretas
 * (dependencia + cargo + grado + consecutivo) y quién ocupa cada una. Se crea
 * como borrador desde las vinculaciones y Talento Humano lo valida. Aún no
 * cambia cómo funcionan delegaciones, bandejas ni asignaciones.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('puesto')) {
            Schema::create('puesto', function (Blueprint $table) {
                $table->increments('id_puesto');
                $table->string('codigo_plaza', 40)->unique();
                $table->unsignedInteger('id_dependencia');
                $table->unsignedInteger('id_cargo');
                $table->unsignedSmallInteger('codigo_cargo')->nullable();
                $table->unsignedTinyInteger('grado')->nullable();
                $table->string('nivel_jerarquico', 50)->nullable();
                $table->string('sistema_evaluacion', 30)->nullable();
                $table->unsignedInteger('id_puesto_superior')->nullable();
                $table->enum('estado', ['BORRADOR', 'VALIDADA', 'INACTIVA'])->default('BORRADOR');
                // Comparte dependencia, cargo y grado con otra plaza: TH confirma que son distintas.
                $table->boolean('requiere_revision')->default(false);
                $table->unsignedInteger('validada_por')->nullable();
                $table->timestamp('validada_at')->nullable();
                $table->timestamps();

                $table->index(['id_dependencia', 'codigo_cargo', 'grado'], 'idx_puesto_dependencia_cargo');
                $table->foreign('id_dependencia', 'fk_puesto_dependencia')->references('id_dependencia')->on('dependencia');
                $table->foreign('id_cargo', 'fk_puesto_cargo')->references('id_cargo')->on('cargo');
                $table->foreign('id_puesto_superior', 'fk_puesto_superior')->references('id_puesto')->on('puesto')->nullOnDelete();
                $table->foreign('validada_por', 'fk_puesto_validada_por')->references('id_usuario')->on('usuario');
            });
        }

        if (! Schema::hasTable('puesto_ocupacion')) {
            Schema::create('puesto_ocupacion', function (Blueprint $table) {
                $table->increments('id_puesto_ocupacion');
                $table->unsignedInteger('id_puesto');
                $table->unsignedInteger('id_vinculacion');
                // Copia del tipo de vinculación (carrera, provisionalidad, LNR...).
                $table->string('tipo_ocupacion', 50)->nullable();
                $table->date('vigente_desde')->nullable();
                $table->date('vigente_hasta')->nullable();
                // Sin fecha de ingreso conocida no se inventa: queda marcada como estimada.
                $table->boolean('fecha_estimada')->default(false);
                $table->enum('estado', ['VIGENTE', 'CERRADA'])->default('VIGENTE');
                $table->unsignedInteger('registrado_por')->nullable();
                $table->timestamps();

                $table->index(['id_puesto', 'vigente_desde', 'vigente_hasta'], 'idx_ocupacion_puesto_vigencia');
                $table->index(['id_vinculacion', 'estado'], 'idx_ocupacion_vinculacion_estado');
                $table->foreign('id_puesto', 'fk_ocupacion_puesto')->references('id_puesto')->on('puesto');
                $table->foreign('id_vinculacion', 'fk_ocupacion_vinculacion')->references('id_vinculacion')->on('vinculacion');
                $table->foreign('registrado_por', 'fk_ocupacion_registrado_por')->references('id_usuario')->on('usuario');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('puesto_ocupacion');
        Schema::dropIfExists('puesto');
    }
};
