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
 * Carga masiva de notas finales de un semestre (contingencia). Solo se guarda la
 * nota final: los soportes reposan en físico. La nota alimenta la consolidación
 * del semestre (matriz e informe anual); si la persona ya tiene nota calificada
 * por el flujo normal, esa prevalece y la fila se omite.
 */
class NotasImportadas
{
    private const COLUMNAS = [
        'cedula' => 'No IDENTIFICACION',
        'nombre' => 'NOMBRE FUNCIONARIO',
        'cargo' => 'CARGO',
        'dependencia' => 'DEPENDENCIA',
        'sistema' => 'SISTEMA (RL / AG)',
        'nota' => 'NOTA FINAL (1.0 a 5.0)',
        'fecha' => 'FECHA CALIFICACION (OPCIONAL)',
        'observacion' => 'OBSERVACION (OPCIONAL)',
    ];

    private const SISTEMAS = ['RL' => 'RENDIMIENTO_LABORAL', 'AG' => 'ACUERDO_GESTION'];

    public function plantilla(string $ruta, int $anio, int $semestre): int
    {
        $encabezado = new Style(fontBold: true, fontName: 'Arial', fontSize: 10, fontColor: 'FFFFFF', backgroundColor: '1F4E78', shouldWrapText: true, cellVerticalAlignment: CellVerticalAlignment::CENTER);
        $informativo = new Style(fontName: 'Arial', fontSize: 10, fontColor: '595959', backgroundColor: 'EDEDED');
        $resaltado = new Style(fontName: 'Arial', fontSize: 10, fontBold: true, backgroundColor: 'FFF2CC');
        $titulo = new Style(fontBold: true, fontName: 'Arial', fontSize: 14, fontColor: '1F4E78');
        $subtitulo = new Style(fontBold: true, fontName: 'Arial', fontSize: 11, fontColor: '1F4E78');
        $parrafo = new Style(fontName: 'Arial', fontSize: 10, shouldWrapText: true);

        $personas = DB::table('vinculacion as v')
            ->join('funcionario as f', 'f.id_funcionario', '=', 'v.id_funcionario')
            ->where('v.activa', 1)
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('evaluador_asignacion as ea')->whereColumn('ea.id_vinc_evaluado', 'v.id_vinculacion'))
            ->orderBy('v.area')->orderBy('f.apellidos')
            ->get(['f.numero_doc', 'f.nombres', 'f.apellidos', 'v.cargo', 'v.area', 'v.sistema_evaluacion']);

        $writer = new Writer();
        $writer->openToFile($ruta);

        $etiqueta = "{$anio} - Semestre " . ($semestre === 1 ? 'A (primero)' : 'B (segundo)');
        $writer->getCurrentSheet()->setName('INSTRUCCIONES');
        $writer->getCurrentSheet()->setColumnWidth(110, 1);
        foreach ([
            [$titulo, "Carga de notas por contingencia — {$etiqueta}"],
            [$parrafo, 'Generado el ' . now('America/Bogota')->format('d/m/Y H:i') . ' con los funcionarios activos que tienen evaluador asignado.'],
            [$parrafo, ''],
            [$subtitulo, 'Qué hay que hacer'],
            [$parrafo, '1. En la hoja NOTAS, escriba en NOTA FINAL la calificación definitiva del semestre de cada persona (de 1.0 a 5.0; se acepta coma o punto decimal). Las filas con la nota vacía se ignoran.'],
            [$parrafo, '2. FECHA CALIFICACION y OBSERVACION son opcionales. La fecha puede ir como AAAA-MM-DD o DD/MM/AAAA.'],
            [$parrafo, '3. No modifique la cédula, el sistema ni los encabezados: las columnas en gris son informativas. Si una persona tiene dos vinculaciones activas, deje una fila por cada sistema (RL o AG).'],
            [$parrafo, '4. Guarde el archivo como .xlsx y cárguelo en Administración → Notas por contingencia. La plataforma muestra una revisión fila por fila antes de guardar nada.'],
            [$parrafo, ''],
            [$subtitulo, 'Reglas importantes'],
            [$parrafo, '• Solo se registra la nota final: los soportes (compromisos, competencias, evidencias) reposan en físico.'],
            [$parrafo, '• Si la persona ya tiene el semestre calificado en la plataforma por el flujo normal, prevalece esa calificación y la fila se omite.'],
            [$parrafo, '• La nota cargada entra en la matriz de calificaciones y en el consolidado anual junto con la del otro semestre. Cada carga queda en un lote que se puede revertir.'],
        ] as [$estilo, $linea]) {
            $writer->addRow(Row::fromValuesWithStyle([$linea], $estilo));
        }

