<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Datos ficticios mínimos para las pruebas de SERAG. Se usan dentro de una
 * transacción que la prueba revierte al terminar.
 */
trait CreaDatosSerag
{
    protected string $sufijoDatos = '';
    protected int $consecutivoDatos = 0;

    protected function iniciarDatosSerag(): void
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
        DB::beginTransaction();
        $this->sufijoDatos = 'T' . random_int(10000, 99999);
        $this->consecutivoDatos = 0;
    }

    protected function finalizarDatosSerag(): void
    {
        DB::rollBack();
    }

    protected function crearUsuarioPrueba(string $rol = 'EVALUADO'): int
    {
        return DB::table('usuario')->insertGetId([
            'username' => 'tmp.' . strtolower($this->sufijoDatos) . '.' . (++$this->consecutivoDatos) . '@prueba.local',
            'password' => bcrypt('temporal'),
            'rol' => $rol,
            'activo' => 1,
        ]);
    }

    /** Devuelve [id_funcionario, id_usuario]. */
    protected function crearFuncionarioPrueba(string $nombres, string $apellidos, string $rol = 'EVALUADO'): array
    {
        $idUsuario = $this->crearUsuarioPrueba($rol);
        $numDoc = (string) ($this->sufijoDatos . (++$this->consecutivoDatos));
        $idFuncionario = DB::table('funcionario')->insertGetId([
            'id_usuario' => $idUsuario,
            'tipo_documento' => 'CEDULA_CIUDADANIA',
            'numero_doc' => $numDoc,
            'nombres' => $nombres,
            'apellidos' => $apellidos,
        ]);

        if (Schema::hasColumn('usuario', 'documento_login')) {
            DB::table('usuario')->where('id_usuario', $idUsuario)->update(['documento_login' => $numDoc]);
        }

        return [$idFuncionario, $idUsuario];
    }

    protected function crearVinculacionPrueba(int $idFuncionario, bool $esEvaluador = false, string $sistema = 'RENDIMIENTO_LABORAL', ?int $idJefe = null): int
    {
        return DB::table('vinculacion')->insertGetId([
            'id_funcionario' => $idFuncionario,
            'cargo' => 'PROFESIONAL UNIVERSITARIO',
            'codigo_cargo' => 219,
            'grado_cargo' => 1,
            'nivel_jerarquico' => 'PROFESIONAL',
            'area' => 'AREA DE PRUEBA',
            'tipo_vinculacion' => 'PROVISIONALIDAD',
            'sistema_evaluacion' => $sistema,
            'es_evaluador' => $esEvaluador ? 1 : 0,
            'id_vinc_jefe' => $idJefe,
            'fecha_ingreso' => '2020-01-01',
            'activa' => 1,
        ]);
    }

    protected function crearPeriodoPrueba(string $sistema, int $anio, int $semestre, string $inicio, string $fin, string $estado = 'ABIERTO'): int
    {
        return DB::table('periodo')->insertGetId([
            'id_usuario_apertura' => $this->crearUsuarioPrueba('ADMINISTRADOR'),
            'sistema' => $sistema,
            'anio' => $anio,
            'semestre' => $semestre,
            'fecha_inicio' => $inicio,
            'fecha_fin' => $fin,
            'estado' => $estado,
        ]);
    }

    protected function crearEvaluacionPrueba(int $idPeriodo, int $idVincEvaluado, int $idVincEvaluador, array $datos = []): int
    {
        return DB::table('evaluacion')->insertGetId(array_merge([
            'id_periodo' => $idPeriodo,
            'id_vinc_evaluado' => $idVincEvaluado,
            'id_vinc_evaluador' => $idVincEvaluador,
            'tipo_evaluacion' => 'SEMESTRE_1',
            'fase_actual' => 1,
            'concertacion_firmada' => 0,
            'estado' => 'EN_PROCESO',
        ], $datos));
    }

    protected function asignarEvaluadorPrueba(int $idVincEvaluador, int $idVincEvaluado): void
    {
        DB::table('evaluador_asignacion')->insert([
            'id_vinc_evaluador' => $idVincEvaluador,
            'id_vinc_evaluado' => $idVincEvaluado,
            'fecha_asignacion' => now(),
        ]);
    }

    protected function sesionComo(string $rol, ?int $idFuncionario, ?int $idUsuario = null): void
    {
        session(['usuario_autenticado' => [
            'id_usuario' => $idUsuario ?? $this->crearUsuarioPrueba($rol === 'admin' ? 'ADMINISTRADOR' : 'EVALUADO'),
            'id_funcionario' => $idFuncionario,
            'nombres' => 'Tmp',
            'apellidos' => 'Prueba',
            'correo' => 'tmp@prueba.local',
            'rol_activo' => $rol,
            'roles' => [$rol],
        ]]);
    }
}
