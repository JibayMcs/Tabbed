@php
    $plugin = \JibayMcs\Tabbed\TabbedPlugin::get();
    $config = [
        'persistKey' => $plugin->getPersistKey(),
        'defaultPage' => $plugin->getDefaultPage(),
        'middleClickToClose' => $plugin->getMiddleClickToClose(),
        'showTabIcons' => $plugin->getShowTabIcons(),
        'lazyLoad' => $plugin->getLazyLoad(),
        'destroyInactive' => $plugin->getDestroyInactive(),
    ];
@endphp

<div
    x-load
    x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('tabbed', 'jibaymcs/tabbed') }}"
    x-data="tabbedManager({{ \Illuminate\Support\Js::from($config) }})"
    x-cloak
    class="fi-tabbed-container"
>
    {{-- Tab bar — teleported to portal target at user-configured render hook --}}
    <template x-teleport="#fi-tabbed-bar-portal">
        <div x-show="hasTabs" class="fi-tabbed-bar">
            <div class="fi-tabbed-bar-tabs" role="tablist">
                <template x-for="tab in tabs" :key="tab.id">
                    <div
                        class="fi-tabbed-bar-tab"
                        :data-tab-id="tab.id"
                        :class="{
                            'fi-active': isActive(tab.id),
                            'fi-drag-over-before': isDragOver(tab.id, 'before'),
                            'fi-drag-over-after': isDragOver(tab.id, 'after'),
                        }"
                        :style="getTabStyle(tab)"
                        role="tab"
                        :aria-selected="isActive(tab.id)"
                        @click="setActiveTab(tab.id)"
                        @auxclick="onMiddleClick($event, tab.id)"
                        @contextmenu="openContextMenu($event, tab.id)"
                        @dblclick="startRename(tab.id)"
                        draggable="true"
                        @dragstart="onDragStart($event, tab.id)"
                        @dragend="onDragEnd($event)"
                        @dragover="onDragOver($event, tab.id)"
                        @dragleave="onDragLeave($event, tab.id)"
                        @drop="onDrop($event, tab.id)"
                    >
                        {{-- Icon --}}
                        <template x-if="showTabIcons && tabIcons[tab.resource]">
                            <span x-html="tabIcons[tab.resource]"></span>
                        </template>

                        {{-- Label or rename input --}}
                        <template x-if="!isRenaming(tab.id)">
                            <span
                                class="fi-tabbed-bar-tab-label"
                                x-text="getTabLabel(tab)"
                            ></span>
                        </template>

                        <template x-if="isRenaming(tab.id)">
                            <input
                                type="text"
                                class="fi-tabbed-bar-tab-rename-input"
                                x-model="renameValue"
                                :data-rename-input="tab.id"
                                @click.stop
                                @keydown.enter.prevent="confirmRename()"
                                @keydown.escape.prevent="cancelRename()"
                                @blur="confirmRename()"
                            />
                        </template>

                        <button
                            type="button"
                            class="fi-tabbed-bar-tab-close"
                            @click.stop="removeTab(tab.id)"
                            aria-label="{{ __('tabbed::tabbed.close_tab') }}"
                        >
                            <x-filament::icon
                                icon="heroicon-m-x-mark"
                                class="fi-tabbed-bar-tab-close-icon"
                            />
                        </button>
                    </div>
                </template>
            </div>

            {{-- Overflow button — always in layout, visibility toggled --}}
            <div class="fi-tabbed-bar-overflow" :style="hasOverflow ? '' : 'visibility: hidden'">
                <button
                    type="button"
                    class="fi-tabbed-bar-overflow-btn"
                    @click.stop="toggleOverflowMenu($event)"
                    aria-label="{{ __('tabbed::tabbed.all_tabs') }}"
                >
                    <x-filament::icon icon="heroicon-m-ellipsis-horizontal" class="fi-tabbed-bar-overflow-icon" />
                </button>
            </div>
        </div>
    </template>

    {{-- Overflow dropdown — teleported to body to escape topbar clip --}}
    <template x-teleport="body">
        <div
            x-show="showOverflowMenu"
            x-cloak
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="fi-tabbed-overflow-menu"
            :style="`left: ${overflowMenuX}px; top: ${overflowMenuY}px`"
            @click.stop
        >
            <template x-for="tab in overflowTabs" :key="'overflow-' + tab.id">
                <button
                    type="button"
                    class="fi-tabbed-overflow-menu-item"
                    :class="{ 'fi-active': isActive(tab.id) }"
                    @click="setActiveTab(tab.id); closeOverflowMenu()"
                >
                    <span x-show="showTabIcons && tabIcons[tab.resource]" x-html="tabIcons[tab.resource]"></span>
                    <span class="fi-tabbed-overflow-menu-item-label" x-text="getTabLabel(tab)"></span>
                </button>
            </template>
        </div>
    </template>

    {{-- Context menu --}}
    <div
        x-show="showContextMenu"
        x-cloak
        data-context-menu
        class="fi-tabbed-context-menu"
        :style="`left: ${contextMenuX}px; top: ${contextMenuY}px`"
        @click.stop
    >
        <button type="button" class="fi-tabbed-context-menu-item" @click="contextMenuAction('rename')">
            <x-filament::icon icon="heroicon-m-pencil-square" class="fi-tabbed-context-menu-icon" />
            <span>{{ __('tabbed::tabbed.rename') }}</span>
        </button>
        <button type="button" class="fi-tabbed-context-menu-item" @click="contextMenuAction('close')">
            <x-filament::icon icon="heroicon-m-x-mark" class="fi-tabbed-context-menu-icon" />
            <span>{{ __('tabbed::tabbed.close') }}</span>
        </button>
        <div class="fi-tabbed-context-menu-separator"></div>
        <button type="button" class="fi-tabbed-context-menu-item" @click="contextMenuAction('close-others')">
            <x-filament::icon icon="heroicon-m-x-circle" class="fi-tabbed-context-menu-icon" />
            <span>{{ __('tabbed::tabbed.close_others') }}</span>
        </button>
        <button type="button" class="fi-tabbed-context-menu-item fi-tabbed-context-menu-item-danger" @click="contextMenuAction('close-all')">
            <x-filament::icon icon="heroicon-m-trash" class="fi-tabbed-context-menu-icon" />
            <span>{{ __('tabbed::tabbed.close_all') }}</span>
        </button>
    </div>

    {{-- Tab content panels --}}
    @foreach($tabs as $tab)
        @php
            $pageClass = $this->resolvePageClass($tab['resource'] ?? '', $tab['page'] ?? '');
            $isLoaded = in_array($tab['id'], $this->loadedTabIds);
        @endphp

        @if($pageClass && $isLoaded)
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
