<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 6 (v1.5) — Ingreso por número de documento.
     * Agrega el campo documento_login en la tabla usuario, lo puebla desde
     * funcionario.numero_doc y asigna los documentos de administrador:
     * th1 -> 11111, th2 -> 11112, th3 -> 11113.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('usuario', 'documento_login')) {
            Schema::table('usuario', function (Blueprint $table) {
                $table->string('documento_login', 20)->nullable()->unique()->after('username');
            });
        }

        // Población automática desde funcionario.numero_doc para funcionarios existentes
        DB::statement("
            UPDATE usuario u
            JOIN funcionario f ON f.id_usuario = u.id_usuario
            SET u.documento_login = TRIM(f.numero_doc)
            WHERE (u.documento_login IS NULL OR u.documento_login = '')
              AND f.numero_doc IS NOT NULL
              AND TRIM(f.numero_doc) != ''
        ");

        // Asignación explícita para las cuentas de administración de Talento Humano
        $admins = DB::table('usuario')
            ->where('rol', 'ADMINISTRADOR')
            ->orderBy('id_usuario')
            ->get();

        foreach ($admins as $idx => $admin) {
            $username = strtolower((string) $admin->username);
            $doc = match (true) {
                str_contains($username, 'th1') => '11111',
                str_contains($username, 'th2') => '11112',
                str_contains($username, 'th3') => '11113',
                default => '1111' . ($idx + 1),
            };

            DB::table('usuario')
                ->where('id_usuario', $admin->id_usuario)
                ->update(['documento_login' => $doc]);
        }
    }

    /**
     * Reversión de la migración.
     */
    public function down(): void
    {
        if (Schema::hasColumn('usuario', 'documento_login')) {
            Schema::table('usuario', function (Blueprint $table) {
                $table->dropUnique(['documento_login']);
                $table->dropColumn('documento_login');
            });
        }
    }
};
