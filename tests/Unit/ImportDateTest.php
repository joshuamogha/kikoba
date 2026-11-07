<?php

use App\Support\ImportDate;

test('common date and date-time spellings are understood', function (string $text, string $expected) {
    expect(ImportDate::parse($text)?->format('Y-m-d H:i:s'))->toBe($expected);
})->with([
    ['2025-12-15', '2025-12-15 00:00:00'],
    ['2025-12-15 08:32:47', '2025-12-15 08:32:47'],
    ['2025-12-15T08:32:47', '2025-12-15 08:32:47'],
    ['15/12/2025', '2025-12-15 00:00:00'],
    ['15/12/2025 08:32:47', '2025-12-15 08:32:47'],
    ['15/12/2025   08:32', '2025-12-15 08:32:00'],
    ['5/2/2026', '2026-02-05 00:00:00'],
    ['15-12-2025', '2025-12-15 00:00:00'],
    ['15.12.2025 23:59:59', '2025-12-15 23:59:59'],
]);

test('impossible or unreadable dates are rejected', function (string $text) {
    expect(ImportDate::parse($text))->toBeNull();
})->with(['', 'yesterday', '30/02/2026', '2026-02-30', '12/15/2025', '15/12/25', '15/12/2025 25:00:00']);
