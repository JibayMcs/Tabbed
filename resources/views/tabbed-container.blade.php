@php
    $plugin = \JibayMcs\Tabbed\TabbedPlugin::get();
    $config = [
        'persistKey' => $plugin->getPersistKey(),
        'defaultPage' => $plugin->getDefaultPage(),
        'middleClickToClose' => $plugin->getMiddleClickToClose(),
        'showTabIcons' => $plugin->getShowTabIcons(),
        'lazyLoad' => $plugin->getLazyLoad(),
        'destroyInactive' => $plugin->getDestroyInactive(),
        'keepAlive' => $plugin->getKeepAlive(),
        'dropdown' => $plugin->getDropdown(),
        'confirmClose' => $plugin->getConfirmClose(),
        'interceptRedirects' => $plugin->getInterceptRedirects(),
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
        @if($plugin->getDropdown())
            {{-- Dropdown mode: compact trigger button --}}
            <div x-show="hasTabs" class="fi-tabbed-dropdown-trigger">
                <button
                    type="button"
                    class="fi-tabbed-dropdown-btn fi-tabbed-dropdown-btn-colored @if($plugin->getDropdownOutlined()) fi-tabbed-dropdown-btn-outlined @endif"
                    style="--c-500: var(--{{ $plugin->getDropdownColor() }}-500); --c-600: var(--{{ $plugin->getDropdownColor() }}-600); --c-400: var(--{{ $plugin->getDropdownColor() }}-400); --c-50: var(--{{ $plugin->getDropdownColor() }}-50);"
                    @click.stop="toggleOverflowMenu($event)"
                    aria-label="{{ __('tabbed::tabbed.all_tabs') }}"
                >
                    @if($plugin->getDropdownIcon())
                        <x-filament::icon
                            :icon="$plugin->getDropdownIcon()"
                            class="fi-tabbed-dropdown-btn-icon"
                        />
                    @endif

                    @if($plugin->getDropdownLabel())
                        <span class="fi-tabbed-dropdown-btn-label">{{ $plugin->getDropdownLabel() }}</span>
                    @endif

                    @if($plugin->getDropdownCountBadge())
                        <span
                            class="fi-tabbed-dropdown-badge"
                            x-text="tabCount"
                            x-show="tabCount > 0"
                        ></span>
                    @endif
                </button>
            </div>
        @else
            {{-- Normal mode: full tab bar --}}
            <div x-show="hasTabs" class="fi-tabbed-bar">
                <div class="fi-tabbed-bar-tabs" role="tablist">
                    <template x-for="tab in tabs" :key="tab.id">
                        <div
                            class="fi-tabbed-bar-tab"
                            :data-tab-id="tab.id"
                            :class="{
                                'fi-active': isActive(tab.id),
                                'fi-pinned': tab.pinned,
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
                            @mouseenter="hoverCardEnter(tab.id, $el)"
                            @mouseleave="hoverCardLeave(tab.id)"
                        >
                            {{-- Icon --}}
                            <template x-if="showTabIcons && tabIcons[tab.resource]">
                                <span x-html="tabIcons[tab.resource]"></span>
                            </template>

                            {{-- Pin icon --}}
                            <template x-if="tab.pinned">
                                <span class="fi-tabbed-bar-tab-pin-icon">
                                    <x-filament::icon icon="heroicon-m-map-pin" class="fi-tabbed-bar-tab-pin-svg" />
                                </span>
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

                            {{-- Loading badge --}}
                            <template x-if="isTabLoading(tab.id)">
                                <span class="fi-tabbed-bar-tab-loading">
                                    <x-filament::loading-indicator class="fi-tabbed-bar-tab-loading-icon" />
                                </span>
                            </template>

                            {{-- Dirty indicator --}}
                            <template x-if="isTabDirty(tab.id)">
                                <span class="fi-tabbed-bar-tab-dirty"></span>
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
        @endif
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
            {{-- Search input --}}
            <div x-show="showSearch" class="fi-tabbed-search">
                <x-filament::icon icon="heroicon-m-magnifying-glass" class="fi-tabbed-search-icon" />
                <input
                    type="text"
                    class="fi-tabbed-search-input"
                    x-model="searchQuery"
                    @input="onSearchInput()"
                    @keydown="onSearchKeydown($event)"
                    placeholder="{{ __('tabbed::tabbed.search_tabs') }}"
                />
            </div>

            {{-- No results --}}
            <div x-show="showSearch && searchQuery && filteredOverflowTabs.length === 0" class="fi-tabbed-search-empty">
                {{ __('tabbed::tabbed.no_results') }}
            </div>

            <template x-for="(tab, index) in filteredOverflowTabs" :key="'overflow-' + tab.id">
                <div
                    class="fi-tabbed-overflow-menu-item"
                    :class="{ 'fi-active': isActive(tab.id), 'fi-highlighted': index === searchHighlightIndex }"
                    @click="setActiveTab(tab.id); closeOverflowMenu()"
                    @auxclick="onMiddleClick($event, tab.id)"
                    @mouseenter="hoverCardEnter(tab.id, $el)"
                    @mouseleave="hoverCardLeave(tab.id)"
                >
                    <span x-show="showTabIcons && tabIcons[tab.resource]" x-html="tabIcons[tab.resource]"></span>
                    <template x-if="tab.pinned">
                        <span class="fi-tabbed-bar-tab-pin-icon">
                            <x-filament::icon icon="heroicon-m-map-pin" class="fi-tabbed-bar-tab-pin-svg" />
                        </span>
                    </template>
                    <span class="fi-tabbed-overflow-menu-item-label" x-text="getTabLabel(tab)"></span>
                    <template x-if="isTabLoading(tab.id)">
                        <x-filament::loading-indicator class="fi-tabbed-bar-tab-loading-icon" />
                    </template>
                    <template x-if="isTabDirty(tab.id)">
                        <span class="fi-tabbed-bar-tab-dirty"></span>
                    </template>
                    <button
                        type="button"
                        class="fi-tabbed-overflow-menu-item-close"
                        @click.stop="removeTab(tab.id)"
                        aria-label="{{ __('tabbed::tabbed.close_tab') }}"
                    >
                        <x-filament::icon
                            icon="heroicon-m-x-mark"
                            class="fi-tabbed-overflow-menu-item-close-icon"
                        />
                    </button>
                </div>
            </template>
        </div>
    </template>

    {{-- Hover card — teleported to body for proper positioning --}}
    <template x-teleport="body">
        <template x-if="hoverCardVisible && hoverCardContent">
            <div
                class="fi-tabbed-hover-card"
                :class="'fi-tabbed-hover-card-' + hoverCardPosition"
                :style="`left: ${hoverCardX}px; top: ${hoverCardY}px`"
                @mouseenter="hoverCardContentEnter()"
                @mouseleave="hoverCardContentLeave()"
            >
                <div class="fi-tabbed-hover-card-content" x-html="hoverCardContent"></div>
            </div>
        </template>
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
        <template x-if="!isTabPinned(contextMenuTabId)">
            <button type="button" class="fi-tabbed-context-menu-item" @click="contextMenuAction('pin')">
                <x-filament::icon icon="heroicon-m-map-pin" class="fi-tabbed-context-menu-icon" />
                <span>{{ __('tabbed::tabbed.pin') }}</span>
            </button>
        </template>
        <template x-if="isTabPinned(contextMenuTabId)">
            <button type="button" class="fi-tabbed-context-menu-item" @click="contextMenuAction('unpin')">
                <x-filament::icon icon="heroicon-m-map-pin" class="fi-tabbed-context-menu-icon" />
                <span>{{ __('tabbed::tabbed.unpin') }}</span>
            </button>
        </template>
        <button type="button" class="fi-tabbed-context-menu-item" @click="contextMenuAction('duplicate')">
            <x-filament::icon icon="heroicon-m-document-duplicate" class="fi-tabbed-context-menu-icon" />
            <span>{{ __('tabbed::tabbed.duplicate') }}</span>
        </button>
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

    {{-- Dirty close confirmation modal --}}
    <template x-teleport="body">
        <div
            x-show="dirtyModalVisible"
            x-cloak
            class="fi-tabbed-dirty-modal-backdrop"
            @click.self="cancelDirtyClose()"
        >
            <div
                class="fi-tabbed-dirty-modal"
                x-show="dirtyModalVisible"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
            >
                <div class="fi-tabbed-dirty-modal-icon">
                    <x-filament::icon icon="heroicon-o-exclamation-triangle" class="fi-tabbed-dirty-modal-icon-svg" />
                </div>
                <h3 class="fi-tabbed-dirty-modal-title">{{ __('tabbed::tabbed.unsaved_changes') }}</h3>
                <p class="fi-tabbed-dirty-modal-description">{{ __('tabbed::tabbed.unsaved_changes_description') }}</p>
                <div class="fi-tabbed-dirty-modal-actions">
                    <button type="button" class="fi-tabbed-dirty-modal-btn fi-tabbed-dirty-modal-btn-cancel" @click="cancelDirtyClose()">
                        {{ __('tabbed::tabbed.cancel') }}
                    </button>
                    <button type="button" class="fi-tabbed-dirty-modal-btn fi-tabbed-dirty-modal-btn-confirm" @click="confirmDirtyClose()">
                        {{ __('tabbed::tabbed.close_anyway') }}
                    </button>
                </div>
            </div>
        </div>
    </template>

    {{-- Loading indicator (shown while a tab's Livewire component is loading) --}}
    <template x-for="tab in tabs" :key="'loading-' + tab.id">
        <div
            x-show="isActive(tab.id) && isTabLoading(tab.id)"
            class="fi-tabbed-panel-loading"
        >
            <x-filament::loading-indicator class="fi-tabbed-panel-loading-spinner" />
        </div>
    </template>

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
                wire:ignore
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
