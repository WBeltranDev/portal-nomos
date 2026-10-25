<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * S14 - Carga de la planta oficial de Talento Humano (Excel de funcionarios y
 * vacantes) al catálogo de plazas.
 *
 * Reglas acordadas: el Excel manda sobre los datos del cargo (área, jefe,
 * correo, fechas...) y se corrigen directo; cambiar el jefe reasigna el
 * evaluador, salvo que haya una evaluación abierta con el anterior (eso es un
 * traslado y queda pendiente). El correo del cargo pertenece a la plaza y la
 * reconoce en cargas futuras. Las vacantes son plazas sin ocupante; los
 * funcionarios ficticios de "VACANTE" se inactivan. Cargos nuevos se agregan
 * al catálogo; lo que no se puede ubicar (p. ej. una dependencia ambigua)
 * queda pendiente para resolver en el panel.
 *
 * analizar() no escribe nada (ensayo); aplicar() hace todo en una transacción.
 */
class CargaPlanta
{
    private const SISTEMAS = ['RENDIMIENTO LABORAL' => 'RENDIMIENTO_LABORAL', 'RL' => 'RENDIMIENTO_LABORAL', 'ACUERDO DE GESTION' => 'ACUERDO_GESTION', 'AG' => 'ACUERDO_GESTION'];
    private const NIVELES = ['ASISTENTE' => 'ASISTENCIAL'];
    private const SIN_JEFE = ['CONSEJO SUPERIOR'];

    private array $dependencias = [];
    private array $cargos = [];

    public function __construct(private LectorXlsx $lector)
    {
    }

    public static function normalizar(mixed $texto): string
    {
        return preg_replace('/\s+/', ' ', mb_strtoupper(trim(Str::ascii((string) $texto))));
    }

    // ------------------------------------------------------------ Lectura

