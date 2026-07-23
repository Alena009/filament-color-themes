<x-filament-panels::page>
    <div class="fi-color-themes-page">
        <div class="fi-color-themes-header">
            <h3 class="fi-color-themes-heading">
                {{ __('filament-color-themes::color-themes.sections.default_themes') }}
            </h3>

            <p class="fi-color-themes-description">
                {{ __('filament-color-themes::color-themes.sections.default_themes_description') }}
            </p>
        </div>

        <div class="fi-color-themes-grid">
            @foreach ($this->getThemes() as $theme)
                @php
                    $isActive = filled($this->getCurrentThemeKey()) && $this->getCurrentThemeKey() === $theme->key;
                @endphp

                <div
                    wire:key="color-theme-{{ $theme->key }}"
                    @class([
                        'fi-color-theme-card',
                        'fi-color-theme-card-active' => $isActive,
                    ])
                    style="
                        --theme-bg: {{ $theme->cardBackground }};
                        --theme-border: {{ $theme->cardBorder }};
                        --theme-text: {{ $theme->cardText }};
                    "
                >
                    <div
                        wire:loading.flex
                        wire:target="selectTheme('{{ $theme->key }}')"
                        class="fi-color-theme-card-loading"
                    >
                        <x-filament::loading-indicator class="h-5 w-5" />
                    </div>

                    <div
                        class="fi-color-theme-avatar"
                        style="background-color: {{ $theme->cardBorder }};"
                    >
                        {{ strtoupper(substr($theme->name, 0, 1)) }}
                    </div>

                    <div class="fi-color-theme-main">
                        <div class="fi-color-theme-title" style="color: {{ $theme->cardText }};">
                            {{ $theme->getLabel() }}
                        </div>

                        <div class="fi-color-theme-subtitle" style="color: {{ $theme->cardBorder }};">
                            @if ($isActive)
                                {{ __('filament-color-themes::color-themes.active') }}
                            @else
                                {{ $theme->hex }}
                            @endif
                        </div>
                    </div>

                    <div class="fi-color-theme-actions">
                        <x-filament::button
                            color="gray"
                            icon="heroicon-m-swatch"
                            labeled-from="sm"
                            tag="button"
                            type="button"
                            wire:click="selectTheme('{{ $theme->key }}')"
                            wire:loading.attr="disabled"
                            wire:target="selectTheme('{{ $theme->key }}')"
                        >
                            {{ __('filament-color-themes::color-themes.select') }}
                        </x-filament::button>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <style>
        .fi-color-themes-page {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .fi-color-themes-heading {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            line-height: 1.5rem;
            color: rgba(255, 255, 255, 0.95);
        }

        .fi-body:not(.dark) .fi-color-themes-heading,
        html:not(.dark) .fi-color-themes-heading {
            color: rgb(3, 7, 18);
        }

        .fi-color-themes-description {
            margin: 0.25rem 0 0;
            font-size: 0.875rem;
            line-height: 1.25rem;
            color: rgba(156, 163, 175, 1);
        }

        .fi-color-themes-grid {
            display: grid;
            grid-template-columns: repeat(1, minmax(0, 1fr));
            gap: 1.5rem;
        }

        @media (min-width: 768px) {
            .fi-color-themes-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (min-width: 1280px) {
            .fi-color-themes-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        .fi-color-theme-card {
            position: relative;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            min-height: 4.5rem;
            padding: 1rem 1.25rem;
            border-radius: 0.75rem;
            border: 1px solid var(--theme-border);
            background: var(--theme-bg);
            box-sizing: border-box;
        }

        .fi-color-theme-card-active {
            box-shadow: 0 0 0 2px var(--theme-border);
        }

        .fi-color-theme-card-loading {
            display: none;
            position: absolute;
            inset: 0;
            z-index: 10;
            align-items: center;
            justify-content: center;
            border-radius: 0.75rem;
            background: rgba(255, 255, 255, 0.45);
        }

        .fi-color-theme-avatar {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 9999px;
            flex-shrink: 0;
            color: #fff;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .fi-color-theme-main {
            min-width: 0;
            flex: 1 1 auto;
        }

        .fi-color-theme-title {
            font-size: 0.875rem;
            font-weight: 600;
            line-height: 1.25rem;
        }

        .fi-color-theme-subtitle {
            margin-top: 0.125rem;
            font-size: 0.875rem;
            line-height: 1.25rem;
            opacity: 0.9;
        }

        .fi-color-theme-actions {
            flex-shrink: 0;
            margin-inline-start: auto;
        }
    </style>
</x-filament-panels::page>
