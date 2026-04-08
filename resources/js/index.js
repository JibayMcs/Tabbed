export default function tabbedManager(config = {}) {
    return {
        tabs: [],
        activeTabId: null,
        persistKey: config.persistKey ?? 'tabbed_tabs',
        defaultPage: config.defaultPage ?? 'edit',
        middleClickToClose: config.middleClickToClose ?? false,
        showTabIcons: config.showTabIcons ?? true,
        lazyLoad: config.lazyLoad ?? false,
        destroyInactive: config.destroyInactive ?? false,
        tabIcons: {},
        loadedTabIds: [],

        // Drag & drop state
        dragTabId: null,
        dragOverTabId: null,
        dragPosition: null, // 'before' | 'after'

        // Rename state
        renamingTabId: null,
        renameValue: '',

        // Context menu state
        contextMenuTabId: null,
        contextMenuX: 0,
        contextMenuY: 0,

        // Overflow menu state
        showOverflowMenu: false,
        overflowMenuX: 0,
        overflowMenuY: 0,
        hasOverflow: false,
        overflowTabs: [],

        init() {
            this.loadFromStorage()

            // Sync initial tabs to Livewire
            this.wireSyncTabs()

            // Toggle page content visibility on state changes
            this.$watch('activeTabId', () => this.togglePageContent())

            // Observe tab bar DOM to detect overflow
            this.setupOverflowObserver()

            // Livewire dispatch (server-side: row click, action via Livewire)
            // Livewire.on passes named params as a flat object { resource, page, ... }
            Livewire.on('tabbed:open', (data) => {
                this.addTab(data)
            })

            Livewire.on('tabbed:close', ({ id }) => {
                this.removeTab(id)
            })

            // CustomEvent dispatch (client-side: alpineClickHandler, JS API)
            // CustomEvent wraps data in event.detail
            window.addEventListener('tabbed:open', (e) => {
                this.addTab(e.detail)
            })

            window.addEventListener('tabbed:close', (e) => {
                this.removeTab(e.detail.id)
            })

            // Close menus on click anywhere
            document.addEventListener('click', () => {
                this.closeContextMenu()
                this.closeOverflowMenu()
            })

            // Close menus on Escape
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    this.closeContextMenu()
                    this.closeOverflowMenu()
                    this.cancelRename()
                }
            })

        },

        loadFromStorage() {
            try {
                const stored = localStorage.getItem(this.persistKey)
                if (stored) {
                    const data = JSON.parse(stored)
                    this.tabs = data.tabs ?? []
                }
            } catch (e) {
                console.warn('[Tabbed] Failed to load from localStorage:', e)
                this.tabs = []
            }

            // activeTabId is ephemeral - never restored from storage
            // On page load, no tab is active = normal page content is visible
            this.activeTabId = null
        },

        saveToStorage() {
            try {
                localStorage.setItem(this.persistKey, JSON.stringify({
                    tabs: this.tabs,
                }))
            } catch (e) {
                console.warn('[Tabbed] Failed to save to localStorage:', e)
            }
        },

        async wireSyncTabs() {
            if (this.$wire && this.tabs.length > 0) {
                const icons = await this.$wire.syncTabs(this.tabs)
                if (icons && this.showTabIcons) {
                    this.tabIcons = { ...this.tabIcons, ...icons }
                }

                // After sync, ensure active tab's component is loaded (lazy mode)
                if (this.activeTabId) {
                    this.ensureTabLoaded(this.activeTabId)
                }
            }
        },

        ensureTabLoaded(tabId) {
            if (!this.lazyLoad && !this.destroyInactive) return

            if (this.destroyInactive) {
                this.loadedTabIds = [tabId]
                this.$wire.loadTab(tabId)
            } else if (!this.loadedTabIds.includes(tabId)) {
                this.loadedTabIds.push(tabId)
                this.$wire.loadTab(tabId)
            }
        },

        togglePageContent() {
            const shouldHide = this.hasTabs && this.activeTabId !== null

            // Hide all sibling elements after the container
            let sibling = this.$root.nextElementSibling
            while (sibling) {
                sibling.style.display = shouldHide ? 'none' : ''
                sibling = sibling.nextElementSibling
            }
        },

        generateId() {
            return crypto.randomUUID()
        },

        generateLabel(tab) {
            const resourceName = tab.resource.split('\\').pop().replace('Resource', '')

            switch (tab.page) {
                case 'index':
                    return resourceName
                case 'create':
                    return `${resourceName} (+)`
                case 'edit':
                    return `${resourceName} #${tab.recordId}`
                case 'view':
                    return `${resourceName} #${tab.recordId}`
                default:
                    return resourceName
            }
        },

        addTab({ resource, page, recordId = null, label = null, background = false, tabColor = null, tabBackground = null, tabTextColor = null }) {
            const existing = this.tabs.find(t =>
                t.resource === resource &&
                t.page === page &&
                t.recordId === recordId
            )

            if (existing) {
                if (!background) {
                    this.setActiveTab(existing.id)
                }
                return existing
            }

            const tab = {
                id: this.generateId(),
                label: null,
                customLabel: label ?? null,
                resource,
                page: page ?? this.defaultPage,
                recordId: recordId ?? null,
                order: this.tabs.length,
                tabColor: tabColor ?? null,
                tabBackground: tabBackground ?? null,
                tabTextColor: tabTextColor ?? null,
            }

            tab.label = this.generateLabel(tab)

            this.tabs.push(tab)

            if (!background) {
                this.activeTabId = tab.id
                this.togglePageContent()
            }

            this.saveToStorage()
            this.wireSyncTabs()
            this.$nextTick(() => this.recalcOverflow())

            this.$dispatch('tabbed:tab-opened', { tab })

            return tab
        },

        removeTab(tabId) {
            const index = this.tabs.findIndex(t => t.id === tabId)
            if (index === -1) return

            const wasActive = this.activeTabId === tabId
            const removedTab = this.tabs[index]

            this.tabs.splice(index, 1)
            this.reindex()

            if (wasActive) {
                if (this.tabs.length > 0) {
                    const newIndex = Math.min(index, this.tabs.length - 1)
                    this.activeTabId = this.tabs[newIndex].id
                } else {
                    this.activeTabId = null
                }
            }

            this.saveToStorage()
            this.wireSyncTabs()
            this.togglePageContent()
            this.$nextTick(() => this.recalcOverflow())

            this.$dispatch('tabbed:tab-closed', { tab: removedTab })
        },

        closeOtherTabs(tabId) {
            this.tabs = this.tabs.filter(t => t.id === tabId)
            this.activeTabId = tabId
            this.reindex()
            this.saveToStorage()
            this.wireSyncTabs()
            this.$nextTick(() => this.recalcOverflow())
        },

        closeAllTabs() {
            const hadTabs = this.tabs.length > 0
            this.tabs = []
            this.activeTabId = null
            this.saveToStorage()
            this.wireSyncTabs()
            this.togglePageContent()
            this.$nextTick(() => this.recalcOverflow())

            if (hadTabs) {
                this.$dispatch('tabbed:all-closed')
            }
        },

        setActiveTab(tabId) {
            if (!this.tabs.find(t => t.id === tabId)) return

            if (this.activeTabId === tabId) {
                // Re-clicking active tab deactivates it = show page content
                this.activeTabId = null
                this.$dispatch('tabbed:tab-deactivated', { tabId })
                return
            }

            this.activeTabId = tabId
            this.ensureTabLoaded(tabId)
            this.$dispatch('tabbed:tab-activated', { tabId })
        },

        renameTab(tabId, newLabel) {
            const tab = this.tabs.find(t => t.id === tabId)
            if (!tab) return

            tab.customLabel = newLabel || null

            if (!tab.customLabel) {
                tab.label = this.generateLabel(tab)
            }

            this.saveToStorage()
        },

        getTabLabel(tab) {
            return tab.customLabel ?? tab.label
        },

        getTabStyle(tab) {
            const parts = []
            if (tab.tabBackground) parts.push(`background-color: ${tab.tabBackground}`)
            if (tab.tabTextColor) parts.push(`color: ${tab.tabTextColor}`)
            if (tab.tabColor) parts.push(`border-left: 3px solid ${tab.tabColor}`)
            return parts.join('; ')
        },

        moveTab(fromIndex, toIndex) {
            if (fromIndex === toIndex) return
            if (fromIndex < 0 || fromIndex >= this.tabs.length) return
            if (toIndex < 0 || toIndex >= this.tabs.length) return

            const [moved] = this.tabs.splice(fromIndex, 1)
            this.tabs.splice(toIndex, 0, moved)
            this.reindex()
            this.saveToStorage()
            this.$nextTick(() => this.recalcOverflow())
        },

        reindex() {
            this.tabs.forEach((tab, i) => tab.order = i)
        },

        get activeTab() {
            return this.tabs.find(t => t.id === this.activeTabId) ?? null
        },

        get hasTabs() {
            return this.tabs.length > 0
        },

        get tabCount() {
            return this.tabs.length
        },

        isActive(tabId) {
            return this.activeTabId === tabId
        },

        // --- Overflow Menu ---

        setupOverflowObserver() {
            const trySetup = () => {
                const container = document.querySelector('.fi-tabbed-bar-tabs')
                if (!container) {
                    setTimeout(trySetup, 100)
                    return
                }

                this._overflowContainer = container

                let rafId = null
                const scheduleUpdate = () => {
                    if (rafId) cancelAnimationFrame(rafId)
                    rafId = requestAnimationFrame(() => {
                        rafId = null
                        this.recalcOverflow()
                    })
                }

                new ResizeObserver(scheduleUpdate).observe(container)
                new MutationObserver(scheduleUpdate)
                    .observe(container, { childList: true, subtree: true })

                scheduleUpdate()
            }

            setTimeout(trySetup, 100)
        },

        recalcOverflow() {
            const container = this._overflowContainer
            if (!container) return

            const tabEls = container.querySelectorAll('.fi-tabbed-bar-tab')
            const overflowEl = container.parentElement?.querySelector('.fi-tabbed-bar-overflow')

            // Show all tabs to measure accurately
            tabEls.forEach(el => el.classList.remove('fi-tabbed-overflow-hidden'))

            // The button always takes layout space (visibility:hidden).
            // containerRight is already reduced by the button's width.
            // Calculate fullRight = the true available width without the button.
            const containerRight = container.getBoundingClientRect().right
            const buttonWidth = overflowEl ? overflowEl.getBoundingClientRect().width : 0
            const fullRight = containerRight + buttonWidth

            // Do tabs overflow the FULL available width (without button)?
            let anyOverflow = false
            for (let i = 0; i < tabEls.length; i++) {
                if (tabEls[i].getBoundingClientRect().right > fullRight) {
                    anyOverflow = true
                    break
                }
            }

            if (!anyOverflow) {
                this.hasOverflow = false
                this.overflowTabs = []
                return
            }

            // True overflow — find cut point WITH button space (containerRight)
            this.hasOverflow = true

            let cutIndex = -1
            for (let i = 0; i < tabEls.length; i++) {
                if (tabEls[i].getBoundingClientRect().right > containerRight) {
                    cutIndex = i
                    break
                }
            }

            if (cutIndex === -1) cutIndex = tabEls.length - 1

            for (let i = cutIndex; i < tabEls.length; i++) {
                tabEls[i].classList.add('fi-tabbed-overflow-hidden')
            }

            this.overflowTabs = this.tabs.slice(cutIndex)
        },

        toggleOverflowMenu(e) {
            if (this.showOverflowMenu) {
                this.closeOverflowMenu()
                return
            }

            if (this.overflowTabs.length === 0) return

            const btn = e.currentTarget
            const rect = btn.getBoundingClientRect()

            this.overflowMenuY = rect.bottom + 4
            this.showOverflowMenu = true

            // Position so right edge of menu aligns with right edge of button
            this.$nextTick(() => {
                const menu = document.querySelector('.fi-tabbed-overflow-menu')
                if (!menu) return

                const menuRect = menu.getBoundingClientRect()
                const viewportW = window.innerWidth
                const viewportH = window.innerHeight

                this.overflowMenuX = rect.right - menuRect.width

                if (this.overflowMenuX < 8) {
                    this.overflowMenuX = 8
                }
                if (rect.right > viewportW) {
                    this.overflowMenuX = viewportW - menuRect.width - 8
                }
                if (menuRect.bottom > viewportH) {
                    this.overflowMenuY = rect.top - menuRect.height - 4
                }
            })
        },

        closeOverflowMenu() {
            this.showOverflowMenu = false
        },

        // --- Middle Click to Close ---

        onMiddleClick(e, tabId) {
            if (!this.middleClickToClose || e.button !== 1) return
            e.preventDefault()
            this.removeTab(tabId)
        },

        // --- Drag & Drop ---

        onDragStart(e, tabId) {
            this.dragTabId = tabId
            e.dataTransfer.effectAllowed = 'move'
            e.dataTransfer.setData('text/plain', tabId)

            // Make the drag image slightly transparent
            if (e.target) {
                e.target.style.opacity = '0.5'
            }
        },

        onDragEnd(e) {
            if (e.target) {
                e.target.style.opacity = ''
            }
            this.dragTabId = null
            this.dragOverTabId = null
            this.dragPosition = null
        },

        onDragOver(e, tabId) {
            if (!this.dragTabId || this.dragTabId === tabId) return

            e.preventDefault()
            e.dataTransfer.dropEffect = 'move'

            const rect = e.currentTarget.getBoundingClientRect()
            const midX = rect.left + rect.width / 2

            this.dragOverTabId = tabId
            this.dragPosition = e.clientX < midX ? 'before' : 'after'
        },

        onDragLeave(e, tabId) {
            if (this.dragOverTabId === tabId) {
                this.dragOverTabId = null
                this.dragPosition = null
            }
        },

        onDrop(e, tabId) {
            e.preventDefault()

            if (!this.dragTabId || this.dragTabId === tabId) return

            const fromIndex = this.tabs.findIndex(t => t.id === this.dragTabId)
            let toIndex = this.tabs.findIndex(t => t.id === tabId)

            if (fromIndex === -1 || toIndex === -1) return

            // Adjust target index based on drop position
            if (this.dragPosition === 'after') {
                toIndex = fromIndex < toIndex ? toIndex : toIndex + 1
            } else {
                toIndex = fromIndex < toIndex ? toIndex - 1 : toIndex
            }

            this.moveTab(fromIndex, toIndex)

            this.dragTabId = null
            this.dragOverTabId = null
            this.dragPosition = null
        },

        isDragOver(tabId, position) {
            return this.dragOverTabId === tabId && this.dragPosition === position
        },

        // --- Inline Rename ---

        startRename(tabId) {
            const tab = this.tabs.find(t => t.id === tabId)
            if (!tab) return

            this.renamingTabId = tabId
            this.renameValue = tab.customLabel ?? tab.label

            // Focus the input after Alpine renders it
            this.$nextTick(() => {
                const input = this.$root.querySelector(`[data-rename-input="${tabId}"]`)
                if (input) {
                    input.focus()
                    input.select()
                }
            })
        },

        confirmRename() {
            if (!this.renamingTabId) return

            const trimmed = this.renameValue.trim()
            this.renameTab(this.renamingTabId, trimmed || null)

            this.renamingTabId = null
            this.renameValue = ''
        },

        cancelRename() {
            this.renamingTabId = null
            this.renameValue = ''
        },

        isRenaming(tabId) {
            return this.renamingTabId === tabId
        },

        // --- Context Menu ---

        openContextMenu(e, tabId) {
            e.preventDefault()
            e.stopPropagation()

            this.contextMenuTabId = tabId
            this.contextMenuX = e.clientX
            this.contextMenuY = e.clientY

            // Adjust position if menu would overflow viewport
            this.$nextTick(() => {
                const menu = this.$root.querySelector('[data-context-menu]')
                if (!menu) return

                const rect = menu.getBoundingClientRect()
                const viewportW = window.innerWidth
                const viewportH = window.innerHeight

                if (rect.right > viewportW) {
                    this.contextMenuX = viewportW - rect.width - 8
                }
                if (rect.bottom > viewportH) {
                    this.contextMenuY = viewportH - rect.height - 8
                }
            })
        },

        closeContextMenu() {
            this.contextMenuTabId = null
        },

        get showContextMenu() {
            return this.contextMenuTabId !== null
        },

        contextMenuAction(action) {
            const tabId = this.contextMenuTabId
            this.closeContextMenu()

            if (!tabId) return

            switch (action) {
                case 'rename':
                    this.startRename(tabId)
                    break
                case 'close':
                    this.removeTab(tabId)
                    break
                case 'close-others':
                    this.closeOtherTabs(tabId)
                    break
                case 'close-all':
                    this.closeAllTabs()
                    break
            }
        },
    }
}
