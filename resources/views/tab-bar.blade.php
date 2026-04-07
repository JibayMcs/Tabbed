@php
    $config = [
        'maxTabs' => config('tabbed.max_tabs', 20),
        'persistKey' => config('tabbed.persist_key', 'tabbed_tabs'),
        'defaultPage' => config('tabbed.default_page', 'edit'),
    ];
@endphp

<div
    x-load
    x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('tabbed', 'jibaymcs/tabbed') }}"
    x-data="tabbedManager({{ \Illuminate\Support\Js::from($config) }})"
    x-show="hasTabs"
    x-cloak
    class="fi-tabbed-bar"
>
    <div class="fi-tabbed-bar-inner">
        {{-- Tabs list --}}
        <div
            class="fi-tabbed-bar-scroll"
            x-ref="tabScroll"
        >
            <div class="fi-tabbed-bar-tabs" role="tablist">
                <template x-for="tab in tabs" :key="tab.id">
                    <div
                        class="fi-tabbed-bar-tab"
                        :class="{ 'fi-active': isActive(tab.id) }"
                        role="tab"
                        :aria-selected="isActive(tab.id)"
                        @click="setActiveTab(tab.id)"
                    >
                        {{-- Tab label --}}
                        <span
                            class="fi-tabbed-bar-tab-label"
                            x-text="getTabLabel(tab)"
                        ></span>

                        {{-- Close button --}}
                        <button
                            type="button"
                            class="fi-tabbed-bar-tab-close"
                            @click.stop="removeTab(tab.id)"
                            aria-label="Close tab"
                        >
                            <x-filament::icon
                                icon="heroicon-m-x-mark"
                                class="fi-tabbed-bar-tab-close-icon"
                            />
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>
