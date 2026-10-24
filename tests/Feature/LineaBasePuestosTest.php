<?php

namespace Tests\Feature;

use App\Services\LineaBasePuestos;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * S14 — Línea base para conciliar la migración al modelo de puestos: detecta
 * cualquier alta, baja o cambio de valores clave respecto de la foto, y no
 * guarda nombres ni documentos.
 *
 * La prueba trabaja sobre la base configurada en el entorno y se ejecuta dentro
 * de una transacción que se revierte al terminar, de modo que no deja datos.
 */
class LineaBasePuestosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requiere una base MySQL con el esquema institucional aplicado.');
        }

        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    private function crearVinculacion(): int
    {
        $doc = 'L' . random_int(100000, 999999);
        $idFuncionario = DB::table('funcionario')->insertGetId([
            'id_usuario' => DB::table('usuario')->insertGetId(['username' => "tmp.base.{$doc}", 'password' => 'x', 'rol' => 'EVALUADO', 'activo' => 1]),
            'tipo_documento' => 'CEDULA_CIUDADANIA',
            'numero_doc' => $doc,
            'nombres' => 'Nombre Privado',
            'apellidos' => $doc,
        ]);

        return DB::table('vinculacion')->insertGetId([
            'id_funcionario' => $idFuncionario, 'cargo' => 'PROFESIONAL', 'codigo_cargo' => 1, 'grado_cargo' => 1,
            'nivel_jerarquico' => 'PROFESIONAL', 'area' => 'PRUEBA', 'tipo_vinculacion' => 'PROVISIONALIDAD',
            'sistema_evaluacion' => 'RENDIMIENTO_LABORAL', 'es_evaluador' => 0, 'fecha_ingreso' => '2026-01-01', 'activa' => 1,
        ]);
    }

    public function test_sin_cambios_no_hay_diferencias(): void
    {
        $servicio = new LineaBasePuestos();
        $foto = $servicio->capturar();

        $this->assertSame([], $servicio->comparar($foto));
    }

    public function test_detecta_altas_y_cambios_de_estado(): void
    {
        $idVinc = $this->crearVinculacion();
        $servicio = new LineaBasePuestos();
        $foto = $servicio->capturar();

        DB::table('vinculacion')->where('id_vinculacion', $idVinc)->update(['activa' => 0]);
        $this->crearVinculacion();

        $diferencias = $servicio->comparar($foto);

        $this->assertArrayHasKey('vinculacion', $diferencias);
        $this->assertSame($foto['tablas']['vinculacion']['total'] + 1, $diferencias['vinculacion']['total']['despues']);
        $this->assertSame(['antes' => 0, 'despues' => 1], $diferencias['vinculacion']['por_estado']['0']);
    }

    public function test_la_foto_no_guarda_nombres_ni_documentos(): void
    {
        $this->crearVinculacion();

        $json = json_encode((new LineaBasePuestos())->capturar());

        $this->assertStringNotContainsString('Nombre Privado', $json);
        $this->assertStringNotContainsString('numero_doc', $json);
    }
}
