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

    public function syncTabs(array $tabs): array
    {
        $this->tabs = collect($tabs)
            ->filter(fn (array $tab) => $this->resolvePageClass($tab['resource'] ?? '', $tab['page'] ?? '') !== null)
            ->values()
            ->toArray();

        return $this->resolveTabIcons();
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
