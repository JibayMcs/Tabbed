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
- Hover cards on tabs (rich tooltip with custom content on hover)
- Lazy loading & destroy inactive (performance optimization)
- Dropdown mode (compact button replacing the full tab bar)
- Dirty state detection with unsaved changes confirmation modal
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
    ->confirmOnClose()                                // Ask confirmation before closing if dirty
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

### Hover cards

Display a rich tooltip when hovering over a tab. The content is fully customizable and has access to the `$record`:

```php
use JibayMcs\Tabbed\Enums\HoverCardPosition;
use Illuminate\Support\HtmlString;

// Blade view with record data
OpenInTabAction::make()
    ->hoverCardContent(fn ($record) => view('partials.tab-preview', ['record' => $record]))
    ->hoverCardPosition(HoverCardPosition::Bottom)

// Inline HTML
OpenInTabAction::make()
    ->hoverCardContent(fn ($record) => new HtmlString("<strong>{$record->name}</strong><br>{$record->email}"))
    ->hoverCardDelay(400)           // Delay before showing (default: 600ms)
    ->hoverCardLeaveDelay(300)      // Delay before hiding (default: 500ms)

// Plain text
OpenInTabAction::make()
    ->hoverCardContent(fn ($record) => "#{$record->id} - {$record->name}")
    ->hoverCardPosition(HoverCardPosition::Top)

// Disable hover card
OpenInTabAction::make()
    ->hoverCard(false)
```

Available positions: `Top`, `TopStart`, `TopEnd`, `Bottom`, `BottomStart`, `BottomEnd`, `Left`, `Right`.

The hover card stays visible when moving the cursor from the tab to the card. It also works on overflow dropdown items.

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
    ->renderHook(PanelsRenderHook::TOPBAR_LOGO_AFTER)       // Tab bar position (default: TOPBAR_LOGO_AFTER)
    ->persistKey('my_panel_tabs')                            // localStorage key (default: tabbed_tabs)
    ->middleClickToClose()                                  // Close tabs with middle mouse button (default: off)
    ->showTabIcons(false)                                   // Hide resource icons in tabs (default: true)
    ->lazyLoad()                                            // Only load tab content on first activation (default: off)
    ->destroyInactive()                                     // Destroy inactive tab components to save memory (default: off)
    ->confirmClose()                                        // Confirm before closing tabs with unsaved changes (default: off)
```

### Performance: Lazy loading & destroy inactive

By default, all open tabs have their Livewire components created immediately. For better performance with many tabs:

```php
// Lazy load: components are created only when a tab is activated for the first time.
// Once loaded, they stay in memory (state preserved on switch).
TabbedPlugin::make()->lazyLoad()

// Destroy inactive: only the active tab has a Livewire component in the DOM.
// Switching tabs destroys the previous component and creates the new one.
// Saves memory but loses form state on switch. Implies lazyLoad.
TabbedPlugin::make()->destroyInactive()

// Keep alive: keep the N most recently visited tabs in memory (LRU).
// Tabs beyond this limit are destroyed. Combines fast switching with memory savings.
TabbedPlugin::make()->destroyInactive(keepAlive: 3)
```

A loading spinner appears in the tab panel while the Livewire component loads, and a small loading indicator is shown on the tab itself.

### Dropdown mode

Replace the full tab bar with a compact dropdown button:

```php
TabbedPlugin::make()
    ->hasDropdown()                                         // Enables dropdown mode (default icon + badge)
    ->hasDropdown(                                          // Full customization
        icon: 'phosphor-tabs-duotone',                     // Custom icon (default: heroicon-m-squares-2x2)
        label: 'Tabs',                                     // Optional text label
        countBadge: true,                                   // Show tab count badge (default: true)
        color: 'primary',                                   // Filament color name (default: primary)
        outlined: false,                                    // Outlined style (default: false)
    )

// Icon only, no badge, outlined
TabbedPlugin::make()->hasDropdown(countBadge: false, outlined: true)

// Label only, no icon
TabbedPlugin::make()->hasDropdown(icon: null, label: 'My tabs')

// Icon + label
TabbedPlugin::make()->hasDropdown(icon: 'heroicon-m-squares-2x2', label: 'Tabs')
```

Clicking the button opens a dropdown listing all tabs with icons, active indicator, close buttons, and hover cards. All existing features (lazy load, middle-click, persistence) work in dropdown mode.

### Dirty state & close confirmation

The plugin detects unsaved changes in tab forms. When a form field is modified, an orange dot appears on the tab. If confirmation is enabled, closing a dirty tab shows a Filament-style modal instead of closing immediately.

```php
// Global: all dirty tabs ask confirmation before closing
TabbedPlugin::make()->confirmClose()

// Per-tab: only specific actions ask confirmation
OpenInTabAction::make()->confirmOnClose()

// Both can be combined: global acts as a default, per-tab overrides
TabbedPlugin::make()->confirmClose()
// A tab without ->confirmOnClose() will still ask because of the global setting
```

The dirty state resets automatically after a successful save (`save` or `create` Livewire calls). The confirmation modal also appears for "Close others" and "Close all" context menu actions when dirty tabs are involved.

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

// At the start of the page content
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
