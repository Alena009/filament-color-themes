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
     * White outline icon for the user-menu swatch button.
     */
    public function getSwatchIconSvg(): string
    {
        $path = match ($this->key) {
            // Leaf — forest
            'forest-green' => <<<'SVG'
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 3c-4.5 1.5-7 4-8.5 7.5C9 7 5.5 5 2 5c2 5.5 5 9 9.5 11.5-.5 1.5-1.5 3-3.5 4.5 5-.5 9-3 11.5-7.5C21 10 21.5 6 21 3Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M12.5 10.5c0 4.5-2 8-5.5 10.5" />
                SVG,
            // Building — office
            'office-blue' => <<<'SVG'
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M5.25 21V5.25A2.25 2.25 0 0 1 7.5 3h9a2.25 2.25 0 0 1 2.25 2.25V21M9 7.5h.008v.008H9V7.5Zm0 3h.008v.008H9V10.5Zm0 3h.008v.008H9V13.5Zm3-6h.008v.008H12V7.5Zm0 3h.008v.008H12V10.5Zm0 3h.008v.008H12V13.5Zm3-6h.008v.008H15V7.5Zm0 3h.008v.008H15V10.5Zm0 3h.008v.008H15V13.5Z" />
                SVG,
            // Swatch — midtone / neutrals
            'midtone' => <<<'SVG'
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.098 19.902a3.75 3.75 0 0 0 5.304 0l6.71-6.71A3.75 3.75 0 0 0 17.25 9.75V6.75a.75.75 0 0 0-.75-.75h-3a3.75 3.75 0 0 0-3.442 2.138l-6.71 6.71a3.75 3.75 0 0 0 0 5.304Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75.53-.53a3.75 3.75 0 0 1 5.303 0l.53.53M12.75 12.75l.53-.53a3.75 3.75 0 0 1 5.303 0l.53.53" />
                SVG,
            // Camera — sepia / photo tone
            'sepia' => <<<'SVG'
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.055-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z" />
                SVG,
            // Moon — midnight
            'midnight' => <<<'SVG'
                <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
                SVG,
            default => <<<'SVG'
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m6-6H6" />
                SVG,
        };

        return <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true" class="fi-color-theme-switcher-icon">
                {$path}
            </svg>
            SVG;
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
