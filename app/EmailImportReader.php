<?php

namespace App;

use DOMDocument;
use DOMElement;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class EmailImportReader
{
    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }

    /** @return array<int,array{number:int,cells:array<int,string>,formula:bool}> */
    public function read(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['csv', 'xlsx'], true)) {
            $this->invalid('Gunakan file .xlsx atau .csv.');
        }
        if ($file->getSize() > 5 * 1024 * 1024) {
            $this->invalid('Ukuran file maksimal 5 MB.');
        }

        return $extension === 'csv' ? $this->csv(file_get_contents($file->getRealPath())) : $this->xlsx($file->getRealPath());
    }

    /** @return array<int,array{number:int,cells:array<int,string>,formula:bool}> */
    private function csv(string $input): array
    {
        if (! mb_check_encoding($input, 'UTF-8')) {
            $this->invalid('Simpan CSV menggunakan UTF-8.');
        }
        $input = preg_replace('/^\\xEF\\xBB\\xBF/', '', $input);
        $line = strtok($input, "\r\n") ?: '';
        $separator = str_contains($line, ';') ? ';' : (str_contains($line, "\t") ? "\t" : ',');
        $rows = [];
        $cells = [];
        $cell = '';
        $quoted = false;
        $closed = false;
        $number = 1;
        $rowNumber = 1;
        $finish = function () use (&$rows, &$cells, &$cell, &$closed, &$rowNumber): void {
            $cells[] = $cell;
            $rows[] = ['number' => $rowNumber, 'cells' => $cells, 'formula' => false];
            if (count($rows) > 10001) {
                $this->invalid('Maksimal 10.000 baris email per file.');
            }
            $cells = [];
            $cell = '';
            $closed = false;
        };
        $length = strlen($input);
        for ($index = 0; $index < $length; $index++) {
            $char = $input[$index];
            if ($quoted) {
                if ($char === '"') {
                    if (($input[$index + 1] ?? '') === '"') {
                        $cell .= '"';
                        $index++;
                    } else {
                        $quoted = false;
                        $closed = true;
                    }
                } else {
                    $cell .= $char;
                    if ($char === "\n") {
                        $number++;
                    }
                }
            } elseif ($char === '"') {
                if (trim($cell) !== '' || $closed) {
                    $this->invalid('Format tanda kutip CSV tidak valid.');
                }
                $cell = '';
                $quoted = true;
            } elseif ($char === $separator) {
                $cells[] = $cell;
                $cell = '';
                $closed = false;
            } elseif ($char === "\r" || $char === "\n") {
                if ($char === "\r" && ($input[$index + 1] ?? '') === "\n") {
                    $index++;
                }
                $finish();
                $number++;
                $rowNumber = $number;
            } else {
                if ($closed && trim($char) !== '') {
                    $this->invalid('Format tanda kutip CSV tidak valid.');
                }
                if (! $closed) {
                    $cell .= $char;
                }
            }
        }
        if ($quoted) {
            $this->invalid('Tanda kutip CSV belum ditutup.');
        }
        if ($cell !== '' || $cells !== [] || $closed) {
            $finish();
        }

        return $rows;
    }

    private function xml(ZipArchive $zip, string $path, bool $optional = false): ?DOMDocument
    {
        $stat = $zip->statName($path);
        if (! $stat) {
            if ($optional) {
                return null;
            }

            $this->invalid('File Excel tidak lengkap. Simpan ulang sebagai .xlsx.');
        }
        if ($stat['size'] > 8 * 1024 * 1024) {
            $this->invalid('Isi worksheet terlalu besar.');
        }
        $text = $zip->getFromName($path);
        if ($text === false || preg_match('/<!DOCTYPE|<!ENTITY/i', $text)) {
            $this->invalid('Struktur XML Excel tidak didukung.');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = new DOMDocument;
            if (! $xml->loadXML($text, LIBXML_NONET) || $xml->doctype !== null) {
                $this->invalid('Struktur file Excel tidak valid.');
            }

            return $xml;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function text(DOMElement $element, string $name): string
    {
        $value = '';
        foreach ($element->getElementsByTagNameNS('*', $name) as $node) {
            $value .= $node->textContent;
        }

        return $value;
    }

    /** @return array<int,array{number:int,cells:array<int,string>,formula:bool}> */
    private function xlsx(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            $this->invalid('File Excel tidak dapat dibaca. Gunakan .xlsx tanpa kata sandi.');
        }
        try {
            if ($zip->numFiles > 2000) {
                $this->invalid('Struktur file Excel terlalu besar.');
            }
            $size = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $size += $zip->statIndex($index)['size'];
                if ($size > 32 * 1024 * 1024) {
                    $this->invalid('Isi file Excel terlalu besar.');
                }
            }
            $workbook = $this->xml($zip, 'xl/workbook.xml');
            $relations = $this->xml($zip, 'xl/_rels/workbook.xml.rels');
            $sheet = $workbook->getElementsByTagNameNS('*', 'sheet')->item(0);
            if (! $sheet instanceof DOMElement) {
                $this->invalid('Worksheet tidak ditemukan.');
            }
            $id = $sheet->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
            $target = null;
            foreach ($relations->getElementsByTagNameNS('*', 'Relationship') as $relation) {
                if ($relation->getAttribute('Id') === $id) {
                    if (strtolower($relation->getAttribute('TargetMode')) === 'external') {
                        $this->invalid('Worksheet eksternal tidak didukung.');
                    }
                    $target = $relation->getAttribute('Target');
                }
            }
            if (! $target || preg_match('/[:?#]/', $target)) {
                $this->invalid('Worksheet pertama tidak didukung.');
            }
            $parts = [];
            foreach (explode('/', str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.str_replace('\\', '/', $target)) as $part) {
                if ($part === '..') {
                    array_pop($parts);
                } elseif ($part !== '' && $part !== '.') {
                    $parts[] = $part;
                }
            }
            $worksheetPath = implode('/', $parts);
            if (! str_starts_with($worksheetPath, 'xl/')) {
                $this->invalid('Lokasi worksheet tidak didukung.');
            }
            $worksheet = $this->xml($zip, $worksheetPath);
            $shared = $this->xml($zip, 'xl/sharedStrings.xml', true);
            $strings = [];
            if ($shared) {
                foreach ($shared->getElementsByTagNameNS('*', 'si') as $node) {
                    $strings[] = $this->text($node, 't');
                }
            }
            $rows = [];
            foreach ($worksheet->getElementsByTagNameNS('*', 'row') as $index => $row) {
                if (count($rows) >= 10001) {
                    $this->invalid('Maksimal 10.000 baris email per file.');
                }
                $number = $row->hasAttribute('r') ? (int) $row->getAttribute('r') : $index + 1;
                if ($number < 1 || $number > 10001) {
                    $this->invalid('Maksimal 10.000 baris email per file.');
                }
                $cells = [];
                $formula = false;
                foreach ($row->getElementsByTagNameNS('*', 'c') as $cell) {
                    $reference = $cell->getAttribute('r');
                    if (! preg_match('/^([A-Z]+)[1-9][0-9]*$/', $reference, $match)) {
                        $this->invalid('Referensi sel Excel tidak valid.');
                    }
                    $raw = $this->text($cell, 'v');
                    $type = $cell->getAttribute('t');
                    if ($type === 's') {
                        if (! ctype_digit($raw) || ! array_key_exists((int) $raw, $strings)) {
                            $this->invalid('Indeks teks Excel tidak valid.');
                        }
                        $value = $strings[(int) $raw];
                    } else {
                        $value = $type === 'inlineStr' ? $this->text($cell, 't') : $raw;
                    }
                    $formula = $formula || $cell->getElementsByTagNameNS('*', 'f')->length > 0;
                    if ($match[1] === 'A') {
                        $cells[0] = $value;
                    } elseif (trim($value) !== '') {
                        $cells[1] = $value;
                    }
                }
                $rows[] = ['number' => $number, 'cells' => $cells, 'formula' => $formula];
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    /** @param array<int,array{number:int,cells:array<int,string>,formula:bool}> $rows
     * @param  array<int,string>  $existing
     * @return array{rows:array,valid:array,duplicates:int,invalid:int,blank:int}
     */
    public function validateRows(array $rows, array $existing): array
    {
        $header = null;
        foreach ($rows as $index => $row) {
            if (array_any($row['cells'], fn (string $cell): bool => trim($cell) !== '')) {
                $header = $index;
                break;
            }
        }
        if ($header === null) {
            $this->invalid('File kosong. Gunakan kolom email pada baris pertama.');
        }
        $first = $rows[$header];
        if (strtolower(trim($first['cells'][0] ?? '')) !== 'email' || $first['formula'] || array_any(array_slice($first['cells'], 1), fn (string $cell): bool => trim($cell) !== '')) {
            $this->invalid('Gunakan satu kolom dengan judul email pada baris pertama yang terisi.');
        }
        $known = array_fill_keys($existing, true);
        $seen = [];
        $result = ['rows' => [], 'valid' => [], 'duplicates' => 0, 'invalid' => 0, 'blank' => 0];
        foreach (array_slice($rows, $header + 1) as $row) {
            if (! $row['formula'] && ! array_any($row['cells'], fn (string $cell): bool => trim($cell) !== '')) {
                $result['blank']++;

                continue;
            }
            $value = trim($row['cells'][0] ?? '');
            $email = strtolower($value);
            $item = ['number' => $row['number'], 'value' => mb_substr($value, 0, 300), 'email' => $email, 'status' => 'valid', 'reason' => 'Siap ditambahkan'];
            $valid = Validator::make(['email' => $email], ['email' => ['required', 'string', 'email:rfc', 'max:254']])->passes();
            if ($row['formula'] || ! $valid || array_any(array_slice($row['cells'], 1), fn (string $cell): bool => trim($cell) !== '')) {
                $item['status'] = 'invalid';
                $item['reason'] = $row['formula'] ? 'Gunakan nilai email, bukan rumus' : 'Format email tidak valid atau terdapat kolom tambahan';
                $result['invalid']++;
            } elseif (isset($seen[$email]) || isset($known[$email])) {
                $item['status'] = 'duplicate';
                $item['reason'] = isset($seen[$email]) ? 'Berulang di dalam file' : 'Sudah ada di daftar email';
                $result['duplicates']++;
            } else {
                $result['valid'][] = $email;
            }
            if ($item['status'] !== 'invalid') {
                $seen[$email] = true;
            }
            $result['rows'][] = $item;
        }
        if ($result['rows'] === []) {
            $this->invalid('Belum ada email di file ini.');
        }

        return $result;
    }
}
