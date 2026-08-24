<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Casos controlados de aceptación para recorrer los flujos de semanas 1 a 9.
 *
 * Todos se identifican con el prefijo PRUEBA COBERTURA S1-S9 y usan funcionarios
 * distintos de los casos demostrativos principales. No sustituye información real.
 */
class PruebasCoberturaSemanas1a9Seeder extends Seeder
{
    private const PREFIJO = 'PRUEBA COBERTURA S1-S9:';

    public function run(): void
    {
        if (DB::table('evaluacion')->where('referencia', 'like', self::PREFIJO . '%')->exists()) {
            $this->command?->info('Las pruebas de cobertura S1-S9 ya están cargadas.');
            return;
        }

        DB::transaction(function (): void {
            $periodoRl2 = (int) DB::table('periodo')->where('id_periodo', 10)->value('id_periodo');
            $periodoAg2 = (int) DB::table('periodo')->where('id_periodo', 11)->value('id_periodo');
            $periodoRl1 = (int) DB::table('periodo')->where('id_periodo', 8)->value('id_periodo');
            $admin = (int) DB::table('vinculacion')->where('id_vinculacion', 93)->value('id_vinculacion');
            $evaluador = (int) DB::table('vinculacion')->where('id_vinculacion', 7)->value('id_vinculacion');
            $delegado = (int) DB::table('vinculacion')->where('id_vinculacion', 36)->value('id_vinculacion');

            if (!$periodoRl2 || !$periodoAg2 || !$periodoRl1 || !$admin || !$evaluador || !$delegado) {
                throw new \RuntimeException('Faltan los periodos o vinculaciones requeridos para las pruebas.');
            }

            // Semanas 3 a 7: cuatro decisiones de recursos con evaluados distintos.
            $repoAprobada = $this->evaluacion($periodoAg2, 4, $evaluador, 'SEMESTRE_2', 'EN_PROCESO', 'REPOSICION APROBADA', 62);
            $repoNegada = $this->evaluacion($periodoAg2, 5, $evaluador, 'SEMESTRE_2', 'CALIFICADA', 'REPOSICION NEGADA', 63);
            $apelAprobada = $this->evaluacion($periodoAg2, 11, $evaluador, 'SEMESTRE_2', 'EN_PROCESO', 'APELACION APROBADA', 60);
            $apelNegada = $this->evaluacion($periodoAg2, 14, $evaluador, 'SEMESTRE_2', 'CALIFICADA', 'APELACION NEGADA', 64);

            foreach ([[$repoAprobada, 4], [$repoNegada, 5], [$apelAprobada, 11], [$apelNegada, 14]] as [$id, $evaluado]) {
                $this->firmasCompletas($id, $evaluado, $evaluador);
                $this->compromisoYEvidencia($id, $evaluado, 62);
            }
            $this->recurso($repoAprobada, 'REPOSICION', $evaluador, 'APROBADO', 'Luz Mary Alegría', 'REP', 'Se reabre para ajustar la calificación con el soporte aportado.');
            $this->recurso($repoNegada, 'REPOSICION', $evaluador, 'NEGADO', 'Yeimy Katerine Avella', 'REP', 'La evidencia fue revisada y no modifica la calificación final.');
            $this->recurso($apelAprobada, 'APELACION', $admin, 'APROBADO', 'Daniela Smith Bermudez', 'APL', 'Talento Humano aprueba y reabre la evaluación para nuevo cálculo.');
            $this->recurso($apelNegada, 'APELACION', $admin, 'NEGADO', 'Ligia Joana Bolaños', 'APL', 'La apelación fue estudiada y se conserva la decisión inicial.');

            // Semana 7: renuencia documentada que también habilita el recurso para nota no satisfactoria.
            $renuencia = $this->evaluacion($periodoRl2, 17, $evaluador, 'SEMESTRE_2', 'CALIFICADA', 'RENUENCIA DE NOTIFICACION', 58);
            $this->firmasCompletas($renuencia, 17, $evaluador, true);
            $this->compromisoYEvidencia($renuencia, 17, 58);
            $this->recurso($renuencia, 'REPOSICION', $evaluador, 'PENDIENTE', 'Never Caceres', 'REP', 'Radicado pendiente después de la renuencia documentada.');

            // Semana 7: plan ya concertado y congelado después de ambas firmas.
            $planCongelado = $this->evaluacion($periodoRl2, 20, $evaluador, 'SEMESTRE_2', 'CALIFICADA', 'PLAN CONGELADO', 61);
            $this->firmasCompletas($planCongelado, 20, $evaluador);
            $this->compromisoYEvidencia($planCongelado, 20, 61);
            $plan = DB::table('plan_mejoramiento')->insertGetId([
                'id_evaluacion' => $planCongelado,
                'descripcion_temas' => 'PRUEBA COBERTURA: seguimiento a indicadores y comunicación de resultados.',
                'firmado_evaluado' => 1,
                'firmado_evaluador' => 1,
                'fecha_firma_evaluado' => now(),
                'fecha_firma_evaluador' => now(),
                'estado' => 'CONCERTADO',
            ]);
            DB::table('tema_capacitacion')->insert([
                'id_plan' => $plan,
                'tema' => 'Seguimiento de indicadores',
                'descripcion' => 'Caso controlado para verificar que el plan queda de solo lectura.',
            ]);

            // Semana 6/8: una parcial válida (90 días) no consolida semestre; dos sí se promedian.
            $unaParcial = $this->evaluacion($periodoRl2, 21, $evaluador, 'PARCIAL', 'CALIFICADA', 'PARCIAL UNICA 90 DIAS', 75, true, 90);
            $this->firmasCompletas($unaParcial, 21, $evaluador);
            $this->compromisoYEvidencia($unaParcial, 21, 75);
            DB::table('periodo_parcial')->insert([
                'id_periodo' => $periodoRl2, 'id_vinc_funcionario' => 21,
                'fecha_inicio' => '2026-08-02', 'fecha_fin' => '2026-10-30',
                'referencia' => self::PREFIJO . ' parcial única válida (90 días)', 'estado' => 'ABIERTO',
            ]);

            foreach ([['2026-01-01', '2026-03-31', 72], ['2026-04-01', '2026-06-30', 88]] as $orden => [$inicio, $fin, $nota]) {
                $id = $this->evaluacion($periodoRl1, 22, $evaluador, 'PARCIAL', 'CALIFICADA', 'PARCIALES PROMEDIABLES ' . ($orden + 1), $nota, true, $orden === 0 ? 90 : 91);
                $this->firmasCompletas($id, 22, $evaluador);
                $this->compromisoYEvidencia($id, 22, $nota);
                DB::table('periodo_parcial')->insert([
                    'id_periodo' => $periodoRl1, 'id_vinc_funcionario' => 22,
                    'fecha_inicio' => $inicio, 'fecha_fin' => $fin,
                    'referencia' => self::PREFIJO . ' parcial promediable ' . ($orden + 1), 'estado' => 'CERRADO',
                ]);
            }

            // Semana 6 y 8: traslado bloqueado para consulta y delegación activa visible en la evaluación.
            $traslado = $this->evaluacion($periodoAg2, 23, $evaluador, 'PARCIAL', 'CALIFICADA', 'TRASLADO BLOQUEADO', 70, true, 90, true);
            $this->firmasCompletas($traslado, 23, $evaluador);
            $this->compromisoYEvidencia($traslado, 23, 70);
            DB::table('traslado')->insert([
                'id_vinc_funcionario' => 23, 'id_vinc_evaluador_origen' => $evaluador, 'id_vinc_evaluador_nuevo' => $delegado,
                'area_origen' => 'VICERRECTORIA DE PROYECCION SOCIAL', 'cargo_origen' => 'AUXILIAR ADMINISTRATIVO',
                'area_nuevo' => 'VICERRECTORIA DE INVESTIGACION', 'cargo_nuevo' => 'AUXILIAR ADMINISTRATIVO',
                'fecha_traslado' => '2026-10-31', 'dias_laborados' => 90, 'id_evaluacion_parcial' => $traslado,
                'resolucion' => 'PRUEBA-COB-S6-TR-001', 'motivo' => 'Caso controlado de traslado.',
            ]);
            DB::table('delegacion')->insert([
                'id_vinc_delegante' => $evaluador, 'id_vinc_delegado' => $delegado,
                'motivo' => 'PRUEBA COBERTURA: ausencia temporal del titular.',
                'fecha_inicio' => '2026-08-20', 'fecha_fin' => '2026-09-30', 'estado' => 'ACTIVA',
                'detalle_transferencia' => json_encode(['evaluados_transferidos' => [4, 5, 11, 14, 17, 20, 21, 23]]),
                'created_at' => now(), 'updated_at' => now(),
            ]);

            foreach ([4, 5, 11, 14, 17, 20, 21, 22, 23] as $evaluado) {
                DB::table('evaluador_asignacion')->updateOrInsert(
                    ['id_vinc_evaluador' => $evaluador, 'id_vinc_evaluado' => $evaluado],
                    ['fecha_asignacion' => now()]
                );
            }
        });

        $this->command?->info('Pruebas de cobertura S1-S9 cargadas.');
    }

