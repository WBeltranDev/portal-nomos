<?php

namespace App\Services;

use RuntimeException;
use XMLReader;
use ZipArchive;

/**
 * Lector mínimo de .xlsx que entrega los valores crudos de cada celda (texto
 * o número), sin interpretar estilos. Las planillas institucionales llegan de
 * herramientas distintas y algunos formatos numéricos (p. ej. cédulas en
 * notación científica con estilo propio) se pierden en lectores que deducen
 * el tipo por el estilo. Las fechas se convierten donde se sabe que lo son,
 * con fechaExcel().
 */
class LectorXlsx
{
    /** @return array<string, array<int, array<int, string|int|float|bool|null>>> hoja => filas (1-based) => columnas (1-based) */
    public function leer(string $ruta): array
    {
        $zip = new ZipArchive();
        if ($zip->open($ruta) !== true) {
            throw new RuntimeException('El archivo no es un Excel (.xlsx) válido.');
        }

        try {
            $compartidos = $this->textosCompartidos($zip);
            $hojas = [];
            foreach ($this->hojas($zip) as $nombre => $ruta) {
                $xml = $zip->getFromName($ruta);
                $hojas[$nombre] = $xml === false ? [] : $this->filas($xml, $compartidos);
            }

            return $hojas;
        } finally {
            $zip->close();
        }
    }

    /** Número de serie de Excel (sistema 1900) a fecha Y-m-d; null si no es fecha. */
    public static function fechaExcel(mixed $valor): ?string
    {
        if (is_numeric($valor) && (float) $valor > 0) {
            return (new \DateTimeImmutable('1899-12-30'))->modify('+' . (int) floor((float) $valor) . ' days')->format('Y-m-d');
        }
        if (is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2}/', trim($valor))) {
            return substr(trim($valor), 0, 10);
        }

        return null;
    }

    /** Nombre de cada hoja => ruta de su XML dentro del zip. */
    private function hojas(ZipArchive $zip): array
    {
        $libro = simplexml_load_string((string) $zip->getFromName('xl/workbook.xml'));
        $relaciones = simplexml_load_string((string) $zip->getFromName('xl/_rels/workbook.xml.rels'));
        if (! $libro || ! $relaciones) {
            throw new RuntimeException('El Excel no tiene la estructura esperada (libro u hojas).');
        }

        $destinos = [];
        foreach ($relaciones->Relationship as $rel) {
            $destinos[(string) $rel['Id']] = ltrim(str_replace('/xl/', '', (string) $rel['Target']), '/');
        }

        $hojas = [];
        foreach ($libro->sheets->sheet as $hoja) {
            $id = (string) $hoja->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            if (isset($destinos[$id])) {
                $hojas[(string) $hoja['name']] = 'xl/' . $destinos[$id];
            }
        }

        return $hojas;
    }

    private function textosCompartidos(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $textos = [];
        $lector = new XMLReader();
        $lector->XML($xml);
        while ($lector->read()) {
            if ($lector->nodeType === XMLReader::ELEMENT && $lector->localName === 'si') {
                // Texto simple (<t>) o enriquecido (varios <r><t>): se concatenan.
                $nodo = simplexml_load_string($lector->readOuterXml());
                $textos[] = $nodo ? implode('', array_map('strval', $nodo->xpath('.//*[local-name()="t"]'))) : '';
            }
        }

        return $textos;
    }

    private function filas(string $xml, array $compartidos): array
    {
        $filas = [];
        $lector = new XMLReader();
        $lector->XML($xml);

        while ($lector->read()) {
            if ($lector->nodeType !== XMLReader::ELEMENT || $lector->localName !== 'c') {
                continue;
            }

            [$columna, $fila] = $this->referencia((string) $lector->getAttribute('r'));
            $tipo = $lector->getAttribute('t');
            $celda = simplexml_load_string($lector->readOuterXml());
            if (! $celda || $fila === 0) {
                continue;
            }

            $crudo = isset($celda->v) ? (string) $celda->v : null;
            $valor = match ($tipo) {
                's' => $compartidos[(int) $crudo] ?? null,
                'inlineStr' => implode('', array_map('strval', $celda->xpath('.//*[local-name()="t"]'))),
                'str', 'e' => $crudo,
                'b' => $crudo === '1',
                default => $crudo === null || $crudo === '' ? null : $this->numero($crudo),
            };

            if ($valor !== null && $valor !== '') {
                $filas[$fila][$columna] = is_string($valor) ? trim($valor) : $valor;
            }
        }

        return $filas;
    }

    /** "1.00774757E9" => 1007747570; "407.0" => 407; "3.5" => 3.5. */
    private function numero(string $crudo): int|float|string
    {
        if (! is_numeric($crudo)) {
            return $crudo;
        }
        $flotante = (float) $crudo;

        return floor($flotante) === $flotante && abs($flotante) < PHP_INT_MAX ? (int) $flotante : $flotante;
    }

    /** "AB12" => [28, 12] */
    private function referencia(string $ref): array
    {
        if (! preg_match('/^([A-Z]+)(\d+)$/', $ref, $m)) {
            return [0, 0];
        }
        $columna = 0;
        foreach (str_split($m[1]) as $letra) {
            $columna = $columna * 26 + (ord($letra) - 64);
        }

        return [$columna, (int) $m[2]];
    }
}
