<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Clear existing seed data if any
        if (\Illuminate\Support\Facades\Schema::hasTable('compromiso_observacion')) {
            \Illuminate\Support\Facades\DB::table('compromiso_observacion')->truncate();
        }
        \Illuminate\Support\Facades\DB::table('firma')->truncate();
        \Illuminate\Support\Facades\DB::table('compromiso_meta')->truncate();
        \Illuminate\Support\Facades\DB::table('compromiso')->truncate();
        \Illuminate\Support\Facades\DB::table('evaluacion')->truncate();
        \Illuminate\Support\Facades\DB::table('vinculacion')->truncate();
        \Illuminate\Support\Facades\DB::table('funcionario')->truncate();
        \Illuminate\Support\Facades\DB::table('periodo')->truncate();
        \Illuminate\Support\Facades\DB::table('usuario')->where('id_usuario', '>', 1)->delete();

        // 2. Insert main Admin if not exists (handled by SQL dump, but ensure here)
        $adminEmail = 'wiliverbeltran.es@unitropico.edu.co';
        $adminId = \Illuminate\Support\Facades\DB::table('usuario')->where('username', $adminEmail)->value('id_usuario');
        if (!$adminId) {
            $adminId = \Illuminate\Support\Facades\DB::table('usuario')->insertGetId([
                'username' => $adminEmail,
                'password' => \Illuminate\Support\Facades\Hash::make('123456789'),
                'rol' => 'ADMINISTRADOR',
                'activo' => 1,
            ]);
        }

        // 3. Create Evaluador User
        $evaluadorEmail = 'evaluador@unitropico.edu.co';
        $evaluadorId = \Illuminate\Support\Facades\DB::table('usuario')->insertGetId([
            'username' => $evaluadorEmail,
            'password' => \Illuminate\Support\Facades\Hash::make('123456789'),
            'rol' => 'EVALUADOR',
            'activo' => 1,
        ]);

        $evaluadorFuncId = \Illuminate\Support\Facades\DB::table('funcionario')->insertGetId([
            'id_usuario' => $evaluadorId,
            'tipo_documento' => 'CEDULA_CIUDADANIA',
            'numero_doc' => '80111222',
            'nombres' => 'Juan',
            'apellidos' => 'Pérez',
            'correo_cargo' => $evaluadorEmail,
        ]);

        $evaluadorVincId = \Illuminate\Support\Facades\DB::table('vinculacion')->insertGetId([
            'id_funcionario' => $evaluadorFuncId,
            'cargo' => 'Director de Sistemas',
            'codigo_cargo' => 201,
            'grado_cargo' => 2,
            'nivel_jerarquico' => 'DIRECTIVO',
            'area' => 'Oficina de Sistemas',
            'tipo_vinculacion' => 'PROVISIONALIDAD',
            'sistema_evaluacion' => 'RENDIMIENTO_LABORAL',
            'es_evaluador' => 1,
            'aplica_eje_misional' => 0,
            'fecha_ingreso' => '2020-02-01',
            'activa' => 1,
        ]);

        // 4. Create Evaluado User
        $evaluadoEmail = 'evaluado@unitropico.edu.co';
        $evaluadoId = \Illuminate\Support\Facades\DB::table('usuario')->insertGetId([
            'username' => $evaluadoEmail,
            'password' => \Illuminate\Support\Facades\Hash::make('123456789'),
            'rol' => 'EVALUADO',
            'activo' => 1,
        ]);

        $evaluadoFuncId = \Illuminate\Support\Facades\DB::table('funcionario')->insertGetId([
            'id_usuario' => $evaluadoId,
            'tipo_documento' => 'CEDULA_CIUDADANIA',
            'numero_doc' => '1015333444',
            'nombres' => 'María',
            'apellidos' => 'Gómez',
            'correo_cargo' => $evaluadoEmail,
        ]);

        $evaluadoVincId = \Illuminate\Support\Facades\DB::table('vinculacion')->insertGetId([
            'id_funcionario' => $evaluadoFuncId,
            'cargo' => 'Profesional de Soporte',
            'codigo_cargo' => 301,
            'grado_cargo' => 1,
            'nivel_jerarquico' => 'PROFESIONAL',
            'area' => 'Oficina de Sistemas',
            'tipo_vinculacion' => 'PROVISIONALIDAD',
            'sistema_evaluacion' => 'RENDIMIENTO_LABORAL',
            'es_evaluador' => 0,
            'aplica_eje_misional' => 1,
            'fecha_ingreso' => '2022-06-15',
            'activa' => 1,
        ]);

        // 5. Open a Period
        $periodoId = \Illuminate\Support\Facades\DB::table('periodo')->insertGetId([
            'id_usuario_apertura' => $adminId,
            'sistema' => 'RENDIMIENTO_LABORAL',
            'anio' => 2026,
            'semestre' => 1,
            'fecha_inicio' => '2026-01-15',
            'fecha_fin' => '2026-07-15',
            'estado' => 'ABIERTO',
        ]);

        // 6. Create active Evaluation assignment
        \Illuminate\Support\Facades\DB::table('evaluacion')->insert([
            'id_periodo' => $periodoId,
            'id_vinc_evaluado' => $evaluadoVincId,
            'id_vinc_evaluador' => $evaluadorVincId,
            'tipo_evaluacion' => 'SEMESTRE_1',
            'fase_actual' => 1,
            'concertacion_firmada' => 0,
            'estado' => 'EN_PROCESO',
        ]);
    }
}
