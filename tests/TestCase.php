<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Las pruebas solo corren sobre una base cuyo nombre termine en "_test".
     * ExampleTest usa RefreshDatabase, que borra todas las tablas: si por un
     * .env mal configurado apuntara a la base de trabajo, la vaciaría. La
     * revisión ocurre al crear la aplicación, antes de que los traits de
     * base de datos toquen nada.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $config = $app['config'];
        $base = $config->get('database.connections.'.$config->get('database.default').'.database');

        if (! is_string($base) || ! str_ends_with($base, '_test')) {
            throw new \RuntimeException(
                "Pruebas detenidas: la base configurada es '{$base}'. Solo se permiten bases que terminen en '_test'."
            );
        }

        return $app;
    }

    /**
     * Las pruebas no compilan el frontend (no hay public/build/manifest.json):
     * las vistas se renderizan sin las etiquetas de Vite.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }
}
