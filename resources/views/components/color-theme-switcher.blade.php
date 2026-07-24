@php
    use Dashk\FilamentColorThemes\ColorThemeManager;

    $manager = app(ColorThemeManager::class);
    $themes = $manager->getThemes();
    $current = $manager->getCurrentThemeKey();
    // Placeholder must match route where: [A-Za-z0-9\-]+ (no underscores).
    $setUrlTemplate = route('filament-color-themes.set', ['theme' => 'THEMEKEY']);
    $cookieName = $manager->getSessionKey();
@endphp

{{-- Same layout pattern as Filament's light/dark/system switcher --}}
<div class="fi-dropdown-list">
    <div
        role="group"
        aria-label="{{ __('filament-color-themes::color-themes.user_menu_label') }}"
        class="fi-theme-switcher fi-color-theme-switcher"
        data-color-theme-switcher
        data-set-url-template="{{ $setUrlTemplate }}"
        data-cookie-name="{{ $cookieName }}"
    >
        @foreach ($themes as $theme)
            @php
                $isActive = $current === $theme->key;
                $label = $theme->getLabel();
            @endphp

            <button
                type="button"
                aria-label="{{ $label }}"
                aria-pressed="{{ $isActive ? 'true' : 'false' }}"
                title="{{ $label }}"
                data-color-theme-key="{{ $theme->key }}"
                @class([
                    'fi-theme-switcher-btn',
                    'fi-color-theme-switcher-btn',
                    'fi-active' => $isActive,
                ])
                style="--ct-color: {{ $theme->cardBorder }}; --ct-bg: {{ $theme->cardBackground }};"
            >
                <span
                    class="fi-color-theme-switcher-swatch"
                    style="background-color: {{ $theme->cardBorder }};"
                >
                    {!! $theme->getSwatchIconSvg() !!}
                </span>
            </button>
        @endforeach
    </div>
</div>

<style>
    .fi-color-theme-switcher {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 0.25rem;
        width: 100%;
        padding: 0.25rem;
    }

    .fi-color-theme-switcher-btn {
        display: flex;
        justify-content: center;
        align-items: center;
        border-radius: 0.375rem;
        padding: 0.5rem;
        border: none;
        background: transparent;
        cursor: pointer;
        transition: background-color 0.15s ease;
    }

    .fi-color-theme-switcher-btn:hover {
        background-color: color-mix(in srgb, var(--ct-bg) 80%, transparent);
    }

    .fi-color-theme-switcher-btn.fi-active {
        background-color: var(--ct-bg);
    }

    .fi-color-theme-switcher-swatch {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.25rem;
        height: 1.25rem;
        border-radius: 9999px;
        color: #fff;
        line-height: 1;
        box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.08);
    }

    .fi-color-theme-switcher-icon {
        width: 0.75rem;
        height: 0.75rem;
        display: block;
        color: #fff;
        stroke: #fff;
    }
</style>
