<?php

namespace Tests\Feature;

use Tests\Concerns\CreaDatosSerag;
use Tests\TestCase;

/**
 * Las tarjetas de evaluación del evaluador eran un <button> con el botón de
 * Bitácora adentro. Un elemento interactivo dentro de otro es HTML inválido: el
 * navegador cierra la tarjeta antes de tiempo y el tablero se desarma (tarjetas
 * fuera de la cuadrícula, panel de concertación debajo del menú lateral).
 */
class HtmlValidoTableroTest extends TestCase
{
    use CreaDatosSerag;

    private int $idFuncionarioEvaluado;
    private int $idUsuarioEvaluado;
    private int $idFuncionarioEvaluador;
    private int $idUsuarioEvaluador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarDatosSerag();

        [$this->idFuncionarioEvaluado, $this->idUsuarioEvaluado] = $this->crearFuncionarioPrueba('Tmp', 'Evaluado');
        [$this->idFuncionarioEvaluador, $this->idUsuarioEvaluador] = $this->crearFuncionarioPrueba('Tmp', 'Evaluador', 'EVALUADOR');
        $vincEvaluado = $this->crearVinculacionPrueba($this->idFuncionarioEvaluado);
        $vincEvaluador = $this->crearVinculacionPrueba($this->idFuncionarioEvaluador, true);
        $this->asignarEvaluadorPrueba($vincEvaluador, $vincEvaluado);
        $idPeriodo = $this->crearPeriodoPrueba('RENDIMIENTO_LABORAL', 2099, 1, '2099-02-01', '2099-07-31');

        $this->crearEvaluacionPrueba($idPeriodo, $vincEvaluado, $vincEvaluador, [
            'fase_actual' => 5,
            'concertacion_firmada' => 1,
            'estado' => 'CALIFICADA',
            'calificacion_final' => 4.4,
            'categoria_final' => 'BUENO',
        ]);
        $this->crearEvaluacionPrueba($idPeriodo, $vincEvaluado, $vincEvaluador, [
            'tipo_evaluacion' => 'PARCIAL',
            'fase_actual' => 2,
        ]);
    }

    protected function tearDown(): void
    {
        $this->finalizarDatosSerag();
        parent::tearDown();
    }

    /**
     * Elementos interactivos (button, a) abiertos dentro de otro, sin contar el
     * contenido de <script>, <template> ni comentarios HTML.
     */
    private function anidamientosInvalidos(string $html): array
    {
        $html = preg_replace('#<script\b.*?</script>|<template\b.*?</template>|<!--.*?-->#si', '', $html);
        preg_match_all('#<(/?)(button|a)\b[^>]*>#i', $html, $etiquetas, PREG_OFFSET_CAPTURE);

        $abiertos = [];
        $errores = [];
        foreach ($etiquetas[0] as $i => [$etiqueta, $posicion]) {
            $cierre = $etiquetas[1][$i][0] === '/';
            $nombre = strtolower($etiquetas[2][$i][0]);
            if ($cierre) {
                array_pop($abiertos);
                continue;
            }
            if ($abiertos) {
                $errores[] = "<{$nombre}> dentro de <" . end($abiertos) . '>: ' . substr($html, $posicion, 120);
            }
            $abiertos[] = $nombre;
        }

        return $errores;
    }

    public function test_el_tablero_del_evaluador_no_anida_botones(): void
    {
        $this->sesionComo('evaluador', $this->idFuncionarioEvaluador, $this->idUsuarioEvaluador);
        $html = $this->get('/dashboard')->assertOk()->assertSee('Bitácora')->getContent();

        $this->assertSame([], $this->anidamientosInvalidos($html));
    }

    public function test_el_tablero_del_evaluado_no_anida_botones(): void
    {
        $this->sesionComo('evaluado', $this->idFuncionarioEvaluado, $this->idUsuarioEvaluado);
        $html = $this->get('/dashboard')->assertOk()->getContent();

        $this->assertSame([], $this->anidamientosInvalidos($html));
    }

    public function test_el_tablero_del_administrador_no_anida_botones(): void
    {
        $this->sesionComo('admin', null);
        $html = $this->get('/dashboard')->assertOk()->getContent();

        $this->assertSame([], $this->anidamientosInvalidos($html));
    }
}
