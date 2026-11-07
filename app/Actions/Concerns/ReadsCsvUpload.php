<?php

namespace App\Actions\Concerns;

use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

trait ReadsCsvUpload
{
    /**
     * Read the rows below the heading row of an uploaded CSV file, keyed by their line number in the file. Each row is an array
     * keyed by the known column names; unknown columns are ignored and missing optional columns are empty strings.
     *
     * @param  array<int, string>  $required  headings that must be present
     * @param  array<int, string>  $optional  headings that may be present
     * @return array<int, array<string, string>>
     *
     * @throws InvalidArgumentException when the file is not a usable import file
     */
    protected function readCsv(UploadedFile $file, array $required, array $optional = [], int $maxRows = 500): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            throw new InvalidArgumentException('The file could not be read.');
        }

        $header = fgetcsv($handle, escape: '\\');
        $headings = array_map(fn (?string $column) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $column) ?? '')), $header ?: []);
        $known = [...$required, ...$optional];

        if (array_diff($required, $headings) !== []) {
            fclose($handle);

            throw new InvalidArgumentException('The first row must be the headings: '.implode(', ', $required).'. Download the template to start from.');
        }

        $rows = [];
        $line = 1;

        while (($cells = fgetcsv($handle, escape: '\\')) !== false) {
            $line++;

            if (count(array_filter($cells, fn ($cell) => trim((string) $cell) !== '')) === 0) {
                continue;
            }

            if (count($rows) >= $maxRows) {
                fclose($handle);

                throw new InvalidArgumentException("A file can have at most {$maxRows} rows. Split it and upload again.");
            }

            $row = array_fill_keys($known, '');

            foreach ($headings as $index => $heading) {
                if (in_array($heading, $known, true)) {
                    $row[$heading] = trim((string) ($cells[$index] ?? ''));
                }
            }

            $rows[$line] = $row;
        }

        fclose($handle);

        if ($rows === []) {
            throw new InvalidArgumentException('The file has no rows below the headings.');
        }

        return $rows;
    }
}