    /** Personas, vacantes y cuadro de conformación del Excel. */
    public function leer(string $ruta): array
    {
        $hojas = $this->lector->leer($ruta);

        [$hojaPersonas, $filaEncabezado] = $this->ubicarHoja($hojas, ['NO IDENTIFICACION', 'NOMBRE FUNCIONARIO']);
        if (! $hojaPersonas) {
            throw new RuntimeException('No encontré la hoja de funcionarios: debe tener las columnas «No IDENTIFICACIÓN» y «NOMBRE FUNCIONARIO».');
        }
        $columnas = $this->columnas($hojas[$hojaPersonas][$filaEncabezado]);
        $col = fn (string ...$opciones) => $this->columna($columnas, $opciones);
        $c = [
            'cedula' => $col('NO IDENTIFICACION'), 'nombre' => $col('NOMBRE FUNCIONARIO'), 'cargo' => $col('CARGO'),
            'codigo' => $col('CODIGO'), 'grado' => $col('GRADO'), 'area' => $col('AREA'), 'ingreso' => $col('FECHA INGRESO'),
            'retiro' => $col('FECHA RETIRO'), 'tipo' => $col('TIPO DE VINCULACION'), 'correo' => $col('CORREO DEL CARGO'),
            'nivel' => $col('NIVEL'), 'jefe' => $col('JEFE INMEDIATO'), 'eje' => $col('EJE MISIONAL'), 'rol' => $col('ROL'),
            'sistema' => $col('TIPO DE EVALUACION'),
        ];
        if (in_array(null, [$c['cedula'], $c['cargo'], $c['codigo'], $c['grado'], $c['area'], $c['correo']], true)) {
            throw new RuntimeException('Faltan columnas obligatorias en la hoja de funcionarios (cédula, cargo, código, grado, área o correo del cargo).');
        }

        $personas = [];
        foreach ($hojas[$hojaPersonas] as $n => $fila) {
            if ($n <= $filaEncabezado || empty($fila[$c['cedula']])) {
                continue;
            }
            $v = fn ($k) => $c[$k] ? ($fila[$c[$k]] ?? null) : null;
            $personas[] = [
                'fila' => $n,
                'cedula' => (string) (is_numeric($v('cedula')) ? (int) $v('cedula') : trim((string) $v('cedula'))),
                'nombre' => trim((string) $v('nombre')),
                'cargo' => self::normalizar($v('cargo')),
                'codigo' => (int) $v('codigo'),
                'grado' => (int) $v('grado'),
                'area' => self::normalizar($v('area')),
                'fecha_ingreso' => LectorXlsx::fechaExcel($v('ingreso')),
                'fecha_retiro' => LectorXlsx::fechaExcel($v('retiro')),
                'tipo_vinculacion' => str_replace(' ', '_', self::normalizar($v('tipo'))),
                // Algunas celdas traen dos correos separados por salto de línea.
                'correo' => mb_strtolower(preg_replace('/\s+/', ' ', trim((string) $v('correo')))),
                'nivel' => $this->nivel($v('nivel')),
                'jefe' => self::normalizar($v('jefe')),
                'eje' => self::normalizar($v('eje')) === 'SI',
                'evaluador' => str_contains(self::normalizar($v('rol')), 'EVALUADOR'),
                'sistema' => self::SISTEMAS[self::normalizar($v('sistema'))] ?? null,
            ];
        }

        $vacantes = [];
        $conformacion = [];
        [$hojaVacantes, $filaVac] = $this->ubicarHoja($hojas, ['CANTIDAD', 'TIPO DE VACANTE']);
        if ($hojaVacantes) {
            $cv = $this->columnas($hojas[$hojaVacantes][$filaVac]);
            $vcol = fn (string ...$o) => $this->columna($cv, $o);
            $k = ['cargo' => $vcol('CARGO'), 'codigo' => $vcol('CODIGO'), 'grado' => $vcol('GRADO'), 'nivel' => $vcol('NIVEL'),
                'tipo' => $vcol('TIPO DE VINCULACION'), 'sistema' => $vcol('SISTEMA DE EVALUACION'), 'area' => $vcol('AREA'), 'cantidad' => $vcol('CANTIDAD')];
            foreach ($hojas[$hojaVacantes] as $n => $fila) {
                $primera = self::normalizar(reset($fila) ?: '');
                if ($n <= $filaVac) {
                    continue;
                }
                if (str_starts_with($primera, 'TOTAL')) {
                    break;
                }
                if (empty($fila[$k['cargo']]) || empty($fila[$k['cantidad']])) {
                    continue;
                }
                $vacantes[] = [
                    'fila' => $n,
                    'cargo' => self::normalizar($fila[$k['cargo']]),
                    'codigo' => (int) ($fila[$k['codigo']] ?? 0),
                    'grado' => (int) ($fila[$k['grado']] ?? 0),
                    'nivel' => $this->nivel($fila[$k['nivel']] ?? null),
                    'tipo_vinculacion' => str_replace(' ', '_', self::normalizar($fila[$k['tipo']] ?? '')),
                    'sistema' => self::SISTEMAS[self::normalizar($fila[$k['sistema']] ?? '')] ?? null,
                    'area' => self::normalizar($fila[$k['area']] ?? ''),
                    'cantidad' => (int) $fila[$k['cantidad']],
                ];
            }

            // Cuadro de conformación de la planta (tipo de vinculación => cantidad), si viene.
            foreach ($hojas[$hojaVacantes] as $fila) {
                $valores = array_values($fila);
                foreach ($valores as $i => $valor) {
                    if (is_string($valor) && is_numeric($valores[$i + 1] ?? null) && ! str_contains(self::normalizar($valor), 'TOTAL')
                        && preg_match('/PROVISIONALIDAD|PERIODO FIJO|LIBRE NOMBRAMIENTO/', self::normalizar($valor))) {
                        $conformacion[self::normalizar($valor)] = (int) $valores[$i + 1];
                    }
                }
            }
        }

        return compact('personas', 'vacantes', 'conformacion');
    }

