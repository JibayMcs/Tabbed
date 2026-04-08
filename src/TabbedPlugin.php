<?php

namespace JibayMcs\Tabbed;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\HtmlString;
use JibayMcs\Tabbed\Livewire\TabbedContainer;

class TabbedPlugin implements Plugin
{
    protected string $renderHookName = PanelsRenderHook::PAGE_START;

    protected ?int $maxTabs = null;

    protected ?string $defaultPage = null;

    protected ?string $persistKey = null;

    public function getId(): string
    {
        return 'tabbed';
    }

    public function register(Panel $panel): void
    {
        // Tab bar portal target — rendered at user-configured hook position
        $panel->renderHook(
            $this->getRenderHook(),
            function (): HtmlString|string {
                if (TabbedContainer::$barRendered) {
                    return '';
                }

                TabbedContainer::$barRendered = true;

                return new HtmlString('<div id="fi-tabbed-bar-portal"></div>');
            },
        );

        // Tab content panels — always rendered at CONTENT_START (inside <main class="fi-main">)
        $panel->renderHook(
            PanelsRenderHook::CONTENT_START,
            function (): \Illuminate\Contracts\View\View|string {
                if (TabbedContainer::$contentRendered) {
                    return '';
                }

                TabbedContainer::$contentRendered = true;

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

    public function maxTabs(int $maxTabs): static
    {
        $this->maxTabs = $maxTabs;

        return $this;
    }

    public function getMaxTabs(): int
    {
        return $this->maxTabs ?? config('tabbed.max_tabs', 20);
    }

    public function defaultPage(string $defaultPage): static
    {
        $this->defaultPage = $defaultPage;

        return $this;
    }

    public function getDefaultPage(): string
    {
        return $this->defaultPage ?? config('tabbed.default_page', 'edit');
    }

    public function persistKey(string $persistKey): static
    {
        $this->persistKey = $persistKey;

        return $this;
    }

    public function getPersistKey(): string
    {
        return $this->persistKey ?? config('tabbed.persist_key', 'tabbed_tabs');
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
