<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Informe de usabilidad 11-11-2026: la fecha de la institución es la de Colombia
 * aunque el servidor declare UTC, y la exportación de la matriz protege las celdas.
 */
class ZonaHorariaYExportacionTest extends TestCase
{
    public function test_la_zona_horaria_de_la_aplicacion_es_la_de_colombia(): void
    {
        $this->assertSame('America/Bogota', config('app.timezone'));
        $this->assertSame('America/Bogota', date_default_timezone_get());
        $this->assertSame('America/Bogota', now()->timezoneName);
    }

    public function test_a_las_ocho_de_la_noche_de_colombia_hoy_sigue_siendo_hoy(): void
    {
        // 20:00 en Bogotá = 01:00 UTC del día siguiente: "hoy" debe seguir siendo el día de Colombia.
        $this->travelTo(\Carbon\Carbon::parse('2026-11-11 20:00:00', 'America/Bogota'));

        $this->assertSame('America/Bogota', date_default_timezone_get());
        $this->assertSame('2026-11-11', now()->toDateString());
        $this->assertSame('2026-11-11', today()->toDateString());
    }

    public function test_la_exportacion_de_la_matriz_entrecomilla_cada_celda_y_avisa(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requiere una base MySQL con el esquema institucional aplicado.');
        }

        session(['usuario_autenticado' => ['id_usuario' => 1, 'rol_activo' => 'admin', 'roles' => ['admin']]]);

        $this->get('/dashboard')->assertOk()
            ->assertSee("const esc = valor => '\"' + String(valor ?? '').replace(/\"/g, '\"\"') + '\"';", false)
            ->assertSee('window.fechaLocalISO()', false)
            ->assertSee("window.avisar('Matriz descargada con '", false);
    }
}