    private function nivel(mixed $valor): ?string
    {
        $n = self::normalizar($valor);

        return $n === '' ? null : (self::NIVELES[$n] ?? $n);
    }

    private function ubicarHoja(array $hojas, array $encabezados): array
    {
        foreach ($hojas as $nombre => $filas) {
            foreach ($filas as $n => $fila) {
                $textos = array_map(fn ($v) => self::normalizar($v), $fila);
                if (count(array_filter($encabezados, fn ($e) => (bool) array_filter($textos, fn ($t) => str_contains($t, $e)))) === count($encabezados)) {
                    return [$nombre, $n];
                }
                if ($n > 20) {
                    break;
                }
            }
        }

        return [null, null];
    }

    private function columnas(array $encabezado): array
    {
        return array_map(fn ($v) => self::normalizar($v), $encabezado);
    }

    private function columna(array $columnas, array $opciones): ?int
    {
        foreach ($opciones as $opcion) {
            foreach ($columnas as $indice => $titulo) {
                if ($titulo === $opcion) {
                    return $indice;
                }
            }
            foreach ($columnas as $indice => $titulo) {
                if (str_contains($titulo, $opcion)) {
                    return $indice;
                }
            }
        }

        return null;
    }

    // ------------------------------------------------------------ Análisis

    private function cargarCatalogos(): void
    {
        $this->dependencias = DB::table('dependencia')->get(['id_dependencia', 'nombre'])
            ->mapWithKeys(fn ($d) => [self::normalizar($d->nombre) => $d])->all();
        $this->cargos = DB::table('cargo')->get(['id_cargo', 'nombre'])
            ->mapWithKeys(fn ($c) => [self::normalizar($c->nombre) => $c])->all();
    }

