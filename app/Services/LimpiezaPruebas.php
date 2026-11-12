<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Limpieza de los datos de prueba: vacía periodos, evaluaciones, delegaciones,
 * traslados, recursos, impedimentos y todo lo que cuelga de ellos, y deja la
 * asignación de evaluadores como en la planta (cada persona con su jefe inmediato).
 * Conserva usuarios, funcionarios, vinculaciones, cargos, dependencias, plazas,
 * ocupaciones, catálogo de competencias, ponderaciones y configuración.
 *
 * Solo toca las tablas listadas aquí: una tabla que no esté clasificada se
 * reporta y no se modifica.
 */
class LimpiezaPruebas
{
    /** Datos de movimiento o derivados: se vacían por completo. */
    public const A_VACIAR = [
        'bitacora_modificacion_compromiso', 'compromiso_observacion', 'compromiso_meta', 'compromiso',
        'competencia_evaluada', 'eje_misional_calificacion', 'evaluacion_eje', 'evidencia', 'firma',
        'tema_capacitacion', 'plan_mejoramiento', 'recurso_evidencia', 'recurso', 'impedimento_recusacion',
        'solicitud_modificacion_compromiso', 'concertacion_extratiempo', 'notificacion_documento',
        'evaluacion_evento', 'notificacion', 'evaluacion', 'delegacion', 'traslado', 'traslado_lote',
        'periodo_parcial', 'periodo_auditoria', 'periodo', 'puesto_discrepancia',
    ];

    /** Datos maestros: no se tocan (evaluador_asignacion se restablece aparte). */
    public const A_CONSERVAR = [
        'usuario', 'funcionario', 'vinculacion', 'cargo', 'dependencia', 'puesto', 'puesto_ocupacion',
        'evaluador_asignacion', 'competencia_catalogo', 'ponderacion', 'configuracion_sistema',
        'modelo_puestos_cambio', 'carga_planta', 'carga_planta_pendiente', 'nota_importada_lote', 'nota_semestre_importada', 'migrations',
        'cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens',
    ];

    /** @return array<string, int> tabla => registros, solo de las que existen */
    private function conteo(array $tablas): array
    {
        $resultado = [];
        foreach ($tablas as $tabla) {
            if (Schema::hasTable($tabla)) {
                $resultado[$tabla] = DB::table($tabla)->count();
            }
        }

        return $resultado;
    }

    /** Qué haría la limpieza, sin cambiar nada. */
    public function plan(): array
    {
        $existentes = collect(DB::select('select table_name as n from information_schema.tables where table_schema = database() and table_type = "BASE TABLE"'))
            ->map(fn ($t) => $t->n ?? $t->N)->all();
        $clasificadas = array_merge(self::A_VACIAR, self::A_CONSERVAR);

        return [
            'vaciar' => $this->conteo(self::A_VACIAR),
            'conservar' => $this->conteo(array_diff(self::A_CONSERVAR, ['migrations', 'cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens'])),
            'sin_clasificar' => array_values(array_diff($existentes, $clasificadas)),
            'asignaciones_hoy' => Schema::hasTable('evaluador_asignacion') ? DB::table('evaluador_asignacion')->count() : 0,
            'asignaciones_base' => $this->consultaAsignacionesBase()->count(),
            'inactivas_no_vacante' => DB::table('vinculacion as v')->join('funcionario as f', 'f.id_funcionario', '=', 'v.id_funcionario')
                ->where('v.activa', 0)->where('v.es_vacante', 0)
                ->get(['f.numero_doc', 'f.nombres', 'f.apellidos', 'v.cargo', 'v.area'])->map(fn ($r) => (array) $r)->all(),
        ];
    }

    /** Evaluador de cada persona activa según la planta: su jefe inmediato. */
    private function consultaAsignacionesBase()
    {
        return DB::table('vinculacion as v')
            ->join('vinculacion as j', 'j.id_vinculacion', '=', 'v.id_vinc_jefe')
            ->where('v.activa', 1)->where('j.activa', 1)
            ->select('v.id_vinc_jefe as id_vinc_evaluador', 'v.id_vinculacion as id_vinc_evaluado');
    }

    /**
     * Ejecuta la limpieza en una transacción (todo o nada).
     *
     * @return array{vaciadas: array<string,int>, asignaciones: int|null}
     */
    public function ejecutar(bool $restablecerEvaluadores = true, bool $reiniciarIds = true): array
    {
        $vaciadas = $this->conteo(self::A_VACIAR);
        $asignaciones = null;

        DB::transaction(function () use (&$asignaciones, $restablecerEvaluadores, $vaciadas) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            try {
                foreach (array_keys($vaciadas) as $tabla) {
                    DB::table($tabla)->delete();
                }

                if ($restablecerEvaluadores && Schema::hasTable('evaluador_asignacion')) {
                    DB::table('evaluador_asignacion')->delete();
                    $filas = $this->consultaAsignacionesBase()->get()->map(fn ($f) => [
                        'id_vinc_evaluador' => $f->id_vinc_evaluador, 'id_vinc_evaluado' => $f->id_vinc_evaluado, 'fecha_asignacion' => now(),
                    ])->all();
                    foreach (array_chunk($filas, 200) as $bloque) {
                        DB::table('evaluador_asignacion')->insert($bloque);
                    }
                    $asignaciones = count($filas);
                }
            } finally {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        });

        // Los ids vuelven a empezar en 1 (ALTER TABLE confirma la transacción, por eso va después).
        if ($reiniciarIds) {
            foreach (array_keys($vaciadas) as $tabla) {
                DB::statement("ALTER TABLE `{$tabla}` AUTO_INCREMENT = 1");
            }
        }

        return ['vaciadas' => $vaciadas, 'asignaciones' => $asignaciones];
    }
}