    private function evaluacion(int $periodo, int $evaluado, int $evaluador, string $tipo, string $estado, string $caso, float $nota, bool $parcial = false, ?int $dias = null, bool $traslado = false): int
    {
        return DB::table('evaluacion')->insertGetId([
            'id_periodo' => $periodo, 'id_vinc_evaluado' => $evaluado, 'id_vinc_evaluador' => $evaluador,
            'tipo_evaluacion' => $tipo, 'fase_actual' => 5, 'concertacion_firmada' => 1,
            'estado' => $estado, 'calificacion_final' => $nota, 'calificacion_parcial' => $parcial ? $nota : null,
            'nota_compromisos' => round($nota * .8, 2), 'nota_competencias' => round($nota * .2, 2),
            'categoria_final' => 'NO_SATISFACTORIO', 'es_parcial' => $parcial ? 1 : 0,
            'es_traslado' => $traslado ? 1 : 0, 'dias_laborados' => $dias,
            'referencia' => self::PREFIJO . ' ' . $caso,
        ]);
    }

    private function firmasCompletas(int $evaluacion, int $evaluado, int $evaluador, bool $renuencia = false): void
    {
        foreach ([['CONCERTACION_EVALUADO', $evaluado, 0], ['CONCERTACION_EVALUADOR', $evaluador, 0], ['NOTIFICACION_EVALUADO', $evaluado, $renuencia ? 1 : 0]] as [$tipo, $firmante, $esRenuencia]) {
            $idFirma = DB::table('firma')->insertGetId([
                'id_evaluacion' => $evaluacion, 'tipo_firma' => $tipo, 'id_vinc_firmante' => $firmante,
                'fecha_firma' => now(), 'renuencia' => $esRenuencia,
                'testigo_nombre' => $esRenuencia ? 'Testigo Cobertura' : null,
                'testigo_documento' => $esRenuencia ? 'Profesional universitario' : null,
                'observacion_renuencia' => $esRenuencia ? 'PRUEBA COBERTURA: renuencia registrada con soporte.' : null,
            ]);
            if ($esRenuencia) {
                DB::table('testigo_renuencia')->insert(['id_firma' => $idFirma, 'nombre_testigo' => 'Testigo Cobertura', 'cargo_testigo' => 'Profesional universitario']);
                DB::table('renuencia_evidencia')->insert(['id_firma' => $idFirma, 'descripcion' => 'Acta de renuencia de prueba', 'url' => 'https://unitropico.edu.co/acta-prueba-renuencia.pdf']);
            }
        }
    }