    /**
     * Qué haría la carga, sin escribir nada: cambios por persona, plazas a crear
     * o actualizar, cargos nuevos, vacantes, inactivaciones y pendientes.
     */
    public function analizar(array $planta): array
    {
        $this->cargarCatalogos();

        $plataforma = DB::table('funcionario as f')
            ->join('vinculacion as v', 'v.id_funcionario', '=', 'f.id_funcionario')
            ->leftJoin('vinculacion as vj', 'vj.id_vinculacion', '=', 'v.id_vinc_jefe')
            ->orderByDesc('v.activa')
            ->orderByDesc('v.id_vinculacion')
            ->get(['f.id_funcionario', 'f.id_usuario', 'f.numero_doc', 'f.nombres', 'f.apellidos', 'f.correo_cargo', 'v.*', 'vj.id_vinculacion as jefe_vinc'])
            ->unique('numero_doc')
            ->keyBy('numero_doc');

        $porCedula = collect($planta['personas'])->keyBy('cedula');
        $cedulaPorNombre = collect($planta['personas'])->mapWithKeys(fn ($p) => [self::normalizar($p['nombre']) => $p['cedula']]);

        $cambios = [];
        $pendientes = [];
        $cargosNuevos = [];
        $personas = [];

        foreach ($planta['personas'] as $p) {
            $actual = $plataforma[$p['cedula']] ?? null;
            if (! $actual) {
                $pendientes[] = ['tipo' => 'PERSONA_NO_REGISTRADA', 'descripcion' => "{$p['nombre']} (cédula {$p['cedula']}) está en la planta pero no en la plataforma: créalo en Usuarios.", 'datos' => $p];
                continue;
            }

            $dependencia = $this->dependencias[$p['area']] ?? null;
            if (! isset($this->cargos[$p['cargo']]) && ! isset($cargosNuevos[$p['cargo']])) {
                $cargosNuevos[$p['cargo']] = ['nombre' => $p['cargo'], 'codigo' => $p['codigo'], 'grado' => $p['grado'], 'nivel' => $p['nivel']];
            }
            if (! $dependencia) {
                $pendientes[] = ['tipo' => 'PERSONA_SIN_PLAZA', 'descripcion' => "La dependencia «{$p['area']}» de {$p['nombre']} no está en el catálogo.", 'datos' => $p];
            }

            // Jefe: por nombre dentro del mismo Excel; «CONSEJO SUPERIOR» = sin jefe.
            $cedulaJefe = $cedulaPorNombre[$p['jefe']] ?? null;
            $idJefe = $cedulaJefe && isset($plataforma[$cedulaJefe]) ? (int) $plataforma[$cedulaJefe]->id_vinculacion : null;
            $jefeDesconocido = $p['jefe'] !== '' && ! $idJefe && ! in_array($p['jefe'], self::SIN_JEFE, true);

            $nuevos = [
                'area' => $dependencia->nombre ?? $actual->area,
                'cargo' => isset($this->cargos[$p['cargo']]) ? $this->cargos[$p['cargo']]->nombre : $p['cargo'],
                'codigo_cargo' => $p['codigo'],
                'grado_cargo' => $p['grado'],
                'nivel_jerarquico' => $p['nivel'] ?? $actual->nivel_jerarquico,
                'tipo_vinculacion' => $p['tipo_vinculacion'] ?: $actual->tipo_vinculacion,
                'sistema_evaluacion' => $p['sistema'] ?? $actual->sistema_evaluacion,
                'fecha_ingreso' => $p['fecha_ingreso'] ?? $actual->fecha_ingreso,
                'fecha_retiro' => $p['fecha_retiro'],
                'es_evaluador' => $p['evaluador'] ? 1 : 0,
                'aplica_eje_misional' => $p['eje'] ? 1 : 0,
                'id_vinc_jefe' => $jefeDesconocido ? $actual->id_vinc_jefe : $idJefe,
                'activa' => 1,
            ];

            $diferencias = [];
            foreach ($nuevos as $campo => $valor) {
                $antes = $actual->{$campo};
                if (self::normalizar($antes) !== self::normalizar($valor)) {
                    $diferencias[$campo] = ['antes' => $antes, 'despues' => $valor];
                }
            }
            if (mb_strtolower(preg_replace('/\s+/', ' ', trim((string) $actual->correo_cargo))) !== $p['correo']) {
                $diferencias['correo_cargo'] = ['antes' => $actual->correo_cargo, 'despues' => $p['correo']];
            }

            // Cambia el jefe y hay evaluación abierta con el evaluador actual: es un traslado.
            $reasignar = isset($diferencias['id_vinc_jefe']) && $nuevos['id_vinc_jefe'];
            if ($reasignar) {
                $evaluadorActual = DB::table('evaluador_asignacion')->where('id_vinc_evaluado', $actual->id_vinculacion)->value('id_vinc_evaluador');
                $abierta = $evaluadorActual && DB::table('evaluacion')
                    ->where('id_vinc_evaluado', $actual->id_vinculacion)
                    ->where('id_vinc_evaluador', $evaluadorActual)
                    ->where('estado', 'EN_PROCESO')
                    ->exists();
                if ($abierta) {
                    $reasignar = false;
                    unset($diferencias['id_vinc_jefe']);
                    $nuevos['id_vinc_jefe'] = $actual->id_vinc_jefe;
                    $pendientes[] = ['tipo' => 'REQUIERE_TRASLADO', 'descripcion' => "{$p['nombre']} cambia de jefe según la planta, pero tiene una evaluación abierta con su evaluador actual: registra el traslado para partirla en tramos.", 'datos' => ['cedula' => $p['cedula'], 'id_vinc_jefe_nuevo' => $idJefe]];
                }
            }
            if ($jefeDesconocido) {
                $pendientes[] = ['tipo' => 'PERSONA_SIN_PLAZA', 'descripcion' => "No encontré al jefe «{$p['jefe']}» de {$p['nombre']} entre los funcionarios de la planta.", 'datos' => $p];
            }

            if ($diferencias) {
                $cambios[] = ['cedula' => $p['cedula'], 'nombre' => $p['nombre'], 'diferencias' => $diferencias];
            }
            $personas[] = ['excel' => $p, 'actual' => $actual, 'nuevos' => $nuevos, 'reasignar' => $reasignar,
                'dependencia' => $dependencia, 'cedula_jefe' => $cedulaJefe, 'diferencias' => $diferencias];
        }

        // Activos en la plataforma que no están en la planta: ficticios de vacante o casos para TH.
        $inactivar = [];
        foreach ($plataforma as $cedula => $actual) {
            if (! $actual->activa || isset($porCedula[$cedula])) {
                continue;
            }
            $nombre = trim("{$actual->nombres} {$actual->apellidos}");
            if (trim($cedula, '0') === '' || str_contains(self::normalizar($nombre), 'VACANTE')) {
                $inactivar[] = ['id_vinculacion' => (int) $actual->id_vinculacion, 'id_usuario' => $actual->id_usuario, 'nombre' => $nombre, 'cargo' => $actual->cargo];
            } else {
                $pendientes[] = ['tipo' => 'PERSONA_SIN_PLAZA', 'descripcion' => "{$nombre} (cédula {$cedula}) está activo en la plataforma pero no aparece en la planta.", 'datos' => ['cedula' => $cedula, 'id_vinculacion' => (int) $actual->id_vinculacion]];
            }
        }

        // Vacantes: con dependencia exacta se crean; si no, una pendiente por plaza.
        $vacantes = [];
        foreach ($planta['vacantes'] as $vac) {
            if (! isset($this->cargos[$vac['cargo']]) && ! isset($cargosNuevos[$vac['cargo']])) {
                $cargosNuevos[$vac['cargo']] = ['nombre' => $vac['cargo'], 'codigo' => $vac['codigo'], 'grado' => $vac['grado'], 'nivel' => $vac['nivel']];
            }
            $dependencia = $this->dependencias[$vac['area']] ?? null;
            if ($dependencia) {
                $vacantes[] = $vac + ['dependencia' => $dependencia];
                continue;
            }
            for ($i = 1; $i <= $vac['cantidad']; $i++) {
                $pendientes[] = ['tipo' => 'VACANTE_SIN_DEPENDENCIA', 'descripcion' => "Vacante de {$vac['cargo']} ({$vac['codigo']}-{$vac['grado']}) en «{$vac['area']}»" . ($vac['cantidad'] > 1 ? " ({$i} de {$vac['cantidad']})" : '') . ': elige la dependencia exacta.', 'datos' => array_merge($vac, ['cantidad' => 1])];
            }
        }

        $advertencias = $this->advertenciasConformacion($planta);

        return [
            'resumen' => [
                'personas_en_planta' => count($planta['personas']),
                'personas_reconocidas' => count($personas),
                'personas_con_cambios' => count($cambios),
                'reasignaciones_de_evaluador' => count(array_filter($personas, fn ($x) => $x['reasignar'])),
                'vacantes_en_planta' => array_sum(array_column($planta['vacantes'], 'cantidad')),
                'vacantes_a_crear' => array_sum(array_column($vacantes, 'cantidad')),
                'cargos_nuevos' => count($cargosNuevos),
                'ficticios_a_inactivar' => count($inactivar),
                'pendientes' => count($pendientes),
            ],
            'cambios' => $cambios,
            'cargos_nuevos' => array_values($cargosNuevos),
            'inactivar' => $inactivar,
            'pendientes' => $pendientes,
            'advertencias' => $advertencias,
            // Datos internos para aplicar().
            '_personas' => $personas,
            '_vacantes' => $vacantes,
        ];
    }

