<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Validation\CellReference;
use OpenSpout\Writer\XLSX\Validation\Rules\ListValidationRule;
use OpenSpout\Writer\XLSX\Validation\ValidationDisplay;
use OpenSpout\Writer\XLSX\Writer;

/**
 * S14 - Planta actual de la plataforma en el mismo formato que lee la carga
 * (CargaPlanta), para que Talento Humano la revise, la corrija y la vuelva a
 * subir. Las columnas que lee la carga llevan listas desplegables con los
 * valores del catálogo; las columnas en gris son solo informativas (la carga
 * no las lee). Lo que TH debe decidir va resaltado en amarillo.
 */
class ExportePlanta
{
    /** Encabezados que lee CargaPlanta::leer (el orden no importa, el texto sí). */
    private const COLUMNAS = [
        'ITEM', 'No IDENTIFICACIÓN', 'NOMBRE FUNCIONARIO', 'CARGO', 'CODIGO', 'GRADO', 'AREA', 'FECHA INGRESO', 'FECHA RETIRO',
        'TIPO DE VINCULACIÓN', 'CORREO DEL CARGO', 'NIVEL', 'JEFE INMEDIATO', 'LE APLICA EJE MISIONAL DE DOCENCIA?', 'ROL', 'Tipo de evaluación',
    ];

    /** Informativas: la carga no las lee. */
    private const INFORMATIVAS = ['CÓDIGO DE PLAZA (plataforma)', 'EVALUADOR EN LA PLATAFORMA', 'OBSERVACIONES DE TH'];

    private const SISTEMAS = ['RENDIMIENTO_LABORAL' => 'RENDIMIENTO LABORAL', 'ACUERDO_GESTION' => 'ACUERDO DE GESTION'];

    private Style $encabezado;
    private Style $informativo;
    private Style $resaltado;
    private Style $fecha;
    private Style $texto;

    public function generar(string $ruta): array
    {
        $this->estilos();
        $personas = $this->personas();
        $vacantes = $this->vacantes();
        $listas = $this->listas($personas, $vacantes);

        $options = new Options();
        $writer = new Writer($options);
        $writer->openToFile($ruta);

        $this->hojaInstrucciones($writer, $personas, $vacantes);

        $writer->addNewSheetAndMakeItCurrent()->setName('Listado ACTUALIZADO');
        $this->hojaPersonas($writer, $options, $personas, $listas);

        $writer->addNewSheetAndMakeItCurrent()->setName('VACANTES');
        $this->hojaVacantes($writer, $options, $vacantes, $listas);

        $writer->addNewSheetAndMakeItCurrent()->setName('LISTAS');
        $this->hojaListas($writer, $listas);

        $writer->close();

        return [
            'personas' => count($personas),
            'vacantes' => array_sum(array_column($vacantes, 'cantidad')),
            'por_definir' => count(array_filter($vacantes, fn ($v) => ! $v['dependencia_exacta'])),
        ];
    }

    private function estilos(): void
    {
        $base = new Style(fontName: 'Arial', fontSize: 10, cellVerticalAlignment: CellVerticalAlignment::TOP);
        $this->texto = $base;
        $this->encabezado = new Style(fontBold: true, fontName: 'Arial', fontSize: 10, fontColor: 'FFFFFF', backgroundColor: '1F4E78', shouldWrapText: true, cellVerticalAlignment: CellVerticalAlignment::CENTER);
        $this->informativo = new Style(fontName: 'Arial', fontSize: 10, fontColor: '595959', backgroundColor: 'EDEDED', cellVerticalAlignment: CellVerticalAlignment::TOP);
        $this->resaltado = new Style(fontName: 'Arial', fontSize: 10, fontBold: true, backgroundColor: 'FFF2CC', cellVerticalAlignment: CellVerticalAlignment::TOP);
        $this->fecha = new Style(fontName: 'Arial', fontSize: 10, format: 'dd/mm/yyyy', cellVerticalAlignment: CellVerticalAlignment::TOP);
    }

    // ------------------------------------------------------------ Datos

