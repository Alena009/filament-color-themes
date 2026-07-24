<?php

namespace AlenaDashko\FilamentColorThemes\Themes;

use AlenaDashko\FilamentColorThemes\Support\FilamentCompat;
use Filament\Support\Colors\Color;

class PaletteFactory
{
    /**
     * Build a full primary palette from a brand hex color.
     *
     * @return array<int, string>
     */
    public static function primary(string $hex): array
    {
        return Color::hex($hex);
    }

    /**
     * Build a neutral/surface palette tinted with the brand hue.
     *
     * Filament 5: OKLCH strings. Filament 3: "r, g, b" triplets.
     *
     * @return array<int, string>
     */
    public static function tintedGray(string $hex, float $chromaStrength = 0.35): array
    {
        if (FilamentCompat::usesOklch()) {
            return self::tintedGrayOklch($hex, $chromaStrength);
        }

        return self::tintedGrayRgb($hex, $chromaStrength);
    }

    /**
     * @return array<int, string>
     */
    protected static function tintedGrayOklch(string $hex, float $chromaStrength): array
    {
        $oklch = Color::convertToOklch($hex);

        /** @var array{0: float|null, 1: float|null, 2: float|null} $parts */
        $parts = sscanf($oklch, 'oklch(%f %f %f)');

        $sourceChroma = (float) ($parts[1] ?? 0);
        $hue = (float) ($parts[2] ?? 0);

        $baseChroma = max(min($sourceChroma * $chromaStrength, 0.055), 0.012);

        $steps = [
            50 => [0.985, 0.20],
            100 => [0.967, 0.28],
            200 => [0.920, 0.40],
            300 => [0.871, 0.55],
            400 => [0.705, 0.75],
            500 => [0.552, 0.90],
            600 => [0.442, 0.95],
            700 => [0.370, 0.90],
            800 => [0.274, 0.80],
            900 => [0.210, 0.70],
            950 => [0.141, 0.55],
        ];

        $palette = [];

        foreach ($steps as $shade => [$lightness, $chromaFactor]) {
            $chroma = round($baseChroma * $chromaFactor, 3);
            $palette[$shade] = sprintf(
                'oklch(%.3f %.3f %.3f)',
                $lightness,
                $chroma,
                $hue,
            );
        }

        return $palette;
    }

    /**
     * Filament 3 gray scale: Zinc RGB blended toward the brand color.
     *
     * @return array<int, string>
     */
    protected static function tintedGrayRgb(string $hex, float $chromaStrength): array
    {
        $hex = ltrim($hex, '#');

        /** @var array{0: int|null, 1: int|null, 2: int|null} $brand */
        $brand = sscanf($hex, '%02x%02x%02x');
        $br = (int) ($brand[0] ?? 0);
        $bg = (int) ($brand[1] ?? 0);
        $bb = (int) ($brand[2] ?? 0);

        $mixByShade = [
            50 => 0.08,
            100 => 0.10,
            200 => 0.12,
            300 => 0.14,
            400 => 0.18,
            500 => 0.22,
            600 => 0.24,
            700 => 0.22,
            800 => 0.18,
            900 => 0.14,
            950 => 0.10,
        ];

        $palette = [];

        foreach (Color::Zinc as $shade => $rgb) {
            [$r, $g, $b] = array_map(
                static fn (string $part): int => (int) trim($part),
                explode(',', $rgb),
            );

            $mix = min(max($chromaStrength, 0), 1) * ($mixByShade[$shade] ?? 0.15);

            $palette[$shade] = sprintf(
                '%d, %d, %d',
                (int) round($r * (1 - $mix) + $br * $mix),
                (int) round($g * (1 - $mix) + $bg * $mix),
                (int) round($b * (1 - $mix) + $bb * $mix),
            );
        }

        return $palette;
    }
}
