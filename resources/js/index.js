export default function tabbedManager(config = {}) {
    return {
        tabs: [],
        activeTabId: null,
        maxTabs: config.maxTabs ?? 20,
        persistKey: config.persistKey ?? 'tabbed_tabs',
        defaultPage: config.defaultPage ?? 'edit',

        init() {
            this.loadFromStorage()

            window.addEventListener('tabbed:open', (e) => {
                this.addTab(e.detail)
            })

            window.addEventListener('tabbed:close', (e) => {
                this.removeTab(e.detail.id)
            })
        },

        loadFromStorage() {
            try {
                const stored = localStorage.getItem(this.persistKey)
                if (stored) {
                    const data = JSON.parse(stored)
                    this.tabs = data.tabs ?? []
                    this.activeTabId = data.activeTabId ?? null

                    if (this.activeTabId && !this.tabs.find(t => t.id === this.activeTabId)) {
                        this.activeTabId = this.tabs.length > 0 ? this.tabs[0].id : null
                    }
                }
            } catch (e) {
                console.warn('[Tabbed] Failed to load from localStorage:', e)
                this.tabs = []
                this.activeTabId = null
            }
        },

        saveToStorage() {
            try {
                localStorage.setItem(this.persistKey, JSON.stringify({
                    tabs: this.tabs,
                    activeTabId: this.activeTabId,
                }))
            } catch (e) {
                console.warn('[Tabbed] Failed to save to localStorage:', e)
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

        addTab({ resource, page, recordId = null, label = null }) {
            const existing = this.tabs.find(t =>
                t.resource === resource &&
                t.page === page &&
                t.recordId === recordId
            )

            if (existing) {
                this.setActiveTab(existing.id)
                return existing
            }

            if (this.tabs.length >= this.maxTabs) {
                this.$dispatch('tabbed:max-reached', { maxTabs: this.maxTabs })
                return null
            }

            const tab = {
                id: this.generateId(),
                label: null,
                customLabel: label ?? null,
                resource,
                page: page ?? this.defaultPage,
                recordId: recordId ?? null,
                order: this.tabs.length,
            }

            tab.label = this.generateLabel(tab)

            this.tabs.push(tab)
            this.setActiveTab(tab.id)
            this.saveToStorage()

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
            this.$dispatch('tabbed:tab-closed', { tab: removedTab })
        },

        closeOtherTabs(tabId) {
            this.tabs = this.tabs.filter(t => t.id === tabId)
            this.activeTabId = tabId
            this.reindex()
            this.saveToStorage()
        },

        closeAllTabs() {
            const hadTabs = this.tabs.length > 0
            this.tabs = []
            this.activeTabId = null
            this.saveToStorage()

            if (hadTabs) {
                this.$dispatch('tabbed:all-closed')
            }
        },

        setActiveTab(tabId) {
            if (this.tabs.find(t => t.id === tabId)) {
                this.activeTabId = tabId
                this.saveToStorage()
                this.$dispatch('tabbed:tab-activated', { tabId })
            }
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

        moveTab(fromIndex, toIndex) {
            if (fromIndex === toIndex) return
            if (fromIndex < 0 || fromIndex >= this.tabs.length) return
            if (toIndex < 0 || toIndex >= this.tabs.length) return

            const [moved] = this.tabs.splice(fromIndex, 1)
            this.tabs.splice(toIndex, 0, moved)
            this.reindex()
            this.saveToStorage()
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
    }
}
