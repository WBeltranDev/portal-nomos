<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;

/**
 * Traslados en bloque (rotación). Un lote agrupa traslados normales: cada persona
 * conserva su registro en `traslado` (programar y confirmar usan las mismas
 * funciones del traslado individual), pero el lote se programa y se confirma
 * todo junto, o no se mueve nadie.
 *
 * Reglas: todos los tramos de origen deben estar calificados (o la persona no
 * tiene evaluación en el periodo), se respeta el plazo de recursos, y una plaza
 * destino puede estar ocupada si su ocupante también sale en el mismo lote.
 */
class TrasladoLote
{
    private const COLUMNAS = [
        'cedula' => 'No IDENTIFICACION',
        'nombre' => 'NOMBRE FUNCIONARIO',
        'dependencia' => 'DEPENDENCIA ACTUAL',
        'plaza_actual' => 'PLAZA ACTUAL',
        'plaza_destino' => 'PLAZA DESTINO (CODIGO)',
        'evaluador' => 'CEDULA NUEVO EVALUADOR (OPCIONAL)',
        'observacion' => 'OBSERVACION',
    ];

    // ------------------------------------------------------------ Plantilla

    /** Genera el Excel de la plantilla; con $cargo trae precargadas las personas de ese cargo. */
    public function plantilla(string $ruta, ?string $cargo = null): array
    {
        $encabezado = new Style(fontBold: true, fontName: 'Arial', fontSize: 10, fontColor: 'FFFFFF', backgroundColor: '1F4E78', shouldWrapText: true, cellVerticalAlignment: CellVerticalAlignment::CENTER);
        $informativo = new Style(fontName: 'Arial', fontSize: 10, fontColor: '595959', backgroundColor: 'EDEDED');
        $resaltado = new Style(fontName: 'Arial', fontSize: 10, fontBold: true, backgroundColor: 'FFF2CC');
        $texto = new Style(fontName: 'Arial', fontSize: 10);
        $titulo = new Style(fontBold: true, fontName: 'Arial', fontSize: 14, fontColor: '1F4E78');
        $subtitulo = new Style(fontBold: true, fontName: 'Arial', fontSize: 11, fontColor: '1F4E78');
        $parrafo = new Style(fontName: 'Arial', fontSize: 10, shouldWrapText: true);

        $personas = $this->personasActivas($cargo);
        $plazas = $this->catalogoPlazas();

        $writer = new Writer();
        $writer->openToFile($ruta);

        $writer->getCurrentSheet()->setName('INSTRUCCIONES');
        $writer->getCurrentSheet()->setColumnWidth(110, 1);
        foreach ([
            [$titulo, 'Traslados en bloque (rotación) — formato de carga'],
            [$parrafo, 'Generado el ' . now('America/Bogota')->format('d/m/Y H:i') . ($cargo ? " con los funcionarios activos del cargo «{$cargo}»." : '. La hoja ROTACION va sin personas: agregue una fila por cada traslado.')],
            [$parrafo, ''],
            [$subtitulo, 'Qué hay que hacer'],
            [$parrafo, '1. En la hoja ROTACION, escriba en PLAZA DESTINO el código de la plaza a la que pasa cada persona (los códigos están en la hoja PLAZAS). Las filas con PLAZA DESTINO vacía se ignoran.'],
            [$parrafo, '2. CEDULA NUEVO EVALUADOR es opcional: si la deja vacía, el evaluador será quien ocupe la plaza superior de la plaza destino (columna EVALUADOR POR DEFECTO de la hoja PLAZAS). Escríbala solo si debe ser otra persona.'],
            [$parrafo, '3. Guarde el archivo como .xlsx sin cambiar los nombres de las hojas ni de los encabezados y cárguelo en Administración → Traslados → Traslados en bloque. La plataforma muestra una revisión fila por fila antes de programar nada.'],
            [$parrafo, ''],
            [$subtitulo, 'Reglas importantes'],
            [$parrafo, '• Es una rotación: una plaza destino puede estar hoy ocupada, siempre que su ocupante también salga en este mismo archivo. Una plaza no puede recibir a dos personas.'],
            [$parrafo, '• Cada persona es un traslado normal: queda su registro, su bitácora y el aviso al evaluado y al nuevo evaluador.'],
            [$parrafo, '• El lote solo se confirma cuando TODOS los tramos de origen están calificados y vencido el plazo de recursos (o la persona no tiene evaluación en el periodo). Mientras tanto no se mueve a nadie.'],
            [$parrafo, '• La cédula identifica a la persona; no la modifique. Las columnas en gris son solo informativas.'],
        ] as [$estilo, $linea]) {
            $writer->addRow(Row::fromValuesWithStyle([$linea], $estilo));
        }

        $writer->addNewSheetAndMakeItCurrent()->setName('ROTACION');
        $hoja = $writer->getCurrentSheet();
        foreach ([18, 34, 40, 18, 24, 24, 40] as $i => $ancho) {
            $hoja->setColumnWidth($ancho, $i + 1);
        }
        $writer->addRow(Row::fromValuesWithStyle(array_values(self::COLUMNAS), $encabezado));
        foreach ($personas as $p) {
            // Gris: informativo. Amarillo: lo que decide Talento Humano.
            $writer->addRow(new Row([
                Cell::fromValue(is_numeric($p->numero_doc) ? (int) $p->numero_doc : $p->numero_doc, $informativo),
                Cell::fromValue(trim("{$p->nombres} {$p->apellidos}"), $informativo),
                Cell::fromValue((string) $p->area, $informativo),
                Cell::fromValue((string) $p->codigo_plaza, $informativo),
                Cell::fromValue('', $resaltado),
                Cell::fromValue('', $resaltado),
                Cell::fromValue('', $resaltado),
            ]));
        }
        if (! $personas) {
            for ($i = 0; $i < 3; $i++) {
                $writer->addRow(Row::fromValuesWithStyle(['', '', '', '', '', '', ''], $resaltado));
            }
        }

        $writer->addNewSheetAndMakeItCurrent()->setName('PLAZAS');
        foreach ([22, 30, 44, 38, 22, 38] as $i => $ancho) {
            $writer->getCurrentSheet()->setColumnWidth($ancho, $i + 1);
        }
        $writer->addRow(Row::fromValuesWithStyle(['CODIGO PLAZA', 'CARGO', 'DEPENDENCIA', 'OCUPANTE ACTUAL', 'PLAZA SUPERIOR', 'EVALUADOR POR DEFECTO'], $encabezado));
        foreach ($plazas as $z) {
            $writer->addRow(Row::fromValuesWithStyle([$z['codigo'], $z['cargo'], $z['dependencia'], $z['ocupante'] ?: '(vacante)', $z['superior'] ?: '', $z['evaluador_defecto'] ?: ''], $texto));
        }

        $writer->close();

        return ['personas' => count($personas), 'plazas' => count($plazas)];
    }

