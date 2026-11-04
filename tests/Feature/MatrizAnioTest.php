<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaDatosSerag;
use Tests\TestCase;

/**
 * La matriz de calificaciones se calculaba siempre con el año en curso. En
 * enero aparecía vacía aunque el semestre B del año anterior siguiera vigente.
 * Ahora Talento Humano elige el año.
 */
class MatrizAnioTest extends TestCase
{
    use CreaDatosSerag;

    private int $idVincEvaluado;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarDatosSerag();

        [$idEvaluado] = $this->crearFuncionarioPrueba('Tmp', 'Matriz');
        [$idEvaluador] = $this->crearFuncionarioPrueba('Tmp', 'Evaluador', 'EVALUADOR');
        $this->idVincEvaluado = $this->crearVinculacionPrueba($idEvaluado);
        $vincEvaluador = $this->crearVinculacionPrueba($idEvaluador, true);
        $idPeriodo = $this->crearPeriodoPrueba('RENDIMIENTO_LABORAL', 2098, 1, '2098-02-01', '2098-07-31');

        $idEvaluacion = $this->crearEvaluacionPrueba($idPeriodo, $this->idVincEvaluado, $vincEvaluador, [
            'fase_actual' => 5,
            'concertacion_firmada' => 1,
            'estado' => 'CALIFICADA',
        ]);
        DB::table('compromiso')->insert([
            'id_evaluacion' => $idEvaluacion,
            'numero_orden' => 1,
            'descripcion' => 'Compromiso de prueba',
            'porcentaje_peso' => 100,
            'calificacion_definitiva' => 4.5,
        ]);

        $this->sesionComo('admin', null);
    }

    protected function tearDown(): void
    {
        $this->finalizarDatosSerag();
        parent::tearDown();
    }

    private function filaDelEvaluado($respuesta): ?object
    {
        return collect($respuesta->viewData('matrizCalificaciones'))
            ->first(fn ($f) => $f->apellidos === 'Matriz');
    }

    public function test_por_defecto_muestra_el_anio_en_curso(): void
    {
        $respuesta = $this->get('/dashboard')->assertOk();

        $this->assertSame((int) date('Y'), $respuesta->viewData('anioMatriz'));
        $this->assertNull($this->filaDelEvaluado($respuesta)->nota_semestre_1);
    }

    public function test_al_elegir_otro_anio_muestra_sus_notas(): void
    {
        $respuesta = $this->get('/dashboard?anio_matriz=2098')->assertOk();

        $this->assertSame(2098, $respuesta->viewData('anioMatriz'));
        $this->assertContains(2098, $respuesta->viewData('aniosMatriz')->all());
        $this->assertNotNull($this->filaDelEvaluado($respuesta)->nota_semestre_1);
    }

    public function test_un_anio_sin_periodos_vuelve_al_anio_en_curso(): void
    {
        $respuesta = $this->get('/dashboard?anio_matriz=1990')->assertOk();

        $this->assertSame((int) date('Y'), $respuesta->viewData('anioMatriz'));
    }
}
