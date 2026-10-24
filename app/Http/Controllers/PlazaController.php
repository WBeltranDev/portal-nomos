<?php

namespace App\Http\Controllers;

use App\Services\ModeloPuestos;
use App\Services\OcupacionPuestos;
use App\Services\ResolutorResponsable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * S14 - Catálogo de plazas (modelo de puestos, fases A-B del plan).
 *
 * El borrador crea una plaza por cada vinculación activa que aún no la tiene,
 * con código dependencia-cargo-grado-consecutivo (D012-219-10-03), su ocupante
 * vigente y su superior según el jefe registrado. Las plazas que comparten
 * dependencia, cargo y grado quedan por revisar: Talento Humano confirma que
 * son plazas distintas y no una misma plaza con dos ocupantes. Las demás se
 * validan en bloque.
 */
class PlazaController extends Controller
{
    private function soloAdmin(): void
    {
        abort_unless(session('usuario_autenticado.rol_activo') === 'admin', 403);
    }

    /** Vinculaciones activas sin ocupación vigente en ninguna plaza. */
    private function vinculacionesSinPlaza()
    {
        return DB::table('vinculacion as v')
            ->where('v.activa', 1)
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('puesto_ocupacion as po')
                ->whereColumn('po.id_vinculacion', 'v.id_vinculacion')
                ->where('po.estado', 'VIGENTE'))
            ->orderBy('v.id_vinculacion')
            ->get();
    }

    public function index()
    {
        $this->soloAdmin();

        $plazas = DB::table('puesto as p')
            ->join('dependencia as d', 'd.id_dependencia', '=', 'p.id_dependencia')
            ->join('cargo as c', 'c.id_cargo', '=', 'p.id_cargo')
            ->leftJoin('puesto as ps', 'ps.id_puesto', '=', 'p.id_puesto_superior')
            ->leftJoin('puesto_ocupacion as po', function ($j) {
                $j->on('po.id_puesto', '=', 'p.id_puesto')->where('po.estado', '=', 'VIGENTE');
            })
            ->leftJoin('vinculacion as v', 'v.id_vinculacion', '=', 'po.id_vinculacion')
            ->leftJoin('funcionario as f', 'f.id_funcionario', '=', 'v.id_funcionario')
            ->orderBy('d.nombre')
            ->orderBy('p.codigo_plaza')
            ->get([
                'p.id_puesto', 'p.codigo_plaza', 'p.codigo_cargo', 'p.grado', 'p.nivel_jerarquico', 'p.estado',
                'p.requiere_revision', 'p.id_puesto_superior', 'p.validada_at',
                'd.nombre as dependencia', 'c.nombre as cargo', 'ps.codigo_plaza as superior_codigo',
                'po.id_vinculacion', 'po.tipo_ocupacion', 'po.vigente_desde', 'po.fecha_estimada',
                DB::raw("TRIM(CONCAT(COALESCE(f.nombres, ''), ' ', COALESCE(f.apellidos, ''))) as ocupante"),
            ]);

        $modelo = app(ModeloPuestos::class);

        return response()->json([
            'plazas' => $plazas,
            'modelo' => [
                'modo' => $modelo->modo(),
                'requisitos' => [
                    ModeloPuestos::OBSERVACION => $modelo->requisitosPendientes(ModeloPuestos::OBSERVACION),
                    ModeloPuestos::ACTIVO => $modelo->requisitosPendientes(ModeloPuestos::ACTIVO),
                ],
                'historial' => $modelo->historial(),
                'diferencias' => $this->diferenciasPendientes(),
            ],
            'resumen' => [
                'total' => $plazas->count(),
                'borrador' => $plazas->where('estado', 'BORRADOR')->count(),
                'por_revisar' => $plazas->where('estado', 'BORRADOR')->where('requiere_revision', 1)->count(),
                'validadas' => $plazas->where('estado', 'VALIDADA')->count(),
                'vinculaciones_sin_plaza' => $this->vinculacionesSinPlaza()->count(),
            ],
        ]);
    }

    /**
     * Genera el borrador para las vinculaciones activas sin plaza. Se puede
     * repetir: solo agrega las que falten (p. ej. ingresos nuevos).
     */
    public function generar()
    {
        $this->soloAdmin();

        // Los nombres se comparan sin tildes ni mayúsculas, como lo hace la base
        // (p. ej. "RECURSOS FÍSICOS" en la vinculación y "FISICOS" en el catálogo).
        $normalizar = fn ($nombre) => mb_strtoupper(\Illuminate\Support\Str::ascii(trim((string) $nombre)));
        $dependencias = DB::table('dependencia')->get(['id_dependencia', 'nombre'])->mapWithKeys(fn ($d) => [$normalizar($d->nombre) => $d->id_dependencia]);
        $cargos = DB::table('cargo')->get(['id_cargo', 'nombre'])->mapWithKeys(fn ($c) => [$normalizar($c->nombre) => $c->id_cargo]);
        $idUsuario = session('usuario_autenticado.id_usuario');

        [$creadas, $sinCatalogo] = DB::transaction(function () use ($dependencias, $cargos, $idUsuario, $normalizar) {
            $creadas = 0;
            $sinCatalogo = [];

            foreach ($this->vinculacionesSinPlaza() as $v) {
                $idDependencia = $dependencias[$normalizar($v->area)] ?? null;
                $idCargo = $cargos[$normalizar($v->cargo)] ?? null;
                if (! $idDependencia || ! $idCargo) {
                    $sinCatalogo[] = (int) $v->id_vinculacion;
                    continue;
                }

                $consecutivo = DB::table('puesto')
                    ->where('id_dependencia', $idDependencia)
                    ->where('codigo_cargo', $v->codigo_cargo)
                    ->where('grado', $v->grado_cargo)
                    ->count() + 1;
                do {
                    $codigo = sprintf('D%03d-%d-%d-%02d', $idDependencia, $v->codigo_cargo, $v->grado_cargo, $consecutivo++);
                } while (DB::table('puesto')->where('codigo_plaza', $codigo)->exists());

                $idPuesto = DB::table('puesto')->insertGetId([
                    'codigo_plaza' => $codigo,
                    'id_dependencia' => $idDependencia,
                    'id_cargo' => $idCargo,
                    'codigo_cargo' => $v->codigo_cargo,
                    'grado' => $v->grado_cargo,
                    'nivel_jerarquico' => $v->nivel_jerarquico,
                    'sistema_evaluacion' => $v->sistema_evaluacion,
                    'estado' => 'BORRADOR',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('puesto_ocupacion')->insert([
                    'id_puesto' => $idPuesto,
                    'id_vinculacion' => $v->id_vinculacion,
                    'tipo_ocupacion' => $v->tipo_vinculacion,
                    'vigente_desde' => $v->fecha_ingreso,
                    'fecha_estimada' => empty($v->fecha_ingreso),
                    'estado' => 'VIGENTE',
                    'registrado_por' => $idUsuario,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $creadas++;
            }

            // Plazas que comparten dependencia, cargo y grado: TH confirma que son distintas.
            $grupos = DB::table('puesto')
                ->where('estado', '!=', 'INACTIVA')
                ->groupBy('id_dependencia', 'codigo_cargo', 'grado')
                ->havingRaw('COUNT(*) > 1')
                ->get(['id_dependencia', 'codigo_cargo', 'grado']);
            foreach ($grupos as $g) {
                DB::table('puesto')
                    ->where('estado', 'BORRADOR')
                    ->where('id_dependencia', $g->id_dependencia)
                    ->where('codigo_cargo', $g->codigo_cargo)
                    ->where('grado', $g->grado)
                    ->update(['requiere_revision' => 1]);
            }

            // Superior: la plaza que ocupa el jefe registrado del ocupante.
            $sinSuperior = DB::table('puesto as p')
                ->join('puesto_ocupacion as po', fn ($j) => $j->on('po.id_puesto', '=', 'p.id_puesto')->where('po.estado', '=', 'VIGENTE'))
                ->join('vinculacion as v', 'v.id_vinculacion', '=', 'po.id_vinculacion')
                ->join('puesto_ocupacion as poj', fn ($j) => $j->on('poj.id_vinculacion', '=', 'v.id_vinc_jefe')->where('poj.estado', '=', 'VIGENTE'))
                ->whereNull('p.id_puesto_superior')
                ->whereColumn('poj.id_puesto', '!=', 'p.id_puesto')
                ->get(['p.id_puesto', 'poj.id_puesto as id_superior']);
            foreach ($sinSuperior as $s) {
                DB::table('puesto')->where('id_puesto', $s->id_puesto)->update(['id_puesto_superior' => $s->id_superior]);
            }

            return [$creadas, $sinCatalogo];
        });

        return response()->json([
            'success' => true,
            'creadas' => $creadas,
            'sin_catalogo' => $sinCatalogo,
            'message' => "Borrador generado: {$creadas} plaza(s) nueva(s)."
                . ($sinCatalogo ? ' ' . count($sinCatalogo) . ' vinculación(es) sin dependencia o cargo en los catálogos quedaron sin plaza.' : ''),
        ]);
    }

    /** Valida en bloque las plazas del borrador que no requieren revisión. */
    public function validarMasivo()
    {
        $this->soloAdmin();

        $validadas = DB::table('puesto')
            ->where('estado', 'BORRADOR')
            ->where('requiere_revision', 0)
            ->update([
                'estado' => 'VALIDADA',
                'validada_por' => session('usuario_autenticado.id_usuario'),
                'validada_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json(['success' => true, 'validadas' => $validadas, 'message' => "{$validadas} plaza(s) validada(s)."]);
    }

    /** Valida una plaza; para las que comparten grupo, confirma que es una plaza distinta. */
    public function validar(int $id)
    {
        $this->soloAdmin();

        $actualizada = DB::table('puesto')->where('id_puesto', $id)->where('estado', 'BORRADOR')->update([
            'estado' => 'VALIDADA',
            'requiere_revision' => 0,
            'validada_por' => session('usuario_autenticado.id_usuario'),
            'validada_at' => now(),
            'updated_at' => now(),
        ]);
        abort_unless($actualizada, 404, 'La plaza no existe o ya fue validada.');

        return response()->json(['success' => true, 'message' => 'Plaza validada.']);
    }

    /**
     * Para el formulario de traslado: plaza que ocupa hoy la persona y plazas
     * vacantes, primero las de la nueva dependencia y el nuevo cargo.
     */
    public function vacantes(Request $request)
    {
        $this->soloAdmin();

        $ocupaciones = app(OcupacionPuestos::class);
        $actual = $request->integer('id_vinc') ? $ocupaciones->plazaVigente($request->integer('id_vinc')) : null;

        $normalizar = fn ($nombre) => mb_strtoupper(\Illuminate\Support\Str::ascii(trim((string) $nombre)));
        $buscar = fn ($tabla, $id, $nombre) => $nombre === null ? null : DB::table($tabla)->get([$id, 'nombre'])
            ->first(fn ($fila) => $normalizar($fila->nombre) === $normalizar($nombre))?->{$id};

        return response()->json([
            'plaza_actual' => $actual?->codigo_plaza,
            'vacantes' => $ocupaciones->vacantes(
                $buscar('dependencia', 'id_dependencia', $request->query('area')) ?? $actual?->id_dependencia,
                $buscar('cargo', 'id_cargo', $request->query('cargo')) ?? $actual?->id_cargo,
            ),
        ]);
    }

    /** Diferencias de la observación aún sin revisar, con nombres para TH. */
    private function diferenciasPendientes(): array
    {
        $nombre = fn ($alias) => DB::raw("TRIM(CONCAT(COALESCE({$alias}.nombres, ''), ' ', COALESCE({$alias}.apellidos, ''))) as {$alias}_nombre");

        return DB::table('puesto_discrepancia as d')
            ->join('evaluacion as ev', 'ev.id_evaluacion', '=', 'd.id_evaluacion')
            ->join('vinculacion as ve', 've.id_vinculacion', '=', 'ev.id_vinc_evaluado')
            ->join('funcionario as fe', 'fe.id_funcionario', '=', 've.id_funcionario')
            ->leftJoin('vinculacion as vl', 'vl.id_vinculacion', '=', 'd.id_vinc_responsable_legado')
            ->leftJoin('funcionario as fl', 'fl.id_funcionario', '=', 'vl.id_funcionario')
            ->leftJoin('vinculacion as vp', 'vp.id_vinculacion', '=', 'd.id_vinc_responsable_puesto')
            ->leftJoin('funcionario as fp', 'fp.id_funcionario', '=', 'vp.id_funcionario')
            ->where('d.resuelta', 0)
            ->orderBy('d.id_discrepancia')
            ->limit(100)
            ->get(['d.id_discrepancia', 'd.id_evaluacion', 'd.motivo', 'd.created_at', $nombre('fe'), $nombre('fl'), $nombre('fp')])
            ->all();
    }

    /** Ejecuta ahora la comparación del modo Observación. */
    public function observar()
    {
        $this->soloAdmin();

        if (! app(ModeloPuestos::class)->observando()) {
            return response()->json(['message' => 'Pon el modelo de puestos en modo Observación para comparar.'], 422);
        }

        $r = app(ResolutorResponsable::class)->observar();

        return response()->json(['success' => true, 'resultado' => $r,
            'message' => "Comparación hecha sobre {$r['evaluaciones']} evaluación(es) abierta(s): {$r['diferencias_pendientes']} diferencia(s) pendiente(s), {$r['diferencias_nuevas']} nueva(s)."]);
    }

    /** TH revisa una diferencia y la acepta; la corrección se hace en el módulo que corresponda. */
    public function revisarDiferencia(int $id)
    {
        $this->soloAdmin();

        $actualizada = DB::table('puesto_discrepancia')->where('id_discrepancia', $id)->where('resuelta', 0)->update([
            'resuelta' => 1,
            'resuelta_por' => session('usuario_autenticado.id_usuario'),
            'resuelta_at' => now(),
            'updated_at' => now(),
        ]);
        abort_unless($actualizada, 404, 'La diferencia no existe o ya fue revisada.');

        return response()->json(['success' => true, 'message' => 'Diferencia marcada como revisada.']);
    }

    /** Cambia el modo del modelo de puestos (apagado, observación o activo). */
    public function cambiarModo(Request $request)
    {
        $this->soloAdmin();

        $data = $request->validate(['modo' => ['required', 'in:' . implode(',', ModeloPuestos::MODOS)]]);

        $error = app(ModeloPuestos::class)->cambiar($data['modo'], session('usuario_autenticado.id_usuario'));
        if ($error) {
            return response()->json(['message' => $error], 422);
        }

        return response()->json(['success' => true, 'message' => 'Modelo de puestos en modo ' . $data['modo'] . '.']);
    }

    /** Corrige el código o el superior de una plaza antes o después de validarla. */
    public function actualizar(Request $request, int $id)
    {
        $this->soloAdmin();

        $plaza = DB::table('puesto')->where('id_puesto', $id)->where('estado', '!=', 'INACTIVA')->first();
        abort_unless($plaza, 404);

        $data = $request->validate([
            'codigo_plaza' => ['required', 'string', 'max:40', 'unique:puesto,codigo_plaza,' . $id . ',id_puesto'],
            'id_puesto_superior' => ['nullable', 'integer', 'exists:puesto,id_puesto'],
        ], [
            'codigo_plaza.unique' => 'Ya existe otra plaza con ese código.',
        ]);

        $idSuperior = isset($data['id_puesto_superior']) ? (int) $data['id_puesto_superior'] : null;

        // El superior no puede ser la misma plaza ni una que dependa de ella.
        for ($actual = $idSuperior; $actual; $actual = DB::table('puesto')->where('id_puesto', $actual)->value('id_puesto_superior')) {
            if ((int) $actual === $id) {
                return response()->json(['message' => 'El superior elegido depende de esta plaza: se formaría un ciclo en la jerarquía.'], 422);
            }
        }

        DB::table('puesto')->where('id_puesto', $id)->update([
            'codigo_plaza' => trim($data['codigo_plaza']),
            'id_puesto_superior' => $idSuperior,
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Plaza actualizada.']);
    }
}