    private function personasActivas(?string $cargo): array
    {
        return DB::table('vinculacion as v')
            ->join('funcionario as f', 'f.id_funcionario', '=', 'v.id_funcionario')
            ->leftJoin('puesto_ocupacion as po', fn ($j) => $j->on('po.id_vinculacion', '=', 'v.id_vinculacion')->where('po.estado', '=', 'VIGENTE'))
            ->leftJoin('puesto as p', 'p.id_puesto', '=', 'po.id_puesto')
            ->where('v.activa', 1)
            ->when($cargo, fn ($q) => $q->where('v.cargo', $cargo), fn ($q) => $q->whereRaw('1 = 0'))
            ->orderBy('v.area')->orderBy('f.apellidos')->orderBy('f.nombres')
            ->get(['f.numero_doc', 'f.nombres', 'f.apellidos', 'v.area', 'p.codigo_plaza'])
            ->all();
    }

    /** Todas las plazas activas con su ocupante y el evaluador que tendrían por defecto. */
    private function catalogoPlazas(): array
    {
        $plazas = DB::table('puesto as p')
            ->join('dependencia as d', 'd.id_dependencia', '=', 'p.id_dependencia')
            ->join('cargo as c', 'c.id_cargo', '=', 'p.id_cargo')
            ->leftJoin('puesto as ps', 'ps.id_puesto', '=', 'p.id_puesto_superior')
            ->where('p.estado', '!=', 'INACTIVA')
            ->orderBy('d.nombre')->orderBy('c.nombre')->orderBy('p.codigo_plaza')
            ->get(['p.id_puesto', 'p.codigo_plaza', 'c.nombre as cargo', 'd.nombre as dependencia', 'ps.id_puesto as id_superior', 'ps.codigo_plaza as superior']);

        $nombres = $this->nombresOcupantes();

        return $plazas->map(fn ($z) => [
            'codigo' => $z->codigo_plaza, 'cargo' => $z->cargo, 'dependencia' => $z->dependencia, 'superior' => $z->superior,
            'ocupante' => $nombres[$z->id_puesto]['nombre'] ?? null,
            'evaluador_defecto' => $z->id_superior ? ($nombres[$z->id_superior]['nombre'] ?? null) : null,
        ])->all();
    }

