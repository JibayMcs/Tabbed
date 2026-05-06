<?php

namespace JibayMcs\Tabbed\Actions;

use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Js;
use JibayMcs\Tabbed\Traits\HasHoverCard;
use JibayMcs\Tabbed\TabbedPlugin;
use Livewire\Livewire;

class OpenInTabAction extends Action
{
    use HasHoverCard;

    protected ?string $tabbedPage = null;

    protected ?string $tabbedResource = null;

    protected bool $shouldActivate = true;

    protected ?\Closure $tabNameCallback = null;

    /**
     * Optional resolver invoked with the Filament-injected `$record` (the
     * parent record bound by the surrounding context — e.g. the Ticket when
     * the action lives in a `Section::footer()` of a Ticket infolist) and
     * returning the **target** record to open in a tab.
     *
     * Use case: opening a related model in a tab from the parent's view page.
     * Without this resolver the action would call `getKey()` on the parent
     * record, producing a 404 when the resource doesn't match (e.g. opening
     * a Contact tab using a Ticket's id).
     *
     *     OpenInTabAction::make()
     *         ->resource(ContactResource::class)
     *         ->openFor(fn ($record) => $record->contact)
     *         ->tabName(fn ($record) => $record->fullname); // $record = Contact
     */
    protected ?\Closure $recordResolver = null;

    protected \Closure|string|array|null $tabColor = null;

    protected \Closure|string|array|null $tabBackground = null;

    protected \Closure|string|array|null $tabTextColor = null;

    protected bool $confirmOnClose = false;

    protected bool $closeOnSave = false;

    protected \Closure|bool $canReorder = true;

    protected \Closure|bool $canRename = true;

    protected \Closure|bool $canPin = true;

    protected \Closure|bool $canDuplicate = true;

    protected \Closure|bool $canClose = true;

    public static function getDefaultName(): ?string
    {
        return 'tabbed';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('tabbed::tabbed.open_in_tab'));
        $this->icon('heroicon-m-arrow-top-right-on-square');
        $this->color('gray');

        $this->action(function (Action $action, ?Model $record = null) {
            $data = $this->constructTabData($record, false);
            $action->getLivewire()->dispatch('tabbed:open', ...$data);
        });

