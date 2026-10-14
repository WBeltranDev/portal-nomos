<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('notificacion_documento')) {
            return;
        }

        Schema::table('notificacion_documento', function (Blueprint $table) {
            if (!Schema::hasColumn('notificacion_documento', 'tipo_documento')) {
                $table->string('tipo_documento', 20)->default('SEMESTRAL')->after('id_evaluacion');
            }
        });

        // 1. Drop foreign key first so MySQL allows modifying the unique index
        $fkExists = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notificacion_documento' AND CONSTRAINT_NAME = 'notificacion_documento_id_evaluacion_foreign'");
        if (!empty($fkExists)) {
            Schema::table('notificacion_documento', function (Blueprint $table) {
                $table->dropForeign('notificacion_documento_id_evaluacion_foreign');
            });
        }

        // 2. Drop the old unique index on id_evaluacion
        $indexes = DB::select("SHOW INDEX FROM notificacion_documento WHERE Key_name = 'notificacion_documento_id_evaluacion_unique'");
        if (!empty($indexes)) {
            Schema::table('notificacion_documento', function (Blueprint $table) {
                $table->dropUnique('notificacion_documento_id_evaluacion_unique');
            });
        }

        // 3. Add composite unique index on (id_evaluacion, tipo_documento)
        $compositeIndexes = DB::select("SHOW INDEX FROM notificacion_documento WHERE Key_name = 'notif_doc_eval_tipo_unique'");
        if (empty($compositeIndexes)) {
            Schema::table('notificacion_documento', function (Blueprint $table) {
                $table->unique(['id_evaluacion', 'tipo_documento'], 'notif_doc_eval_tipo_unique');
            });
        }

        // 4. Re-add foreign key
        Schema::table('notificacion_documento', function (Blueprint $table) {
            $table->foreign('id_evaluacion', 'notificacion_documento_id_evaluacion_foreign')
                ->references('id_evaluacion')
                ->on('evaluacion')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('notificacion_documento')) {
            return;
        }

        // Drop foreign key
        $fkExists = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notificacion_documento' AND CONSTRAINT_NAME = 'notificacion_documento_id_evaluacion_foreign'");
        if (!empty($fkExists)) {
            Schema::table('notificacion_documento', function (Blueprint $table) {
                $table->dropForeign('notificacion_documento_id_evaluacion_foreign');
            });
        }

        $compositeIndexes = DB::select("SHOW INDEX FROM notificacion_documento WHERE Key_name = 'notif_doc_eval_tipo_unique'");
        if (!empty($compositeIndexes)) {
            Schema::table('notificacion_documento', function (Blueprint $table) {
                $table->dropUnique('notif_doc_eval_tipo_unique');
            });
        }

        Schema::table('notificacion_documento', function (Blueprint $table) {
            if (Schema::hasColumn('notificacion_documento', 'tipo_documento')) {
                $table->dropColumn('tipo_documento');
            }
            $table->unique('id_evaluacion', 'notificacion_documento_id_evaluacion_unique');
            $table->foreign('id_evaluacion', 'notificacion_documento_id_evaluacion_foreign')
                ->references('id_evaluacion')
                ->on('evaluacion')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }
};