    /** @return array<int, array{id_vinc:int, nombre:string}> id_puesto => ocupante vigente */
    private function nombresOcupantes(): array
    {
        return DB::table('puesto_ocupacion as po')
            ->join('vinculacion as v', 'v.id_vinculacion', '=', 'po.id_vinculacion')
            ->join('funcionario as f', 'f.id_funcionario', '=', 'v.id_funcionario')
            ->where('po.estado', 'VIGENTE')
            ->get(['po.id_puesto', 'po.id_vinculacion', DB::raw("TRIM(CONCAT(f.nombres, ' ', f.apellidos)) as nombre")])
            ->mapWithKeys(fn ($o) => [(int) $o->id_puesto => ['id_vinc' => (int) $o->id_vinculacion, 'nombre' => $o->nombre]])
            ->all();
    }

    // ------------------------------------------------------------ Análisis

    /**
     * Lee el Excel y valida cada fila SIN escribir en la base.
     *
     * @return array{filas: array<int, array>, resumen: array}
     */
    public function analizar(string $ruta): array
    {
        $hojas = (new LectorXlsx())->leer($ruta);
        $filasExcel = $this->filasDeRotacion($hojas);
        if (! $filasExcel) {
            throw new RuntimeException('No hay filas con PLAZA DESTINO: escriba el código de la plaza destino de cada persona.');
        }

        $personas = DB::table('vinculacion as v')
            ->join('funcionario as f', 'f.id_funcionario', '=', 'v.id_funcionario')
            ->where('v.activa', 1)
            ->get(['v.id_vinculacion', 'v.area', 'v.cargo', 'v.es_evaluador', 'f.numero_doc', DB::raw("TRIM(CONCAT(f.nombres, ' ', f.apellidos)) as nombre")])
            ->groupBy(fn ($v) => $this->soloDigitos($v->numero_doc));
        $plazas = DB::table('puesto as p')
            ->join('dependencia as d', 'd.id_dependencia', '=', 'p.id_dependencia')
            ->join('cargo as c', 'c.id_cargo', '=', 'p.id_cargo')
            ->get(['p.id_puesto', 'p.codigo_plaza', 'p.estado', 'p.id_puesto_superior', 'd.nombre as dependencia', 'c.nombre as cargo'])
            ->keyBy(fn ($z) => CargaPlanta::normalizar($z->codigo_plaza));
        $ocupantes = $this->nombresOcupantes();
        $plazaDe = DB::table('puesto_ocupacion as po')->join('puesto as p', 'p.id_puesto', '=', 'po.id_puesto')
            ->where('po.estado', 'VIGENTE')->get(['po.id_vinculacion', 'po.id_puesto', 'p.codigo_plaza'])->keyBy('id_vinculacion');
        $conEvaluador = DB::table('evaluador_asignacion')->pluck('id_vinc_evaluado')->flip();
        $programados = DB::table('traslado')->where('estado', 'PROGRAMADO')->pluck('id_vinc_funcionario')->flip();

        // Quiénes salen en este lote (para permitir plazas ocupadas por ellos).
        $salen = [];
        foreach ($filasExcel as $f) {
            $vs = $personas[$this->soloDigitos($f['cedula'])] ?? null;
            if ($vs && $vs->count() === 1) {
                $salen[] = (int) $vs->first()->id_vinculacion;
            }
        }

        $vistasPersona = [];
        $vistasPlaza = [];
        $filas = [];
        foreach ($filasExcel as $f) {
            $errores = [];
            $cedula = $this->soloDigitos($f['cedula']);
            $vs = $personas[$cedula] ?? null;
            $persona = $vs && $vs->count() === 1 ? $vs->first() : null;
            if (! $cedula) {
                $errores[] = 'Falta la cédula.';
            } elseif (! $vs) {
                $errores[] = "No hay un funcionario activo con la cédula {$cedula}.";
            } elseif ($vs->count() > 1) {
                $errores[] = "La cédula {$cedula} tiene varias vinculaciones activas.";
            }

            if ($persona) {
                if (isset($vistasPersona[$persona->id_vinculacion])) {
                    $errores[] = 'La persona está repetida en el archivo.';
                }
                $vistasPersona[$persona->id_vinculacion] = true;
                if (isset($programados[$persona->id_vinculacion])) {
                    $errores[] = 'Ya tiene un traslado programado: confírmalo o cancélalo antes.';
                }
                if (! isset($conEvaluador[$persona->id_vinculacion])) {
                    $errores[] = 'No tiene un evaluador asignado.';
                }
            }

            $plaza = $plazas[CargaPlanta::normalizar($f['plaza_destino'])] ?? null;
            if (! $plaza) {
                $errores[] = "La plaza destino «{$f['plaza_destino']}» no existe en el catálogo.";
            } elseif ($plaza->estado === 'INACTIVA') {
                $errores[] = "La plaza {$plaza->codigo_plaza} está inactiva.";
            } else {
                if (isset($vistasPlaza[$plaza->id_puesto])) {
                    $errores[] = "La plaza {$plaza->codigo_plaza} recibe a más de una persona.";
                }
                $vistasPlaza[$plaza->id_puesto] = true;
                $ocupante = $ocupantes[$plaza->id_puesto] ?? null;
                if ($ocupante && $persona && $ocupante['id_vinc'] === (int) $persona->id_vinculacion) {
                    $errores[] = 'La plaza destino es la que ya ocupa.';
                } elseif ($ocupante && ! in_array($ocupante['id_vinc'], $salen, true)) {
                    $errores[] = "La plaza {$plaza->codigo_plaza} la ocupa {$ocupante['nombre']}, que no sale en este lote: elige una plaza vacante o incluye a esa persona.";
                }
            }

            // Evaluador: el indicado o el ocupante de la plaza superior de la plaza destino.
            $evaluador = null;
            $origenEvaluador = null;
            if ($f['evaluador'] !== '') {
                $ev = ($personas[$this->soloDigitos($f['evaluador'])] ?? collect())->first();
                if (! $ev) {
                    $errores[] = "No hay un funcionario activo con la cédula de evaluador {$f['evaluador']}.";
                } elseif (! $ev->es_evaluador) {
                    $errores[] = "{$ev->nombre} no está marcado como evaluador.";
                } else {
                    $evaluador = $ev;
                    $origenEvaluador = 'indicado';
                }
            } elseif ($plaza && $plaza->id_puesto_superior) {
                $idEv = $ocupantes[$plaza->id_puesto_superior]['id_vinc'] ?? null;
                $ev = $idEv ? ($personas->flatten(1)->firstWhere('id_vinculacion', $idEv)) : null;
                if ($ev && $ev->es_evaluador) {
                    $evaluador = $ev;
                    $origenEvaluador = 'plaza superior';
                } else {
                    $errores[] = 'La plaza superior de la plaza destino no tiene un ocupante evaluador: indica la cédula del evaluador.';
                }
            } elseif ($plaza) {
                $errores[] = 'La plaza destino no tiene plaza superior: indica la cédula del evaluador.';
            }
            if ($evaluador && $persona && (int) $evaluador->id_vinculacion === (int) $persona->id_vinculacion) {
                $errores[] = 'La persona no puede ser su propio evaluador.';
            }

            $filas[] = [
                'fila' => $f['fila'],
                'cedula' => $cedula,
                'id_vinc_funcionario' => $persona ? (int) $persona->id_vinculacion : null,
                'nombre' => $persona->nombre ?? null,
                'dependencia_actual' => $persona->area ?? null,
                'plaza_actual' => $persona ? ($plazaDe[$persona->id_vinculacion]->codigo_plaza ?? null) : null,
                'plaza_destino' => $plaza->codigo_plaza ?? $f['plaza_destino'],
                'id_puesto_destino' => $plaza && $plaza->estado !== 'INACTIVA' ? (int) $plaza->id_puesto : null,
                'dependencia_destino' => $plaza->dependencia ?? null,
                'cargo_destino' => $plaza->cargo ?? null,
                'id_vinc_evaluador_nuevo' => $evaluador ? (int) $evaluador->id_vinculacion : null,
                'evaluador' => $evaluador->nombre ?? null,
                'origen_evaluador' => $origenEvaluador,
                'observacion' => $f['observacion'],
                'errores' => $errores,
            ];
        }

        $conError = count(array_filter($filas, fn ($x) => $x['errores']));

        return ['filas' => $filas, 'resumen' => ['total' => count($filas), 'con_error' => $conError, 'listas' => count($filas) - $conError]];
    }

