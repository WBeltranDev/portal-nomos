<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Esquema BASE del sistema (antes vivía solo en el dump SQL institucional).
 *
 * Crea las tablas núcleo que todas las migraciones de sprint (S4..S9) asumen
 * existentes: usuario, funcionario, vinculacion, periodo, evaluacion, firma,
 * compromiso, compromiso_meta, evidencia, eje_misional_calificacion y
 * competencia_evaluada.
 *
 * IMPORTANTE: aquí SOLO van las columnas originales del dump. Las columnas
 * agregadas por cada sprint las añaden sus propias migraciones
 * (calificacion_parcial, nota_*, referencia, es_traslado, desacuerdo_evaluado,
 * id_vinc_suplente, id_vinc_jefe, es_vacante, componente/estado_aprobacion,
 * testigo_*, id_competencia, descripcion de periodo, etc.).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ------------------------------------------------------------------
        // usuario
        // ------------------------------------------------------------------
        if (!Schema::hasTable('usuario')) {
            Schema::create('usuario', function (Blueprint $table) {
                $table->increments('id_usuario');
                $table->string('username', 191)->unique();
                $table->string('password');
                $table->string('rol', 40);
                $table->boolean('activo')->default(true);
                $table->dateTime('ultimo_acceso')->nullable();
            });
        }

        // ------------------------------------------------------------------
        // funcionario
        // ------------------------------------------------------------------
        if (!Schema::hasTable('funcionario')) {
            Schema::create('funcionario', function (Blueprint $table) {
                $table->increments('id_funcionario');
                $table->unsignedInteger('id_usuario');
                $table->string('tipo_documento', 30)->nullable();
                $table->string('numero_doc', 30)->nullable();
                $table->string('nombres', 100);
                $table->string('apellidos', 100);
                $table->date('fecha_nacimiento')->nullable();
                $table->string('telefono', 30)->nullable();
                $table->string('correo_cargo', 191)->nullable();
                $table->string('genero', 20)->nullable();

                $table->foreign('id_usuario', 'funcionario_usuario_fk')
                    ->references('id_usuario')->on('usuario')
                    ->onDelete('cascade');
            });
        }

        // ------------------------------------------------------------------
        // vinculacion
        // ------------------------------------------------------------------
        if (!Schema::hasTable('vinculacion')) {
            Schema::create('vinculacion', function (Blueprint $table) {
                $table->increments('id_vinculacion');
                $table->unsignedInteger('id_funcionario');
                $table->string('cargo', 255)->nullable();
                $table->integer('codigo_cargo')->nullable()->default(0);
                $table->integer('grado_cargo')->nullable()->default(0);
                $table->string('nivel_jerarquico', 50)->nullable()->default('PROFESIONAL');
                $table->string('area', 255)->nullable();
                $table->string('tipo_vinculacion', 50)->nullable();
                $table->string('sistema_evaluacion', 50)->default('RENDIMIENTO_LABORAL');
                $table->boolean('es_evaluador')->default(false);
                $table->boolean('aplica_eje_misional')->default(false);
                $table->date('fecha_ingreso')->nullable();
                $table->date('fecha_retiro')->nullable();
                $table->string('resolucion', 100)->nullable();
                $table->boolean('activa')->default(true);

                $table->foreign('id_funcionario', 'vinculacion_funcionario_fk')
                    ->references('id_funcionario')->on('funcionario')
                    ->onDelete('cascade');
            });
        }

        // ------------------------------------------------------------------
        // periodo
        // ------------------------------------------------------------------
        if (!Schema::hasTable('periodo')) {
            Schema::create('periodo', function (Blueprint $table) {
                $table->increments('id_periodo');
                $table->unsignedInteger('id_usuario_apertura')->nullable();
                $table->string('sistema', 50);
                $table->integer('anio');
                $table->integer('semestre');
                $table->date('fecha_inicio');
                $table->date('fecha_fin');
                $table->string('estado', 20)->default('ABIERTO');

                $table->foreign('id_usuario_apertura', 'periodo_usuario_fk')
                    ->references('id_usuario')->on('usuario')
                    ->onDelete('set null');
            });
        }

        // ------------------------------------------------------------------
        // evaluacion
        // ------------------------------------------------------------------
        if (!Schema::hasTable('evaluacion')) {
            Schema::create('evaluacion', function (Blueprint $table) {
                $table->increments('id_evaluacion');
                $table->unsignedInteger('id_periodo');
                $table->unsignedInteger('id_vinc_evaluado');
                $table->unsignedInteger('id_vinc_evaluador')->nullable();
                $table->string('tipo_evaluacion', 30);
                $table->tinyInteger('fase_actual')->default(1);
                $table->boolean('concertacion_firmada')->default(false);
                $table->string('estado', 30)->default('EN_PROCESO');
                $table->decimal('calificacion_final', 5, 2)->nullable();
                $table->string('categoria_final', 50)->nullable();
                $table->boolean('es_parcial')->default(false);
                $table->integer('dias_laborados')->nullable();

                $table->foreign('id_periodo', 'evaluacion_periodo_fk')
                    ->references('id_periodo')->on('periodo')
                    ->onDelete('cascade');
                $table->foreign('id_vinc_evaluado', 'evaluacion_evaluado_fk')
                    ->references('id_vinculacion')->on('vinculacion')
                    ->onDelete('cascade');
                $table->foreign('id_vinc_evaluador', 'evaluacion_evaluador_fk')
                    ->references('id_vinculacion')->on('vinculacion')
                    ->onDelete('set null');

                $table->index('id_vinc_evaluado', 'ev_evaluado_idx');
                $table->index('id_vinc_evaluador', 'ev_evaluador_idx');
                $table->index('id_periodo', 'ev_periodo_idx');
            });
        }

        // ------------------------------------------------------------------
        // firma
        // ------------------------------------------------------------------
        if (!Schema::hasTable('firma')) {
            Schema::create('firma', function (Blueprint $table) {
                $table->increments('id_firma');
                $table->unsignedInteger('id_evaluacion');
                $table->enum('tipo_firma', [
                    'CONCERTACION_EVALUADO',
                    'CONCERTACION_EVALUADOR',
                    'SEMESTRAL_EVALUADO',
                    'SEMESTRAL_EVALUADOR',
                    'DEFINITIVA_EVALUADO',
                    'DEFINITIVA_EVALUADOR',
                ]);
                $table->unsignedInteger('id_vinc_firmante')->nullable();
                $table->dateTime('fecha_firma')->nullable();
                $table->boolean('renuencia')->default(false);

                $table->foreign('id_evaluacion', 'firma_evaluacion_fk')
                    ->references('id_evaluacion')->on('evaluacion')
                    ->onDelete('cascade');
                $table->foreign('id_vinc_firmante', 'firma_vinc_firmante_fk')
                    ->references('id_vinculacion')->on('vinculacion')
                    ->onDelete('set null');

                $table->index(['id_evaluacion', 'tipo_firma'], 'firma_eval_tipo_idx');
            });
        }

        // ------------------------------------------------------------------
        // compromiso / compromiso_meta
        // ------------------------------------------------------------------
        if (!Schema::hasTable('compromiso')) {
            Schema::create('compromiso', function (Blueprint $table) {
                $table->increments('id_compromiso');
                $table->unsignedInteger('id_evaluacion');
                $table->integer('numero_orden')->default(1);
                $table->text('descripcion')->nullable();
                $table->decimal('porcentaje_peso', 5, 2)->default(0);
                $table->decimal('calificacion_sem1', 5, 2)->nullable();
                $table->decimal('calificacion_sem2', 5, 2)->nullable();
                $table->decimal('calificacion_definitiva', 5, 2)->nullable();

                $table->foreign('id_evaluacion', 'compromiso_evaluacion_fk')
                    ->references('id_evaluacion')->on('evaluacion')
                    ->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('compromiso_meta')) {
            Schema::create('compromiso_meta', function (Blueprint $table) {
                $table->unsignedInteger('id_compromiso');
                $table->string('meta', 255);

                $table->primary(['id_compromiso', 'meta']);
                $table->foreign('id_compromiso', 'compromiso_meta_compromiso_fk')
                    ->references('id_compromiso')->on('compromiso')
                    ->onDelete('cascade');
            });
        }

        // ------------------------------------------------------------------
        // evidencia
        // ------------------------------------------------------------------
        if (!Schema::hasTable('evidencia')) {
            Schema::create('evidencia', function (Blueprint $table) {
                $table->increments('id_evidencia');
                $table->unsignedInteger('id_evaluacion');
                $table->unsignedInteger('id_compromiso')->nullable();
                $table->text('descripcion')->nullable();
                $table->string('tipo_evidencia', 30)->default('LINK');
                $table->string('url_o_ubicacion', 500)->nullable();
                $table->dateTime('fecha_inclusion')->nullable();
                $table->unsignedInteger('id_vinc_registra')->nullable();

                $table->foreign('id_evaluacion', 'evidencia_evaluacion_fk')
                    ->references('id_evaluacion')->on('evaluacion')
                    ->onDelete('cascade');
                $table->foreign('id_compromiso', 'evidencia_compromiso_fk')
                    ->references('id_compromiso')->on('compromiso')
                    ->onDelete('cascade');
                $table->foreign('id_vinc_registra', 'evidencia_vinc_registra_fk')
                    ->references('id_vinculacion')->on('vinculacion')
                    ->onDelete('set null');

                $table->index('id_evaluacion', 'evidencia_eval_idx');
            });
        }

        // ------------------------------------------------------------------
        // eje_misional_calificacion
        // ------------------------------------------------------------------
        if (!Schema::hasTable('eje_misional_calificacion')) {
            Schema::create('eje_misional_calificacion', function (Blueprint $table) {
                $table->increments('id_eje_calificacion');
                $table->unsignedInteger('id_evaluacion');
                $table->string('eje', 30);
                $table->decimal('calificacion', 5, 2)->nullable();
                $table->text('observaciones')->nullable();
                $table->unsignedInteger('id_vinc_ingresador')->nullable();

                $table->foreign('id_evaluacion', 'eje_cal_evaluacion_fk')
                    ->references('id_evaluacion')->on('evaluacion')
                    ->onDelete('cascade');
                $table->unique(['id_evaluacion', 'eje'], 'uq_eje_cal_eval_eje');
            });
        }

        // ------------------------------------------------------------------
        // competencia_evaluada
        // ------------------------------------------------------------------
        if (!Schema::hasTable('competencia_evaluada')) {
            Schema::create('competencia_evaluada', function (Blueprint $table) {
                $table->increments('id_comp_eval');
                $table->unsignedInteger('id_evaluacion');
                $table->string('nombre_competencia', 150)->nullable();
                $table->enum('tipo', ['COMUN', 'NIVEL_JERARQUICO'])->nullable();
                $table->decimal('calificacion_sem1', 5, 2)->nullable();
                $table->decimal('calificacion_sem2', 5, 2)->nullable();
                $table->decimal('calificacion_definitiva', 5, 2)->nullable();

                $table->foreign('id_evaluacion', 'comp_eval_evaluacion_fk')
                    ->references('id_evaluacion')->on('evaluacion')
                    ->onDelete('cascade');
                $table->index('id_evaluacion', 'comp_eval_eval_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('competencia_evaluada');
        Schema::dropIfExists('eje_misional_calificacion');
        Schema::dropIfExists('evidencia');
        Schema::dropIfExists('compromiso_meta');
        Schema::dropIfExists('compromiso');
        Schema::dropIfExists('firma');
        Schema::dropIfExists('evaluacion');
        Schema::dropIfExists('periodo');
        Schema::dropIfExists('vinculacion');
        Schema::dropIfExists('funcionario');
        Schema::dropIfExists('usuario');
    }
};
