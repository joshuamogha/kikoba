<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Reads the first worksheet of an .xlsx file as plain text cells, without any extra package.
 * Cells with a date format come back as "Y-m-d H:i:s" (or "Y-m-d" for whole days).
 */
class XlsxReader
{
    /**
     * Built-in Excel number formats that show a date and/or time.
     *
     * @var array<int, int>
     */
    private const BUILT_IN_DATE_FORMATS = [14, 15, 16, 17, 18, 19, 20, 21, 22, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 45, 46, 47, 50, 51, 52, 53, 54, 55, 56, 57, 58];

    /**
     * @return array<int, array<int, string>> rows keyed by their row number, each a list of cells from the first column
     *
     * @throws InvalidArgumentException when the file is not a readable workbook
     */
    public static function rows(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new InvalidArgumentException('The file is not a valid Excel (.xlsx) file.');
        }

        try {
            $sheet = self::xml($zip, self::firstSheetPath($zip));
            $strings = self::sharedStrings($zip);
            $dateStyles = self::dateStyles($zip);
        } finally {
            $zip->close();
        }

        $rows = [];
        $nextRow = 0;

        foreach ($sheet->sheetData->row ?? [] as $row) {
            $rowNumber = isset($row['r']) ? (int) $row['r'] : $nextRow + 1;
            $nextRow = $rowNumber;
            $cells = [];
            $nextColumn = 0;

            foreach ($row->c as $cell) {
                $column = isset($cell['r']) ? self::columnIndex((string) $cell['r']) : $nextColumn;
                $nextColumn = $column + 1;
                $cells[$column] = self::cellText($cell, $strings, $dateStyles);
            }

            $rows[$rowNumber] = $cells === [] ? [] : array_replace(array_fill(0, max(array_keys($cells)) + 1, ''), $cells);
        }

        return $rows;
    }

    private static function xml(ZipArchive $zip, string $name): SimpleXMLElement
    {
        $contents = $zip->getFromName($name);
        $xml = $contents === false ? false : @simplexml_load_string($contents);

        if ($xml === false) {
            throw new InvalidArgumentException('The file is not a valid Excel (.xlsx) file.');
        }

        return $xml;
    }

    private static function firstSheetPath(ZipArchive $zip): string
    {
        $workbook = self::xml($zip, 'xl/workbook.xml');
        $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $relationshipId = (string) ($workbook->sheets->sheet[0]->attributes('r', true)['id'] ?? '');

        $relationships = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($relationshipId !== '' && $relationships !== false && ($xml = @simplexml_load_string($relationships)) !== false) {
            foreach ($xml->Relationship as $relationship) {
                if ((string) $relationship['Id'] === $relationshipId) {
                    return 'xl/'.ltrim(preg_replace('#^/?(xl/)?#', '', (string) $relationship['Target']) ?? '', '/');
                }
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    /**
     * @return array<int, string>
     */
    private static function sharedStrings(ZipArchive $zip): array
    {
        $contents = $zip->getFromName('xl/sharedStrings.xml');

        if ($contents === false || ($xml = @simplexml_load_string($contents)) === false) {
            return [];
        }

        $strings = [];

        foreach ($xml->si as $item) {
            $strings[] = self::richText($item);
        }

        return $strings;
    }

    private static function richText(SimpleXMLElement $item): string
    {
        if (isset($item->t)) {
            return (string) $item->t;
        }

        $text = '';

        foreach ($item->r ?? [] as $run) {
            $text .= (string) $run->t;
        }

        return $text;
    }

    /**
     * The style indexes (cellXfs) whose number format is a date or time.
     *
     * @return array<int, true>
     */
    private static function dateStyles(ZipArchive $zip): array
    {
        $contents = $zip->getFromName('xl/styles.xml');

        if ($contents === false || ($xml = @simplexml_load_string($contents)) === false) {
            return [];
        }

        $customDateFormats = [];

        foreach ($xml->numFmts->numFmt ?? [] as $format) {
            $code = preg_replace('/"[^"]*"|\[[^\]]*\]|\\\\./', '', (string) $format['formatCode']) ?? '';

            if (preg_match('/[dmyhs]/i', $code) === 1) {
                $customDateFormats[(int) $format['numFmtId']] = true;
            }
        }

        $styles = [];

        $index = 0;

        foreach ($xml->cellXfs->xf ?? [] as $xf) {
            $id = (int) $xf['numFmtId'];

            if (in_array($id, self::BUILT_IN_DATE_FORMATS, true) || isset($customDateFormats[$id])) {
                $styles[$index] = true;
            }

            $index++;
        }

        return $styles;
    }

    /**
     * @param  array<int, string>  $strings
     * @param  array<int, true>  $dateStyles
     */
    private static function cellText(SimpleXMLElement $cell, array $strings, array $dateStyles): string
    {
        $type = (string) $cell['t'];

        if ($type === 'inlineStr') {
            return trim(self::richText($cell->is));
        }

        $value = trim((string) $cell->v);

        return match (true) {
            $type === 's' => trim($strings[(int) $value] ?? ''),
            $type === 'b' => $value === '1' ? 'TRUE' : 'FALSE',
            $type === 'str' || $type === 'e' => $value,
            $value !== '' && is_numeric($value) && isset($dateStyles[(int) $cell['s']]) => self::serialToDate((float) $value),
            $value !== '' && is_numeric($value) => self::numberText($value),
            default => $value,
        };
    }

    private static function numberText(string $value): string
    {
        return str_contains(strtolower($value), 'e') ? rtrim(rtrim(number_format((float) $value, 6, '.', ''), '0'), '.') : $value;
    }

    private static function serialToDate(float $serial): string
    {
        $seconds = (int) round(($serial - floor($serial)) * 86400);
        $date = Carbon::create(1899, 12, 30)->addDays((int) floor($serial))->addSeconds($seconds);

        return $seconds === 0 ? $date->format('Y-m-d') : $date->format('Y-m-d H:i:s');
    }

    private static function columnIndex(string $reference): int
    {
        $letters = strtoupper(preg_replace('/[^A-Za-z]/', '', $reference) ?? '');
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return max($index - 1, 0);
    }
}
