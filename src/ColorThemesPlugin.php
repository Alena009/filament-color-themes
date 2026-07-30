<?php

namespace AlenaDashko\FilamentColorThemes;

use Closure;
use AlenaDashko\FilamentColorThemes\Http\Middleware\ApplyColorTheme;
use AlenaDashko\FilamentColorThemes\Support\FilamentCompat;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\HtmlString;

class ColorThemesPlugin implements Plugin
{
    protected ?Closure $canViewCallback = null;

    protected bool $overridesPanelColors = false;

    public function getId(): string
    {
        return 'filament-color-themes';
    }

    /**
     * By default, colors the panel defines via ->colors() (e.g. brand primary)
     * are preserved and themes only fill the rest. Call this to let the active
     * theme override the panel colors entirely.
     */
    public function overridePanelColors(bool $condition = true): static
    {
        $this->overridesPanelColors = $condition;

        return $this;
    }

    public function shouldOverridePanelColors(): bool
    {
        return $this->overridesPanelColors;
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
            fn (): HtmlString => new HtmlString(
                (string) $this->renderFilterPanelTypography()
                . (string) $this->renderSwitcherScript()
            ),
        );
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = FilamentCompat::getCurrentPanel()->getPlugin(app(static::class)->getId());

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

        $panelId = FilamentCompat::getCurrentPanel()?->getId() ?? 'admin';

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
        $panelId = FilamentCompat::getCurrentPanel()?->getId() ?? 'admin';

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

        // Don't emit CSS vars for colors the panel defines via ->colors():
        // Filament renders those itself and the theme must not shadow them.
        // Never emit --gray-*: Filament Zinc must stay for placeholders / Selects.
        $preservedKeys = app(ColorApplier::class)->getPreservedColorKeys();

