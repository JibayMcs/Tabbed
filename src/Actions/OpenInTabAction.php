<?php

namespace JibayMcs\Tabbed\Actions;

use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Js;
use JibayMcs\Tabbed\TabbedPlugin;
use Livewire\Livewire;

class OpenInTabAction extends Action
{
    protected ?string $tabbedPage = null;

    protected ?string $tabbedResource = null;

    protected bool $shouldActivate = true;

    protected ?\Closure $tabNameCallback = null;

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
                $data['label'] = ($this->tabNameCallback)($record);
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

    public function background(bool $condition = true): static
    {
        $this->shouldActivate = !$condition;

        return $this;
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