    /** Filas con PLAZA DESTINO de la hoja ROTACION (o la primera hoja con ese encabezado). */
    private function filasDeRotacion(array $hojas): array
    {
        foreach ($hojas as $nombre => $filas) {
            $columnas = null;
            $resultado = [];
            foreach ($filas as $numero => $celdas) {
                if ($columnas === null) {
                    $mapa = [];
                    foreach ($celdas as $col => $valor) {
                        foreach (self::COLUMNAS as $clave => $titulo) {
                            if (CargaPlanta::normalizar($valor) === CargaPlanta::normalizar($titulo)) {
                                $mapa[$clave] = $col;
                            }
                        }
                    }
                    if (isset($mapa['cedula'], $mapa['plaza_destino'])) {
                        $columnas = $mapa;
                    }
                    continue;
                }

                $valor = fn (string $clave) => isset($columnas[$clave]) ? trim((string) ($celdas[$columnas[$clave]] ?? '')) : '';
                if ($valor('plaza_destino') === '' && $valor('cedula') === '') {
                    continue;
                }
                if ($valor('plaza_destino') === '') {
                    continue; // fila sin destino: se ignora
                }
                $resultado[] = [
                    'fila' => (int) $numero, 'cedula' => $valor('cedula'), 'plaza_destino' => $valor('plaza_destino'),
                    'evaluador' => $valor('evaluador'), 'observacion' => $valor('observacion'),
                ];
            }
            if ($columnas !== null) {
                return $resultado;
            }
        }

        throw new RuntimeException('No se encontró la hoja ROTACION con las columnas No IDENTIFICACION y PLAZA DESTINO (CODIGO). Use el formato descargado de la plataforma.');
    }