        foreach (['primary' => $theme->primary] as $name => $palette) {
            if (in_array($name, $preservedKeys, true)) {
                continue;
            }

            foreach ($palette as $shade => $value) {
                if (! is_string($value)) {
                    continue;
                }

                // Filament 5: oklch(...). Filament 3: "r, g, b".
                $isOklch = str_starts_with($value, 'oklch(');
                $isRgbTriplet = (bool) preg_match('/^\d{1,3},\s*\d{1,3},\s*\d{1,3}$/', $value);

                if (! $isOklch && ! $isRgbTriplet) {
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
             * Keep form controls readable: tinted gray used to wash out
             * placeholders / Select options. Theme chrome is CSS-only;
             * Filament Zinc gray stays for text. Scope to form fields only —
             * not topbar / table-toolbar search (those need light-on-chrome).
             */
            html[data-filament-color-theme] .fi-fo-field-wrp .fi-dropdown-panel,
            html[data-filament-color-theme] .fi-fo-select .fi-dropdown-panel {
                background-color: #ffffff !important;
                color: rgb(24, 24, 27) !important;
                z-index: 60 !important;
            }

            html[data-filament-color-theme] .fi-fo-field-wrp .fi-dropdown-panel .fi-dropdown-list-item,
            html[data-filament-color-theme] .fi-fo-field-wrp .fi-dropdown-panel .fi-dropdown-list-item *,
            html[data-filament-color-theme] .fi-fo-select .fi-dropdown-panel .fi-dropdown-list-item,
            html[data-filament-color-theme] .fi-fo-select .fi-dropdown-panel .fi-dropdown-list-item * {
                color: rgb(24, 24, 27) !important;
                -webkit-text-fill-color: rgb(24, 24, 27) !important;
            }

            html[data-filament-color-theme] .fi-fo-field-wrp .fi-input-wrp input::placeholder,
            html[data-filament-color-theme] .fi-fo-field-wrp .fi-select-input {
                color: rgb(82, 82, 91) !important;
                -webkit-text-fill-color: rgb(82, 82, 91) !important;
            }

            html[data-filament-color-theme] .fi-fo-field-wrp .fi-input-wrp input,
            html[data-filament-color-theme] .fi-fo-field-wrp .fi-input-wrp textarea {
                color: rgb(24, 24, 27) !important;
                -webkit-text-fill-color: rgb(24, 24, 27) !important;
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

            /* Search toolbar stays chrome; filters use sidebar colors (see BODY_END). */
            .fi-ta-header-toolbar {
                background-color: {$chrome} !important;
                border-color: {$chrome} !important;
                border-radius: 0 !important;
            }

            .fi-ta-header-ctn {
                background-color: transparent !important;
                border-color: transparent !important;
                border-radius: 0.75rem 0.75rem 0 0;
                /* Must stay visible — Select dropdowns in filters open downward
                 * over the toolbar; overflow:hidden clipped them underneath. */
                overflow: visible !important;
            }

            /* Filters sit above the search toolbar in the stacking order —
             * keep this LOW (below Filament topbar z-20) so global search
             * results are never covered by the filters strip. */
            .fi-ta-filters-above-content-ctn {
                position: relative;
                z-index: 2;
            }

            .fi-ta-header-toolbar {
                position: relative;
                z-index: 1;
            }

            /* Topbar + global search must stay above page/table chrome */
            .fi-topbar {
                z-index: 40 !important;
            }

            .fi-topbar .fi-global-search-results-ctn,
            .fi-topbar .fi-global-search-results,
            .fi-topbar .fi-dropdown-panel,
            .fi-topbar [class*="fi-global-search"] .fi-dropdown-panel {
                z-index: 50 !important;
            }

            .fi-ta-header-toolbar .fi-icon-btn,
            .fi-ta-header-toolbar .fi-icon-btn-icon,
            .fi-ta-header-toolbar .fi-btn,
            .fi-ta-header-toolbar .fi-btn-label,
            .fi-ta-header-toolbar .fi-ac-btn-label,
            .fi-ta-header-toolbar .fi-btn .fi-icon,
            .fi-ta-header-toolbar .fi-ta-actions,
            .fi-ta-header-toolbar .fi-dropdown-trigger {
                color: #ffffff !important;
                -webkit-text-fill-color: #ffffff !important;
            }

            /* Custom header/bulk actions: default light button bg makes white
             * text invisible on chrome — restyle as translucent chips. */
            .fi-ta-header-toolbar .fi-btn,
            .fi-ta-header-toolbar .fi-ta-actions .fi-btn,
            .fi-ta-header-toolbar .fi-dropdown-trigger .fi-btn {
                background-color: rgba(255, 255, 255, 0.16) !important;
                background-image: none !important;
                border-color: rgba(255, 255, 255, 0.28) !important;
                box-shadow: none !important;
                --tw-ring-color: transparent !important;
                --tw-ring-shadow: 0 0 #0000 !important;
                --tw-ring-offset-shadow: 0 0 #0000 !important;
            }

            .fi-ta-header-toolbar .fi-btn:hover,
            .fi-ta-header-toolbar .fi-ta-actions .fi-btn:hover {
                background-color: rgba(255, 255, 255, 0.26) !important;
            }

            .fi-ta-header-toolbar .fi-btn-badge,
            .fi-ta-header-toolbar .fi-btn .fi-badge {
                background-color: rgba(255, 255, 255, 0.9) !important;
                color: {$chrome} !important;
                -webkit-text-fill-color: {$chrome} !important;
            }

            /* Dropdown panels (bulk actions etc.) open inside the toolbar:
             * they are white, so undo the forced white text/chip styles. */
            .fi-ta-header-toolbar .fi-dropdown-panel,
            .fi-ta-header-toolbar .fi-dropdown-panel * {
                color: rgb(24, 24, 27) !important;
                -webkit-text-fill-color: rgb(24, 24, 27) !important;
            }

            .fi-ta-header-toolbar .fi-dropdown-panel .fi-btn,
            .fi-ta-header-toolbar .fi-dropdown-panel .fi-dropdown-list-item {
                background-color: transparent !important;
                border-color: transparent !important;
            }

            .fi-ta-header-toolbar .fi-dropdown-panel .fi-dropdown-list-item:hover {
                background-color: rgba(0, 0, 0, 0.05) !important;
            }

            /* Keep danger items (Delete) red inside the dropdown */
            .fi-ta-header-toolbar .fi-dropdown-panel .fi-color-danger,
            .fi-ta-header-toolbar .fi-dropdown-panel .fi-color-danger *,
            .fi-ta-header-toolbar .fi-dropdown-panel [class*="fi-color-danger"],
            .fi-ta-header-toolbar .fi-dropdown-panel [class*="fi-color-danger"] *,
            .fi-ta-header-toolbar .fi-dropdown-panel [class*="text-danger"] {
                color: rgb(220, 38, 38) !important;
                -webkit-text-fill-color: rgb(220, 38, 38) !important;
            }

            .fi-ta-header-toolbar .fi-ta-search,
            .fi-ta-header-toolbar .fi-ta-search-field,
            .fi-ta-header-toolbar .fi-ta-search-ctn,
            .fi-ta-header-toolbar [class*="fi-ta-search"] {
                background-color: transparent !important;
                background-image: none !important;
                box-shadow: none !important;
                border-color: transparent !important;
            }

            .fi-ta-header-toolbar .fi-input-wrp {
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
            .fi-ta-header-toolbar .fi-input-wrp .fi-icon {
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

            /* Section — light header + thin theme border.
             * overflow:visible so Select / date dropdowns are not clipped. */
            .fi-section,
            .fi-fo-section,
            .fi-sc-section {
                overflow: visible !important;
                border: 1px solid color-mix(in srgb, {$chrome} 32%, transparent) !important;
                border-radius: 0.75rem !important;
            }

            /* Table card: Filament defaults to overflow-hidden which clips
             * filter Selects that open over the toolbar / table. */
            .fi-ta-ctn {
                overflow: visible !important;
            }

            .fi-section-header,
            .fi-fo-section .fi-section-header,
            .fi-sc-section .fi-section-header {
                background-color: {$sidebarBg} !important;
                border: none !important;
                border-bottom: 1px solid color-mix(in srgb, {$chrome} 18%, transparent) !important;
                /* Match section radius at the top when expanded */
                border-radius: 0.75rem 0.75rem 0 0 !important;
            }

            /* Collapsed: header is the whole card — round all corners */
            .fi-section.fi-collapsed > .fi-section-header,
            .fi-fo-section.fi-collapsed > .fi-section-header,
            .fi-sc-section.fi-collapsed > .fi-section-header {
                border-bottom: none !important;
                border-radius: 0.75rem !important;
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

            html[data-filament-color-theme] .fi-dropdown-panel {
                z-index: 60 !important;
            }
            CSS;

        return new HtmlString(
            '<style id="filament-color-themes-vars">' . $css . '</style>'
        );
    }

    /**
     * Filters panel matches the sidebar: same background + nav text color.
     * Injected at BODY_END so rules beat Filament/Tailwind text-gray-* utilities.
     */
    protected function renderFilterPanelTypography(): HtmlString
    {
        $theme = app(ColorThemeManager::class)->getCurrentTheme();

        if (! $theme) {
            return new HtmlString('');
        }

        $bg = $theme->cardBackground;
        $text = $theme->cardText;
        $chrome = $theme->cardBorder;
        // Midnight sidebar is dark with light nav text — filters need a soft
        // light surface and darker type so labels/inputs stay readable.
        $inputText = $text;

        if ($theme->key === 'midnight') {
            $bg = '#f1f5f9';
            $text = '#1e293b';
            $inputText = '#0f172a';
        }

        $hint = "color-mix(in srgb, {$text} 72%, {$bg})";
        $inputHint = "color-mix(in srgb, {$inputText} 55%, #ffffff)";

        $css = <<<CSS
            .fi-ta-filters-above-content-ctn,
            .fi-ta-filters-below-content,
            .fi-ta-header-ctn .fi-ta-filters-above-content-ctn {
                background-color: {$bg} !important;
                color: {$text} !important;
                border: 1px solid color-mix(in srgb, {$chrome} 22%, transparent) !important;
                border-bottom: none !important;
                border-radius: 0.75rem 0.75rem 0 0 !important;
                position: relative;
                z-index: 2;
                overflow: visible !important;
            }

            .fi-ta-filters,
            .fi-ta-filters-above-content-ctn .fi-ta-filters {
                background-color: transparent !important;
                color: {$text} !important;
            }

            .fi-ta-filters h4,
            .fi-ta-filters h4[class*="text-gray"],
            .fi-ta-filters-above-content-ctn .fi-ta-filters h4,
            .fi-ta-filters .fi-fo-field-wrp-label,
            .fi-ta-filters .fi-fo-field-wrp-label span,
            .fi-ta-filters .fi-fo-field-wrp-label span[class*="text-gray"],
            .fi-ta-filters label.fi-fo-field-wrp-label,
            .fi-ta-filters-above-content-ctn .fi-ta-filters .fi-fo-field-wrp-label span {
                color: {$text} !important;
                -webkit-text-fill-color: {$text} !important;
            }

            .fi-ta-filters .fi-fo-field-wrp-hint-label,
            .fi-ta-filters .fi-fo-field-wrp-hint [class*="text-gray"],
            .fi-ta-filters-above-content-ctn .fi-ta-filters .fi-fo-field-wrp-hint-label {
                color: {$hint} !important;
                -webkit-text-fill-color: {$hint} !important;
            }

            .fi-ta-filters-above-content-ctn > span .fi-icon-btn,
            .fi-ta-filters-above-content-ctn > span .fi-btn,
            .fi-ta-filters-above-content-ctn > span svg,
            .fi-ta-filters-above-content-ctn [class*="fi-icon"] {
                color: {$text} !important;
                stroke: currentColor !important;
            }

            .fi-ta-filters .fi-input-wrp,
            .fi-ta-filters .fi-select-input,
            .fi-ta-filters-above-content-ctn .fi-ta-filters .fi-input-wrp {
                background-color: #ffffff !important;
                border: 1px solid color-mix(in srgb, {$chrome} 28%, #cbd5e1) !important;
                border-radius: 0.5rem !important;
                color: {$inputText} !important;
                box-shadow: 0 0 0 1px rgba(15, 23, 42, 0.06) !important;
            }

            .fi-ta-filters .fi-input-wrp input,
            .fi-ta-filters .fi-input-wrp textarea,
            .fi-ta-filters .fi-input-wrp select,
            .fi-ta-filters .fi-select-input,
            .fi-ta-filters .fi-input-wrp .fi-icon,
            .fi-ta-filters-above-content-ctn .fi-ta-filters .fi-input-wrp input {
                color: {$inputText} !important;
                -webkit-text-fill-color: {$inputText} !important;
                background-color: transparent !important;
            }

            .fi-ta-filters .fi-input-wrp input::placeholder,
            .fi-ta-filters-above-content-ctn .fi-ta-filters .fi-input-wrp input::placeholder {
                color: {$inputHint} !important;
                -webkit-text-fill-color: {$inputHint} !important;
            }
            CSS;

        return new HtmlString(
            '<style id="filament-color-themes-filters">' . $css . '</style>'
        );
    }
}
