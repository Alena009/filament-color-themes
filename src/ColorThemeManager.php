<?php

namespace Dashk\FilamentColorThemes;

use Dashk\FilamentColorThemes\Themes\Theme;
use Filament\Support\Colors\Color;
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
            'forest-green' => new Theme(
                key: 'forest-green',
                name: 'ForestGreen',
                hex: '#228B22',
                primary: Color::hex('#228B22'),
                cardBackground: '#dcfce7',
                cardBorder: '#15803d',
                cardText: '#14532d',
            ),
            'office-blue' => new Theme(
                key: 'office-blue',
                name: 'Office Blue',
                hex: '#2563eb',
                primary: Color::hex('#2563eb'),
                cardBackground: '#dbeafe',
                cardBorder: '#1d4ed8',
                cardText: '#1e3a8a',
            ),
            'midtone' => new Theme(
                key: 'midtone',
                name: 'Midtone',
                hex: '#6b7280',
                primary: Color::hex('#6b7280'),
                cardBackground: '#e5e7eb',
                cardBorder: '#374151',
                cardText: '#1f2937',
            ),
        ]);
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
     * @return array<string, string>|null
     */
    public function getCurrentColors(): ?array
    {
        $theme = $this->getCurrentTheme();

        if (! $theme) {
            return null;
        }

        return [
            'primary' => $theme->hex,
        ];
    }
}
