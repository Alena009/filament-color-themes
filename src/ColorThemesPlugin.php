<?php

namespace Dashk\FilamentColorThemes;

use Closure;
use Dashk\FilamentColorThemes\Http\Middleware\ApplyColorTheme;
use Dashk\FilamentColorThemes\Pages\ColorThemes;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\HtmlString;

class ColorThemesPlugin implements Plugin
{
    protected ?Closure $canViewCallback = null;

    public function getId(): string
    {
        return 'filament-color-themes';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->pages([
                ColorThemes::class,
            ])
            ->middleware([
                ApplyColorTheme::class,
            ], isPersistent: true);
    }

    public function boot(Panel $panel): void
    {
        app(ColorApplier::class)->registerDeferred();

        // Inject after @filamentStyles so our palette overrides the defaults.
        FilamentView::registerRenderHook(
            PanelsRenderHook::STYLES_AFTER,
            fn (): HtmlString => $this->renderThemeCssVariables(),
        );
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = Filament::getCurrentOrDefaultPanel()->getPlugin(app(static::class)->getId());

        return $plugin;
    }

    public function canView(Closure $callback): static
    {
        $this->canViewCallback = $callback;

        return $this;
    }

    public function canAccess(): bool
    {
        if ($this->canViewCallback instanceof Closure) {
            return (bool) ($this->canViewCallback)();
        }

        return true;
    }

    protected function renderThemeCssVariables(): HtmlString
    {
        $theme = app(ColorThemeManager::class)->getCurrentTheme();

        if (! $theme) {
            return new HtmlString('');
        }

        $variables = [];

        foreach ($theme->primary as $shade => $value) {
            if (! is_string($value) || ! str_starts_with($value, 'oklch(')) {
                continue;
            }

            $variables[] = "--primary-{$shade}:{$value}";
        }

        if ($variables === []) {
            return new HtmlString('');
        }

        $css = implode(';', $variables) . ';';

        return new HtmlString(
            '<style id="filament-color-themes-vars">:root{' . $css . '}</style>'
        );
    }
}