    private function soloDigitos(mixed $valor): string
    {
        if (is_float($valor) || is_int($valor)) {
            return (string) (int) $valor;
        }

        return preg_replace('/\D+/', '', (string) $valor) ?? '';
    }

    // ------------------------------------------------------------ Programar

    /**
     * Programa el lote completo: todos los traslados o ninguno. Retorna
     * ['id_lote' => n] o ['errores' => [fila => mensaje]].
     */
    public function programar(array $filas, string $fecha, ?string $descripcion, ?string $archivo): array
    {
        $errores = [];
        $idLote = null;

        try {
            DB::transaction(function () use ($filas, $fecha, $descripcion, $archivo, &$errores, &$idLote) {
                $idLote = DB::table('traslado_lote')->insertGetId([
                    'descripcion' => $descripcion ? mb_substr($descripcion, 0, 300) : null,
                    'fecha_traslado' => $fecha,
                    'estado' => 'PROGRAMADO',
                    'total' => count($filas),
                    'archivo' => $archivo ? mb_substr($archivo, 0, 255) : null,
                    'id_usuario_crea' => session('usuario_autenticado.id_usuario'),
                    'created_at' => now(),
                ]);
                $salen = array_map(fn ($f) => (int) $f['id_vinc_funcionario'], $filas);

                foreach ($filas as $f) {
                    $resultado = programarTraslado([
                        'id_vinc_funcionario' => $f['id_vinc_funcionario'],
                        'id_vinc_evaluador_nuevo' => $f['id_vinc_evaluador_nuevo'],
                        'fecha_traslado' => $fecha,
                        'area_nuevo' => $f['dependencia_destino'],
                        'cargo_nuevo' => $f['cargo_destino'],
                        'id_puesto_destino' => $f['id_puesto_destino'],
                        'motivo' => mb_substr("Rotación en bloque, lote #{$idLote}" . ($descripcion ? " - {$descripcion}" : ''), 0, 500),
                    ], null, null, true, 'CAMBIO', [
                        'id_lote' => $idLote,
                        'sin_origen_ok' => true,
                        'permitir_mismo_evaluador' => true,
                        'ocupantes_que_salen' => $salen,
                    ]);

                    if (isset($resultado['error'])) {
                        $errores[$f['fila']] = $resultado['error'];
                    }
                }

                if ($errores) {
                    throw new RuntimeException('lote con errores');
                }
            });
        } catch (RuntimeException $e) {
            if ($errores) {
                return ['errores' => $errores];
            }
            throw $e;
        }

        return ['id_lote' => $idLote];
    }

