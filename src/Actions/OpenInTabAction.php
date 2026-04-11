<?php

namespace JibayMcs\Tabbed\Actions;

use Filament\Actions\Action;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
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

        $this->action(function (?Model $record = null, Action $action) {
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

            if (! $resolved) {
                $data[$perm] = false;
            }
        }

        if ($this->hasHoverCard && $this->hoverCardContentCallback) {
            try {
                $content = ($this->hoverCardContentCallback)($record);

                if ($content instanceof View) {
                    $content = $content->render();
                } elseif ($content instanceof HtmlString) {
                    $content = $content->toHtml();
                }

                $data['hoverCard'] = [
                    'content' => (string) $content,
                    'position' => $this->hoverCardPosition->value,
                    'delay' => $this->hoverCardDelay,
                    'leaveDelay' => $this->hoverCardLeaveDelay,
                ];
            } catch (\Throwable $e) {
                // Hover card rendering failed — skip
            }
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