    private function compromisoYEvidencia(int $evaluacion, int $evaluado, float $nota): void
    {
        $compromiso = DB::table('compromiso')->insertGetId([
            'id_evaluacion' => $evaluacion, 'numero_orden' => 1,
            'descripcion' => 'PRUEBA COBERTURA: compromiso verificable del caso.', 'porcentaje_peso' => 100,
            'calificacion_sem2' => $nota, 'calificacion_definitiva' => $nota,
        ]);
        DB::table('compromiso_meta')->insert(['id_compromiso' => $compromiso, 'meta' => 'Entregar soporte verificable del caso de prueba.']);
        DB::table('evidencia')->insert([
            'id_evaluacion' => $evaluacion, 'id_compromiso' => $compromiso, 'componente' => 'B',
            'descripcion' => 'Evidencia controlada de cobertura.', 'tipo_evidencia' => 'LINK',
            'url_o_ubicacion' => 'https://unitropico.edu.co/pruebas-cobertura', 'fecha_inclusion' => now(),
            'id_vinc_registra' => $evaluado, 'estado_aprobacion' => 'APROBADA',
        ]);
    }

    private function recurso(int $evaluacion, string $tipo, int $receptor, string $decision, string $nombre, string $prefijo, string $decisionTexto): void
    {
        $radicado = $prefijo . '-COB-2026-' . str_pad((string) $evaluacion, 4, '0', STR_PAD_LEFT);
        $recurso = DB::table('recurso')->insertGetId([
            'id_evaluacion' => $evaluacion, 'tipo_recurso' => $tipo, 'id_vinc_receptor' => $receptor,
            'numero_radicado' => $radicado, 'numero_folios' => 2, 'fecha_recurso' => '2026-08-24',
            'decision' => $decision, 'motivacion' => 'Solicitud de ' . strtolower($tipo) . ' - PRUEBA COBERTURA.\n\nDECISIÓN: ' . $decisionTexto,
            'fecha_decision' => $decision === 'PENDIENTE' ? null : '2026-08-24',
        ]);
        DB::table('recurso_evidencia')->insert(['id_recurso' => $recurso, 'descripcion' => 'Soporte de ' . $nombre, 'url' => 'https://unitropico.edu.co/recursos-prueba']);
    }
}