    private function personas(): array
    {
        $filas = DB::table('vinculacion as v')
            ->join('funcionario as f', 'f.id_funcionario', '=', 'v.id_funcionario')
            ->leftJoin('vinculacion as vj', 'vj.id_vinculacion', '=', 'v.id_vinc_jefe')
            ->leftJoin('funcionario as fj', 'fj.id_funcionario', '=', 'vj.id_funcionario')
            ->leftJoin('puesto_ocupacion as po', fn ($j) => $j->on('po.id_vinculacion', '=', 'v.id_vinculacion')->where('po.estado', '=', 'VIGENTE'))
            ->leftJoin('puesto as p', 'p.id_puesto', '=', 'po.id_puesto')
            ->where('v.activa', 1)
            ->orderBy('v.area')->orderBy('f.nombres')->orderBy('f.apellidos')
            ->get([
                'v.id_vinculacion', 'f.numero_doc', 'f.nombres', 'f.apellidos', 'f.correo_cargo', 'v.cargo', 'v.codigo_cargo', 'v.grado_cargo',
                'v.area', 'v.fecha_ingreso', 'v.fecha_retiro', 'v.tipo_vinculacion', 'v.nivel_jerarquico', 'v.es_evaluador',
                'v.aplica_eje_misional', 'v.sistema_evaluacion', 'v.id_vinc_jefe', 'fj.nombres as jefe_nombres', 'fj.apellidos as jefe_apellidos',
                'p.codigo_plaza',
            ]);

        $evaluadores = DB::table('evaluador_asignacion as ea')
            ->join('vinculacion as v', 'v.id_vinculacion', '=', 'ea.id_vinc_evaluador')
            ->join('funcionario as f', 'f.id_funcionario', '=', 'v.id_funcionario')
            ->orderBy('ea.id_asignacion')
            ->get(['ea.id_vinc_evaluado', 'ea.id_vinc_evaluador', DB::raw("TRIM(CONCAT(f.nombres, ' ', f.apellidos)) as nombre")])
            ->groupBy('id_vinc_evaluado');
        $dependencias = DB::table('dependencia')->pluck('nombre')->map(fn ($n) => CargaPlanta::normalizar($n))->flip();

        return $filas->map(function ($f) use ($evaluadores, $dependencias) {
            $evaluador = $evaluadores[$f->id_vinculacion] ?? collect();
            $observaciones = [];
            if (! $f->codigo_plaza) {
                $observaciones[] = 'Sin plaza en el catálogo.';
            }
            if (! isset($dependencias[CargaPlanta::normalizar($f->area)])) {
                $observaciones[] = 'El área no coincide con una dependencia del catálogo: elíjala de la lista.';
            }
            if ($evaluador->isNotEmpty() && (int) $evaluador->first()->id_vinc_evaluador !== (int) $f->id_vinc_jefe) {
                $observaciones[] = 'Su evaluador en la plataforma no es su jefe inmediato: confirme el jefe.';
            }

            return [
                'cedula' => is_numeric($f->numero_doc) ? (int) $f->numero_doc : $f->numero_doc,
                'nombre' => trim("{$f->nombres} {$f->apellidos}"),
                'cargo' => $f->cargo,
                'codigo' => (int) $f->codigo_cargo,
                'grado' => (int) $f->grado_cargo,
                'area' => $f->area,
                'ingreso' => $f->fecha_ingreso,
                'retiro' => $f->fecha_retiro,
                'tipo' => str_replace('_', ' ', (string) $f->tipo_vinculacion),
                'correo' => $f->correo_cargo,
                'nivel' => $f->nivel_jerarquico,
                // Sin jefe registrado: en la planta de TH se escribe «CONSEJO SUPERIOR».
                'jefe' => $f->id_vinc_jefe ? trim("{$f->jefe_nombres} {$f->jefe_apellidos}") : 'CONSEJO SUPERIOR',
                'eje' => $f->aplica_eje_misional ? 'SI' : 'NO',
                'rol' => $f->es_evaluador ? 'EVALUADO & EVALUADOR' : 'EVALUADO',
                'sistema' => self::SISTEMAS[$f->sistema_evaluacion] ?? $f->sistema_evaluacion,
                'plaza' => $f->codigo_plaza,
                'evaluador' => $evaluador->pluck('nombre')->implode(' / '),
                'observaciones' => implode(' ', $observaciones),
            ];
        })->all();
    }

