<?php

namespace AlenaDashko\FilamentColorThemes\Support;

use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Colors\Color;

class FilamentCompat
{
    /**
     * Filament 4/5 expose OKLCH helpers; Filament 3 uses RGB triplets.
     */
    public static function usesOklch(): bool
    {
        return method_exists(Color::class, 'convertToOklch');
    }

    public static function getCurrentPanel(): ?Panel
    {
        if (method_exists(Filament::class, 'getCurrentOrDefaultPanel')) {
            return Filament::getCurrentOrDefaultPanel();
        }

        return Filament::getCurrentPanel();
    }
}
