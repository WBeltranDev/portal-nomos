<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Corrección P3/III del plan de pruebas 29-10-2026: la decisión de un recurso
 * (reposición o apelación) lleva el enlace del oficio con la respuesta y,
 * opcionalmente, enlaces de soporte (acta del CEDP, otros). Los soportes se
 * guardan en recurso_evidencia con origen DECISION para distinguirlos de las
 * evidencias que adjunta el evaluado al radicar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('recurso', 'url_oficio')) {
            Schema::table('recurso', function (Blueprint $table) {
                $table->string('url_oficio', 1000)->nullable()->after('motivacion');
            });
        }

        if (! Schema::hasColumn('recurso_evidencia', 'origen')) {
            Schema::table('recurso_evidencia', function (Blueprint $table) {
                $table->enum('origen', ['RADICACION', 'DECISION'])->default('RADICACION')->after('url');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('recurso_evidencia', 'origen')) {
            Schema::table('recurso_evidencia', fn (Blueprint $table) => $table->dropColumn('origen'));
        }
        if (Schema::hasColumn('recurso', 'url_oficio')) {
            Schema::table('recurso', fn (Blueprint $table) => $table->dropColumn('url_oficio'));
        }
    }
};
