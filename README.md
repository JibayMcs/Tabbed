# Tabbed - In-app tab system for FilamentPHP v5

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jibaymcs/tabbed.svg?style=flat-square)](https://packagist.org/packages/jibaymcs/tabbed)
[![Total Downloads](https://img.shields.io/packagist/dt/jibaymcs/tabbed.svg?style=flat-square)](https://packagist.org/packages/jibaymcs/tabbed)

A FilamentPHP v5 plugin that brings IDE/browser-style tabs to your panel. Open resource pages (Edit, View, Create, List) in tabs, switch between them instantly without losing state, and organize your workflow with drag & drop, renaming, and context menus.

## Features

- Open any Filament resource page in a tab
- Instant tab switching (no page reload, state preserved)
- Drag & drop tab reordering
- Inline tab renaming (double-click)
- Right-click context menu (rename, close, close others, close all)
- Middle-click to close tabs (opt-in)
- Configurable tab bar position (topbar, page start, content start, etc.)
- LocalStorage persistence across page navigations
- Background tab opening
- Custom tab labels
- Custom tab colors (accent, background, text) with Filament Color support
- Dark mode support
- Translations: English & French

## Installation

```bash
composer require jibaymcs/tabbed
```

Add the plugin's views to your custom theme CSS file:

```css
@source '../../../../vendor/jibaymcs/tabbed/resources/**/*.blade.php';
```

## Setup

Register the plugin in your `PanelProvider`:

```php
use JibayMcs\Tabbed\TabbedPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            TabbedPlugin::make(),
        ]);
}
```

## Usage

### Option 1: Automatic with trait

Add `HasTabbedActions` to your Resource to automatically include the "Open in tab" action on every table row:

```php
use JibayMcs\Tabbed\Traits\HasTabbedActions;

class UserResource extends Resource
{
    use HasTabbedActions;

    // Your resource code — no other changes needed
}
```

### Option 2: Manual action

Add `OpenInTabAction` manually in your table configuration for more control:

```php
use JibayMcs\Tabbed\Actions\OpenInTabAction;

public static function table(Table $table): Table
{
    return $table
        ->recordActions([
            OpenInTabAction::make(),
            // ...other actions
        ]);
}
```

### Option 3: Row click

Make clicking a table row open the record in a tab instead of navigating to the edit page:

```php
public static function table(Table $table): Table
{
    return $table
        ->recordUrl(null)
        ->recordAction('tabbed')
        ->recordActions([
            OpenInTabAction::make()
                ->hiddenLabel()
                ->background()
                ->tabName(fn ($record) => "Ticket #{$record->id}"),
        ]);
}
```

- `recordUrl(null)` — disables the default link on the row
- `recordAction('tabbed')` — clicking a row triggers the `OpenInTabAction` via Livewire
- `background()` — opens the tab without switching to it
- `tabName()` — custom label for the tab

### Action options

```php
OpenInTabAction::make()
    ->tabbedPage('view')                              // Target page: edit, view, create, index (default: from config)
    ->background()                                    // Open tab without switching to it
    ->tabName(fn ($record) => $record->name)          // Custom tab label
    ->resource(UserResource::class)                   // Explicit resource (auto-detected by default)
    ->tabColor(Color::Red)                            // Accent color (left border indicator)
    ->tabBackground(Color::Red)                       // Background color
    ->tabTextColor(Color::Red)                        // Text color
```

### Tab colors

Customize tab appearance per action. Accepts Filament `Color` palettes, hex values, or any CSS color string:

```php
use Filament\Support\Colors\Color;

// Filament Color palette (shade picked automatically)
OpenInTabAction::make()
    ->tabColor(Color::Red)                            // border: shade 500
    ->tabBackground(Color::Red)                       // background: shade 50
    ->tabTextColor(Color::Red)                        // text: shade 700

// Specific shade from a palette
OpenInTabAction::make()
    ->tabColor(Color::Blue[600])

// Hex, rgb, rgba
OpenInTabAction::make()
    ->tabColor('#ef4444')
    ->tabBackground('rgba(254, 242, 242, 0.8)')
    ->tabTextColor('#991b1b')
```

### JavaScript events

You can open/close tabs programmatically from anywhere:

```js
// Open a tab
window.dispatchEvent(new CustomEvent('tabbed:open', {
    detail: {
        resource: 'App\\Filament\\Resources\\UserResource',
        page: 'edit',
        recordId: 5,
        label: 'Custom label',     // optional
        background: false,         // optional
    }
}));

// Close a tab
window.dispatchEvent(new CustomEvent('tabbed:close', {
    detail: { id: 'tab-uuid' }
}));
```

**Events dispatched by the plugin:**

| Event | Payload | Description |
|---|---|---|
| `tabbed:tab-opened` | `{ tab }` | A tab was opened |
| `tabbed:tab-closed` | `{ tab }` | A tab was closed |
| `tabbed:tab-activated` | `{ tabId }` | A tab was activated |
| `tabbed:tab-deactivated` | `{ tabId }` | Active tab was toggled off |
| `tabbed:all-closed` | — | All tabs were closed |

## Configuration

### Plugin options

Configure via fluent methods in your `PanelProvider`:

```php
TabbedPlugin::make()
    ->defaultPage('view')                                   // Default page on open (default: edit)
    ->renderHook(PanelsRenderHook::TOPBAR_LOGO_AFTER)       // Tab bar position (default: PAGE_START)
    ->persistKey('my_panel_tabs')                            // localStorage key (default: tabbed_tabs)
    ->middleClickToClose()                                  // Close tabs with middle mouse button (default: off)
    ->showTabIcons(false)                                   // Hide resource icons in tabs (default: true)
```

### Config file

Publish the config file for project-wide defaults:

```bash
php artisan vendor:publish --tag="tabbed-config"
```

```php
// config/tabbed.php
return [
    'default_page' => 'edit',
    'persist_key' => 'tabbed_tabs',
];
```

Plugin fluent methods take priority over config file values.

### Tab bar position

The tab bar can be placed at any Filament render hook position:

```php
use Filament\View\PanelsRenderHook;

// In the topbar (after the logo)
TabbedPlugin::make()->renderHook(PanelsRenderHook::TOPBAR_LOGO_AFTER)

// At the start of the page content (default)
TabbedPlugin::make()->renderHook(PanelsRenderHook::PAGE_START)

// Inside the main content area
TabbedPlugin::make()->renderHook(PanelsRenderHook::CONTENT_START)
```

The tab bar is rendered at the chosen position, while the tab content panels always render inside `<main class="fi-main">`.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

- [JibayMcs](https://github.com/JibayMcs)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
