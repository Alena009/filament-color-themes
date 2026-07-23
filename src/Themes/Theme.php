<?php

namespace Dashk\FilamentColorThemes\Themes;

class Theme
{
    /**
     * @param  array<int, string>  $primary
     */
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly string $hex,
        public readonly array $primary,
        public readonly string $cardBackground,
        public readonly string $cardBorder,
        public readonly string $cardText,
    ) {}

    public function getLabel(): string
    {
        return __('filament-color-themes::color-themes.themes.' . $this->key);
    }
}
