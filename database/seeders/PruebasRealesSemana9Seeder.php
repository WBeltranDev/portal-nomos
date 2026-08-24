<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Datos de demostración sobre funcionarios institucionales ya importados. */
class PruebasRealesSemana9Seeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('evaluacion')->where('referencia', 'like', 'PRUEBA S9:%')->exists()) {
            $this->command?->info('Las pruebas reales de semana 9 ya están cargadas.');
            return;
        }

        DB::transaction(function (): void {
            $admin = (int) DB::table('usuario')->where('username', 'talentohumano@unitropico.edu.co')->value('id_usuario');
            $evaluador = (int) DB::table('vinculacion')->where('id_vinculacion', 66)->value('id_vinculacion'); // Rectoría
            $periodoRl = (int) DB::table('periodo')->where('id_periodo', 10)->value('id_periodo');
            $periodoAg = (int) DB::table('periodo')->where('id_periodo', 11)->value('id_periodo');

            if (!$admin || !$evaluador || !$periodoRl || !$periodoAg) {
                throw new \RuntimeException('No se encontraron las cuentas o periodos institucionales requeridos.');
            }

            // Funcionarios existentes: Javier Achagua (RL), Alda Caro (AG/ejes),
            // Claudia Aguirre (plan/recurso), Astrid Barrera (extratiempo) y Darwin Calderón (parcial).
            $evRl = $this->evaluacion($periodoRl, 1, $evaluador, 'SEMESTRE_2', 1, 'EN_PROCESO', 'PRUEBA S9: RL - concertación pendiente');
            $evAg = $this->evaluacion($periodoAg, 25, $evaluador, 'SEMESTRE_2', 5, 'CALIFICADA', 'PRUEBA S9: AG con ejes misionales', 92.50, 'SOBRESALIENTE');
            $evPlan = $this->evaluacion($periodoAg, 3, $evaluador, 'SEMESTRE_2', 5, 'CALIFICADA', 'PRUEBA S9: recurso y plan de mejoramiento', 65.00, 'APROBADO_MEJORA');
            $evExtra = $this->evaluacion($periodoAg, 10, $evaluador, 'SEMESTRE_2', 1, 'EN_PROCESO', 'PRUEBA S9: concertación extratiempo');
            $evParcial = $this->evaluacion($periodoAg, 19, $evaluador, 'PARCIAL', 5, 'CALIFICADA', 'PRUEBA S9: traslado y prorrateo', 78.00, 'BUENO', true);

            $this->compromisos($evRl, 80, null);
            $this->competencias($evRl, null);
            $this->compromisos($evAg, 50, 93);
            $this->competencias($evAg, 93);
            $this->compromisos($evPlan, 80, 65);
            $this->competencias($evPlan, 65);
            $this->compromisos($evExtra, 80, null);
            $this->competencias($evExtra, null);
            $this->compromisos($evParcial, 80, 78);
            $this->competencias($evParcial, 78);

            DB::table('evaluacion')->where('id_evaluacion', $evAg)->update(['nota_compromisos' => 57.50, 'nota_competencias' => 15.00, 'nota_ejes_misionales' => 20.00, 'concertacion_firmada' => 1]);
            DB::table('evaluacion')->where('id_evaluacion', $evPlan)->update(['nota_compromisos' => 45.00, 'nota_competencias' => 20.00, 'nota_ejes_misionales' => 0, 'concertacion_firmada' => 1]);
            DB::table('evaluacion')->where('id_evaluacion', $evParcial)->update(['dias_laborados' => 61, 'calificacion_parcial' => 31.20, 'nota_compromisos' => 62.00, 'nota_competencias' => 16.00, 'concertacion_firmada' => 1]);

            DB::table('evaluacion_eje')->insert(['id_evaluacion' => $evAg, 'investigacion' => 1, 'proyeccion_social' => 1]);
            foreach ([['DOCENCIA', 95], ['INVESTIGACION', 90], ['PROYECCION_SOCIAL', 92]] as [$eje, $nota]) {
                DB::table('eje_misional_calificacion')->insert(['id_evaluacion' => $evAg, 'eje' => $eje, 'calificacion' => $nota, 'observaciones' => 'Prueba S9: nota cargada por instancia externa.', 'id_usuario_ingresador' => 59, 'origen' => 'INSTANCIA_EXTERNA']);
            }
            $compromiso = DB::table('compromiso')->where('id_evaluacion', $evAg)->value('id_compromiso');
            DB::table('evidencia')->insert(['id_evaluacion' => $evAg, 'id_compromiso' => $compromiso, 'componente' => 'B', 'descripcion' => 'Prueba S9: informe de cumplimiento.', 'tipo_evidencia' => 'LINK', 'url_o_ubicacion' => 'https://unitropico.edu.co', 'fecha_inclusion' => now(), 'id_vinc_registra' => 25, 'estado_aprobacion' => 'APROBADA', 'id_vinc_aprueba' => $evaluador, 'fecha_aprobacion' => now(), 'observacion_aprobacion' => 'Prueba aprobada.']);

            foreach ([[$evAg, 25], [$evPlan, 3], [$evParcial, 19]] as [$id, $evaluado]) {
                foreach ([['CONCERTACION_EVALUADO', $evaluado], ['CONCERTACION_EVALUADOR', $evaluador], ['NOTIFICACION_EVALUADO', $evaluado]] as [$tipo, $firmante]) {
                    DB::table('firma')->insert(['id_evaluacion' => $id, 'tipo_firma' => $tipo, 'id_vinc_firmante' => $firmante, 'fecha_firma' => now(), 'renuencia' => 0]);
                }
            }

            $plan = DB::table('plan_mejoramiento')->insertGetId(['id_evaluacion' => $evPlan, 'descripcion_temas' => 'Fortalecimiento de planeación, seguimiento y comunicación.', 'firmado_evaluado' => 1, 'firmado_evaluador' => 1, 'fecha_firma_evaluado' => now(), 'fecha_firma_evaluador' => now(), 'estado' => 'CONCERTADO']);
            DB::table('tema_capacitacion')->insert([['id_plan' => $plan, 'tema' => 'Planeación y seguimiento', 'descripcion' => 'Taller institucional.'], ['id_plan' => $plan, 'tema' => 'Comunicación efectiva', 'descripcion' => 'Acompañamiento institucional.']]);
            $recurso = DB::table('recurso')->insertGetId(['id_evaluacion' => $evPlan, 'tipo_recurso' => 'REPOSICION', 'id_vinc_receptor' => $evaluador, 'numero_radicado' => 'PRUEBA-S9-001', 'numero_folios' => 3, 'fecha_recurso' => '2026-08-20', 'decision' => 'NEGADO', 'motivacion' => 'Decisión motivada para la demostración.', 'fecha_decision' => '2026-08-22']);
            DB::table('recurso_evidencia')->insert(['id_recurso' => $recurso, 'descripcion' => 'Soporte del recurso.', 'url' => 'https://unitropico.edu.co']);

            DB::table('concertacion_extratiempo')->insert(['id_evaluacion' => $evExtra, 'justificacion' => 'Autorización administrativa para continuar la concertación.', 'autorizado_por' => 'Lilia Andrea Nocua Neme', 'fecha_limite' => '2026-09-05', 'activo' => 1, 'created_at' => now(), 'updated_at' => now()]);
            $compromisoExtra = DB::table('compromiso')->where('id_evaluacion', $evExtra)->value('id_compromiso');
            DB::table('compromiso_observacion')->insert(['id_evaluacion' => $evExtra, 'id_compromiso' => $compromisoExtra, 'id_vinc_evaluador' => $evaluador, 'texto' => 'Observación oficial de reapertura para prueba S9.', 'fecha_inclusion' => now()]);

            DB::table('evaluador_asignacion')->insert([['id_vinc_evaluador' => $evaluador, 'id_vinc_evaluado' => 1, 'fecha_asignacion' => now()], ['id_vinc_evaluador' => $evaluador, 'id_vinc_evaluado' => 25, 'fecha_asignacion' => now()], ['id_vinc_evaluador' => $evaluador, 'id_vinc_evaluado' => 3, 'fecha_asignacion' => now()], ['id_vinc_evaluador' => $evaluador, 'id_vinc_evaluado' => 10, 'fecha_asignacion' => now()], ['id_vinc_evaluador' => $evaluador, 'id_vinc_evaluado' => 19, 'fecha_asignacion' => now()]]);
            DB::table('delegacion')->insert(['id_vinc_delegante' => 93, 'id_vinc_delegado' => $evaluador, 'motivo' => 'Ausencia temporal del titular.', 'area' => 'OFICINA DE TALENTO HUMANO', 'fecha_inicio' => '2026-08-15', 'fecha_fin' => '2026-09-15', 'estado' => 'ACTIVA', 'id_usuario_registra' => $admin, 'acto_administrativo' => 'Resolución', 'acto_administrativo_numero' => 'PRUEBA-S9-009', 'acto_administrativo_fecha' => '2026-08-14', 'acto_administrativo_url' => 'https://unitropico.edu.co', 'detalle_transferencia' => json_encode(['evaluados_transferidos' => [1]]), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('periodo_parcial')->insert(['id_periodo' => $periodoAg, 'id_vinc_funcionario' => 19, 'fecha_inicio' => '2026-08-02', 'fecha_fin' => '2026-10-01', 'referencia' => 'PRUEBA S9: tramo por traslado', 'estado' => 'ABIERTO', 'id_usuario_apertura' => $admin]);
            DB::table('traslado')->insert(['id_vinc_funcionario' => 19, 'id_vinc_evaluador_origen' => 93, 'id_vinc_evaluador_nuevo' => $evaluador, 'area_origen' => 'OFICINA ASESORA DE PLANEACION', 'cargo_origen' => 'PROFESIONAL UNIVERSITARIO', 'area_nuevo' => 'RECTORIA', 'cargo_nuevo' => 'PROFESIONAL UNIVERSITARIO', 'fecha_traslado' => '2026-08-15', 'dias_laborados' => 61, 'id_evaluacion_parcial' => $evParcial, 'resolucion' => 'PRUEBA-S9-TR-001', 'motivo' => 'Prueba de traslado y prorrateo.', 'id_usuario_registra' => $admin]);
            DB::table('impedimento_recusacion')->insert(['id_evaluacion' => $evRl, 'id_vinc_solicitante' => 1, 'tipo' => 'RECUSACION', 'motivo' => 'Solicitud registrada para probar el flujo de recusación.', 'evidencia_url' => 'https://unitropico.edu.co', 'estado' => 'PENDIENTE', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('periodo_auditoria')->insert(['id_periodo' => $periodoAg, 'id_usuario' => $admin, 'accion' => 'EDITAR', 'cambios' => json_encode(['descripcion' => 'Prueba funcional para reunión'])]);
        });
        $this->command?->info('Pruebas con funcionarios institucionales cargadas.');
    }

    private function evaluacion(int $periodo, int $evaluado, int $evaluador, string $tipo, int $fase, string $estado, string $referencia, ?float $nota = null, ?string $categoria = null, bool $parcial = false): int
    {
        return DB::table('evaluacion')->insertGetId(['id_periodo' => $periodo, 'id_vinc_evaluado' => $evaluado, 'id_vinc_evaluador' => $evaluador, 'tipo_evaluacion' => $tipo, 'fase_actual' => $fase, 'concertacion_firmada' => 0, 'estado' => $estado, 'calificacion_final' => $nota, 'categoria_final' => $categoria, 'es_parcial' => $parcial, 'dias_laborados' => $parcial ? 61 : null, 'referencia' => $referencia]);
    }

    private function compromisos(int $evaluacion, int $total, ?int $calificacion): void
    {
        $pesos = $total === 50 ? [8, 7, 7, 7, 7, 7, 7] : [12, 12, 12, 11, 11, 11, 11];
        foreach ($pesos as $orden => $peso) {
            $id = DB::table('compromiso')->insertGetId(['id_evaluacion' => $evaluacion, 'numero_orden' => $orden + 1, 'descripcion' => 'Compromiso de prueba S9 ' . ($orden + 1), 'porcentaje_peso' => $peso, 'calificacion_sem2' => $calificacion, 'calificacion_definitiva' => $calificacion]);
            DB::table('compromiso_meta')->insert(['id_compromiso' => $id, 'meta' => 'Meta verificable ' . ($orden + 1)]);
        }
    }

    private function competencias(int $evaluacion, ?int $calificacion): void
    {
        $contexto = DB::table('evaluacion as e')
            ->join('periodo as p', 'p.id_periodo', '=', 'e.id_periodo')
            ->join('vinculacion as v', 'v.id_vinculacion', '=', 'e.id_vinc_evaluado')
            ->where('e.id_evaluacion', $evaluacion)
            ->first(['p.sistema', 'v.nivel_jerarquico']);

        $competencias = DB::table('competencia_catalogo')
            ->where('sistema', $contexto->sistema)
            ->where(function ($query) use ($contexto) {
                $query->where('tipo', 'COMUN')
                    ->orWhere(function ($nivel) use ($contexto) {
                        $nivel->where('tipo', 'NIVEL_JERARQUICO')
                            ->where('nivel_jerarquico', $contexto->nivel_jerarquico);
                    });
            })
            ->pluck('id_competencia');

        foreach ($competencias as $competencia) {
            DB::table('competencia_evaluada')->insert(['id_evaluacion' => $evaluacion, 'id_competencia' => $competencia, 'calificacion_sem2' => $calificacion, 'calificacion_definitiva' => $calificacion]);
        }
    }
}