        $writer->addNewSheetAndMakeItCurrent()->setName('NOTAS');
        foreach ([18, 34, 36, 44, 14, 20, 22, 40] as $i => $ancho) {
            $writer->getCurrentSheet()->setColumnWidth($ancho, $i + 1);
        }
        $writer->addRow(Row::fromValuesWithStyle(array_values(self::COLUMNAS), $encabezado));
        foreach ($personas as $p) {
            $writer->addRow(new Row([
                Cell::fromValue(is_numeric($p->numero_doc) ? (int) $p->numero_doc : $p->numero_doc, $informativo),
                Cell::fromValue(trim("{$p->nombres} {$p->apellidos}"), $informativo),
                Cell::fromValue((string) $p->cargo, $informativo),
                Cell::fromValue((string) $p->area, $informativo),
                Cell::fromValue(array_search($p->sistema_evaluacion, self::SISTEMAS, true) ?: '', $informativo),
                Cell::fromValue('', $resaltado),
                Cell::fromValue('', $resaltado),
                Cell::fromValue('', $resaltado),
            ]));
        }

        $writer->close();

        return $personas->count();
    }

    /** Revisión fila por fila; no guarda nada. */
    public function analizar(string $ruta, int $anio, int $semestre): array
    {
        $hojas = (new LectorXlsx())->leer($ruta);
        $filasExcel = $this->filasDeNotas($hojas);

        $porCedula = DB::table('vinculacion as v')
            ->join('funcionario as f', 'f.id_funcionario', '=', 'v.id_funcionario')
            ->where('v.activa', 1)
            ->get(['v.id_vinculacion', 'v.cargo', 'v.area', 'v.sistema_evaluacion', 'f.numero_doc', DB::raw("TRIM(CONCAT(f.nombres, ' ', f.apellidos)) as nombre")])
            ->groupBy(fn ($v) => $this->soloDigitos($v->numero_doc));
        $escala = escalaCalificacionConfig();

        $filas = [];
        $vistas = [];
        $sinNota = 0;
        foreach ($filasExcel as $f) {
            $cedula = $this->soloDigitos($f['cedula']);
            $notaTexto = trim((string) $f['nota']);
            if ($notaTexto === '') {
                $sinNota++;
                continue;
            }

            $fila = [
                'fila' => $f['fila'], 'cedula' => $cedula, 'nombre' => trim((string) $f['nombre']), 'cargo' => trim((string) $f['cargo']),
                'sistema' => null, 'nota' => null, 'fecha' => null, 'observacion' => trim((string) $f['observacion']) ?: null,
                'id_vinculacion' => null, 'estado' => 'LISTA', 'mensaje' => null,
            ];

            $error = null;
            $vinculaciones = $porCedula[$cedula] ?? collect();
            $sistemaPedido = $this->sistema((string) $f['sistema']);
            if ($cedula === '') {
                $error = 'Falta la cédula.';
            } elseif ($vinculaciones->isEmpty()) {
                $error = 'No hay una vinculación activa con esa cédula.';
            } else {
                if ($sistemaPedido) {
                    $vinculaciones = $vinculaciones->where('sistema_evaluacion', $sistemaPedido);
                }
                if ($vinculaciones->isEmpty()) {
                    $error = 'La persona no tiene una vinculación activa en el sistema indicado.';
                } elseif ($vinculaciones->count() > 1) {
                    $error = 'Tiene varias vinculaciones activas: indica el SISTEMA (RL o AG) de esta fila.';
                } else {
                    $v = $vinculaciones->first();
                    $fila['id_vinculacion'] = (int) $v->id_vinculacion;
                    $fila['sistema'] = $v->sistema_evaluacion;
                    $fila['nombre'] = $v->nombre;
                    $fila['cargo'] = $v->cargo;
                }
            }

            $nota = $this->nota($notaTexto);
            if (! $error && $nota === null) {
                $error = 'La nota no es un número.';
            } elseif (! $error && ($nota < $escala['minimo'] || $nota > $escala['maximo'])) {
                $error = "La nota debe estar entre {$escala['minimo']} y {$escala['maximo']}.";
            }
            $fila['nota'] = $nota !== null ? redondearEscala($nota) : null;

            $fecha = trim((string) $f['fecha']);
            if (! $error && $fecha !== '') {
                $fila['fecha'] = $this->fecha($f['fecha']);
                if (! $fila['fecha']) {
                    $error = 'La fecha no es válida (use AAAA-MM-DD o DD/MM/AAAA).';
                }
            }

            if (! $error && $fila['id_vinculacion']) {
                $clave = $fila['id_vinculacion'] . '|' . $fila['sistema'];
                if (isset($vistas[$clave])) {
                    $error = "Repetida: ya aparece en la fila {$vistas[$clave]}.";
                }
                $vistas[$clave] = $f['fila'];
            }

            if ($error) {
                $fila['estado'] = 'ERROR';
                $fila['mensaje'] = $error;
            } elseif ($previa = $this->notaYaImportada($fila['id_vinculacion'], $anio, $semestre, $fila['sistema'])) {
                $fila['estado'] = 'OMITIDA';
                $fila['mensaje'] = "Ya tiene una nota cargada (lote #{$previa}): reviértelo para volver a cargarla.";
            } elseif ($flujo = $this->notaDelFlujo($fila['id_vinculacion'], $anio, $semestre, $fila['sistema'])) {
                $fila['estado'] = 'OMITIDA';
                $fila['mensaje'] = "Ya está calificada en la plataforma ({$flujo}); prevalece esa nota.";
            }

            $filas[] = $fila;
        }

        if (! $filas && ! $sinNota) {
            throw new RuntimeException('No encontré filas en la hoja NOTAS: descarga el formato y diligéncialo.');
        }
        if (! $filas) {
            throw new RuntimeException('Ninguna fila trae nota final: escríbela en la columna NOTA FINAL.');
        }

        $conteo = collect($filas)->countBy('estado');

        return [
            'filas' => $filas,
            'resumen' => [
                'total' => count($filas), 'listas' => (int) $conteo->get('LISTA', 0), 'omitidas' => (int) $conteo->get('OMITIDA', 0),
                'con_error' => (int) $conteo->get('ERROR', 0), 'sin_nota' => $sinNota,
            ],
        ];
    }

    /** Guarda las filas LISTA de la revisión como un lote. */
    public function aplicar(array $filas, int $anio, int $semestre, ?string $descripcion, ?string $archivo): int
    {
        $listas = array_values(array_filter($filas, fn ($f) => $f['estado'] === 'LISTA'));
        if (! $listas) {
            throw new RuntimeException('No hay filas listas para guardar.');
        }

        return DB::transaction(function () use ($listas, $anio, $semestre, $descripcion, $archivo) {
            $idLote = DB::table('nota_importada_lote')->insertGetId([
                'anio' => $anio, 'semestre' => $semestre, 'descripcion' => $descripcion ? mb_substr($descripcion, 0, 300) : null,
                'archivo' => $archivo ? mb_substr($archivo, 0, 255) : null, 'total' => count($listas), 'estado' => 'APLICADO',
                'id_usuario' => session('usuario_autenticado.id_usuario'), 'created_at' => now(),
            ]);
            foreach ($listas as $f) {
                DB::table('nota_semestre_importada')->insert([
                    'id_lote' => $idLote, 'id_vinculacion' => $f['id_vinculacion'], 'anio' => $anio, 'semestre' => $semestre,
                    'sistema' => $f['sistema'], 'nota' => $f['nota'], 'fecha_calificacion' => $f['fecha'],
                    'observacion' => $f['observacion'], 'created_at' => now(),
                ]);
            }

            return $idLote;
        });
    }

    public function lotes(): array
    {
        return DB::table('nota_importada_lote as l')
            ->leftJoin('usuario as u', 'u.id_usuario', '=', 'l.id_usuario')
            ->orderByDesc('l.id_lote')->limit(50)
            ->get(['l.*', 'u.usuario as registrado_por'])
            ->all();
    }

    public function revertir(int $idLote): ?string
    {
        $lote = DB::table('nota_importada_lote')->where('id_lote', $idLote)->first();
        if (! $lote) {
            return 'El lote no existe.';
        }
        if ($lote->estado !== 'APLICADO') {
            return 'El lote ya estaba revertido.';
        }

        DB::transaction(function () use ($idLote) {
            DB::table('nota_semestre_importada')->where('id_lote', $idLote)->delete();
            DB::table('nota_importada_lote')->where('id_lote', $idLote)->update(['estado' => 'REVERTIDO', 'revertido_at' => now()]);
        });

        return null;
    }

    private function notaYaImportada(int $idVinc, int $anio, int $semestre, string $sistema): ?int
    {
        $id = DB::table('nota_semestre_importada')->where(['id_vinculacion' => $idVinc, 'anio' => $anio, 'semestre' => $semestre, 'sistema' => $sistema])->value('id_lote');

        return $id ? (int) $id : null;
    }

    private function notaDelFlujo(int $idVinc, int $anio, int $semestre, string $sistema): ?string
    {
        $r = obtenerNotaSemestreConsolidada($idVinc, $anio, $sistema, $semestre);

        return $r && $r['nota'] !== null && ($r['tipo'] ?? '') !== 'IMPORTADA' ? 'nota ' . number_format((float) $r['nota'], 1) : null;
    }

    private function sistema(string $texto): ?string
    {
        $t = strtoupper(trim($texto));

        return self::SISTEMAS[$t] ?? (in_array($t, self::SISTEMAS, true) ? $t : null);
    }

    private function nota(string $texto): ?float
    {
        $texto = str_replace(',', '.', trim($texto));

        return is_numeric($texto) ? (float) $texto : null;
    }

    private function fecha(mixed $valor): ?string
    {
        if (is_numeric($valor)) {
            return LectorXlsx::fechaExcel($valor);
        }
        $texto = trim((string) $valor);
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $formato) {
            $fecha = \DateTimeImmutable::createFromFormat('!' . $formato, $texto);
            if ($fecha && $fecha->format($formato) === $texto) {
                return $fecha->format('Y-m-d');
            }
        }

        return null;
    }

    private function soloDigitos(mixed $valor): string
    {
        if (is_float($valor) && floor($valor) === $valor) {
            $valor = (int) $valor;
        }

        return preg_replace('/\D+/', '', (string) $valor);
    }

    /** Filas de la hoja NOTAS (o la primera hoja con el encabezado NOTA FINAL). */
    private function filasDeNotas(array $hojas): array
    {
        foreach ($hojas as $nombre => $filas) {
            $cabecera = null;
            foreach ($filas as $n => $columnas) {
                $textos = array_map(fn ($c) => mb_strtoupper(trim((string) $c)), $columnas);
                if (in_array(mb_strtoupper(self::COLUMNAS['cedula']), $textos, true) && collect($textos)->contains(fn ($t) => str_starts_with($t, 'NOTA FINAL'))) {
                    $cabecera = [$n, $textos];
                    break;
                }
            }
            if (! $cabecera) {
                continue;
            }

            [$filaCabecera, $textos] = $cabecera;
            $col = fn (string $prefijo) => array_key_first(array_filter($textos, fn ($t) => str_starts_with($t, $prefijo)));
            $indices = [
                'cedula' => $col('NO IDENTIFICACION'), 'nombre' => $col('NOMBRE'), 'cargo' => $col('CARGO'), 'sistema' => $col('SISTEMA'),
                'nota' => $col('NOTA FINAL'), 'fecha' => $col('FECHA'), 'observacion' => $col('OBSERVACION'),
            ];

            $resultado = [];
            foreach ($filas as $n => $columnas) {
                if ($n <= $filaCabecera) {
                    continue;
                }
                $fila = ['fila' => $n];
                foreach ($indices as $campo => $i) {
                    $fila[$campo] = $i !== null ? ($columnas[$i] ?? '') : '';
                }
                if (trim((string) $fila['cedula']) === '' && trim((string) $fila['nota']) === '') {
                    continue;
                }
                $resultado[] = $fila;
            }

            return $resultado;
        }

        throw new RuntimeException('No encontré la hoja NOTAS con los encabezados del formato: descárgalo de nuevo.');
    }
}
