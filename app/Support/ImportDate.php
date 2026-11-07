<?php

namespace App\Support;

use DateTimeImmutable;
use Illuminate\Support\Carbon;

/**
 * Reads the dates people type or export into import files, e.g. "2025-12-15", "15/12/2025" or "15/12/2025 08:32:47".
 * Slashes, dashes and dots with the day first are accepted, with or without a time. Impossible dates (30/02/2026) are rejected.
 */
class ImportDate
{
    /**
     * @var array<int, string>
     */
    private const FORMATS = [
        'Y-m-d', 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i:s', 'Y/m/d', 'Y/m/d H:i:s', 'Y/m/d H:i',
        'j/n/Y', 'j/n/Y H:i:s', 'j/n/Y H:i', 'j-n-Y', 'j-n-Y H:i:s', 'j-n-Y H:i', 'j.n.Y', 'j.n.Y H:i:s', 'j.n.Y H:i',
    ];

    /**
     * The date (and time, when given) written in the text, or null when it is not a real date.
     */
    public static function parse(string $value): ?Carbon
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');

        if ($value === '') {
            return null;
        }

        foreach (self::FORMATS as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $value);
            $errors = DateTimeImmutable::getLastErrors();

            if ($date !== false && (int) $date->format('Y') >= 1900 && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return Carbon::instance($date);
            }
        }

        return null;
    }
}
