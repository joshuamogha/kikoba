<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams the rows of a report as a CSV file that opens in Excel.
 */
class ReportCsv
{
    /**
     * @param  array<int, array<int, string|int|float|null>>  $rows  every line of the file, including headings, blank lines and totals
     */
    public static function download(string $filename, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            foreach ($rows as $row) {
                fputcsv($out, $row, escape: '\\');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