        $this->alpineClickHandler(function (?Model $record = null): string {
            $jsData = $this->constructTabData($record);
            return "window.dispatchEvent(new CustomEvent('tabbed:open', { detail: {$jsData} }))";
        });

    }

    private function constructTabData(?Model $record = null, bool $hasJsData = true): Js|array
    {
        $resource = $this->getTabResource();
        $page = $this->getTabbedPage();

        $data = [
            'resource' => $resource,
            'page' => $page,
            'background' => !$this->shouldActivate,
        ];

        // Resolve the **target** record from the Filament-injected parent
        // record if a `->openFor()` callback was provided. All downstream
        // callbacks (tabName, hoverCard, colors) receive the resolved record
        // so they can read the target model's attributes directly.
        // The user is expected to gate the action with `->visible()` when
        // the relation might be null; an unresolved record here falls back
        // to the parent (preserving the legacy behaviour).
        if ($record && $this->recordResolver) {
            try {
                $resolved = ($this->recordResolver)($record);
                if ($resolved instanceof Model) {
                    $record = $resolved;
                }
            } catch (\Throwable $e) {
                // Resolver crashed — keep the parent record to avoid
                // silently breaking the action.
            }
        }

        if ($record) {
            $data['recordId'] = $record->getKey();

            if ($this->tabNameCallback) {
                try {
                    $data['label'] = ($this->tabNameCallback)($record);
                } catch (\Throwable $e) {
                    // Callback failed — use default label
                }
            }
        }

        if ($this->tabColor !== null) {
            try {
                $color = $this->tabColor instanceof \Closure ? ($this->tabColor)($record) : $this->tabColor;
                $data['tabColor'] = $this->resolveColor($color, 500);
            } catch (\Throwable $e) {
                // Color resolution failed — skip
            }
        }

        if ($this->tabBackground !== null) {
            try {
                $color = $this->tabBackground instanceof \Closure ? ($this->tabBackground)($record) : $this->tabBackground;
                $data['tabBackground'] = $this->resolveColor($color, 50);
            } catch (\Throwable $e) {
                // Background resolution failed — skip
            }
        }

        if ($this->tabTextColor !== null) {
            try {
                $color = $this->tabTextColor instanceof \Closure ? ($this->tabTextColor)($record) : $this->tabTextColor;
                $data['tabTextColor'] = $this->resolveColor($color, 700);
            } catch (\Throwable $e) {
                // Text color resolution failed — skip
            }
        }

        if ($this->confirmOnClose) {
            $data['confirmOnClose'] = true;
        }

        if ($this->closeOnSave) {
            $data['closeOnSave'] = true;
        }

        // Resolve per-tab permissions (only include if false to keep payload lean)
        foreach (['canReorder', 'canRename', 'canPin', 'canDuplicate', 'canClose'] as $perm) {
            $value = $this->{$perm};
            $resolved = $value instanceof \Closure ? $value($record) : $value;

            if (!$resolved) {
                $data[$perm] = false;
            }
        }

        $hoverContent = $this->resolveHoverCardContent($record);
        if ($hoverContent !== null) {
            $data['hoverCard'] = [
                'content' => $hoverContent,
                'position' => $this->hoverCardPosition->value,
                'delay' => $this->hoverCardDelay,
                'leaveDelay' => $this->hoverCardLeaveDelay,
            ];
        }

        $jsData = $hasJsData ? Js::from($data) : $data;

        return $jsData;
    }

    public function tabbedPage(string $page): static
    {
        $this->tabbedPage = $page;

        return $this;
    }

    public function getTabbedPage(): string
    {
        return $this->tabbedPage ?? TabbedPlugin::get()->getDefaultPage();
    }

    public function resource(string $resource): static
    {
        $this->tabbedResource = $resource;

        return $this;
    }

    public function tabName(\Closure $callback): static
    {
        $this->tabNameCallback = $callback;

        return $this;
    }

    /**
     * Resolve the target record from the action's parent context.
     *
     * The closure receives the Filament-injected `$record` (the parent record
     * of the surrounding schema component — e.g. the Ticket when this action
     * lives in the footer of a Ticket infolist Section) and must return the
     * record whose `view` / `edit` page should be opened in a new tab.
     *
     * Without this resolver the action calls `getKey()` on the parent record,
     * which 404s when the resource is different (e.g. opening a Contact tab
     * with a Ticket id).
     *
     *     OpenInTabAction::make()
     *         ->resource(ContactResource::class)
     *         ->openFor(fn (Ticket $record) => $record->contact);
     *
     * If the closure returns `null`, the action becomes a no-op (no tab is
     * opened) — useful when the related record may not exist.
     */
    public function openFor(\Closure $callback): static
    {
        $this->recordResolver = $callback;

        return $this;
    }

    public function activate(bool $condition = true): static
    {
        $this->shouldActivate = $condition;

        return $this;
    }

    public function confirmOnClose(bool $condition = true): static
    {
        $this->confirmOnClose = $condition;

        return $this;
    }

    public function closeOnSave(bool $condition = true): static
    {
        $this->closeOnSave = $condition;

        return $this;
    }

    public function canReorder(\Closure|bool $condition = true): static
    {
        $this->canReorder = $condition;

        return $this;
    }

    public function canRename(\Closure|bool $condition = true): static
    {
        $this->canRename = $condition;

        return $this;
    }

    public function canPin(\Closure|bool $condition = true): static
    {
        $this->canPin = $condition;

        return $this;
    }

    public function canDuplicate(\Closure|bool $condition = true): static
    {
        $this->canDuplicate = $condition;

        return $this;
    }

    public function canClose(\Closure|bool $condition = true): static
    {
        $this->canClose = $condition;

        return $this;
    }

    public function background(bool $condition = true): static
    {
        $this->shouldActivate = !$condition;

        return $this;
    }

    public function tabColor(\Closure|string|array $color): static
    {
        $this->tabColor = $color;

        return $this;
    }

    public function tabBackground(\Closure|string|array $color): static
    {
        $this->tabBackground = $color;

        return $this;
    }

    public function tabTextColor(\Closure|string|array $color): static
    {
        $this->tabTextColor = $color;

        return $this;
    }

    /**
     * Resolve a color value to a CSS-usable string.
     *
     * Accepts:
     * - A string (hex, rgb, rgba, oklch, named CSS color) — used as-is
     * - A Filament Color palette array (e.g. Color::Red) — picks the given shade
     */
    private function resolveColor(string|array $color, int $shade = 500): string
    {
        if (is_string($color)) {
            return $color;
        }

        return $color[$shade] ?? $color[500] ?? '';
    }

    public function getTabResource(): string
    {
        if ($this->tabbedResource) {
            return $this->tabbedResource;
        }

        $livewire = $this->getTable()?->getLivewire();

        if ($livewire && method_exists($livewire, 'getResource')) {
            return $livewire::getResource();
        }

        throw new \RuntimeException(
            'Could not detect resource for OpenInTabAction. Set it explicitly with ->resource(YourResource::class).'
        );
    }
}
