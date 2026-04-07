<?php

namespace JibayMcs\Tabbed\Livewire;

use Filament\Resources\Resource;
use Livewire\Component;

class TabbedContainer extends Component
{
    public array $tabs = [];

    public function syncTabs(array $tabs): void
    {
        $this->tabs = collect($tabs)
            ->filter(fn (array $tab) => $this->resolvePageClass($tab['resource'] ?? '', $tab['page'] ?? '') !== null)
            ->values()
            ->toArray();
    }

    public function resolvePageClass(string $resource, string $page): ?string
    {
        if (! class_exists($resource)) {
            return null;
        }

        if (! is_subclass_of($resource, Resource::class)) {
            return null;
        }

        $pages = $resource::getPages();

        if (! isset($pages[$page])) {
            return null;
        }

        return $pages[$page]->getPage();
    }

    public function render()
    {
        return view('tabbed::tabbed-container');
    }
}
