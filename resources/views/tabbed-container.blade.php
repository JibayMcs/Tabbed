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
    {{-- Tab bar — wire:ignore prevents Livewire from recreating on re-render --}}
    <div wire:ignore>
    <div x-show="hasTabs" class="fi-tabbed-bar">
        <div class="fi-tabbed-bar-tabs" role="tablist">
            <template x-for="tab in tabs" :key="tab.id">
                <div
                    class="fi-tabbed-bar-tab"
                    :class="{
                        'fi-active': isActive(tab.id),
                        'fi-drag-over-before': isDragOver(tab.id, 'before'),
                        'fi-drag-over-after': isDragOver(tab.id, 'after'),
                    }"
                    role="tab"
                    :aria-selected="isActive(tab.id)"
                    @click="setActiveTab(tab.id)"
                    @contextmenu="openContextMenu($event, tab.id)"
                    @dblclick="startRename(tab.id)"
                    draggable="true"
                    @dragstart="onDragStart($event, tab.id)"
                    @dragend="onDragEnd($event)"
                    @dragover="onDragOver($event, tab.id)"
                    @dragleave="onDragLeave($event, tab.id)"
                    @drop="onDrop($event, tab.id)"
                >
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
    </div>
    </div>

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
