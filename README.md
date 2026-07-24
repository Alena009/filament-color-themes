# Filament Color Themes

A Filament PHP plugin that adds panel color theme selection from the user menu.

## Features

- Theme switcher in the user menu (next to light/dark/system), with icon swatches
- Five themes:
  - **ForestGreen**
  - **Office Blue**
  - **Midtone**
  - **Sepia**
  - **Midnight**
- Selecting a theme updates the panel primary color, sidebar, and topbar
- Color themes and light/dark/system are mutually exclusive
- Multilingual (EN / RU), easy to extend

## Requirements

- PHP 8.2+
- Filament 5.x
- Laravel 11+ / 12+

## Installation

### 1. Require the package

If the package lives locally next to your app:

```bash
composer config repositories.filament-color-themes path ../filament-color-themes
composer require alenadashko/filament-color-themes:@dev
```

Or via a Composer path repository in your app `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../filament-color-themes"
        }
    ],
    "require": {
        "alenadashko/filament-color-themes": "@dev"
    }
}
```

### 2. Register the plugin in your Panel Provider

Add the plugin to `AdminPanelProvider` (or your panel provider):

```php
use AlenaDashko\FilamentColorThemes\ColorThemesPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(ColorThemesPlugin::make());
}
```

After installing or updating the plugin, clear caches:

```bash
php artisan filament:optimize-clear
php artisan optimize:clear
php artisan view:clear
```

Your `AdminPanelProvider` should look like this:

```php
use AlenaDashko\FilamentColorThemes\ColorThemesPlugin;
use AlenaDashko\FilamentColorThemes\Http\Middleware\ApplyColorTheme;

return $panel
    ->plugin(ColorThemesPlugin::make())
    ->middleware([
        ApplyColorTheme::class,
    ], isPersistent: true);
```

`isPersistent: true` is required for Livewire.

### 3. (Optional) Publish config and translations

```bash
php artisan vendor:publish --tag="filament-color-themes-config"
php artisan vendor:publish --tag="filament-color-themes-translations"
```

### How it works

When a theme is selected, the plugin updates both the `primary` palette and a tinted `gray` palette (backgrounds, borders, sidebar/card text).  
`gray` follows the same lightness curve as Filament’s Zinc scale, but keeps a low chroma of the selected theme — similar to dark mode, where everything is built from dark-gray variations.

Restrict access to the user-menu theme switcher:

```php
->plugin(
    ColorThemesPlugin::make()
        ->canView(fn (): bool => auth()->user()?->isAdmin() ?? false)
)
```

## Configuration

File: `config/filament-color-themes.php`

| Key | Description | Default |
|-----|-------------|---------|
| `session_key` | Session key for the selected theme | `filament_color_theme` |
| `default` | Default theme (`null` = none until selected) | `null` |

Available theme keys: `forest-green`, `office-blue`, `midtone`, `sepia`, `midnight`.

## Localization

Translations live in:

- `resources/lang/en/color-themes.php`
- `resources/lang/ru/color-themes.php`

The language is taken from the Laravel locale (`config('app.locale')`). To add a language, create `resources/lang/{locale}/color-themes.php` and publish the translations.

## License

MIT
