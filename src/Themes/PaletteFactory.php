<?php

namespace Dashk\FilamentColorThemes\Themes;

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
     * Uses the same lightness curve as Filament's Zinc gray scale, but keeps
     * a low chroma of the selected theme — similar to how dark mode is
     * "variations of black", a green theme becomes "variations of green".
     *
     * @return array<int, string>
     */
    public static function tintedGray(string $hex, float $chromaStrength = 0.35): array
    {
        $oklch = Color::convertToOklch($hex);

        /** @var array{0: float|null, 1: float|null, 2: float|null} $parts */
        $parts = sscanf($oklch, 'oklch(%f %f %f)');

        $sourceChroma = (float) ($parts[1] ?? 0);
        $hue = (float) ($parts[2] ?? 0);

        // Keep gray readable: enough tint to feel themed, not so much it looks neon.
        $baseChroma = max(min($sourceChroma * $chromaStrength, 0.055), 0.012);

        // Lightness + relative chroma multipliers aligned with Zinc's feel.
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
}
