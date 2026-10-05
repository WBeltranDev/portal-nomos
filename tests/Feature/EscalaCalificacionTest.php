<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * S11/S13 — Escala institucional de 1.0 a 5.0 con sus cuatro umbrales.
 *
 *   1.0 a 3.4  No satisfactorio             (sin plan de mejoramiento)
 *   3.5 a 4.0  Susceptible a plan de mejora (con plan de mejoramiento)
 *   4.1 a 4.5  Bueno                        (sin plan de mejoramiento)
 *   4.6 a 5.0  Sobresaliente                (sin plan de mejoramiento)
 *
 * Estas pruebas fijan los límites de cada banda y, sobre todo, que el plan de
 * mejoramiento NO se exija en notas buenas: ese era el defecto del umbral
 * "<= 80" que quedó de la escala 0-100, con el que una nota 4.5 (Bueno) se
 *tomaba por exigir plan de mejoramiento, por ser menor que 80.
 */
class EscalaCalificacionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requiere una base MySQL con el esquema institucional aplicado.');
        }
    }

    /** @return array<string, array{0: float, 1: string}> */
    public static function bandasProveedor(): array
    {
        return [
            'mínimo 1.0 es no satisfactorio'      => [1.0, 'NO_SATISFACTORIO'],
            'borde inferior 3.4 sigue siendo bajo' => [3.4, 'NO_SATISFACTORIO'],
            'borde inferior 3.5 entra a plan'      => [3.5, 'APROBADO_MEJORA'],
            'tope de plan 4.0'                     => [4.0, 'APROBADO_MEJORA'],
            'borde inferior 4.1 es bueno'          => [4.1, 'BUENO'],
            'tope de bueno 4.5'                    => [4.5, 'BUENO'],
            'borde inferior 4.6 es sobresaliente'  => [4.6, 'SOBRESALIENTE'],
            'máximo 5.0 es sobresaliente'          => [5.0, 'SOBRESALIENTE'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('bandasProveedor')]
    public function test_cada_nota_cae_en_su_banda(float $nota, string $nivelEsperado): void
    {
        $this->assertSame($nivelEsperado, nivelEscalaCalificacion($nota));
    }

    public function test_la_escala_tiene_cuatro_bandas_con_los_cuatro_umbrales(): void
    {
        $bandas = escalaCalificacionConfig()['bandas'];

        $this->assertCount(4, $bandas);
        $this->assertSame(
            ['NO_SATISFACTORIO', 'APROBADO_MEJORA', 'BUENO', 'SOBRESALIENTE'],
            array_column($bandas, 'nivel')
        );
        $this->assertSame(1.0, escalaCalificacionConfig()['minimo']);
        $this->assertSame(5.0, escalaCalificacionConfig()['maximo']);

        // Las bandas no pueden dejar huecos ni superponerse, y la última cierra
        // en el máximo de la escala.
        $total = count($bandas);
        foreach ($bandas as $indice => $banda) {
            $this->assertLessThan($banda['hasta'], $banda['desde']);

            if ($indice < $total - 1) {
                $this->assertEqualsWithDelta(
                    $bandas[$indice + 1]['desde'],
                    $banda['hasta'] + 0.1,
                    0.001,
                    'La banda debe continuar justo donde termina la anterior.'
                );
            } else {
                $this->assertSame(5.0, $banda['hasta'], 'La última banda debe cerrar en el máximo.');
            }
        }
    }

    public function test_las_dos_bandas_bajas_exigen_plan_y_las_buenas_no(): void
    {
        // No satisfactorio y Susceptible a plan exigen plan de mejoramiento;
        // Bueno y Sobresaliente no. Ese era el punto que fallaba con el umbral
        // "<= 80" de la escala 0-100, que además alcanzaba las notas buenas.
        $this->assertTrue(notaEscalaAplicaPlanMejoramiento(1.0));
        $this->assertTrue(notaEscalaAplicaPlanMejoramiento(2.5));
        $this->assertTrue(notaEscalaAplicaPlanMejoramiento(3.4));
        $this->assertTrue(notaEscalaAplicaPlanMejoramiento(3.5));
        $this->assertTrue(notaEscalaAplicaPlanMejoramiento(4.0));

        // Notas buenas: nunca deben exigir plan de mejoramiento.
        $this->assertFalse(notaEscalaAplicaPlanMejoramiento(4.1));
        $this->assertFalse(notaEscalaAplicaPlanMejoramiento(4.5));
        $this->assertFalse(notaEscalaAplicaPlanMejoramiento(4.6));
        $this->assertFalse(notaEscalaAplicaPlanMejoramiento(5.0));
    }

    public function test_una_nota_buena_no_exige_plan_de_mejoramiento_aunque_se_pase_la_categoria(): void
    {
        // Regresión: con el código anterior, el fallback "<= 80" hacía que una
        // categoría BUENO con nota 4.5 exigiera plan de mejoramiento.
        $evaluacion = (object) ['categoria_final' => 'BUENO', 'calificacion_final' => 4.5];

        $this->assertFalse(evaluacionRequierePlanMejoramiento($evaluacion));

        $sobresaliente = (object) ['categoria_final' => 'SOBRESALIENTE', 'calificacion_final' => 4.8];
        $this->assertFalse(evaluacionRequierePlanMejoramiento($sobresaliente));
    }

    public function test_las_categorias_bajas_exigen_plan_y_las_buenas_no(): void
    {
        $this->assertTrue(evaluacionRequierePlanMejoramiento(
            (object) ['categoria_final' => 'APROBADO_MEJORA', 'calificacion_final' => 3.7]
        ));
        $this->assertTrue(evaluacionRequierePlanMejoramiento(
            (object) ['categoria_final' => 'NO_SATISFACTORIO', 'calificacion_final' => 2.6]
        ));

        // Sin categoría calculada, la nota se resuelve con la misma escala.
        $this->assertTrue(evaluacionRequierePlanMejoramiento(
            (object) ['categoria_final' => null, 'calificacion_final' => 3.9]
        ));
        $this->assertTrue(evaluacionRequierePlanMejoramiento(
            (object) ['categoria_final' => null, 'calificacion_final' => 2.1]
        ));
        $this->assertFalse(evaluacionRequierePlanMejoramiento(
            (object) ['categoria_final' => null, 'calificacion_final' => 4.4]
        ));
    }

    public function test_las_notas_se_acotan_a_la_escala_vigente(): void
    {
        $this->assertSame(1.0, redondearEscala(0));
        $this->assertSame(1.0, redondearEscala(-10));
        $this->assertSame(5.0, redondearEscala(9.99));
        $this->assertSame(5.0, redondearEscala(100));
        $this->assertSame(3.5, redondearEscala(3.46));
        $this->assertSame(4.5, redondearEscala(4.5));
    }

    public function test_el_catalogo_expone_cuatro_rangos_de_la_escala(): void
    {
        $catalogo = escalaCalificacionCatalogo();

        $this->assertSame(1.0, $catalogo['minimo']);
        $this->assertSame(5.0, $catalogo['maximo']);
        $this->assertCount(4, $catalogo['rangos']);
        $this->assertStringContainsString('1.0', $catalogo['descripcion']);
        $this->assertStringContainsString('5.0', $catalogo['descripcion']);

        // Las dos bandas bajas exigen plan: No satisfactorio (código 1) y
        // Susceptible a plan de mejora (código 2). Las buenas no.
        $conPlan = array_values(array_filter($catalogo['rangos'], fn ($r) => $r['aplica_plan_mejoramiento']));
        $this->assertCount(2, $conPlan);
        $this->assertSame([1, 2], array_column($conPlan, 'codigo'));
        $this->assertSame(3.5, $conPlan[1]['desde']);
        $this->assertSame(4.0, $conPlan[1]['hasta']);

        // Los cuatro rótulos del catálogo, en orden.
        $this->assertSame(
            ['No satisfactorio', 'Susceptible a plan de mejora', 'Bueno', 'Sobresaliente'],
            array_column($catalogo['rangos'], 'nivel')
        );
    }

    public function test_no_quedan_umbrales_de_la_escala_0_100_en_el_codigo_de_calificacion(): void
    {
        // Guarda contra reintroducir comparaciones numéricas de la escala vieja.
        $archivos = [
            base_path('routes/web.php'),
            base_path('app/Helpers/calificaciones.php'),
        ];

        foreach ($archivos as $archivo) {
            $contenido = file_get_contents($archivo);

            // Comparaciones de notas contra 0-100 dentro de funciones de la escala.
            $this->assertDoesNotMatchRegularExpression(
                '/nota[^\n;]*<=?\s*(?:60|70|80|90|100)\b/i',
                $contenido,
                "Se detectó un umbral de la escala 0-100 en {$archivo}."
            );
        }
    }
}
