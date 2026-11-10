<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Corrección P2 del plan de pruebas 29-10-2026: el Rector no es evaluado por
 * nadie, así que entra solo como evaluador. Quien tiene evaluador asignado (o
 * ya tiene evaluaciones) conserva la vista de evaluado.
 */
class RolEvaluadoTest extends TestCase
{
    private int $idVincRector;
    private int $idVincEvaluado;
    private int $idVincEvaluador;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requiere una base MySQL con el esquema institucional aplicado.');
        }

        DB::beginTransaction();

        $sufijo = 'T' . random_int(10000, 99999);
        $this->idVincRector = $this->crearVinculacion("{$sufijo}R", true);
        $this->idVincEvaluador = $this->crearVinculacion("{$sufijo}E", true);
        $this->idVincEvaluado = $this->crearVinculacion("{$sufijo}V", false);

        DB::table('evaluador_asignacion')->insert([
            'id_vinc_evaluador' => $this->idVincRector,
            'id_vinc_evaluado' => $this->idVincEvaluador,
            'fecha_asignacion' => now(),
        ]);
        DB::table('evaluador_asignacion')->insert([
            'id_vinc_evaluador' => $this->idVincEvaluador,
            'id_vinc_evaluado' => $this->idVincEvaluado,
            'fecha_asignacion' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    private function crearVinculacion(string $doc, bool $esEvaluador): int
    {
        $idUsuario = DB::table('usuario')->insertGetId([
            'username' => 'tmp.rol.' . strtolower($doc),
            'password' => bcrypt('temporal'),
            'rol' => $esEvaluador ? 'EVALUADOR' : 'EVALUADO',
            'activo' => 1,
        ]);
        $idFuncionario = DB::table('funcionario')->insertGetId([
            'id_usuario' => $idUsuario,
            'tipo_documento' => 'CEDULA_CIUDADANIA',
            'numero_doc' => $doc,
            'nombres' => 'Tmp',
            'apellidos' => $doc,
        ]);

        return DB::table('vinculacion')->insertGetId([
            'id_funcionario' => $idFuncionario,
            'cargo' => 'PRUEBA',
            'codigo_cargo' => 1,
            'grado_cargo' => 1,
            'nivel_jerarquico' => 'DIRECTIVO',
            'area' => 'PRUEBA',
            'tipo_vinculacion' => 'LNR',
            'sistema_evaluacion' => 'ACUERDO_GESTION',
            'es_evaluador' => $esEvaluador ? 1 : 0,
            'fecha_ingreso' => '2026-01-01',
            'activa' => 1,
        ]);
    }

    /** Roles que calcula el tablero para la persona de una vinculación. */
    private function roles(int $idVinc): array
    {
        $idFuncionario = (int) DB::table('vinculacion')->where('id_vinculacion', $idVinc)->value('id_funcionario');
        session(['usuario_autenticado' => [
            'id_usuario' => (int) DB::table('funcionario')->where('id_funcionario', $idFuncionario)->value('id_usuario'),
            'id_funcionario' => $idFuncionario,
            'rol_activo' => 'evaluador',
            'roles' => [],
        ]]);
        $this->get('/dashboard');

        return session('usuario_autenticado.roles');
    }

    public function test_el_rector_entra_solo_como_evaluador(): void
    {
        $this->assertSame(['evaluador'], $this->roles($this->idVincRector));
    }

    public function test_un_evaluador_con_evaluador_propio_conserva_la_vista_de_evaluado(): void
    {
        $this->assertEqualsCanonicalizing(['evaluado', 'evaluador'], $this->roles($this->idVincEvaluador));
    }

    public function test_un_evaluado_sin_marca_de_evaluador_conserva_la_vista_de_evaluado(): void
    {
        $this->assertSame(['evaluado'], $this->roles($this->idVincEvaluado));
    }
}
