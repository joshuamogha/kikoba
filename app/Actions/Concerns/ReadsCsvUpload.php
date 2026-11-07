<?php

namespace App\Actions\Concerns;

use App\Models\UserManagement\User;
use App\Support\XlsxReader;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

trait ReadsCsvUpload
{
    /**
     * Read the rows below the heading row of an uploaded CSV or Excel (.xlsx) file, keyed by their line number in the file. Each row is
     * an array keyed by the known column names; unknown columns are ignored and missing optional columns are empty strings.
     *
     * @param  array<int, string>  $required  headings that must be present
     * @param  array<int, string>  $optional  headings that may be present
     * @return array<int, array<string, string>>
     *
     * @throws InvalidArgumentException when the file is not a usable import file
     */
    protected function readCsv(UploadedFile $file, array $required, array $optional = [], int $maxRows = 500): array
    {
        $lines = $this->isExcelFile($file) ? XlsxReader::rows($file->getRealPath()) : $this->csvLines($file);
        $known = [...$required, ...$optional];

        $headingLine = array_key_first($lines);
        $headings = array_map(
            fn (?string $column) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $column) ?? '')),
            $headingLine === null ? [] : $lines[$headingLine],
        );

        if (array_diff($required, $headings) !== []) {
            throw new InvalidArgumentException('The first row must be the headings: '.implode(', ', $required).'. Download the template to start from.');
        }

        $rows = [];

        foreach (array_slice($lines, 1, preserve_keys: true) as $number => $cells) {
            if (count(array_filter($cells, fn ($cell) => trim((string) $cell) !== '')) === 0) {
                continue;
            }

            if (count($rows) >= $maxRows) {
                throw new InvalidArgumentException("A file can have at most {$maxRows} rows. Split it and upload again.");
            }

            $row = array_fill_keys($known, '');

            foreach ($headings as $index => $heading) {
                if (in_array($heading, $known, true)) {
                    $row[$heading] = trim((string) ($cells[$index] ?? ''));
                }
            }

            $rows[$number] = $row;
        }

        if ($rows === []) {
            throw new InvalidArgumentException('The file has no rows below the headings.');
        }

        return $rows;
    }

    /**
     * A phone number cleaned for lookups. Excel drops the leading zero of a number typed as 0712345678, so a bare
     * 9-digit local number is given its zero back.
     */
    protected function cleanPhone(string $raw): string
    {
        $phone = User::normalizePhone($raw);

        return preg_match('/^[67][0-9]{8}$/', $phone) === 1 ? '0'.$phone : $phone;
    }

    private function isExcelFile(UploadedFile $file): bool
    {
        return strtolower($file->getClientOriginalExtension()) === 'xlsx';
    }

    /**
     * The lines of a CSV file keyed by their line number.
     *
     * @return array<int, array<int, string|null>>
     */
    private function csvLines(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            throw new InvalidArgumentException('The file could not be read.');
        }

        $lines = [];
        $line = 0;

        while (($cells = fgetcsv($handle, escape: '\\')) !== false) {
            $lines[++$line] = $cells;
        }

        fclose($handle);

        return $lines;
    }
}
