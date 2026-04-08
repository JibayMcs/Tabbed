<?php

namespace JibayMcs\Tabbed;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use JibayMcs\Tabbed\Livewire\TabbedContainer;

class TabbedPlugin implements Plugin
{
    protected string $renderHookName = PanelsRenderHook::PAGE_START;

    public function getId(): string
    {
        return 'tabbed';
    }

    public function register(Panel $panel): void
    {
        $panel->renderHook(
            $this->getRenderHook(),
            function (): \Illuminate\Contracts\View\View|string {
                if (TabbedContainer::$rendered) {
                    return '';
                }

                TabbedContainer::$rendered = true;

                return view('tabbed::tab-bar');
            },
        );
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public function renderHook(string $hookName): static
    {
        $this->renderHookName = $hookName;

        return $this;
    }

    public function getRenderHook(): string
    {
        return $this->renderHookName;
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }
}