    private function advertenciasConformacion(array $planta): array
    {
        if (! $planta['conformacion']) {
            return [];
        }

        $real = [];
        $tipo = fn ($t) => match (true) {
            str_contains($t, 'PROVISIONALIDAD') => 'PROVISIONALIDAD',
            str_contains($t, 'PERIODO') => 'PERIODO FIJO',
            str_contains($t, 'LNR'), str_contains($t, 'LIBRE') => 'LIBRE NOMBRAMIENTO',
            default => $t,
        };
        foreach ($planta['personas'] as $p) {
            $real[$tipo(str_replace('_', ' ', $p['tipo_vinculacion']))] = ($real[$tipo(str_replace('_', ' ', $p['tipo_vinculacion']))] ?? 0) + 1;
        }
        foreach ($planta['vacantes'] as $v) {
            $real[$tipo(str_replace('_', ' ', $v['tipo_vinculacion']))] = ($real[$tipo(str_replace('_', ' ', $v['tipo_vinculacion']))] ?? 0) + $v['cantidad'];
        }

        $advertencias = [];
        foreach ($planta['conformacion'] as $etiqueta => $cantidad) {
            $calculado = $real[$tipo($etiqueta)] ?? 0;
            if ($calculado !== $cantidad) {
                $advertencias[] = "El cuadro de conformación dice {$cantidad} en «{$etiqueta}», pero el listado más las vacantes suman {$calculado}.";
            }
        }

        return $advertencias;
    }

