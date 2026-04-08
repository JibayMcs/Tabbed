<?php

namespace JibayMcs\Tabbed\Livewire;

use Filament\Resources\Resource;
use JibayMcs\Tabbed\TabbedPlugin;
use Livewire\Component;

class TabbedContainer extends Component
{
    public static bool $barRendered = false;

    public static bool $contentRendered = false;

    public array $tabs = [];

    public array $loadedTabIds = [];

    public function syncTabs(array $tabs): array
    {
        $previousLoadedIds = $this->loadedTabIds;

        $this->tabs = collect($tabs)
            ->filter(fn (array $tab) => $this->resolvePageClass($tab['resource'] ?? '', $tab['page'] ?? '') !== null)
            ->values()
            ->toArray();

        // Without lazy loading, load all tabs immediately (default behavior)
        if (! TabbedPlugin::get()->getLazyLoad()) {
            $this->loadedTabIds = array_column($this->tabs, 'id');
        } else {
            // Clean up loaded IDs for tabs that no longer exist
            $validIds = array_column($this->tabs, 'id');
            $this->loadedTabIds = array_values(array_intersect($this->loadedTabIds, $validIds));
        }

        // Only re-render when new tabs need their Livewire component created.
        // Skipping render prevents the parent morph from corrupting child
        // components (e.g. Select::multiple() value duplication).
        $newlyLoaded = array_diff($this->loadedTabIds, $previousLoadedIds);
        if (empty($newlyLoaded)) {
            $this->skipRender();
        }

        return $this->resolveTabIcons();
    }

    public function loadTab(string $tabId): void
    {
        if (TabbedPlugin::get()->getDestroyInactive()) {
            $this->loadedTabIds = [$tabId];
        } elseif (! in_array($tabId, $this->loadedTabIds)) {
            $this->loadedTabIds[] = $tabId;
        }
    }

    protected function resolveTabIcons(): array
    {
        if (! TabbedPlugin::get()->getShowTabIcons()) {
            return [];
        }

        $icons = [];

        foreach ($this->tabs as $tab) {
            $resource = $tab['resource'] ?? '';

            if (isset($icons[$resource]) || ! class_exists($resource) || ! is_subclass_of($resource, Resource::class)) {
                continue;
            }

            $icon = $resource::getNavigationIcon();

            if ($icon) {
                $iconName = $icon instanceof \BackedEnum ? "heroicon-{$icon->value}" : $icon;
                $icons[$resource] = svg($iconName, 'fi-tabbed-bar-tab-icon')->toHtml();
            }
        }

        return $icons;
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