    /**
     * Vacantes: las plazas sin ocupante agrupadas por cargo, grado y
     * dependencia, más las que quedaron pendientes en la última carga porque su
     * dependencia no era exacta (TH debe elegirla).
     */
    private function vacantes(): array
    {
        $plazas = DB::table('puesto as p')
            ->join('dependencia as d', 'd.id_dependencia', '=', 'p.id_dependencia')
            ->join('cargo as c', 'c.id_cargo', '=', 'p.id_cargo')
            ->where('p.estado', '!=', 'INACTIVA')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('puesto_ocupacion as po')->whereColumn('po.id_puesto', 'p.id_puesto')->where('po.estado', 'VIGENTE'))
            ->orderBy('c.nombre')->orderBy('d.nombre')
            ->get(['p.codigo_plaza', 'p.codigo_cargo', 'p.grado', 'p.nivel_jerarquico', 'p.sistema_evaluacion', 'd.nombre as dependencia', 'c.nombre as cargo']);

        // Tipo de vinculación y de vacante solo vienen en la planta: se toman de la última carga.
        $origen = [];
        foreach ($this->vacantesUltimaCarga() as $v) {
            $origen[$v['cargo'] . '|' . $v['grado']] = $v;
        }

        $vacantes = [];
        foreach ($plazas->groupBy(fn ($p) => "{$p->cargo}|{$p->grado}|{$p->dependencia}") as $grupo) {
            $p = $grupo->first();
            $o = $origen[CargaPlanta::normalizar($p->cargo) . '|' . $p->grado] ?? [];
            $vacantes[] = [
                'cargo' => $p->cargo, 'codigo' => (int) $p->codigo_cargo, 'grado' => (int) $p->grado, 'nivel' => $p->nivel_jerarquico,
                'tipo' => str_replace('_', ' ', $o['tipo_vinculacion'] ?? ''), 'sistema' => self::SISTEMAS[$p->sistema_evaluacion] ?? $p->sistema_evaluacion,
                'area' => $p->dependencia, 'cantidad' => $grupo->count(), 'tipo_vacante' => $o['tipo_vacante'] ?? '',
                'plazas' => $grupo->pluck('codigo_plaza')->implode(', '), 'dependencia_exacta' => true,
                'observaciones' => '',
            ];
        }

        // Pendientes de la última carga: una fila por cargo y área, para que TH elija la dependencia.
        $pendientes = DB::table('carga_planta_pendiente')->where('tipo', 'VACANTE_SIN_DEPENDENCIA')->where('estado', 'PENDIENTE')->get();
        foreach ($pendientes->groupBy(fn ($p) => json_decode($p->datos, true)['cargo'] . '|' . json_decode($p->datos, true)['area']) as $grupo) {
            $d = json_decode($grupo->first()->datos, true);
            $o = $origen[$d['cargo'] . '|' . $d['grado']] ?? [];
            $vacantes[] = [
                'cargo' => $d['cargo'], 'codigo' => (int) $d['codigo'], 'grado' => (int) $d['grado'], 'nivel' => $d['nivel'] ?? '',
                'tipo' => str_replace('_', ' ', $d['tipo_vinculacion'] ?? ''), 'sistema' => self::SISTEMAS[$d['sistema'] ?? ''] ?? ($d['sistema'] ?? ''),
                'area' => $d['area'], 'cantidad' => $grupo->count(), 'tipo_vacante' => $o['tipo_vacante'] ?? '',
                'plazas' => '', 'dependencia_exacta' => false,
                'observaciones' => $grupo->count() > 1
                    ? "«{$d['area']}» no es una dependencia del catálogo. Escriba una fila por dependencia (con su cantidad) y elija cada dependencia de la lista."
                    : "«{$d['area']}» no es una dependencia del catálogo: elija la dependencia de la lista.",
            ];
        }

