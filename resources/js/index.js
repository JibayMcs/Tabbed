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
        keepAlive: config.keepAlive ?? 1,
        dropdownMode: config.dropdown ?? false,
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

        // Loading state
        loadingTabIds: [],

        // Dirty state
        dirtyTabIds: [],
        confirmClose: config.confirmClose ?? false,

        // Dirty modal state
        dirtyModalVisible: false,
        dirtyModalTabId: null,
        dirtyModalMode: 'single',

        // Hover card state
        hoverCardTabId: null,
        hoverCardVisible: false,
        hoverCardX: 0,
        hoverCardY: 0,
        _hoverCardTimeout: null,
        _hoverCardLeaveTimeout: null,

        init() {
            this.loadFromStorage()

            // Sync initial tabs to Livewire
            this.wireSyncTabs()

            // Toggle page content visibility on state changes
            this.$watch('activeTabId', () => this.togglePageContent())

            // Observe tab bar DOM to detect overflow (not needed in dropdown mode)
            if (this.dropdownMode) {
                this.recalcOverflow()
            } else {
                this.setupOverflowObserver()
            }

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

            // Detect save/create calls to clear dirty state
            Livewire.hook('commit', ({ component, commit, succeed }) => {
                succeed(() => {
                    const hasSaveCall = commit.calls?.some(call =>
                        ['save', 'create'].includes(call.method)
                    )
                    if (!hasSaveCall) return

                    const el = document.querySelector(`[wire\\:id="${component.id}"]`)
                    const panel = el?.closest('.fi-tabbed-panel')
                    if (!panel) return

                    const tabId = panel.getAttribute('wire:key')?.replace('tab-panel-', '')
                    if (tabId) {
                        this.clearTabDirty(tabId)
                    }
                })
            })

            // Re-apply page content visibility after Livewire morphs
            // (e.g. table refresh re-creates elements without display:none)
            Livewire.hook('morph.updated', () => {
                this.togglePageContent()
            })

            // Watch for new panels (lazy load, destroyInactive) to attach dirty listeners
            let dirtyRafId = null
            new MutationObserver(() => {
                if (dirtyRafId) cancelAnimationFrame(dirtyRafId)
                dirtyRafId = requestAnimationFrame(() => {
                    dirtyRafId = null
                    this.setupDirtyListeners()
                })
            }).observe(this.$root, { childList: true, subtree: true })

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

                // Attach dirty detection listeners on new panels
                this.$nextTick(() => this.setupDirtyListeners())
            }
        },

        ensureTabLoaded(tabId) {
            if (!this.lazyLoad && !this.destroyInactive) return

            if (this.destroyInactive) {
                // LRU: move tabId to the end (most recent), trim to keepAlive
                const lru = this.loadedTabIds.filter(id => id !== tabId)
                lru.push(tabId)

                // Already loaded and within keepAlive — no server call needed
                if (this.loadedTabIds.includes(tabId) && lru.length <= this.keepAlive) return

                // Evict oldest entries beyond keepAlive
                const kept = lru.slice(-this.keepAlive)

                this.loadingTabIds = [...this.loadingTabIds.filter(id => id !== tabId), tabId]
                this.loadedTabIds = kept

                Promise.resolve(this.$wire.loadTabs(kept)).then(() => {
                    this.loadingTabIds = this.loadingTabIds.filter(id => id !== tabId)
                })
            } else {
                // Lazy load: load once, keep forever
                if (this.loadedTabIds.includes(tabId)) return

                this.loadingTabIds = [...this.loadingTabIds.filter(id => id !== tabId), tabId]
                this.loadedTabIds.push(tabId)

                Promise.resolve(this.$wire.loadTab(tabId)).then(() => {
                    this.loadingTabIds = this.loadingTabIds.filter(id => id !== tabId)
                })
            }
        },

        isTabLoading(tabId) {
            return this.loadingTabIds.includes(tabId)
        },

        // --- Dirty State ---

        isTabDirty(tabId) {
            return this.dirtyTabIds.includes(tabId)
        },

        markTabDirty(tabId) {
            if (!this.dirtyTabIds.includes(tabId)) {
                this.dirtyTabIds.push(tabId)
            }
        },

        clearTabDirty(tabId) {
            this.dirtyTabIds = this.dirtyTabIds.filter(id => id !== tabId)
        },

        setupDirtyListeners() {
            const panels = document.querySelectorAll('.fi-tabbed-panel')
            panels.forEach(panel => {
                if (panel._dirtyListenerAttached) return
                panel._dirtyListenerAttached = true

                const tabId = panel.getAttribute('wire:key')?.replace('tab-panel-', '')
                if (!tabId) return

                // Delay to avoid catching initial Livewire hydration events
                setTimeout(() => {
                    const handler = (e) => {
                        if (!e.isTrusted) return
                        this.markTabDirty(tabId)
                    }

                    panel.addEventListener('input', handler, true)
                    panel.addEventListener('change', handler, true)
                }, 500)
            })
        },

        togglePageContent() {
            const shouldHide = this.hasTabs && this.activeTabId !== null
            let portalInSibling = false

            let sibling = this.$root.nextElementSibling
            while (sibling) {
                if (sibling.querySelector('#fi-tabbed-bar-portal')) {
                    portalInSibling = true
                    if (shouldHide) {
                        // Hide children individually, keep the portal visible
                        Array.from(sibling.children).forEach(child => {
                            child.style.display = child.id === 'fi-tabbed-bar-portal' ? '' : 'none'
                        })
                        sibling.style.display = ''
                    } else {
                        Array.from(sibling.children).forEach(child => {
                            child.style.display = ''
                        })
                        sibling.style.display = ''
                    }
                } else {
                    sibling.style.display = shouldHide ? 'none' : ''
                }
                sibling = sibling.nextElementSibling
            }

            // When the portal is in a later sibling (PAGE_START), the tab panels
            // render above the tab bar in DOM order. Use flex + order to flip them.
            if (portalInSibling) {
                const parent = this.$root.parentElement
                if (parent) {
                    if (shouldHide) {
                        parent.style.display = 'flex'
                        parent.style.flexDirection = 'column'
                        this.$root.style.order = '2'
                    } else {
                        parent.style.display = ''
                        parent.style.flexDirection = ''
                        this.$root.style.order = ''
                    }
                }
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

        addTab({ resource, page, recordId = null, label = null, background = false, tabColor = null, tabBackground = null, tabTextColor = null, hoverCard = null, confirmOnClose = false }) {
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
                hoverCard: hoverCard ?? null,
                confirmOnClose: confirmOnClose,
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
            const tab = this.tabs.find(t => t.id === tabId)
            if (!tab) return

            const needsConfirm = this.isTabDirty(tabId) && (this.confirmClose || tab.confirmOnClose)
            if (needsConfirm) {
                this.dirtyModalTabId = tabId
                this.dirtyModalMode = 'single'
                this.dirtyModalVisible = true
                return
            }

            this._doRemoveTab(tabId)
        },

        _doRemoveTab(tabId) {
            const index = this.tabs.findIndex(t => t.id === tabId)
            if (index === -1) return

            this.dismissHoverCard()
            this.closeOverflowMenu()

            const wasActive = this.activeTabId === tabId
            const removedTab = this.tabs[index]

            this.tabs.splice(index, 1)
            this.clearTabDirty(tabId)
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
            const dirtyOthers = this.tabs.filter(t =>
                t.id !== tabId && this.isTabDirty(t.id) && (this.confirmClose || t.confirmOnClose)
            )

            if (dirtyOthers.length > 0) {
                this.dirtyModalTabId = tabId
                this.dirtyModalMode = 'close-others'
                this.dirtyModalVisible = true
                return
            }

            this._doCloseOtherTabs(tabId)
        },

        _doCloseOtherTabs(tabId) {
            this.tabs = this.tabs.filter(t => t.id === tabId)
            this.dirtyTabIds = this.dirtyTabIds.filter(id => id === tabId)
            this.activeTabId = tabId
            this.reindex()
            this.saveToStorage()
            this.wireSyncTabs()
            this.$nextTick(() => this.recalcOverflow())
        },

        closeAllTabs() {
            const dirtyTabs = this.tabs.filter(t =>
                this.isTabDirty(t.id) && (this.confirmClose || t.confirmOnClose)
            )

            if (dirtyTabs.length > 0) {
                this.dirtyModalMode = 'close-all'
                this.dirtyModalVisible = true
                return
            }

            this._doCloseAllTabs()
        },

        _doCloseAllTabs() {
            const hadTabs = this.tabs.length > 0
            this.tabs = []
            this.dirtyTabIds = []
            this.activeTabId = null
            this.saveToStorage()
            this.wireSyncTabs()
            this.togglePageContent()
            this.$nextTick(() => this.recalcOverflow())

            if (hadTabs) {
                this.$dispatch('tabbed:all-closed')
            }
        },

        confirmDirtyClose() {
            switch (this.dirtyModalMode) {
                case 'single':
                    if (this.dirtyModalTabId) {
                        this._doRemoveTab(this.dirtyModalTabId)
                    }
                    break
                case 'close-others':
                    if (this.dirtyModalTabId) {
                        this._doCloseOtherTabs(this.dirtyModalTabId)
                    }
                    break
                case 'close-all':
                    this._doCloseAllTabs()
                    break
            }
            this.dirtyModalVisible = false
            this.dirtyModalTabId = null
        },

        cancelDirtyClose() {
            this.dirtyModalVisible = false
            this.dirtyModalTabId = null
        },

        setActiveTab(tabId) {
            if (!this.tabs.find(t => t.id === tabId)) return

            this.dismissHoverCard()

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
            if (this.dropdownMode) {
                this.hasOverflow = this.tabs.length > 0
                this.overflowTabs = [...this.tabs]
                return
            }

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
            this.dismissHoverCard()

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

        // --- Hover Card ---

        hoverCardEnter(tabId, el) {
            const tab = this.tabs.find(t => t.id === tabId)
            if (!tab?.hoverCard) return

            clearTimeout(this._hoverCardLeaveTimeout)

            if (this.hoverCardVisible && this.hoverCardTabId === tabId) return

            clearTimeout(this._hoverCardTimeout)
            this._hoverCardTimeout = setTimeout(() => {
                this.hoverCardTabId = tabId
                this.hoverCardVisible = true
                this.$nextTick(() => this.positionHoverCard(el, tab.hoverCard.position))
            }, tab.hoverCard.delay ?? 600)
        },

        hoverCardLeave(tabId) {
            const tab = this.tabs.find(t => t.id === tabId)
            if (!tab?.hoverCard) return

            clearTimeout(this._hoverCardTimeout)

            if (!this.hoverCardVisible) return

            clearTimeout(this._hoverCardLeaveTimeout)
            this._hoverCardLeaveTimeout = setTimeout(() => {
                this.hoverCardVisible = false
                this.hoverCardTabId = null
            }, tab.hoverCard.leaveDelay ?? 500)
        },

        hoverCardContentEnter() {
            clearTimeout(this._hoverCardLeaveTimeout)
        },

        hoverCardContentLeave() {
            clearTimeout(this._hoverCardLeaveTimeout)
            this._hoverCardLeaveTimeout = setTimeout(() => {
                this.hoverCardVisible = false
                this.hoverCardTabId = null
            }, 300)
        },

        dismissHoverCard() {
            clearTimeout(this._hoverCardTimeout)
            clearTimeout(this._hoverCardLeaveTimeout)
            this.hoverCardVisible = false
            this.hoverCardTabId = null
        },

        get hoverCardTab() {
            return this.tabs.find(t => t.id === this.hoverCardTabId) ?? null
        },

        get hoverCardContent() {
            return this.hoverCardTab?.hoverCard?.content ?? ''
        },

        get hoverCardPosition() {
            return this.hoverCardTab?.hoverCard?.position ?? 'bottom'
        },

        positionHoverCard(triggerEl, position) {
            const card = document.querySelector('.fi-tabbed-hover-card')
            if (!card || !triggerEl) return

            const triggerRect = triggerEl.getBoundingClientRect()
            const cardRect = card.getBoundingClientRect()
            const gap = 8
            const vw = window.innerWidth
            const vh = window.innerHeight

            let x = 0
            let y = 0

            switch (position) {
                case 'top':
                    x = triggerRect.left + (triggerRect.width - cardRect.width) / 2
                    y = triggerRect.top - cardRect.height - gap
                    break
                case 'top-start':
                    x = triggerRect.left
                    y = triggerRect.top - cardRect.height - gap
                    break
                case 'top-end':
                    x = triggerRect.right - cardRect.width
                    y = triggerRect.top - cardRect.height - gap
                    break
                case 'bottom':
                    x = triggerRect.left + (triggerRect.width - cardRect.width) / 2
                    y = triggerRect.bottom + gap
                    break
                case 'bottom-start':
                    x = triggerRect.left
                    y = triggerRect.bottom + gap
                    break
                case 'bottom-end':
                    x = triggerRect.right - cardRect.width
                    y = triggerRect.bottom + gap
                    break
                case 'left':
                    x = triggerRect.left - cardRect.width - gap
                    y = triggerRect.top + (triggerRect.height - cardRect.height) / 2
                    break
                case 'right':
                    x = triggerRect.right + gap
                    y = triggerRect.top + (triggerRect.height - cardRect.height) / 2
                    break
            }

            // Clamp to viewport
            if (x < 8) x = 8
            if (x + cardRect.width > vw - 8) x = vw - cardRect.width - 8
            if (y < 8) y = 8
            if (y + cardRect.height > vh - 8) y = vh - cardRect.height - 8

            this.hoverCardX = x
            this.hoverCardY = y
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

            this.dismissHoverCard()

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
