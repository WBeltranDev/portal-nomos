<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** El catálogo de nivel jerárquico queda como el Excel "competencias nivel jerarquico - SERAG". */
class CompetenciasNivelJerarquicoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requiere una base MySQL con el esquema institucional aplicado.');
        }
        session(['usuario_autenticado' => ['id_usuario' => 1, 'rol_activo' => 'admin', 'roles' => ['admin']]]);
    }

    private function nombres(string $sistema, string $nivel): array
    {
        return collect($this->getJson("/catalogo/competencias?sistema={$sistema}&nivel={$nivel}")->assertOk()->json('nivel_jerarquico'))->pluck('nombre')->all();
    }

    public function test_cada_sistema_y_nivel_trae_las_competencias_del_excel(): void
    {
        $this->assertSame(['Apoyo Técnico Profesional', 'Comunicación Efectiva', 'Gestión de Procedimientos', 'Instrumentación de Decisiones'], $this->nombres('RENDIMIENTO_LABORAL', 'PROFESIONAL'));
        $this->assertSame(['Confiabilidad Técnica', 'Gestión del cambio', 'Responsabilidad'], $this->nombres('RENDIMIENTO_LABORAL', 'TECNICO'));
        $this->assertSame(['Manejo de la información', 'Relaciones interpersonales', 'Colaboración'], $this->nombres('RENDIMIENTO_LABORAL', 'ASISTENCIAL'));
        $this->assertCount(7, $this->nombres('ACUERDO_GESTION', 'DIRECTIVO'));
        $this->assertCount(5, $this->nombres('ACUERDO_GESTION', 'ASESOR'));
        $this->assertCount(6, $this->nombres('ACUERDO_GESTION', 'PROFESIONAL'));
        $this->assertSame(['Manejo de información', 'Relaciones interpersonales', 'Colaboración'], $this->nombres('ACUERDO_GESTION', 'ASISTENCIAL'));
    }

    public function test_los_niveles_sin_hoja_no_ofrecen_competencias(): void
    {
        $this->assertSame([], $this->nombres('RENDIMIENTO_LABORAL', 'DIRECTIVO'));
        $this->assertSame([], $this->nombres('RENDIMIENTO_LABORAL', 'ASESOR'));
        $this->assertSame([], $this->nombres('ACUERDO_GESTION', 'TECNICO'));
    }
}
