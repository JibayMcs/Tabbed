<?php

namespace JibayMcs\Tabbed;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\HtmlString;
use JibayMcs\Tabbed\Livewire\TabbedContainer;

class TabbedPlugin implements Plugin
{
    protected string $renderHookName = PanelsRenderHook::TOPBAR_LOGO_AFTER;

    protected ?string $defaultPage = null;

    protected ?string $persistKey = null;

    protected bool $middleClickToClose = false;

    protected bool $showTabIcons = true;

    protected bool $lazyLoad = false;

    protected bool $destroyInactive = false;

    protected int $keepAlive = 1;

    protected bool $confirmClose = false;

    protected bool $interceptRedirects = true;

    protected bool $dropdown = false;

    protected ?string $dropdownIcon = 'heroicon-m-squares-2x2';

    protected ?string $dropdownLabel = null;

    protected bool $dropdownCountBadge = true;

    protected string $dropdownColor = 'primary';

    protected bool $dropdownOutlined = false;

    protected bool $allowReorder = true;

    protected bool $allowRename = true;

    protected bool $allowPin = true;

    protected bool $allowDuplicate = true;

    protected bool $allowCloseOthers = true;

    protected bool $allowCloseAll = true;

    public function getId(): string
    {
        return 'tabbed';
    }

    public function register(Panel $panel): void
    {
        $panelId = $panel->getId();

        // Tab bar portal target — rendered at user-configured hook position
        $panel->renderHook(
            $this->getRenderHook(),
            function () use ($panelId): HtmlString|string {
                if (TabbedContainer::$barRenderedFor[$panelId] ?? false) {
                    return '';
                }

                TabbedContainer::$barRenderedFor[$panelId] = true;

                return new HtmlString('<div id="fi-tabbed-bar-portal" wire:ignore></div>');
            },
        );

        // Tab content panels — always rendered at CONTENT_START (inside <main class="fi-main">)
        $panel->renderHook(
            PanelsRenderHook::CONTENT_START,
            function () use ($panelId): \Illuminate\Contracts\View\View|string {
                if (TabbedContainer::$contentRenderedFor[$panelId] ?? false) {
                    return '';
                }

                TabbedContainer::$contentRenderedFor[$panelId] = true;

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

    public function middleClickToClose(bool $condition = true): static
    {
        $this->middleClickToClose = $condition;

        return $this;
    }

    public function getMiddleClickToClose(): bool
    {
        return $this->middleClickToClose;
    }

    public function showTabIcons(bool $condition = true): static
    {
        $this->showTabIcons = $condition;

        return $this;
    }

    public function getShowTabIcons(): bool
    {
        return $this->showTabIcons;
    }

    public function lazyLoad(bool $condition = true): static
    {
        $this->lazyLoad = $condition;

        return $this;
    }

    public function getLazyLoad(): bool
    {
        return $this->lazyLoad || $this->destroyInactive;
    }

    public function destroyInactive(bool $condition = true, int $keepAlive = 1): static
    {
        $this->destroyInactive = $condition;
        $this->keepAlive = max(1, $keepAlive);

        return $this;
    }

    public function getDestroyInactive(): bool
    {
        return $this->destroyInactive;
    }

    public function getKeepAlive(): int
    {
        return $this->keepAlive;
    }

    public function confirmClose(bool $condition = true): static
    {
        $this->confirmClose = $condition;

        return $this;
    }

    public function getConfirmClose(): bool
    {
        return $this->confirmClose;
    }

    public function interceptRedirects(bool $condition = true): static
    {
        $this->interceptRedirects = $condition;

        return $this;
    }

    public function getInterceptRedirects(): bool
    {
        return $this->interceptRedirects;
    }

    public function hasDropdown(
        ?string $icon = 'heroicon-m-squares-2x2',
        ?string $label = null,
        bool $countBadge = true,
        string $color = 'primary',
        bool $outlined = false,
    ): static {
        $this->dropdown = true;
        $this->dropdownIcon = $icon;
        $this->dropdownLabel = $label;
        $this->dropdownCountBadge = $countBadge;
        $this->dropdownColor = $color;
        $this->dropdownOutlined = $outlined;

        return $this;
    }

    public function getDropdown(): bool
    {
        return $this->dropdown;
    }

    public function getDropdownIcon(): ?string
    {
        return $this->dropdownIcon;
    }

    public function getDropdownLabel(): ?string
    {
        return $this->dropdownLabel;
    }

    public function getDropdownCountBadge(): bool
    {
        return $this->dropdownCountBadge;
    }

    public function getDropdownColor(): string
    {
        return $this->dropdownColor;
    }

    public function getDropdownOutlined(): bool
    {
        return $this->dropdownOutlined;
    }

    public function allowReorder(bool $condition = true): static
    {
        $this->allowReorder = $condition;

        return $this;
    }

    public function getAllowReorder(): bool
    {
        return $this->allowReorder;
    }

    public function allowRename(bool $condition = true): static
    {
        $this->allowRename = $condition;

        return $this;
    }

    public function getAllowRename(): bool
    {
        return $this->allowRename;
    }

    public function allowPin(bool $condition = true): static
    {
        $this->allowPin = $condition;

        return $this;
    }

    public function getAllowPin(): bool
    {
        return $this->allowPin;
    }

    public function allowDuplicate(bool $condition = true): static
    {
        $this->allowDuplicate = $condition;

        return $this;
    }

    public function getAllowDuplicate(): bool
    {
        return $this->allowDuplicate;
    }

    public function allowCloseOthers(bool $condition = true): static
    {
        $this->allowCloseOthers = $condition;

        return $this;
    }

    public function getAllowCloseOthers(): bool
    {
        return $this->allowCloseOthers;
    }

    public function allowCloseAll(bool $condition = true): static
    {
        $this->allowCloseAll = $condition;

        return $this;
    }

    public function getAllowCloseAll(): bool
    {
        return $this->allowCloseAll;
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
