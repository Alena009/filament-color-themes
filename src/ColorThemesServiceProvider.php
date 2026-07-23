<?php

namespace Dashk\FilamentColorThemes;

use Dashk\FilamentColorThemes\Http\Controllers\ClearColorThemeController;
use Dashk\FilamentColorThemes\Http\Controllers\SetColorThemeController;
use Illuminate\Support\Facades\Route;
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
        $this->registerRoutes();
    }

    protected function registerRoutes(): void
    {
        Route::middleware(['web'])->group(function (): void {
            Route::post('/filament-color-themes/clear', ClearColorThemeController::class)
                ->name('filament-color-themes.clear');

            Route::post('/filament-color-themes/set/{theme}', SetColorThemeController::class)
                ->where('theme', '[A-Za-z0-9\-]+')
                ->name('filament-color-themes.set');
        });
    }
}
