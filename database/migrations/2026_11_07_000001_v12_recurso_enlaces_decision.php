<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('recurso')) {
            Schema::table('recurso', function (Blueprint $table) {
                if (! Schema::hasColumn('recurso', 'url_oficio_decision')) {
                    $table->string('url_oficio_decision', 1000)->nullable()->after('motivacion');
                }
                if (! Schema::hasColumn('recurso', 'url_evidencia_decision')) {
                    $table->string('url_evidencia_decision', 1000)->nullable()->after('url_oficio_decision');
                }
                if (! Schema::hasColumn('recurso', 'nota_antes_recurso')) {
                    $table->decimal('nota_antes_recurso', 4, 2)->nullable()->after('url_evidencia_decision');
                }
                if (! Schema::hasColumn('recurso', 'categoria_antes_recurso')) {
                    $table->string('categoria_antes_recurso', 50)->nullable()->after('nota_antes_recurso');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('recurso')) {
            Schema::table('recurso', function (Blueprint $table) {
                if (Schema::hasColumn('recurso', 'categoria_antes_recurso')) {
                    $table->dropColumn('categoria_antes_recurso');
                }
                if (Schema::hasColumn('recurso', 'nota_antes_recurso')) {
                    $table->dropColumn('nota_antes_recurso');
                }
                if (Schema::hasColumn('recurso', 'url_evidencia_decision')) {
                    $table->dropColumn('url_evidencia_decision');
                }
                if (Schema::hasColumn('recurso', 'url_oficio_decision')) {
                    $table->dropColumn('url_oficio_decision');
                }
            });
        }
    }
};
