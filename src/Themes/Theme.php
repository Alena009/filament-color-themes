<?php

namespace Dashk\FilamentColorThemes\Themes;

class Theme
{
    /**
     * @param  array<int, string>  $primary
     * @param  array<int, string>  $gray
     */
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly string $hex,
        public readonly array $primary,
        public readonly array $gray,
        public readonly string $cardBackground,
        public readonly string $cardBorder,
        public readonly string $cardText,
        public readonly string $swatch = '',
    ) {}

    public function getLabel(): string
    {
        return __('filament-color-themes::color-themes.themes.' . $this->key);
    }

    public function getSwatchLetter(): string
    {
        if (filled($this->swatch)) {
            return strtoupper($this->swatch);
        }

        return strtoupper(substr($this->name, 0, 1));
    }

    /**
     * Colors registered with FilamentColor / CSS variables.
     *
     * @return array{primary: array<int, string>, gray: array<int, string>}
     */
    public function getFilamentColors(): array
    {
        return [
            'primary' => $this->primary,
            'gray' => $this->gray,
        ];
    }
}