        return $vacantes;
    }

    /** Filas de vacantes del último Excel cargado (para tipo de vinculación y tipo de vacante). */
    private function vacantesUltimaCarga(): array
    {
        $huella = DB::table('carga_planta')->orderByDesc('id_carga')->value('huella_archivo');
        $ruta = $huella ? storage_path("app/cargas_planta/{$huella}.xlsx") : null;
        if (! $ruta || ! is_file($ruta)) {
            return [];
        }

        return app(CargaPlanta::class)->leer($ruta)['vacantes'];
    }

    private function listas(array $personas, array $vacantes): array
    {
        $unicos = fn ($valores) => collect($valores)->filter()->map(fn ($v) => trim((string) $v))->unique()->sort()->values()->all();

        return [
            'AREA / DEPENDENCIA' => DB::table('dependencia')->where('activa', 1)->orderBy('nombre')->pluck('nombre')->all(),
            'CARGO' => DB::table('cargo')->where('activo', 1)->orderBy('nombre')->pluck('nombre')->all(),
            'JEFE INMEDIATO' => array_merge(['CONSEJO SUPERIOR'], $unicos(array_column($personas, 'nombre'))),
            'TIPO DE VINCULACIÓN' => $unicos(array_merge(array_column($personas, 'tipo'), array_column($vacantes, 'tipo'), ['PROVISIONALIDAD', 'PERIODO FIJO', 'LIBRE NOMBRAMIENTO Y REMOCION', 'CARRERA ADMINISTRATIVA'])),
            'NIVEL' => $unicos(array_merge(array_column($personas, 'nivel'), ['ASESOR', 'ASISTENCIAL', 'DIRECTIVO', 'PROFESIONAL', 'TECNICO'])),
            'SI / NO' => ['SI', 'NO'],
            'ROL' => ['EVALUADO', 'EVALUADO & EVALUADOR'],
            'TIPO DE EVALUACIÓN' => array_values(self::SISTEMAS),
        ];
    }

    // ------------------------------------------------------------ Hojas

    private function hojaInstrucciones(Writer $writer, array $personas, array $vacantes): void
    {
        $writer->getCurrentSheet()->setName('INSTRUCCIONES');
        $writer->getCurrentSheet()->setColumnWidth(110, 1);
        $titulo = new Style(fontBold: true, fontName: 'Arial', fontSize: 14, fontColor: '1F4E78');
        $subtitulo = new Style(fontBold: true, fontName: 'Arial', fontSize: 11, fontColor: '1F4E78');
        $parrafo = new Style(fontName: 'Arial', fontSize: 10, shouldWrapText: true);

        $porDefinir = array_filter($vacantes, fn ($v) => ! $v['dependencia_exacta']);
        $conObservacion = count(array_filter($personas, fn ($p) => $p['observaciones'] !== ''));

        $lineas = [
            [$titulo, 'Planta de cargos y vacantes — revisión de Talento Humano'],
            [$parrafo, 'Generado desde la plataforma de evaluación el ' . now('America/Bogota')->format('d/m/Y H:i') . '. Contiene los ' . count($personas) . ' funcionarios activos y las vacantes tal como están hoy en la plataforma.'],
            [$parrafo, ''],
            [$subtitulo, 'Qué hay que hacer'],
            [$parrafo, '1. Revise la hoja «Listado ACTUALIZADO» fila por fila y corrija lo que no corresponda directamente en la celda. Las columnas con lista desplegable solo aceptan valores del catálogo.'],
            [$parrafo, '2. Revise la hoja «VACANTES». Las filas en amarillo no tienen una dependencia exacta: elija la dependencia en la columna AREA. Si una fila agrupa vacantes de varias dependencias (p. ej. los decanos de «Facultades»), escriba una fila por dependencia con su cantidad.'],
            [$parrafo, '3. Guarde el archivo en formato .xlsx sin cambiar el nombre de las hojas ni de los encabezados, y entréguelo para cargarlo en la plataforma (Administración → Catálogo de plazas → Carga de la planta). Antes de aplicar, la plataforma muestra un ensayo con cada cambio para su revisión.'],
            [$parrafo, '4. Después de la carga, Talento Humano valida las plazas en la plataforma («Validar plazas únicas») y descarga el documento oficial.'],
            [$parrafo, ''],
            [$subtitulo, 'Reglas importantes'],
            [$parrafo, '• La cédula identifica a la persona: no la modifique. Para retirar a alguien, escriba la FECHA RETIRO; para agregar a alguien, agregue la fila (debe existir como usuario en la plataforma).'],
            [$parrafo, '• El CORREO DEL CARGO identifica la plaza: si una persona cambia de cargo, debe llevar el correo del cargo que pasa a ocupar.'],
            [$parrafo, '• El JEFE INMEDIATO debe escribirse igual que el nombre de esa persona en la columna NOMBRE FUNCIONARIO (use la lista). «CONSEJO SUPERIOR» significa que no tiene jefe en la planta. El jefe inmediato pasa a ser su evaluador; si la persona tiene una evaluación abierta, el cambio queda como traslado pendiente.'],
            [$parrafo, '• ROL: «EVALUADO & EVALUADOR» si la persona evalúa a otros.'],
            [$parrafo, '• Las columnas en gris (código de plaza, evaluador en la plataforma, observaciones) son informativas: la carga no las lee. Use «OBSERVACIONES DE TH» para dejar notas.'],
            [$parrafo, ''],
            [$subtitulo, 'Pendientes para su decisión'],
            [$parrafo, '• ' . count($porDefinir) . ' fila(s) de vacantes sin dependencia exacta (' . array_sum(array_column($porDefinir, 'cantidad')) . ' plaza(s)), resaltadas en amarillo en la hoja VACANTES.'],
            [$parrafo, '• ' . $conObservacion . ' funcionario(s) con observación de la plataforma (columna «OBSERVACIONES DE TH», resaltada en amarillo).'],
            [$parrafo, '• El cuadro de conformación de la planta ya no hace falta: la plataforma lo calcula a partir del listado y las vacantes.'],
        ];
        foreach ($lineas as [$estilo, $texto]) {
            $writer->addRow(Row::fromValuesWithStyle([$texto], $estilo));
        }
    }

    private function hojaPersonas(Writer $writer, Options $options, array $personas, array $listas): void
    {
        $hoja = $writer->getCurrentSheet();
        $hoja->setSheetView((new SheetView())->withFreezeRow(2)->withFreezeColumn('D'));
        foreach ([1 => 6, 2 => 14, 3 => 34, 4 => 34, 5 => 9, 6 => 8, 7 => 38, 8 => 13, 9 => 13, 10 => 22, 11 => 32, 12 => 14, 13 => 34, 14 => 14, 15 => 22, 16 => 22, 17 => 20, 18 => 34, 19 => 50] as $col => $ancho) {
            $hoja->setColumnWidth($ancho, $col);
        }

        $writer->addRow(Row::fromValuesWithStyles(
            array_merge(self::COLUMNAS, self::INFORMATIVAS),
            array_merge(array_fill(0, count(self::COLUMNAS), $this->encabezado), array_fill(0, count(self::INFORMATIVAS), $this->encabezado->withBackgroundColor('7F7F7F'))),
            30
        ));

        foreach ($personas as $i => $p) {
            $celdas = [
                Cell::fromValue($i + 1, $this->texto), Cell::fromValue($p['cedula'], $this->texto), Cell::fromValue($p['nombre'], $this->texto),
                Cell::fromValue($p['cargo'], $this->texto), Cell::fromValue($p['codigo'], $this->texto), Cell::fromValue($p['grado'], $this->texto),
                Cell::fromValue($p['area'], $this->texto), $this->celdaFecha($p['ingreso']), $this->celdaFecha($p['retiro']),
                Cell::fromValue($p['tipo'], $this->texto), Cell::fromValue($p['correo'], $this->texto), Cell::fromValue($p['nivel'], $this->texto),
                Cell::fromValue($p['jefe'], $this->texto), Cell::fromValue($p['eje'], $this->texto), Cell::fromValue($p['rol'], $this->texto),
                Cell::fromValue($p['sistema'], $this->texto),
                Cell::fromValue($p['plaza'], $this->informativo), Cell::fromValue($p['evaluador'], $this->informativo),
                Cell::fromValue($p['observaciones'], $p['observaciones'] !== '' ? $this->resaltado : $this->informativo),
            ];
            $writer->addRow(new Row($celdas));
        }

        // Listas desplegables (filas de datos y 200 de margen para filas nuevas).
        $ultima = count($personas) + 201;
        foreach ([3 => 'CARGO', 6 => 'AREA / DEPENDENCIA', 9 => 'TIPO DE VINCULACIÓN', 11 => 'NIVEL', 12 => 'JEFE INMEDIATO', 13 => 'SI / NO', 14 => 'ROL', 15 => 'TIPO DE EVALUACIÓN'] as $col => $lista) {
            $this->validacion($options, $col, 2, $ultima, $listas, $lista, 1);
        }
    }

    private function hojaVacantes(Writer $writer, Options $options, array $vacantes, array $listas): void
    {
        $hoja = $writer->getCurrentSheet();
        foreach ([1 => 6, 2 => 40, 3 => 9, 4 => 8, 5 => 14, 6 => 22, 7 => 22, 8 => 40, 9 => 10, 10 => 14, 11 => 30, 12 => 60] as $col => $ancho) {
            $hoja->setColumnWidth($ancho, $col);
        }

        $writer->addRow(Row::fromValuesWithStyle(['CARGOS VACANTES'], new Style(fontBold: true, fontName: 'Arial', fontSize: 12, fontColor: '1F4E78')));
        $writer->addRow(Row::fromValuesWithStyles(
            ['ITEM', 'CARGO', 'CODIGO ', 'GRADO', 'NIVEL', 'Tipo de Vinculación', 'SISTEMA DE EVALUACIÓN', 'AREA', 'CANTIDAD', 'TIPO DE VACANTE', 'CÓDIGOS DE PLAZA (plataforma)', 'OBSERVACIONES'],
            array_merge(array_fill(0, 10, $this->encabezado), array_fill(0, 2, $this->encabezado->withBackgroundColor('7F7F7F'))),
            30
        ));

        foreach ($vacantes as $i => $v) {
            $area = $v['dependencia_exacta'] ? $this->texto : $this->resaltado;
            $writer->addRow(new Row([
                Cell::fromValue($i + 1, $this->texto), Cell::fromValue($v['cargo'], $this->texto), Cell::fromValue($v['codigo'], $this->texto),
                Cell::fromValue($v['grado'], $this->texto), Cell::fromValue($v['nivel'], $this->texto), Cell::fromValue($v['tipo'], $this->texto),
                Cell::fromValue($v['sistema'], $this->texto), Cell::fromValue($v['area'], $area), Cell::fromValue($v['cantidad'], $this->texto),
                Cell::fromValue($v['tipo_vacante'], $this->texto), Cell::fromValue($v['plazas'], $this->informativo),
                Cell::fromValue($v['observaciones'], $v['observaciones'] !== '' ? $this->resaltado : $this->informativo),
            ]));
        }

        // Espacio para filas nuevas antes del total; la carga lee hasta la fila TOTAL.
        for ($i = 0; $i < 10; $i++) {
            $writer->addRow(Row::fromValues([]));
        }
        $writer->addRow(Row::fromValuesWithStyles(
            ['TOTAL VACANTES', null, null, null, null, null, null, null, array_sum(array_column($vacantes, 'cantidad'))],
            array_fill(0, 9, $this->texto->withFontBold(true))
        ));

        $ultima = count($vacantes) + 12;
        foreach ([1 => 'CARGO', 4 => 'NIVEL', 5 => 'TIPO DE VINCULACIÓN', 6 => 'TIPO DE EVALUACIÓN', 7 => 'AREA / DEPENDENCIA'] as $col => $lista) {
            $this->validacion($options, $col, 3, $ultima, $listas, $lista, 2);
        }
    }

    private function hojaListas(Writer $writer, array $listas): void
    {
        $hoja = $writer->getCurrentSheet();
        foreach (array_keys($listas) as $i => $_) {
            $hoja->setColumnWidth(40, $i + 1);
        }
        $writer->addRow(Row::fromValuesWithStyle(array_keys($listas), $this->encabezado));
        $alto = max(array_map('count', $listas));
        for ($n = 0; $n < $alto; $n++) {
            $writer->addRow(Row::fromValuesWithStyle(array_map(fn ($l) => $l[$n] ?? null, array_values($listas)), $this->texto));
        }
    }

    /** Lista desplegable que toma sus opciones de la hoja LISTAS (sin límite de 255 caracteres). */
    private function validacion(Options $options, int $columna, int $desde, int $hasta, array $listas, string $lista, int $hoja): void
    {
        $indice = array_search($lista, array_keys($listas), true);
        $options->addValidation($columna, $desde, $columna, $hasta,
            new ListValidationRule(new CellReference($indice, 2, $indice, count($listas[$lista]) + 1, true, 'LISTAS')),
            new ValidationDisplay(errorTitle: 'Valor no válido', error: "Elija un valor de la lista ({$lista})."),
            $hoja);
    }

    private function celdaFecha(?string $fecha): Cell
    {
        return $fecha ? Cell::fromValue(new \DateTimeImmutable($fecha), $this->fecha) : Cell::fromValue(null, $this->texto);
    }
}