    // ------------------------------------------------------------ Aplicación

    /** Aplica la carga (todo o nada) y la registra. Retorna el análisis aplicado y el id de la carga. */
    public function aplicar(string $ruta, string $nombreArchivo, ?int $idUsuario): array
    {
        $analisis = $this->analizar($this->leer($ruta));
        $ocupaciones = app(OcupacionPuestos::class);

        $idCarga = DB::transaction(function () use ($analisis, $ruta, $nombreArchivo, $idUsuario, $ocupaciones) {
            $hoy = now()->toDateString();

            // 1. Cargos nuevos al catálogo.
            foreach ($analisis['cargos_nuevos'] as $cargo) {
                $this->cargos[$cargo['nombre']] = (object) [
                    'id_cargo' => DB::table('cargo')->insertGetId([
                        'nombre' => $cargo['nombre'], 'codigo_cargo' => $cargo['codigo'], 'grado_cargo' => $cargo['grado'],
                        'nivel_jerarquico' => $cargo['nivel'] ?? 'PROFESIONAL', 'activo' => 1, 'created_at' => now(), 'updated_at' => now(),
                    ]),
                    'nombre' => $cargo['nombre'],
                ];
            }

            // 2. Datos del cargo según la planta (el Excel manda) y evaluador = jefe.
            foreach ($analisis['_personas'] as $x) {
                $vinc = (int) $x['actual']->id_vinculacion;
                if ($x['diferencias']) {
                    $campos = array_diff_key($x['nuevos'], ['activa' => 1]);
                    DB::table('vinculacion')->where('id_vinculacion', $vinc)->update(array_intersect_key($campos, $x['diferencias']));
                    if (isset($x['diferencias']['correo_cargo'])) {
                        DB::table('funcionario')->where('id_funcionario', $x['actual']->id_funcionario)->update(['correo_cargo' => $x['excel']['correo']]);
                    }
                }
                if ($x['reasignar']) {
                    DB::table('evaluador_asignacion')->where('id_vinc_evaluado', $vinc)->delete();
                    DB::table('evaluador_asignacion')->insert(['id_vinc_evaluador' => $x['nuevos']['id_vinc_jefe'], 'id_vinc_evaluado' => $vinc, 'fecha_asignacion' => now()]);
                }
            }

            // 3. Funcionarios ficticios de vacante: se inactivan (la vacante será una plaza).
            foreach ($analisis['inactivar'] as $f) {
                DB::table('vinculacion')->where('id_vinculacion', $f['id_vinculacion'])->update(['activa' => 0, 'es_vacante' => 1]);
                if ($f['id_usuario']) {
                    DB::table('usuario')->where('id_usuario', $f['id_usuario'])->update(['activo' => 0]);
                }
                $ocupaciones->cerrar($f['id_vinculacion'], $hoy, 'Registro ficticio de vacante');
            }

            // 4. Plazas ocupadas: el correo del cargo reconoce la plaza; primero se
            //    liberan las ocupaciones que cambian y luego se ocupan las nuevas.
            $destinos = [];
            foreach ($analisis['_personas'] as $x) {
                if (! $x['dependencia']) {
                    continue;
                }
                $idPuesto = $this->plazaParaPersona($x);
                $vinc = (int) $x['actual']->id_vinculacion;
                $destinos[$vinc] = $idPuesto;
                if (($ocupaciones->plazaVigente($vinc)->id_puesto ?? null) != $idPuesto) {
                    $ocupaciones->cerrar($vinc, $hoy, 'Carga de planta');
                }
                $otro = $ocupaciones->ocupante($idPuesto);
                if ($otro && $otro !== $vinc) {
                    $ocupaciones->cerrar($otro, $hoy, 'Carga de planta: la plaza la ocupa otra persona');
                }
            }
            foreach ($destinos as $vinc => $idPuesto) {
                if (! $ocupaciones->plazaVigente($vinc)) {
                    if ($error = $ocupaciones->ocupar($idPuesto, $vinc, $hoy, 'Carga de planta')) {
                        throw new RuntimeException($error);
                    }
                    $ingreso = DB::table('vinculacion')->where('id_vinculacion', $vinc)->value('fecha_ingreso');
                    DB::table('puesto_ocupacion')->where('id_vinculacion', $vinc)->where('estado', 'VIGENTE')
                        ->update(['vigente_desde' => $ingreso ?: $hoy, 'fecha_estimada' => $ingreso ? 0 : 1]);
                }
            }

            // 5. Superior de cada plaza: la plaza que ocupa el jefe inmediato.
            foreach ($analisis['_personas'] as $x) {
                $vinc = (int) $x['actual']->id_vinculacion;
                if (! isset($destinos[$vinc])) {
                    continue;
                }
                $jefe = DB::table('vinculacion')->where('id_vinculacion', $vinc)->value('id_vinc_jefe');
                $superior = $jefe ? ($destinos[(int) $jefe] ?? $ocupaciones->plazaVigente((int) $jefe)?->id_puesto) : null;
                DB::table('puesto')->where('id_puesto', $destinos[$vinc])->update(['id_puesto_superior' => $superior, 'updated_at' => now()]);
            }

            // 6. Vacantes con dependencia exacta: solo las que falten (la carga se puede repetir).
            foreach ($analisis['_vacantes'] as $vac) {
                $idCargo = $this->cargos[$vac['cargo']]->id_cargo;
                $existentes = DB::table('puesto as p')
                    ->where('p.id_dependencia', $vac['dependencia']->id_dependencia)->where('p.id_cargo', $idCargo)->where('p.grado', $vac['grado'])
                    ->where('p.estado', '!=', 'INACTIVA')
                    ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('puesto_ocupacion as po')->whereColumn('po.id_puesto', 'p.id_puesto')->where('po.estado', 'VIGENTE'))
                    ->count();
                for ($i = $existentes; $i < $vac['cantidad']; $i++) {
                    $this->crearPlaza((int) $vac['dependencia']->id_dependencia, $idCargo, $vac['codigo'], $vac['grado'], $vac['nivel'], $vac['sistema'], null);
                }
            }

            // 7. Registro de la carga y pendientes para el panel.
            $resumen = $analisis['resumen'] + ['advertencias' => $analisis['advertencias']];
            $idCarga = DB::table('carga_planta')->insertGetId([
                'archivo' => mb_substr($nombreArchivo, 0, 255),
                'huella_archivo' => hash_file('sha256', $ruta),
                'resumen' => json_encode($resumen, JSON_UNESCAPED_UNICODE),
                'id_usuario' => $idUsuario,
                'created_at' => now(),
            ]);
            foreach ($analisis['pendientes'] as $p) {
                DB::table('carga_planta_pendiente')->insert([
                    'id_carga' => $idCarga, 'tipo' => $p['tipo'], 'descripcion' => mb_substr($p['descripcion'], 0, 500),
                    'datos' => json_encode($p['datos'], JSON_UNESCAPED_UNICODE), 'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            return $idCarga;
        });

        return ['id_carga' => $idCarga] + array_diff_key($analisis, ['_personas' => 1, '_vacantes' => 1]);
    }

    /** Crea la plaza vacante de un pendiente cuando TH elige su dependencia en el panel. */
    public function crearVacante(array $vacante, int $idDependencia): int
    {
        $this->cargarCatalogos();
        $idCargo = $this->cargos[self::normalizar($vacante['cargo'])]->id_cargo
            ?? throw new RuntimeException("El cargo «{$vacante['cargo']}» no está en el catálogo.");

        return $this->crearPlaza($idDependencia, (int) $idCargo, (int) $vacante['codigo'], (int) $vacante['grado'], $vacante['nivel'] ?? null, $vacante['sistema'] ?? null, null);
    }

    /** Plaza de la persona: la que tiene su correo de cargo, actualizada; o una nueva. */
    private function plazaParaPersona(array $x): int
    {
        $p = $x['excel'];
        $idCargo = $this->cargos[$p['cargo']]->id_cargo;
        $atributos = [
            'id_dependencia' => (int) $x['dependencia']->id_dependencia, 'id_cargo' => (int) $idCargo,
            'codigo_cargo' => $p['codigo'], 'grado' => $p['grado'], 'nivel_jerarquico' => $p['nivel'],
            'sistema_evaluacion' => $p['sistema'],
        ];

        $plaza = $p['correo'] ? DB::table('puesto')->where('correo_cargo', $p['correo'])->first() : null;
        if (! $plaza) {
            return $this->crearPlaza($atributos['id_dependencia'], $atributos['id_cargo'], $p['codigo'], $p['grado'], $p['nivel'], $p['sistema'], $p['correo'] ?: null);
        }

        // Si cambió lo que define la plaza, TH la vuelve a validar.
        $cambio = (int) $plaza->id_dependencia !== $atributos['id_dependencia'] || (int) $plaza->id_cargo !== $atributos['id_cargo']
            || (int) $plaza->grado !== $atributos['grado'] || (int) $plaza->codigo_cargo !== $atributos['codigo_cargo'];
        DB::table('puesto')->where('id_puesto', $plaza->id_puesto)->update($atributos + ['updated_at' => now()]
            + ($cambio ? ['estado' => 'BORRADOR', 'validada_por' => null, 'validada_at' => null] : []));

        return (int) $plaza->id_puesto;
    }

    private function crearPlaza(int $idDependencia, int $idCargo, int $codigo, int $grado, ?string $nivel, ?string $sistema, ?string $correo): int
    {
        $consecutivo = DB::table('puesto')->where('id_dependencia', $idDependencia)->where('codigo_cargo', $codigo)->where('grado', $grado)->count() + 1;
        do {
            $codigoPlaza = sprintf('D%03d-%d-%d-%02d', $idDependencia, $codigo, $grado, $consecutivo++);
        } while (DB::table('puesto')->where('codigo_plaza', $codigoPlaza)->exists());

        return DB::table('puesto')->insertGetId([
            'codigo_plaza' => $codigoPlaza, 'correo_cargo' => $correo, 'id_dependencia' => $idDependencia, 'id_cargo' => $idCargo,
            'codigo_cargo' => $codigo, 'grado' => $grado, 'nivel_jerarquico' => $nivel, 'sistema_evaluacion' => $sistema,
            'estado' => 'BORRADOR', 'requiere_revision' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