    // ------------------------------------------------------------ Seguimiento

    /** Lotes con el estado de cada traslado: qué le falta para poder confirmarse. */
    public function lotes(): array
    {
        $lotes = DB::table('traslado_lote as l')
            ->leftJoin('usuario as u', 'u.id_usuario', '=', 'l.id_usuario_crea')
            ->orderByDesc('l.id_lote')
            ->get(['l.*', 'u.username as creado_por']);

        return $lotes->map(function ($l) {
            $traslados = DB::table('traslado as t')
                ->join('vinculacion as vf', 'vf.id_vinculacion', '=', 't.id_vinc_funcionario')
                ->join('funcionario as ff', 'ff.id_funcionario', '=', 'vf.id_funcionario')
                ->leftJoin('vinculacion as vn', 'vn.id_vinculacion', '=', 't.id_vinc_evaluador_nuevo')
                ->leftJoin('funcionario as fn', 'fn.id_funcionario', '=', 'vn.id_funcionario')
                ->leftJoin('evaluacion as evo', 'evo.id_evaluacion', '=', 't.id_evaluacion_origen')
                ->leftJoin('puesto as po', 'po.id_puesto', '=', 't.id_puesto_origen')
                ->leftJoin('puesto as pd', 'pd.id_puesto', '=', 't.id_puesto_destino')
                ->where('t.id_lote', $l->id_lote)
                ->orderBy('t.id_traslado')
                ->get([
                    't.id_traslado', 't.estado', 't.area_origen', 't.area_nuevo', 't.dias_laborados', 't.id_evaluacion_origen', 'evo.estado as origen_estado',
                    'po.codigo_plaza as plaza_origen', 'pd.codigo_plaza as plaza_destino',
                    DB::raw("TRIM(CONCAT(ff.nombres, ' ', ff.apellidos)) as funcionario"),
                    DB::raw("TRIM(CONCAT(COALESCE(fn.nombres, ''), ' ', COALESCE(fn.apellidos, ''))) as evaluador_nuevo"),
                ]);

            $l->traslados = $traslados->map(function ($t) {
                $t->bloqueo = $t->estado === 'PROGRAMADO' ? $this->bloqueo($t) : null;

                return $t;
            })->all();
            $l->listos = collect($l->traslados)->where('estado', 'PROGRAMADO')->filter(fn ($t) => ! $t->bloqueo)->count();
            $l->pendientes = collect($l->traslados)->where('estado', 'PROGRAMADO')->count();

            return $l;
        })->all();
    }

