<?php

namespace AlenaDashko\FilamentColorThemes;

use AlenaDashko\FilamentColorThemes\Support\FilamentCompat;
use Filament\Support\Colors\ColorManager;
use Filament\Support\Facades\FilamentColor;
use ReflectionObject;

class ColorApplier
{
    public function __construct(
        protected ColorThemeManager $themes,
    ) {}

    /**
     * Register a deferred color resolver. Evaluated when Filament first
     * resolves colors for the request (after session/cookies are available).
     */
    public function registerDeferred(): void
    {
        FilamentColor::register(function (): array {
            return $this->getApplicableColors() ?? [];
        });
    }

    /**
     * Force the current theme colors onto the ColorManager, clearing any
     * previously cached palette for this request (Filament 4/5).
     */
    public function apply(): void
    {
        $colors = $this->getApplicableColors();

        if ($colors === null) {
            return;
        }

        $manager = app(ColorManager::class);

        $this->clearColorCache($manager);

        $manager->register($colors);
    }

    /**
     * Theme palette minus any color keys the panel defines via ->colors(),
     * so the app's brand colors keep working alongside a theme.
     *
     * @return array<string, array<int, string>>|null
     */
    public function getApplicableColors(): ?array
    {
        $colors = $this->themes->getCurrentColors();

        if ($colors === null) {
            return null;
        }

        foreach ($this->getPreservedColorKeys() as $key) {
            unset($colors[$key]);
        }

        return $colors === [] ? null : $colors;
    }

    /**
     * Color keys that must stay as the panel defined them.
     *
     * @return array<int, string>
     */
    public function getPreservedColorKeys(): array
    {
        $panel = FilamentCompat::getCurrentPanel();

        if (! $panel || ! $panel->hasPlugin('filament-color-themes')) {
            return [];
        }

        /** @var ColorThemesPlugin $plugin */
        $plugin = $panel->getPlugin('filament-color-themes');

        if ($plugin->shouldOverridePanelColors()) {
            return [];
        }

        if (! method_exists($panel, 'getColors')) {
            return [];
        }

        $panelColors = $panel->getColors();

        return is_array($panelColors) ? array_keys($panelColors) : [];
    }

    protected function clearColorCache(ColorManager $manager): void
    {
        $reflection = new ReflectionObject($manager);

        // Filament 3 has no color cache; Filament 4/5 cache resolved palettes.
        if (! $reflection->hasProperty('cachedColors')) {
            return;
        }

        // ColorManager::$cachedColors is an uninitialized typed array property.
        // Setting it to null throws; it must be unset so getColors() rebuilds.
        (function (): void {
            unset($this->cachedColors);
        })->call($manager);
    }
}
