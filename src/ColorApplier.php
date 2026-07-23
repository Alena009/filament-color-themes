<?php

namespace Dashk\FilamentColorThemes;

use Filament\Support\Colors\ColorManager;
use Filament\Support\Facades\FilamentColor;

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
            return $this->themes->getCurrentColors() ?? [];
        });
    }

    /**
     * Force the current theme colors onto the ColorManager, clearing any
     * previously cached palette for this request.
     */
    public function apply(): void
    {
        $colors = $this->themes->getCurrentColors();

        if ($colors === null) {
            return;
        }

        $manager = app(ColorManager::class);

        $this->clearColorCache($manager);

        $manager->register($colors);
    }

    protected function clearColorCache(ColorManager $manager): void
    {
        // ColorManager::$cachedColors is an uninitialized typed array property.
        // Setting it to null throws; it must be unset so getColors() rebuilds.
        (function (): void {
            unset($this->cachedColors);
        })->call($manager);
    }
}
