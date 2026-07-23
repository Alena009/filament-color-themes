<?php

namespace Dashk\FilamentColorThemes\Pages;

use BackedEnum;
use Dashk\FilamentColorThemes\ColorApplier;
use Dashk\FilamentColorThemes\ColorThemeManager;
use Dashk\FilamentColorThemes\ColorThemesPlugin;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Route;

class ColorThemes extends Page
{
    protected string $view = 'filament-color-themes::pages.color-themes';

    protected static ?string $slug = 'color-themes';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static bool $shouldRegisterNavigation = true;

    public static function getNavigationIcon(): string | BackedEnum | Htmlable | null
    {
        return config('filament-color-themes.navigation_icon')
            ?? static::$navigationIcon;
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-color-themes.navigation_sort', 50);
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-color-themes::color-themes.navigation_label');
    }

    public function getTitle(): string | Htmlable
    {
        return __('filament-color-themes::color-themes.title');
    }

    public function getHeading(): string | Htmlable | null
    {
        return __('filament-color-themes::color-themes.heading');
    }

    public static function shouldRegisterNavigation(): bool
    {
        if (! static::routeIsRegistered()) {
            return false;
        }

        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        try {
            if (! Filament::hasPlugin('filament-color-themes')) {
                return false;
            }

            return ColorThemesPlugin::get()->canAccess();
        } catch (\Throwable) {
            return true;
        }
    }

    public static function getNavigationUrl(): string
    {
        if (! static::routeIsRegistered()) {
            return '#';
        }

        return static::getUrl();
    }

    protected static function routeIsRegistered(): bool
    {
        try {
            return Route::has(static::getRouteName());
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, \Dashk\FilamentColorThemes\Themes\Theme>
     */
    public function getThemes(): array
    {
        return app(ColorThemeManager::class)->getThemes()->all();
    }

    public function getCurrentThemeKey(): ?string
    {
        return app(ColorThemeManager::class)->getCurrentThemeKey();
    }

    public function selectTheme(string $key): void
    {
        $manager = app(ColorThemeManager::class);

        if (! $manager->getThemes()->has($key)) {
            return;
        }

        $manager->setTheme($key);
        app(ColorApplier::class)->apply();

        Notification::make()
            ->title(__('filament-color-themes::color-themes.notifications.applied'))
            ->success()
            ->send();

        $this->js('window.location.reload()');
    }
}
