<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Casos complementarios, identificables y no destructivos para semanas 8 y 9. */
class PruebasCoberturaSemanas8y9Seeder extends Seeder
{
    private const PREFIJO = 'PRUEBA COBERTURA S8-S9:';

    public function run(): void
    {
        if (DB::table('evaluacion')->where('referencia', 'like', self::PREFIJO . '%')->exists()) {
            $this->command?->info('Las pruebas complementarias S8-S9 ya están cargadas.');
            return;
        }

        DB::transaction(function (): void {
            $periodo = (int) DB::table('periodo')->where('id_periodo', 10)->value('id_periodo');
            $titular = 26; // César Rolando Castro, Secretario General.
            $delegado = 36; // Óscar Mauricio Cruz, Vicerrector de Proyección.
            $admin = (int) DB::table('usuario')->where('username', 'talentohumano@unitropico.edu.co')->value('id_usuario');

            if (!$periodo || !$admin || !DB::table('vinculacion')->where('id_vinculacion', $titular)->exists()) {
                throw new \RuntimeException('No se encontraron las referencias institucionales para S8-S9.');
            }

            // S8-01: delegación terminada; la asignación y la evaluación pendiente retornan al titular.
            $retorno = $this->evaluacion($periodo, 27, $titular, 'RETORNO TITULAR TRAS DELEGACION');
            DB::table('evaluador_asignacion')->updateOrInsert(
                ['id_vinc_evaluador' => $titular, 'id_vinc_evaluado' => 27],
                ['fecha_asignacion' => now()]
            );
            DB::table('delegacion')->insert([
                'id_vinc_delegante' => $titular, 'id_vinc_delegado' => $delegado,
                'motivo' => 'PRUEBA COBERTURA S8: retorno de responsabilidad al titular.',
                'area' => 'SECRETARIA GENERAL', 'acto_administrativo' => 'Resolución',
                'acto_administrativo_numero' => 'PRUEBA-S8-RET-001', 'acto_administrativo_fecha' => '2026-04-01',
                'acto_administrativo_url' => 'https://unitropico.edu.co/acto-prueba-s8-retorno.pdf',
                'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-04-01', 'estado' => 'FINALIZADA',
                'id_usuario_registra' => $admin,
                'detalle_transferencia' => json_encode(['evaluados_transferidos' => [27], 'periodo_parcial_ids' => []]),
                'created_at' => now(), 'updated_at' => now(),
            ]);

            // S8-02: tramo delegado de 90 días, cerrado, apto para una parcial y visible en el historial.
            DB::table('periodo_parcial')->insert([
                'id_periodo' => $periodo, 'id_vinc_funcionario' => 28,
                'fecha_inicio' => '2026-08-02', 'fecha_fin' => '2026-10-30',
                'referencia' => self::PREFIJO . ' tramo de delegación de 90 días', 'estado' => 'CERRADO',
                'id_usuario_apertura' => $admin,
            ]);

            // S9-01: autorización de concertación extratiempo vigente, aún sin firmas.
            $extratiempo = $this->evaluacion($periodo, 28, $titular, 'CONCERTACION EXTRATIEMPO VIGENTE');
            DB::table('concertacion_extratiempo')->insert([
                'id_evaluacion' => $extratiempo,
                'justificacion' => 'PRUEBA COBERTURA S9: autorización administrativa para continuar la concertación.',
                'autorizado_por' => 'Talento Humano', 'fecha_limite' => '2026-09-30', 'activo' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            // S9-02: recusación pendiente, visible a administración para decisión.
            $recusacion = $this->evaluacion($periodo, 31, $titular, 'RECUSACION PENDIENTE');
            DB::table('impedimento_recusacion')->insert([
                'id_evaluacion' => $recusacion, 'id_vinc_solicitante' => 31, 'tipo' => 'RECUSACION',
                'motivo' => 'PRUEBA COBERTURA S9: solicitud pendiente para revisión administrativa.',
                'evidencia_url' => 'https://unitropico.edu.co/soporte-recusion-prueba.pdf', 'estado' => 'PENDIENTE',
                'created_at' => now(), 'updated_at' => now(),
            ]);

            // S9-03: impedimento aprobado con nuevo evaluador; la evaluación queda reasignada.
            $impedimento = $this->evaluacion($periodo, 33, $delegado, 'IMPEDIMENTO APROBADO Y REASIGNADO');
            DB::table('impedimento_recusacion')->insert([
                'id_evaluacion' => $impedimento, 'id_vinc_solicitante' => $titular, 'tipo' => 'IMPEDIMENTO',
                'motivo' => 'PRUEBA COBERTURA S9: impedimento declarado por el titular.',
                'evidencia_url' => 'https://unitropico.edu.co/soporte-impedimento-prueba.pdf', 'estado' => 'APROBADO',
                'respuesta_admin' => 'Aprobado: se reasigna al evaluador alterno.', 'id_usuario_admin' => $admin,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('evaluador_asignacion')->updateOrInsert(
                ['id_vinc_evaluador' => $delegado, 'id_vinc_evaluado' => 33],
                ['fecha_asignacion' => now()]
            );

            // S9-04: recusación rechazada; se conserva el evaluador titular.
            $rechazada = $this->evaluacion($periodo, 34, $titular, 'RECUSACION RECHAZADA');
            DB::table('impedimento_recusacion')->insert([
                'id_evaluacion' => $rechazada, 'id_vinc_solicitante' => 34, 'tipo' => 'RECUSACION',
                'motivo' => 'PRUEBA COBERTURA S9: recusación que no reúne los requisitos.',
                'evidencia_url' => 'https://unitropico.edu.co/soporte-recusion-rechazada.pdf', 'estado' => 'RECHAZADO',
                'respuesta_admin' => 'Rechazada: no se acreditó causal de recusación.', 'id_usuario_admin' => $admin,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            // S8/S9: trazabilidad del periodo para la consulta de auditoría administrativa.
            DB::table('periodo_auditoria')->insert([
                'id_periodo' => $periodo, 'id_usuario' => $admin, 'accion' => 'EDITAR',
                'cambios' => json_encode(['origen' => 'PRUEBA COBERTURA S8-S9', 'detalle' => 'Validación de historial y controles administrativos']),
            ]);
        });

        $this->command?->info('Pruebas complementarias S8-S9 cargadas.');
    }

    private function evaluacion(int $periodo, int $evaluado, int $evaluador, string $caso): int
    {
        return DB::table('evaluacion')->insertGetId([
            'id_periodo' => $periodo, 'id_vinc_evaluado' => $evaluado, 'id_vinc_evaluador' => $evaluador,
            'tipo_evaluacion' => 'SEMESTRE_2', 'fase_actual' => 1, 'concertacion_firmada' => 0,
            'estado' => 'EN_PROCESO', 'es_parcial' => 0, 'es_traslado' => 0,
            'referencia' => self::PREFIJO . ' ' . $caso,
        ]);
    }
}