    /** Motivo por el que un traslado aún no puede confirmarse; null si está listo. */
    private function bloqueo(object $t): ?string
    {
        if ((int) $t->dias_laborados > 0) {
            if (! $t->id_evaluacion_origen || ! in_array($t->origen_estado, ['CALIFICADA', 'APELACION', 'CERRADA'], true)) {
                return 'Falta calificar el tramo de origen.';
            }

            return motivoRecursosAbiertos((int) $t->id_evaluacion_origen);
        }

        return null;
    }

    // ------------------------------------------------------------ Confirmar / cancelar

    /** Confirma todo el lote en una transacción. Retorna null o el motivo por el que no se confirmó. */
    public function confirmar(int $idLote): ?string
    {
        try {
            DB::transaction(function () use ($idLote) {
                $lote = DB::table('traslado_lote')->where('id_lote', $idLote)->lockForUpdate()->first();
                if (! $lote || $lote->estado !== 'PROGRAMADO') {
                    throw new RuntimeException('El lote no existe o ya fue resuelto.');
                }

                $detalle = collect($this->lotes())->firstWhere('id_lote', $idLote);
                $bloqueados = collect($detalle->traslados)->where('estado', 'PROGRAMADO')->filter(fn ($t) => $t->bloqueo);
                if ($bloqueados->isNotEmpty()) {
                    throw new RuntimeException('El lote aún no se puede confirmar: ' . $bloqueados->map(fn ($t) => "{$t->funcionario} ({$t->bloqueo})")->implode('; '));
                }

                $traslados = DB::table('traslado')->where('id_lote', $idLote)->where('estado', 'PROGRAMADO')->orderBy('id_traslado')->get();

                // Fase 1: se liberan todas las plazas de origen; así una plaza que otra persona del lote
                // ocupaba hoy queda vacante para su nuevo ocupante (rotación entre plazas ocupadas).
                $ocupaciones = app(OcupacionPuestos::class);
                foreach ($traslados as $t) {
                    $ocupaciones->cerrar((int) $t->id_vinc_funcionario, \Carbon\Carbon::parse($t->fecha_traslado)->subDay()->toDateString(), "Traslado en bloque, lote #{$idLote}");
                }

                // Fase 2: cada traslado se confirma con el mismo procedimiento del traslado individual.
                foreach ($traslados as $t) {
                    if ($error = confirmarTrasladoEnTransaccion((int) $t->id_traslado)) {
                        throw new RuntimeException(nombreFuncionarioVinc((int) $t->id_vinc_funcionario) . ': ' . $error);
                    }
                }

                DB::table('traslado_lote')->where('id_lote', $idLote)->update([
                    'estado' => 'CONFIRMADO', 'id_usuario_resuelve' => session('usuario_autenticado.id_usuario'), 'resuelto_at' => now(),
                ]);
            });
        } catch (RuntimeException $e) {
            return $e->getMessage();
        }

        return null;
    }

    /** Cancela el lote completo mientras ningún tramo de origen tenga nota. */
    public function cancelar(int $idLote): ?string
    {
        try {
            DB::transaction(function () use ($idLote) {
                $lote = DB::table('traslado_lote')->where('id_lote', $idLote)->lockForUpdate()->first();
                if (! $lote || $lote->estado !== 'PROGRAMADO') {
                    throw new RuntimeException('El lote no existe o ya fue resuelto.');
                }

                foreach (DB::table('traslado')->where('id_lote', $idLote)->where('estado', 'PROGRAMADO')->pluck('id_traslado') as $idTraslado) {
                    if ($error = cancelarTraslado((int) $idTraslado)) {
                        throw new RuntimeException($error);
                    }
                }

                DB::table('traslado_lote')->where('id_lote', $idLote)->update([
                    'estado' => 'CANCELADO', 'id_usuario_resuelve' => session('usuario_autenticado.id_usuario'), 'resuelto_at' => now(),
                ]);
            });
        } catch (RuntimeException $e) {
            return $e->getMessage();
        }

        return null;
    }
}
