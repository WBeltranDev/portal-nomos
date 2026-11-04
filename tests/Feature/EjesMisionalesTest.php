<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaDatosSerag;
use Tests\TestCase;

/**
 * Acta 29-10-2026, sección 7: ejes misionales y cálculo de la nota de un
 * Acuerdo de Gestión. No se alcanzó a probar en la jornada; estas pruebas
 * cubren el caso de ejemplo (Carlos) y sus variantes.
 */
class EjesMisionalesTest extends TestCase
{
    use CreaDatosSerag;

    private int $idPeriodo;
    private int $idVincEvaluador;
    private int $idFuncionarioEvaluador;
    private int $idUsuarioEvaluador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarDatosSerag();

        DB::table('ponderacion')->delete();
        [$this->idFuncionarioEvaluador, $this->idUsuarioEvaluador] = $this->crearFuncionarioPrueba('Tmp', 'Evaluadora', 'EVALUADOR');
        $this->idVincEvaluador = $this->crearVinculacionPrueba($this->idFuncionarioEvaluador, true, 'ACUERDO_GESTION');
        $this->idPeriodo = $this->crearPeriodoPrueba('ACUERDO_GESTION', 2099, 1, '2099-02-01', '2099-07-31');
    }

    protected function tearDown(): void
    {
        $this->finalizarDatosSerag();
        parent::tearDown();
    }

    /** Evaluación AG con los ejes indicados. Docencia aplica siempre que el cargo tenga ejes. */
    private function evaluacionAg(bool $conEjes, bool $investigacion = false, bool $proyeccion = false): int
    {
        [$idFuncionario] = $this->crearFuncionarioPrueba('Tmp', 'Carlos');
        $idVinc = $this->crearVinculacionPrueba($idFuncionario, false, 'ACUERDO_GESTION');
        DB::table('vinculacion')->where('id_vinculacion', $idVinc)->update(['aplica_eje_misional' => $conEjes ? 1 : 0]);

        $id = $this->crearEvaluacionPrueba($this->idPeriodo, $idVinc, $this->idVincEvaluador, [
            'fase_actual' => 4,
            'concertacion_firmada' => 1,
        ]);
        DB::table('evaluacion_eje')->insert(['id_evaluacion' => $id, 'investigacion' => $investigacion, 'proyeccion_social' => $proyeccion]);

        return $id;
    }

    private function calificarCompromisosYCompetencias(int $idEvaluacion, float $compromisos, float $competencias): void
    {
        DB::table('compromiso')->insert([
            'id_evaluacion' => $idEvaluacion,
            'numero_orden' => 1,
            'descripcion' => 'Compromiso de prueba',
            'porcentaje_peso' => 100,
            'calificacion_definitiva' => $compromisos,
        ]);
        foreach (['COMUN' => null, 'NIVEL_JERARQUICO' => 'PROFESIONAL'] as $tipo => $nivel) {
            $idCompetencia = DB::table('competencia_catalogo')->insertGetId([
                'sistema' => 'ACUERDO_GESTION',
                'tipo' => $tipo,
                'nivel_jerarquico' => $nivel,
                'nombre' => 'Competencia de prueba ' . $tipo . ' ' . $this->sufijoDatos,
                'orden' => 99,
            ]);
            DB::table('competencia_evaluada')->insert([
                'id_evaluacion' => $idEvaluacion,
                'id_competencia' => $idCompetencia,
                'calificacion_definitiva' => $competencias,
            ]);
        }
    }

    private function calificarEje(int $idEvaluacion, string $eje, float $nota): void
    {
        DB::table('eje_misional_calificacion')->insert([
            'id_evaluacion' => $idEvaluacion,
            'eje' => $eje,
            'calificacion' => $nota,
            'origen' => 'EVALUADOR',
        ]);
    }

    public function test_el_caso_de_carlos_con_tres_ejes_da_4_2(): void
    {
        $id = $this->evaluacionAg(true, true, true);
        $this->calificarCompromisosYCompetencias($id, 4.2, 4.0);
        $this->calificarEje($id, 'DOCENCIA', 4.5);
        $this->calificarEje($id, 'INVESTIGACION', 4.0);
        $this->calificarEje($id, 'PROYECCION_SOCIAL', 4.3);

        $calculo = calcularNotaEvaluacion($id);

        // (4,2 × 50 %) + (4,5 × 10 %) + (4,0 × 10 %) + (4,3 × 10 %) + (4,0 × 20 %) = 4,18 → 4,2
        $this->assertSame(4.2, (float) $calculo['nota_final']);
        $this->assertSame('BUENO', $calculo['categoria']);
    }

    /** @return array<string, array{bool, bool, bool, float, array<string, float>}> */
    public static function variantes(): array
    {
        return [
            'tres ejes' => [true, true, true, 50.0, ['DOCENCIA' => 10.0, 'INVESTIGACION' => 10.0, 'PROYECCION_SOCIAL' => 10.0]],
            'docencia e investigación' => [true, true, false, 60.0, ['DOCENCIA' => 10.0, 'INVESTIGACION' => 10.0]],
            'docencia y proyección social' => [true, false, true, 60.0, ['DOCENCIA' => 10.0, 'PROYECCION_SOCIAL' => 10.0]],
            'solo docencia' => [true, false, false, 70.0, ['DOCENCIA' => 10.0]],
            'cargo sin ejes' => [false, false, false, 80.0, []],
        ];
    }

    /** @dataProvider variantes */
    #[\PHPUnit\Framework\Attributes\DataProvider('variantes')]
    public function test_los_pesos_dependen_de_los_ejes_activos_y_suman_100(bool $conEjes, bool $inv, bool $proy, float $pesoCompromisos, array $pesoEjes): void
    {
        $calculo = calcularNotaEvaluacion($this->evaluacionAg($conEjes, $inv, $proy));

        $this->assertSame($pesoCompromisos, (float) $calculo['pesos']['compromisos']);
        $this->assertEquals($pesoEjes, $calculo['pesos']['ejes']);
        $this->assertSame(array_keys($pesoEjes), $calculo['ejes_activos'], 'Solo aparecen los ejes que aplican.');
        $total = $calculo['pesos']['compromisos'] + $calculo['pesos']['comun'] + $calculo['pesos']['nivel_jerarquico'] + array_sum($calculo['pesos']['ejes']);
        $this->assertEqualsWithDelta(100.0, $total, 0.0001);
    }

    public function test_no_se_cierra_la_calificacion_con_un_eje_activo_sin_nota(): void
    {
        $id = $this->evaluacionAg(true, true, true);
        $this->calificarCompromisosYCompetencias($id, 4.2, 4.0);
        $this->calificarEje($id, 'DOCENCIA', 4.5);
        $this->calificarEje($id, 'INVESTIGACION', 4.0);

        $calculo = calcularNotaEvaluacion($id);
        $this->assertFalse($calculo['calificacion_completa']);
        $this->assertContains('PROYECCION_SOCIAL', $calculo['pendientes']['ejes_faltantes']);

        $this->sesionComo('evaluador', $this->idFuncionarioEvaluador, $this->idUsuarioEvaluador);
        $respuesta = $this->postJson("/evaluaciones/{$id}/calcular-final")->assertStatus(422);
        $this->assertContains('PROYECCION_SOCIAL', $respuesta->json('pendientes.ejes_faltantes'),
            'El bloqueo debe señalar el eje sin nota.');
        $this->assertNotSame('CALIFICADA', DB::table('evaluacion')->where('id_evaluacion', $id)->value('estado'));
    }

    public function test_las_notas_de_los_ejes_fuera_de_1_a_5_se_rechazan(): void
    {
        $id = $this->evaluacionAg(true);
        $this->sesionComo('evaluador', $this->idFuncionarioEvaluador, $this->idUsuarioEvaluador);

        foreach ([0.9, 5.1] as $nota) {
            $this->postJson("/evaluaciones/{$id}/calificar-ejes", [
                'ejes' => [['tipo_eje' => 'DOCENCIA', 'calificacion' => $nota]],
            ])->assertStatus(422);
        }
        $this->assertSame(0, DB::table('eje_misional_calificacion')->where('id_evaluacion', $id)->count());

        $this->postJson("/evaluaciones/{$id}/calificar-ejes", [
            'ejes' => [['tipo_eje' => 'DOCENCIA', 'calificacion' => 4.5]],
        ])->assertOk();
    }
}
