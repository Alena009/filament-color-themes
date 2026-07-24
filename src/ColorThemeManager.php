<?php

namespace AlenaDashko\FilamentColorThemes;

use AlenaDashko\FilamentColorThemes\Themes\PaletteFactory;
use AlenaDashko\FilamentColorThemes\Themes\Theme;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;

class ColorThemeManager
{
    public const SESSION_KEY = 'filament_color_theme';

    /**
     * @return Collection<string, Theme>
     */
    public function getThemes(): Collection
    {
        return collect([
            'forest-green' => $this->makeTheme(
                key: 'forest-green',
                name: 'ForestGreen',
                hex: '#228B22',
                cardBackground: '#dcfce7',
                cardBorder: '#15803d',
                cardText: '#14532d',
                grayChromaStrength: 0.40,
            ),
            'office-blue' => $this->makeTheme(
                key: 'office-blue',
                name: 'Office Blue',
                hex: '#2563eb',
                cardBackground: '#dbeafe',
                cardBorder: '#1d4ed8',
                cardText: '#1e3a8a',
                grayChromaStrength: 0.38,
            ),
            'midtone' => $this->makeTheme(
                key: 'midtone',
                name: 'Midtone',
                hex: '#6b7280',
                cardBackground: '#e5e7eb',
                cardBorder: '#374151',
                cardText: '#1f2937',
                grayChromaStrength: 0.20,
            ),
            'sepia' => $this->makeTheme(
                key: 'sepia',
                name: 'Sepia',
                hex: '#8B5E3C',
                cardBackground: '#f5e6d3',
                cardBorder: '#6B4423',
                cardText: '#3E2723',
                grayChromaStrength: 0.32,
            ),
            'midnight' => $this->makeTheme(
                key: 'midnight',
                name: 'Midnight',
                hex: '#5B7C99',
                cardBackground: '#334155',
                cardBorder: '#1e293b',
                cardText: '#e2e8f0',
                grayChromaStrength: 0.22,
                swatch: 'N',
            ),
        ]);
    }

    protected function makeTheme(
        string $key,
        string $name,
        string $hex,
        string $cardBackground,
        string $cardBorder,
        string $cardText,
        float $grayChromaStrength,
        string $swatch = '',
    ): Theme {
        return new Theme(
            key: $key,
            name: $name,
            hex: $hex,
            primary: PaletteFactory::primary($hex),
            gray: PaletteFactory::tintedGray($hex, $grayChromaStrength),
            cardBackground: $cardBackground,
            cardBorder: $cardBorder,
            cardText: $cardText,
            swatch: $swatch,
        );
    }

    public function getTheme(string $key): ?Theme
    {
        return $this->getThemes()->get($key);
    }

    public function getDefaultThemeKey(): ?string
    {
        $default = config('filament-color-themes.default');

        if (! filled($default) || ! $this->getThemes()->has($default)) {
            return null;
        }

        return (string) $default;
    }

    public function getSessionKey(): string
    {
        return (string) config('filament-color-themes.session_key', self::SESSION_KEY);
    }

    public function getCurrentThemeKey(): ?string
    {
        $key = session($this->getSessionKey())
            ?? request()->cookie($this->getSessionKey());

        if (filled($key) && $this->getThemes()->has($key)) {
            return (string) $key;
        }

        return $this->getDefaultThemeKey();
    }

    public function hasActiveTheme(): bool
    {
        return filled($this->getCurrentThemeKey());
    }

    public function getCurrentTheme(): ?Theme
    {
        $key = $this->getCurrentThemeKey();

        if (! filled($key)) {
            return null;
        }

        return $this->getTheme($key);
    }

    public function setTheme(string $key): void
    {
        if (! $this->getThemes()->has($key)) {
            return;
        }

        session()->put($this->getSessionKey(), $key);
        session()->save();

        Cookie::queue(
            Cookie::forever($this->getSessionKey(), $key)
        );
    }

    public function clearTheme(): void
    {
        session()->forget($this->getSessionKey());
        session()->save();

        Cookie::queue(
            Cookie::forget($this->getSessionKey())
        );
    }

    /**
     * @return array{primary: array<int, string>, gray: array<int, string>}|null
     */
    public function getCurrentColors(): ?array
    {
        $theme = $this->getCurrentTheme();

        if (! $theme) {
            return null;
        }

        return $theme->getFilamentColors();
    }
}
