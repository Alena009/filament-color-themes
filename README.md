# Filament Color Themes

Плагин для Filament PHP, который добавляет страницу выбора цветовых тем панели.

## Возможности

- Пункт меню **Color Themes** / **Цветовые темы**
- Секция **Default Themes** с тремя карточками:
  - **ForestGreen** — светло-зелёная с тёмно-зелёной границей
  - **Office Blue** — светло-голубая с тёмно-синей границей
  - **Midtone** — сероватая с тёмной границей
- Клик по карточке меняет primary-цвет всего приложения
- Мультиязычность (EN / RU), легко расширяется

## Требования

- PHP 8.2+
- Filament 5.x
- Laravel 11+ / 12+

## Установка

### 1. Подключите пакет

Если пакет лежит локально рядом с проектом:

```bash
composer config repositories.filament-color-themes path ../filament-color-themes
composer require dashk/filament-color-themes:@dev
```

Или через Composer path в `composer.json` приложения:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../filament-color-themes"
        }
    ],
    "require": {
        "dashk/filament-color-themes": "@dev"
    }
}
```

### 2. Зарегистрируйте плагин в Panel Provider

Обязательно добавьте плагин в `AdminPanelProvider` (или ваш panel provider):

```php
use Dashk\FilamentColorThemes\ColorThemesPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(ColorThemesPlugin::make());
}
```

После установки / обновления плагина сбросьте кэш:

```bash
php artisan filament:optimize-clear
php artisan optimize:clear
php artisan view:clear
```

В `AdminPanelProvider` должно быть так:

```php
use Dashk\FilamentColorThemes\ColorThemesPlugin;
use Dashk\FilamentColorThemes\Http\Middleware\ApplyColorTheme;

return $panel
    ->plugin(ColorThemesPlugin::make())
    ->middleware([
        ApplyColorTheme::class,
    ], isPersistent: true);
```

`isPersistent: true` обязателен для Livewire.
### 3. (Опционально) Опубликуйте конфиг и переводы

```bash
php artisan vendor:publish --tag="filament-color-themes-config"
php artisan vendor:publish --tag="filament-color-themes-translations"
```

### Как это работает

При выборе темы плагин меняет не только `primary`, но и палитру `gray` (фоны, бордеры, текст сайдбара и карточек).  
`gray` строится с той же кривой светлоты, что у стандартного Zinc, но с оттенком выбранной темы — по аналогии с dark mode, где всё строится на вариациях тёмно-серого.

Ограничить доступ к странице:

```php
->plugin(
    ColorThemesPlugin::make()
        ->canView(fn (): bool => auth()->user()?->isAdmin() ?? false)
)
```

## Конфигурация

Файл `config/filament-color-themes.php`:

| Ключ | Описание | По умолчанию |
|------|----------|--------------|
| `session_key` | Ключ сессии для выбранной темы | `filament_color_theme` |
| `default` | Тема по умолчанию | `forest-green` |
| `navigation_icon` | Иконка меню | `heroicon-o-swatch` |
| `navigation_sort` | Порядок в меню | `50` |

Доступные ключи тем: `forest-green`, `office-blue`, `midtone`.

## Локализация

Переводы находятся в:

- `resources/lang/en/color-themes.php`
- `resources/lang/ru/color-themes.php`

Язык берётся из локали Laravel (`config('app.locale')`). Чтобы добавить язык, создайте папку `resources/lang/{locale}/color-themes.php` и опубликуйте переводы.

## Лицензия

MIT
