<?php

namespace AlenaDashko\FilamentColorThemes;

use Closure;
use AlenaDashko\FilamentColorThemes\Http\Middleware\ApplyColorTheme;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\URL;
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
            ->middleware([
                ApplyColorTheme::class,
            ], isPersistent: true);
    }

    public function boot(Panel $panel): void
    {
        app(ColorApplier::class)->registerDeferred();

        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_START,
            fn (): HtmlString => $this->renderExclusiveModeBootScript(),
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::STYLES_AFTER,
            fn (): HtmlString => $this->renderThemeStyles(),
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::USER_MENU_PROFILE_AFTER,
            function (): string {
                if (! $this->canAccess()) {
                    return '';
                }

                return view('filament-color-themes::components.color-theme-switcher')->render();
            },
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn (): HtmlString => $this->renderSwitcherScript(),
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

    /**
     * Color themes and Filament light/dark/system are mutually exclusive.
     * When a color theme is active, force light appearance before Filament
     * paints dark mode from localStorage.
     */
    protected function renderExclusiveModeBootScript(): HtmlString
    {
        $theme = app(ColorThemeManager::class)->getCurrentTheme();

        if (! $theme) {
            return new HtmlString('');
        }

        $panelId = Filament::getCurrentPanel()?->getId()
            ?? Filament::getCurrentOrDefaultPanel()?->getId()
            ?? 'admin';

        $themeKey = $this->jsEncode($theme->key);
        $panelIdJs = $this->jsEncode($panelId);

        return new HtmlString(<<<HTML
            <script>
                (function () {
                    var themeKey = {$themeKey};
                    var panelId = {$panelIdJs};

                    document.documentElement.setAttribute('data-filament-color-theme', themeKey);

                    try {
                        localStorage.setItem('theme', 'light');
                        localStorage.setItem('theme-' + panelId, 'light');
                    } catch (e) {}

                    document.documentElement.classList.remove('dark');
                    document.documentElement.style.colorScheme = 'light';
                })();
            </script>
            HTML);
    }

    protected function renderSwitcherScript(): HtmlString
    {
        $clearUrl = URL::route('filament-color-themes.clear');
        $cookieName = app(ColorThemeManager::class)->getSessionKey();
        $hasActiveTheme = app(ColorThemeManager::class)->hasActiveTheme();
        $panelId = Filament::getCurrentPanel()?->getId()
            ?? Filament::getCurrentOrDefaultPanel()?->getId()
            ?? 'admin';

        return new HtmlString(<<<HTML
            <script>
                (function () {
                    if (window.__filamentColorThemesSwitcherBound) {
                        return;
                    }

                    window.__filamentColorThemesSwitcherBound = true;

                    const clearUrl = {$this->jsEncode($clearUrl)};
                    const cookieName = {$this->jsEncode($cookieName)};
                    const panelId = {$this->jsEncode($panelId)};
                    let hasActiveTheme = {$this->jsEncode($hasActiveTheme)};

                    function csrfToken() {
                        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                    }

                    function forceFilamentLightMode() {
                        try {
                            localStorage.setItem('theme', 'light');
                            localStorage.setItem('theme-' + panelId, 'light');
                        } catch (e) {}

                        document.documentElement.classList.remove('dark');
                        document.documentElement.style.colorScheme = 'light';
                        document.body?.classList.remove('dark');
                    }

                    async function postJson(url) {
                        const response = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrfToken(),
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                        });

                        if (! response.ok) {
                            throw new Error('Color theme request failed: ' + response.status);
                        }

                        return true;
                    }

                    async function selectColorTheme(key, setUrlTemplate) {
                        const url = setUrlTemplate.replace('THEMEKEY', encodeURIComponent(key));

                        try {
                            await postJson(url);
                            forceFilamentLightMode();
                            hasActiveTheme = true;
                            window.location.reload();
                        } catch (e) {
                            console.error(e);
                        }
                    }

                    async function clearColorThemeAndReload() {
                        try {
                            await postJson(clearUrl);
                        } catch (e) {}

                        document.cookie = cookieName + '=; Max-Age=0; path=/; SameSite=Lax';
                        document.documentElement.removeAttribute('data-filament-color-theme');
                        hasActiveTheme = false;
                        window.location.reload();
                    }

                    document.addEventListener('click', function (event) {
                        const colorBtn = event.target.closest('[data-color-theme-key]');

                        if (colorBtn) {
                            event.preventDefault();
                            event.stopPropagation();

                            const key = colorBtn.getAttribute('data-color-theme-key');
                            const root = colorBtn.closest('[data-color-theme-switcher]');
                            const template = root?.getAttribute('data-set-url-template');

                            if (key && template) {
                                selectColorTheme(key, template);
                            }

                            return;
                        }

                        // Native light/dark/system → drop color theme so only appearance applies.
                        const appearanceBtn = event.target.closest(
                            '.fi-theme-switcher:not([data-color-theme-switcher]) .fi-theme-switcher-btn'
                        );

                        if (appearanceBtn && hasActiveTheme) {
                            setTimeout(function () {
                                clearColorThemeAndReload();
                            }, 0);
                        }
                    });
                })();
            </script>
            HTML);
    }

    protected function jsEncode(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    }

    protected function renderThemeStyles(): HtmlString
    {
        $theme = app(ColorThemeManager::class)->getCurrentTheme();

        if (! $theme) {
            return new HtmlString('');
        }

        $variables = [];

        foreach (['primary' => $theme->primary, 'gray' => $theme->gray] as $name => $palette) {
            foreach ($palette as $shade => $value) {
                if (! is_string($value) || ! str_starts_with($value, 'oklch(')) {
                    continue;
                }

                $variables[] = "--{$name}-{$shade}:{$value}";
            }
        }

        $chrome = $theme->cardBorder;
        $sidebarBg = $theme->cardBackground;
        $sidebarText = $theme->cardText;
        $variables[] = "--color-theme-chrome:{$chrome}";
        $variables[] = "--color-theme-sidebar-bg:{$sidebarBg}";
        $variables[] = "--color-theme-sidebar-text:{$sidebarText}";

        $cssVariables = implode(';', $variables) . ';';

        $css = <<<CSS
            :root, html.fi, .fi-body {
                {$cssVariables}
            }

            /*
             * Mutual exclusivity: while a color theme is active, Filament's
             * light/dark/system row must not look selected (even if Alpine
             * still tracks an appearance mode under the hood).
             */
            html[data-filament-color-theme] .fi-theme-switcher:not([data-color-theme-switcher]) .fi-theme-switcher-btn.fi-active {
                background-color: transparent !important;
                color: inherit !important;
            }

            html[data-filament-color-theme] .fi-theme-switcher:not([data-color-theme-switcher]) .fi-theme-switcher-btn.fi-active svg,
            html[data-filament-color-theme] .fi-theme-switcher:not([data-color-theme-switcher]) .fi-theme-switcher-btn.fi-active .fi-icon {
                color: inherit !important;
                stroke: currentColor !important;
            }

            .fi-topbar,
            .fi-topbar > nav,
            header.fi-topbar {
                background-color: {$chrome} !important;
                border-color: {$chrome} !important;
                box-shadow: none !important;
            }

            .fi-topbar > nav > .fi-logo,
            .fi-topbar > nav .fi-topbar-start .fi-logo,
            .fi-topbar > nav .fi-topbar-open-sidebar-btn,
            .fi-topbar > nav .fi-topbar-open-sidebar-btn-icon,
            .fi-topbar > nav .fi-topbar-close-sidebar-btn,
            .fi-topbar > nav .fi-topbar-close-sidebar-btn-icon,
            .fi-topbar > nav .fi-topbar-open-database-notifications-btn,
            .fi-topbar > nav .fi-topbar-open-database-notifications-btn-icon,
            .fi-topbar > nav .fi-icon-btn:not(.fi-user-menu *),
            .fi-topbar > nav .fi-icon-btn-icon,
            .fi-topbar > nav .fi-global-search-field .fi-icon,
            .fi-topbar > nav .fi-topbar-item-label {
                color: #ffffff !important;
            }

            .fi-topbar > nav .fi-input-wrp,
            .fi-topbar > nav .fi-global-search-field {
                background-color: rgba(255, 255, 255, 0.16) !important;
                border-color: rgba(255, 255, 255, 0.28) !important;
            }

            .fi-topbar > nav .fi-global-search-field input,
            .fi-topbar > nav .fi-global-search-field input::placeholder {
                color: rgba(255, 255, 255, 0.92) !important;
            }

            /* Table toolbar (Search row) — same chrome as the app topbar */
            .fi-ta-header-toolbar,
            .fi-ta-header-ctn {
                background-color: {$chrome} !important;
                border-color: {$chrome} !important;
            }

            .fi-ta-header-toolbar,
            .fi-ta-header-ctn {
                border-radius: 0.75rem 0.75rem 0 0;
            }

            .fi-ta-header-toolbar .fi-icon-btn,
            .fi-ta-header-toolbar .fi-icon-btn-icon,
            .fi-ta-header-toolbar .fi-btn,
            .fi-ta-header-toolbar .fi-btn-label,
            .fi-ta-header-toolbar .fi-ac-btn-label,
            .fi-ta-header-toolbar .fi-ta-actions,
            .fi-ta-header-toolbar .fi-dropdown-trigger,
            .fi-ta-header-ctn .fi-icon-btn,
            .fi-ta-header-ctn .fi-icon-btn-icon {
                color: #ffffff !important;
            }

            /* Kill wrapper backgrounds that peek as sharp corners behind the rounded search */
            .fi-ta-header-toolbar .fi-ta-search,
            .fi-ta-header-toolbar .fi-ta-search-field,
            .fi-ta-header-toolbar .fi-ta-search-ctn,
            .fi-ta-header-toolbar [class*="fi-ta-search"],
            .fi-ta-header-ctn .fi-ta-search,
            .fi-ta-header-ctn .fi-ta-search-field,
            .fi-ta-header-ctn .fi-ta-search-ctn,
            .fi-ta-header-ctn [class*="fi-ta-search"] {
                background-color: transparent !important;
                background-image: none !important;
                box-shadow: none !important;
                border-color: transparent !important;
            }

            .fi-ta-header-toolbar .fi-input-wrp,
            .fi-ta-header-ctn .fi-input-wrp {
                background-color: rgba(255, 255, 255, 0.16) !important;
                border-color: rgba(255, 255, 255, 0.28) !important;
                border-radius: 9999px !important;
                overflow: hidden !important;
                box-shadow: none !important;
                outline: none !important;
                --tw-ring-color: transparent !important;
                --tw-ring-shadow: 0 0 #0000 !important;
                --tw-ring-offset-shadow: 0 0 #0000 !important;
                --tw-ring-offset-color: transparent !important;
            }

            .fi-ta-header-toolbar .fi-input-wrp input,
            .fi-ta-header-toolbar .fi-ta-search-field input,
            .fi-ta-header-toolbar .fi-input-wrp input::placeholder,
            .fi-ta-header-toolbar .fi-ta-search-field input::placeholder,
            .fi-ta-header-toolbar .fi-input-wrp .fi-icon,
            .fi-ta-header-ctn .fi-input-wrp input,
            .fi-ta-header-ctn .fi-input-wrp input::placeholder,
            .fi-ta-header-ctn .fi-input-wrp .fi-icon {
                color: rgba(255, 255, 255, 0.92) !important;
                background-color: transparent !important;
            }
CSS;

        // Dark chrome topbars need light brand text — Filament logo often uses
        // gray-* utilities that beat a single color rule.
        $css .= <<<CSS

            .fi-topbar .fi-logo,
            .fi-topbar .fi-logo *,
            .fi-topbar a.fi-logo,
            .fi-topbar .fi-topbar-start a,
            .fi-topbar .fi-topbar-start a *,
            .fi-topbar .fi-topbar-start .fi-logo,
            .fi-topbar .fi-topbar-start .fi-logo span,
            .fi-topbar [class*="fi-logo"] {
                color: #ffffff !important;
                fill: #ffffff !important;
                stroke: #ffffff !important;
                -webkit-text-fill-color: #ffffff !important;
            }
CSS;

        $css .= <<<CSS

            /* Sidebar / main nav — light tint of the selected theme */
            .fi-sidebar,
            .fi-main-sidebar,
            aside.fi-sidebar,
            .fi-sidebar-ctn,
            .fi-sidebar-nav,
            .fi-sidebar .fi-sidebar-header,
            .fi-sidebar-header,
            html[data-filament-color-theme] .fi-sidebar,
            html[data-filament-color-theme] .fi-main-sidebar,
            html[data-filament-color-theme] aside.fi-sidebar,
            html[data-filament-color-theme] .fi-sidebar-ctn,
            html[data-filament-color-theme] .fi-sidebar-nav,
            html[data-filament-color-theme] .fi-sidebar .fi-sidebar-header,
            html[data-filament-color-theme] .fi-sidebar-header {
                background-color: {$sidebarBg} !important;
                background-image: none !important;
                border-color: color-mix(in srgb, {$chrome} 28%, transparent) !important;
            }

            .fi-sidebar,
            .fi-main-sidebar,
            .fi-sidebar-ctn {
                border-inline-end: 1px solid color-mix(in srgb, {$chrome} 22%, transparent) !important;
                box-shadow: none !important;
            }

            .fi-sidebar .fi-sidebar-header,
            .fi-sidebar .fi-logo,
            .fi-sidebar .fi-sidebar-item-label,
            .fi-sidebar .fi-sidebar-group-label,
            .fi-sidebar .fi-sidebar-item-icon,
            .fi-sidebar .fi-icon-btn,
            .fi-sidebar .fi-icon-btn-icon,
            .fi-sidebar-nav .fi-sidebar-item-btn,
            .fi-sidebar-nav .fi-sidebar-item-button {
                color: {$sidebarText} !important;
            }

            /* Filament 5 uses .fi-sidebar-item-btn; keep .fi-sidebar-item-button for older builds */
            .fi-sidebar .fi-sidebar-item-btn,
            .fi-sidebar .fi-sidebar-item-button,
            .fi-sidebar-nav .fi-sidebar-item-btn,
            .fi-sidebar-nav .fi-sidebar-item-button {
                border: 1px solid transparent !important;
                outline: none !important;
                box-shadow: none !important;
            }

            .fi-sidebar .fi-sidebar-item-btn:hover,
            .fi-sidebar .fi-sidebar-item-button:hover,
            .fi-sidebar .fi-sidebar-item > .fi-sidebar-item-btn:hover,
            .fi-sidebar .fi-sidebar-item > .fi-sidebar-item-button:hover,
            .fi-sidebar-nav .fi-sidebar-item-btn:hover,
            .fi-sidebar-nav .fi-sidebar-item-button:hover {
                background-color: color-mix(in srgb, {$chrome} 12%, {$sidebarBg}) !important;
            }

            .fi-sidebar-item.fi-active > .fi-sidebar-item-btn,
            .fi-sidebar-item.fi-active > .fi-sidebar-item-button,
            .fi-sidebar .fi-sidebar-item.fi-active > .fi-sidebar-item-btn,
            .fi-sidebar .fi-sidebar-item.fi-active > .fi-sidebar-item-button,
            .fi-sidebar .fi-sidebar-item-active > .fi-sidebar-item-btn,
            .fi-sidebar .fi-sidebar-item-active > .fi-sidebar-item-button,
            .fi-sidebar .fi-sidebar-item-btn[aria-current="page"],
            .fi-sidebar .fi-sidebar-item-button[aria-current="page"],
            .fi-sidebar-nav .fi-sidebar-item.fi-active .fi-sidebar-item-btn,
            .fi-sidebar-nav .fi-sidebar-item.fi-active .fi-sidebar-item-button {
                background-color: color-mix(in srgb, {$sidebarBg} 35%, white) !important;
                color: {$chrome} !important;
                border: 1px solid {$chrome} !important;
                outline: 1px solid {$chrome} !important;
                outline-offset: -1px;
                box-shadow: inset 0 0 0 1px {$chrome} !important;
            }

            .fi-sidebar-item.fi-active > .fi-sidebar-item-btn .fi-sidebar-item-icon,
            .fi-sidebar-item.fi-active > .fi-sidebar-item-btn .fi-sidebar-item-label,
            .fi-sidebar-item.fi-active > .fi-sidebar-item-button .fi-sidebar-item-icon,
            .fi-sidebar-item.fi-active > .fi-sidebar-item-button .fi-sidebar-item-label,
            .fi-sidebar .fi-sidebar-item.fi-active > .fi-sidebar-item-btn .fi-sidebar-item-icon,
            .fi-sidebar .fi-sidebar-item.fi-active > .fi-sidebar-item-btn .fi-sidebar-item-label,
            .fi-sidebar .fi-sidebar-item-btn[aria-current="page"] .fi-sidebar-item-icon,
            .fi-sidebar .fi-sidebar-item-btn[aria-current="page"] .fi-sidebar-item-label {
                color: {$chrome} !important;
            }

            /* Soft page canvas in the same palette */
            .fi-body,
            .fi-main,
            .fi-main-ctn {
                background-color: color-mix(in srgb, {$sidebarBg} 55%, white) !important;
            }

            /* Section — light header + thin theme border around the whole block */
            .fi-section,
            .fi-fo-section,
            .fi-sc-section {
                overflow: hidden !important;
                border: 1px solid color-mix(in srgb, {$chrome} 32%, transparent) !important;
                border-radius: 0.75rem !important;
            }

            .fi-section-header,
            .fi-fo-section .fi-section-header,
            .fi-sc-section .fi-section-header {
                background-color: {$sidebarBg} !important;
                border: none !important;
                border-bottom: 1px solid color-mix(in srgb, {$chrome} 18%, transparent) !important;
                border-top-left-radius: 0 !important;
                border-top-right-radius: 0 !important;
            }

            .fi-section-header .fi-section-header-heading,
            .fi-section-header .fi-section-header-description,
            .fi-section-header .fi-icon-btn,
            .fi-section-header .fi-icon-btn-icon,
            .fi-section-header .fi-icon {
                color: {$sidebarText} !important;
            }

            .fi-dropdown-panel,
            .fi-user-menu-panel {
                color: inherit;
            }
            CSS;

        return new HtmlString(
            '<style id="filament-color-themes-vars">' . $css . '</style>'
        );
    }
}
