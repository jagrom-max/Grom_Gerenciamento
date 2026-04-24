<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class SpreadsheetImportReader
{
    public function read(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        return match ($extension) {
            'csv', 'txt' => $this->readCsv($file),
            'xlsx' => $this->readXlsx($file),
            default => throw new RuntimeException('Formato nao suportado. Envie um arquivo CSV, TXT ou XLSX.'),
        };
    }

    private function readCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'rb');

        if (! is_resource($handle)) {
            throw new RuntimeException('Nao foi possivel abrir o arquivo CSV informado.');
        }

        try {
            $firstLine = fgets($handle);
            rewind($handle);

            $delimiter = $this->detectDelimiter((string) $firstLine);
            $headers = null;
            $rows = [];

            while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
                if ($values === [null]) {
                    continue;
                }

                $values = array_map(
                    fn ($value) => trim((string) preg_replace('/^\xEF\xBB\xBF/u', '', (string) $value)),
                    $values,
                );

                if ($headers === null) {
                    $headers = $this->normalizeHeaders($values);
                    continue;
                }

                if ($this->rowIsEmpty($values)) {
                    continue;
                }

                $rows[] = $this->associateRow($headers, $values);
            }
        } finally {
            fclose($handle);
        }

        if ($headers === null) {
            throw new RuntimeException('O arquivo CSV esta vazio ou sem cabecalho legivel.');
        }

        return [
            'sheet' => 'CSV',
            'header_row' => 1,
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    private function readXlsx(UploadedFile $file): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('O ambiente PHP atual nao possui suporte a leitura de arquivos XLSX.');
        }

        $zip = new ZipArchive();
        if ($zip->open($file->getRealPath()) !== true) {
            throw new RuntimeException('Nao foi possivel abrir o arquivo XLSX informado.');
        }

        try {
            $sheetPath = $this->firstWorksheetPath($zip);
            $sheetXml = $zip->getFromName($sheetPath);

            if ($sheetXml === false) {
                throw new RuntimeException('Nao foi possivel ler a primeira planilha do arquivo XLSX.');
            }

            $sharedStrings = $this->readSharedStrings($zip);
            $xml = $this->loadXml($sheetXml);
            $headers = null;
            $rows = [];

            foreach ($xml->sheetData->row as $row) {
                $indexedValues = [];

                foreach ($row->c as $cell) {
                    $reference = (string) ($cell['r'] ?? '');
                    $index = $this->columnReferenceToIndex($reference);
                    $indexedValues[$index] = $this->cellValue($cell, $sharedStrings);
                }

                if ($indexedValues === []) {
                    continue;
                }

                ksort($indexedValues);
                $values = [];
                $maxIndex = max(array_keys($indexedValues));

                for ($i = 0; $i <= $maxIndex; $i++) {
                    $values[] = trim((string) ($indexedValues[$i] ?? ''));
                }

                if ($headers === null) {
                    $headers = $this->normalizeHeaders($values);
                    continue;
                }

                if ($this->rowIsEmpty($values)) {
                    continue;
                }

                $rows[] = $this->associateRow($headers, $values);
            }
        } finally {
            $zip->close();
        }

        if ($headers === null) {
            throw new RuntimeException('O arquivo XLSX esta vazio ou sem cabecalho legivel.');
        }

        return [
            'sheet' => basename($sheetPath),
            'header_row' => 1,
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    private function detectDelimiter(string $line): string
    {
        $counts = [
            ';' => substr_count($line, ';'),
            ',' => substr_count($line, ','),
            "\t" => substr_count($line, "\t"),
        ];

        arsort($counts);

        return (string) array_key_first($counts);
    }

    private function normalizeHeaders(array $headers): array
    {
        return array_values(array_map(
            fn ($header, $index) => trim((string) $header) !== '' ? trim((string) $header) : sprintf('COLUNA_%d', $index + 1),
            $headers,
            array_keys($headers),
        ));
    }

    private function associateRow(array $headers, array $values): array
    {
        $row = [];

        foreach ($headers as $index => $header) {
            $row[$header] = trim((string) ($values[$index] ?? ''));
        }

        return $row;
    }

    private function rowIsEmpty(array $values): bool
    {
        foreach ($values as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function firstWorksheetPath(ZipArchive $zip): string
    {
        $paths = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (preg_match('/^xl\/worksheets\/sheet\d+\.xml$/i', $name)) {
                $paths[] = $name;
            }
        }

        sort($paths, SORT_NATURAL);

        if ($paths === []) {
            throw new RuntimeException('Nenhuma planilha valida foi encontrada no arquivo XLSX.');
        }

        return $paths[0];
    }

    private function readSharedStrings(ZipArchive $zip): array
    {
        $contents = $zip->getFromName('xl/sharedStrings.xml');
        if ($contents === false) {
            return [];
        }

        $xml = $this->loadXml($contents);
        $strings = [];

        foreach ($xml->si as $item) {
            $parts = [];

            if (isset($item->t)) {
                $parts[] = (string) $item->t;
            }

            foreach ($item->r as $run) {
                if (isset($run->t)) {
                    $parts[] = (string) $run->t;
                }
            }

            $strings[] = implode('', $parts);
        }

        return $strings;
    }

    private function cellValue(SimpleXMLElement $cell, array $sharedStrings): string
    {
        $type = (string) ($cell['t'] ?? '');

        return match ($type) {
            's' => (string) ($sharedStrings[(int) $cell->v] ?? ''),
            'inlineStr' => (string) ($cell->is->t ?? ''),
            'b' => ((string) $cell->v) === '1' ? '1' : '0',
            default => trim((string) ($cell->v ?? '')),
        };
    }

    private function columnReferenceToIndex(string $reference): int
    {
        if (! preg_match('/([A-Z]+)/i', $reference, $matches)) {
            return 0;
        }

        $letters = strtoupper($matches[1]);
        $index = 0;

        for ($i = 0; $i < strlen($letters); $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }

        return max($index - 1, 0);
    }

    private function loadXml(string $contents): SimpleXMLElement
    {
        $normalized = preg_replace('/xmlns(:\w+)?="[^"]*"/i', '', $contents);
        $xml = simplexml_load_string((string) $normalized);

        if (! $xml instanceof SimpleXMLElement) {
            throw new RuntimeException('Nao foi possivel interpretar a estrutura interna da planilha.');
        }

        return $xml;
    }
}
