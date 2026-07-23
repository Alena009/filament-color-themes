<?php

namespace Dashk\FilamentColorThemes;

use Dashk\FilamentColorThemes\Pages\ColorThemes;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ColorThemesServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-color-themes';

    public static string $viewNamespace = 'filament-color-themes';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasConfigFile()
            ->hasTranslations()
            ->hasViews(static::$viewNamespace);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(ColorThemeManager::class, fn (): ColorThemeManager => new ColorThemeManager);
        $this->app->singleton(ColorApplier::class, fn (): ColorApplier => new ColorApplier(app(ColorThemeManager::class)));
    }

    public function packageBooted(): void
    {
        Livewire::component('dashk.filament-color-themes.pages.color-themes', ColorThemes::class);
    }
}
