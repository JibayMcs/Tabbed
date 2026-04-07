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
    x-cloak
    class="fi-tabbed-container"
>
    {{-- Tab bar --}}
    <div x-show="hasTabs" class="fi-tabbed-bar" x-transition:enter>
        <div class="fi-tabbed-bar-inner">
            <div class="fi-tabbed-bar-scroll" x-ref="tabScroll">
                <div class="fi-tabbed-bar-tabs" role="tablist">
                    <template x-for="tab in tabs" :key="tab.id">
                        <div
                            class="fi-tabbed-bar-tab"
                            :class="{ 'fi-active': isActive(tab.id) }"
                            role="tab"
                            :aria-selected="isActive(tab.id)"
                            @click="setActiveTab(tab.id)"
                        >
                            <span
                                class="fi-tabbed-bar-tab-label"
                                x-text="getTabLabel(tab)"
                            ></span>

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

    {{-- Tab content panels --}}
    @foreach($tabs as $tab)
        @php
            $pageClass = $this->resolvePageClass($tab['resource'] ?? '', $tab['page'] ?? '');
        @endphp

        @if($pageClass)
            <div
                x-show="isActive('{{ $tab['id'] }}')"
                wire:key="tab-panel-{{ $tab['id'] }}"
                class="fi-tabbed-panel"
            >
                @if(in_array($tab['page'] ?? '', ['edit', 'view']))
                    @livewire($pageClass, ['record' => $tab['recordId']], key('tabbed-page-' . $tab['id']))
                @else
                    @livewire($pageClass, [], key('tabbed-page-' . $tab['id']))
                @endif
            </div>
        @endif
    @endforeach
</div>
