<?php

namespace App\Support;

/**
 * Small helpers for the brand colours (#RRGGBB) used in the site's CSS.
 */
class BrandColor
{
    /**
     * "r, g, b" for use in rgba() and Bootstrap's --bs-*-rgb variables.
     */
    public static function rgb(string $hex): string
    {
        [$red, $green, $blue] = self::channels($hex);

        return "{$red}, {$green}, {$blue}";
    }

    /**
     * The colour moved toward black (negative amount) or white (positive), by 0 to 1.
     */
    public static function shade(string $hex, float $amount): string
    {
        $target = $amount < 0 ? 0 : 255;

        return '#'.implode('', array_map(
            fn (int $channel): string => str_pad(dechex((int) round($channel + ($target - $channel) * abs($amount))), 2, '0', STR_PAD_LEFT),
            self::channels($hex),
        ));
    }

    /**
     * White or black, whichever reads better on top of the colour.
     */
    public static function onColor(string $hex): string
    {
        [$red, $green, $blue] = self::channels($hex);

        return (0.299 * $red + 0.587 * $green + 0.114 * $blue) > 160 ? '#000000' : '#ffffff';
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private static function channels(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}
