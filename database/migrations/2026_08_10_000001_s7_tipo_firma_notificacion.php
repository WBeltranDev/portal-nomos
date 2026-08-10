<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * S7 - Corrección del ENUM `firma.tipo_firma`.
 *
 * El motor de calificación registra la firma/renuencia de la notificación de la
 * nota con el valor `NOTIFICACION_EVALUADO`, pero el ENUM de la tabla `firma`
 * (creada en el dump base de la BD institucional) no lo incluía, provocando
 * SQLSTATE 1265 "Data truncated" (500) al firmar la notificación.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('firma')) {
            DB::statement(
                "ALTER TABLE firma MODIFY COLUMN tipo_firma ENUM('CONCERTACION_EVALUADO','CONCERTACION_EVALUADOR','SEMESTRAL_EVALUADO','SEMESTRAL_EVALUADOR','DEFINITIVA_EVALUADO','DEFINITIVA_EVALUADOR','NOTIFICACION_EVALUADO') NOT NULL"
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('firma')) {
            DB::statement(
                "ALTER TABLE firma MODIFY COLUMN tipo_firma ENUM('CONCERTACION_EVALUADO','CONCERTACION_EVALUADOR','SEMESTRAL_EVALUADO','SEMESTRAL_EVALUADOR','DEFINITIVA_EVALUADO','DEFINITIVA_EVALUADOR') NOT NULL"
            );
        }
    }
};
