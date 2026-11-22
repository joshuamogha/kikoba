<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Writes a single-sheet .xlsx workbook without any extra package. Numbers stay numbers, everything else is text.
 */
class XlsxWriter
{
    /**
     * @param  array<int, array<int, string|int|float|null>>  $rows  every line of the sheet
     * @param  array<int, int>  $boldRows  the indexes of the lines shown in bold (headings and titles)
     * @return string the path of the temporary file; the caller deletes it
     *
     * @throws RuntimeException when the file cannot be created
     */
    public static function write(string $sheetName, array $rows, array $boldRows = [0]): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;

        if ($path === false || $zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('The workbook could not be created.');
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="'.self::escape(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', ' ', $sheetName), 0, 31)).'" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs></styleSheet>');
        $zip->addFromString('xl/worksheets/sheet1.xml', self::sheet($rows, $boldRows));
        $zip->close();

        return $path;
    }

    /**
     * @param  array<int, array<int, string|int|float|null>>  $rows
     * @param  array<int, int>  $boldRows
     */
    private static function sheet(array $rows, array $boldRows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach (array_values($rows) as $r => $row) {
            $xml .= '<row r="'.($r + 1).'">';

            foreach (array_values($row) as $c => $value) {
                $ref = self::column($c).($r + 1);
                $style = in_array($r, $boldRows, true) ? ' s="1"' : '';

                if ($value === null || $value === '') {
                    continue;
                }

                $xml .= is_int($value) || is_float($value)
                    ? "<c r=\"{$ref}\"{$style}><v>{$value}</v></c>"
                    : "<c r=\"{$ref}\"{$style} t=\"inlineStr\"><is><t xml:space=\"preserve\">".self::escape((string) $value).'</t></is></c>';
            }

            $xml .= '</row>';
        }

        return $xml.'</sheetData></worksheet>';
    }

    private static function column(int $index): string
    {
        $name = '';

        for ($n = $index + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $name = chr(65 + ($n - 1) % 26).$name;
        }

        return $name;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
