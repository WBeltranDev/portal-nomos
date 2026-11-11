<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Informe de pruebas 10-11-2026: la matriz de calificaciones debe mostrar las
 * calificaciones semestrales ya realizadas, aunque la evaluación se haya
 * registrado con el otro número de semestre, esté en otra vinculación de la
 * misma persona o el semestre tenga tramos aún sin calificar.
 */
class MatrizCalificacionesTest extends TestCase
{
    private int $anio;
    private int $idUsuario;
    private int $idFuncionario;
    private int $vinc;
    private int $vincAnterior;
    private int $evaluador;
    private int $periodoA;
    private int $periodoB;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requiere una base MySQL con el esquema institucional aplicado.');
        }

        DB::beginTransaction();

        $this->anio = (int) date('Y');
        $s = 'M' . random_int(10000, 99999);
        $this->idUsuario = DB::table('usuario')->insertGetId(['username' => "tmp.mat.{$s}", 'password' => 'x', 'rol' => 'ADMINISTRADOR', 'activo' => 1]);
        $this->idFuncionario = DB::table('funcionario')->insertGetId([
            'id_usuario' => DB::table('usuario')->insertGetId(['username' => "tmp.mat.f.{$s}", 'password' => 'x', 'rol' => 'EVALUADO', 'activo' => 1]),
            'tipo_documento' => 'CEDULA_CIUDADANIA', 'numero_doc' => $s, 'nombres' => 'MATRIZ', 'apellidos' => $s,
        ]);
        $this->vinc = $this->vinculacion($this->idFuncionario, 1);
        $this->vincAnterior = $this->vinculacion($this->idFuncionario, 0);
        $this->evaluador = $this->vinculacion(DB::table('funcionario')->insertGetId([
            'id_usuario' => DB::table('usuario')->insertGetId(['username' => "tmp.mat.e.{$s}", 'password' => 'x', 'rol' => 'EVALUADOR', 'activo' => 1]),
            'tipo_documento' => 'CEDULA_CIUDADANIA', 'numero_doc' => $s . 'E', 'nombres' => 'EVALUADOR', 'apellidos' => $s,
        ]), 1);

        $this->periodoA = $this->periodo(1, "{$this->anio}-01-01", "{$this->anio}-06-30");
        $this->periodoB = $this->periodo(2, "{$this->anio}-07-01", "{$this->anio}-12-31");

        session(['usuario_autenticado' => ['id_usuario' => $this->idUsuario, 'rol_activo' => 'admin', 'roles' => ['admin']]]);
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    private function vinculacion(int $idFuncionario, int $activa): int
    {
        return DB::table('vinculacion')->insertGetId([
            'id_funcionario' => $idFuncionario, 'cargo' => 'PRUEBA', 'codigo_cargo' => 1, 'grado_cargo' => 1,
            'nivel_jerarquico' => 'PROFESIONAL', 'area' => 'AREA MATRIZ', 'tipo_vinculacion' => 'PROVISIONALIDAD',
            'sistema_evaluacion' => 'RENDIMIENTO_LABORAL', 'es_evaluador' => 1, 'fecha_ingreso' => '2020-01-01', 'activa' => $activa,
        ]);
    }

    private function periodo(int $semestre, string $inicio, string $fin): int
    {
        return DB::table('periodo')->insertGetId([
            'id_usuario_apertura' => $this->idUsuario, 'sistema' => 'RENDIMIENTO_LABORAL', 'anio' => $this->anio, 'semestre' => $semestre,
            'fecha_inicio' => $inicio, 'fecha_fin' => $fin, 'estado' => 'ABIERTO',
        ]);
    }

    private function evaluacion(int $idPeriodo, int $idVinc, string $tipo, float $nota, string $estado = 'CALIFICADA', int $dias = 0): int
    {
        $id = DB::table('evaluacion')->insertGetId([
            'id_periodo' => $idPeriodo, 'id_vinc_evaluado' => $idVinc, 'id_vinc_evaluador' => $this->evaluador,
            'tipo_evaluacion' => $tipo, 'fase_actual' => $estado === 'CALIFICADA' ? 5 : 4, 'concertacion_firmada' => 1, 'estado' => $estado,
            'dias_laborados' => $dias ?: null,
        ]);
        if ($estado === 'CALIFICADA') {
            DB::table('compromiso')->insert(['id_evaluacion' => $id, 'numero_orden' => 1, 'descripcion' => 'C', 'porcentaje_peso' => 100, 'calificacion_definitiva' => $nota]);
        }

        return $id;
    }

    private function notaDe(int $id): float
    {
        return (float) calcularNotaEvaluacion($id)['nota_final'];
    }

    private function fila(): ?object
    {
        $html = $this->get('/dashboard')->assertOk()->viewData('matrizCalificaciones');

        return collect($html)->firstWhere('documento', DB::table('funcionario')->where('id_funcionario', $this->idFuncionario)->value('numero_doc'));
    }

    public function test_la_evaluacion_semestral_calificada_aparece_en_su_semestre(): void
    {
        $id = $this->evaluacion($this->periodoA, $this->vinc, 'SEMESTRE_1', 4.0);

        $fila = $this->fila();

        $this->assertEquals($this->notaDe($id), $fila->nota_semestre_1);
        $this->assertNull($fila->nota_semestre_2);
        $this->assertFalse($fila->sem1_provisional);
        $this->assertEquals($this->notaDe($id), $fila->nota_anual);
    }

    public function test_una_evaluacion_registrada_con_el_otro_numero_de_semestre_tambien_aparece(): void
    {
        // Evaluación tipo SEMESTRE_1 dentro del periodo del semestre 2.
        $id = $this->evaluacion($this->periodoB, $this->vinc, 'SEMESTRE_1', 4.0);

        $fila = $this->fila();

        $this->assertNull($fila->nota_semestre_1);
        $this->assertEquals($this->notaDe($id), $fila->nota_semestre_2);
    }

    public function test_la_evaluacion_de_otra_vinculacion_de_la_misma_persona_aparece(): void
    {
        $id = $this->evaluacion($this->periodoA, $this->vincAnterior, 'SEMESTRE_1', 4.0);

        $this->assertEquals($this->notaDe($id), $this->fila()->nota_semestre_1);
    }

    public function test_un_semestre_con_tramos_sin_calificar_muestra_la_nota_provisional(): void
    {
        $origen = $this->evaluacion($this->periodoB, $this->vinc, 'SEMESTRE_1', 4.0, 'CALIFICADA', 60);
        $destino = $this->evaluacion($this->periodoB, $this->vinc, 'PARCIAL', 0, 'EN_PROCESO', 100);
        DB::table('traslado')->insert([
            'id_vinc_funcionario' => $this->vinc, 'estado' => 'CONFIRMADO', 'tipo' => 'CAMBIO', 'id_periodo' => $this->periodoB,
            'id_evaluacion_origen' => $origen, 'id_evaluacion_destino' => $destino, 'id_vinc_evaluador_origen' => $this->evaluador,
            'id_vinc_evaluador_nuevo' => $this->evaluador, 'fecha_traslado' => "{$this->anio}-09-01",
        ]);

        $fila = $this->fila();

        $this->assertEquals($this->notaDe($origen), $fila->nota_semestre_2, 'La nota del tramo calificado no desaparece.');
        $this->assertTrue($fila->sem2_provisional);
        $this->assertSame('1/2', $fila->sem2_tramos);
        $this->assertNull($fila->nota_anual, 'El anual solo promedia semestres completos.');
    }

    public function test_con_todos_los_tramos_calificados_la_nota_es_definitiva_y_ponderada(): void
    {
        $origen = $this->evaluacion($this->periodoB, $this->vinc, 'SEMESTRE_1', 5.0, 'CALIFICADA', 60);
        $destino = $this->evaluacion($this->periodoB, $this->vinc, 'PARCIAL', 2.0, 'CALIFICADA', 100);
        DB::table('traslado')->insert([
            'id_vinc_funcionario' => $this->vinc, 'estado' => 'CONFIRMADO', 'tipo' => 'CAMBIO', 'id_periodo' => $this->periodoB,
            'id_evaluacion_origen' => $origen, 'id_evaluacion_destino' => $destino, 'id_vinc_evaluador_origen' => $this->evaluador,
            'id_vinc_evaluador_nuevo' => $this->evaluador, 'fecha_traslado' => "{$this->anio}-09-01",
        ]);

        $fila = $this->fila();

        $esperada = redondearEscala(($this->notaDe($origen) * 60 + $this->notaDe($destino) * 100) / 160);
        $this->assertEquals($esperada, $fila->nota_semestre_2);
        $this->assertFalse($fila->sem2_provisional);
    }

    public function test_la_matriz_dibuja_la_nota_y_marca_la_provisional(): void
    {
        $origen = $this->evaluacion($this->periodoB, $this->vinc, 'SEMESTRE_1', 4.0, 'CALIFICADA', 60);
        $destino = $this->evaluacion($this->periodoB, $this->vinc, 'PARCIAL', 0, 'EN_PROCESO', 100);
        DB::table('traslado')->insert([
            'id_vinc_funcionario' => $this->vinc, 'estado' => 'CONFIRMADO', 'tipo' => 'CAMBIO', 'id_periodo' => $this->periodoB,
            'id_evaluacion_origen' => $origen, 'id_evaluacion_destino' => $destino, 'id_vinc_evaluador_origen' => $this->evaluador,
            'id_vinc_evaluador_nuevo' => $this->evaluador, 'fecha_traslado' => "{$this->anio}-09-01",
        ]);

        $this->get('/dashboard')->assertOk()->assertSee('Parcial 1/2', false);
    }
}
