<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Turns lists and reports into a downloadable Excel workbook, a CSV file, or a print-ready page to save as PDF. Every export is a
 * list of sections; each section is one table (a title, headings, rows and an optional totals line).
 *
 * @phpstan-type Section array{title: string|null, headings: array<int, string>, rows: array<int, array<int, string|int|float|null>>, totals: array<int, string|int|float|null>|null}
 */
class TableExport
{
    public const FORMATS = ['xlsx', 'pdf', 'csv'];

    /**
     * Whether the request asks for a file (format=xlsx, pdf or csv).
     */
    public static function wanted(Request $request): bool
    {
        return in_array($request->query('format'), self::FORMATS, true);
    }

    /**
     * The chosen format and columns of an export request. Without columns, every column is exported (null).
     *
     * @param  array<int, string>  $allowed  the column keys that may be chosen
     * @return array{format: string, columns: array<int, string>|null}
     */
    public static function choices(Request $request, array $allowed): array
    {
        $data = $request->validate([
            'format' => ['required', Rule::in(self::FORMATS)],
            'columns' => ['nullable', 'array', 'min:1'],
            'columns.*' => ['string', Rule::in($allowed)],
        ]);

        return ['format' => $data['format'], 'columns' => $data['columns'] ?? null];
    }

    /**
     * One table from a list of items. $columns maps a key to its label, whether it is an amount (and so totalled) and how to read it.
     *
     * @param  iterable<mixed>  $items
     * @param  array<string, array{label: string, amount: bool, value: callable(mixed): (string|int|float|null)}>  $columns
     * @param  array<int, string>|null  $chosen  keys to keep, in the order of $columns; null keeps all
     * @return Section
     */
    public static function table(iterable $items, array $columns, ?array $chosen = null, ?string $title = null): array
    {
        $columns = $chosen === null ? $columns : array_intersect_key($columns, array_flip($chosen));
        $rows = [];

        foreach ($items as $item) {
            $rows[] = array_values(array_map(fn (array $column) => $column['value']($item), $columns));
        }

        $totals = null;

        if ($rows !== []) {
            $totals = [];

            foreach (array_values($columns) as $index => $column) {
                $totals[] = $column['amount'] ? round(array_sum(array_column($rows, $index)), 2) : ($index === 0 ? 'Total' : null);
            }
        }

        return ['title' => $title, 'headings' => array_column($columns, 'label'), 'rows' => $rows, 'totals' => $totals];
    }

    /**
     * Columns that read a key of an array row: $spec maps the key to its label and whether it is an amount.
     *
     * @param  array<string, array{0: string, 1?: bool}>  $spec
     * @return array<string, array{label: string, amount: bool, value: callable(mixed): (string|int|float|null)}>
     */
    public static function arrayColumns(array $spec): array
    {
        $columns = [];

        foreach ($spec as $key => $definition) {
            $columns[$key] = ['label' => $definition[0], 'amount' => $definition[1] ?? false, 'value' => fn (array $row) => $row[$key] ?? null];
        }

        return $columns;
    }

    /**
     * The key and label of every column, for the Export window.
     *
     * @param  array<string, array{label: string}>  $columns
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(array $columns): array
    {
        return collect($columns)->map(fn (array $column, string $key) => ['value' => $key, 'label' => $column['label']])->values()->all();
    }

    /**
     * @param  array<int, Section>  $sections
     */
    public static function respond(string $format, string $filename, string $groupName, string $title, string $subtitle, array $sections): BinaryFileResponse|StreamedResponse|View
    {
        if ($format === 'pdf') {
            return view('backend.exports.table', compact('groupName', 'title', 'subtitle', 'sections'));
        }

        $lines = [];
        $bold = [];

        if ($format === 'csv') {
            $lines = [[$groupName, $title, $subtitle, 'Printed', now()->toDateString()], []];
        }

        foreach ($sections as $section) {
            if ($section['title'] !== null) {
                $bold[] = count($lines);
                $lines[] = [$section['title']];
            }

            $bold[] = count($lines);
            $lines[] = $section['headings'];
            array_push($lines, ...$section['rows']);

            if ($section['totals'] !== null) {
                $lines[] = $section['totals'];
            }

            if (count($sections) > 1) {
                $lines[] = [];
            }
        }

        if ($format === 'csv') {
            return ReportCsv::download("{$filename}.csv", $lines);
        }

        return response()->download(XlsxWriter::write($title, $lines, $bold), "{$filename}.xlsx", ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
    }
}
