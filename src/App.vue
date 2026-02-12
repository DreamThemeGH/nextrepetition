<template>
    <NcContent app-name="flashcards">
        <NcAppNavigation :open.sync="navigationOpen">
            <template #list>
                <NcAppNavigationItem :name="t('flashcards', 'Dashboard')"
                    :to="{ name: 'dashboard' }"
                    :class="{ active: route.name === 'dashboard' }">
                    <template #icon>
                        <IconHome :size="20" />
                    </template>
                </NcAppNavigationItem>

                <NcAppNavigationItem :name="t('flashcards', 'Decks')"
                    :to="{ name: 'decks' }"
                    :class="{ active: route.name === 'decks' }">
                    <template #icon>
                        <IconCards :size="20" />
                    </template>
                </NcAppNavigationItem>

                <NcAppNavigationItem :name="t('flashcards', 'Statistics')"
                    :to="{ name: 'statistics' }"
                    :class="{ active: route.name === 'statistics' }">
                    <template #icon>
                        <IconChart :size="20" />
                    </template>
                </NcAppNavigationItem>

                <NcAppNavigationItem :name="t('flashcards', 'Settings')"
                    :to="{ name: 'settings' }"
                    :class="{ active: route.name === 'settings' }">
                    <template #icon>
                        <IconSettings :size="20" />
                    </template>
                </NcAppNavigationItem>
            </template>
        </NcAppNavigation>

        <NcAppContent>
            <router-view />
        </NcAppContent>
    </NcContent>
</template>

<script setup lang="ts">
import { onMounted, computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { translate as t } from '@nextcloud/l10n'

import NcContent from '@nextcloud/vue/components/NcContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcCounterBubble from '@nextcloud/vue/components/NcCounterBubble'

import IconHome from 'vue-material-design-icons/Home.vue'
import IconCards from 'vue-material-design-icons/Cards.vue'
import IconChart from 'vue-material-design-icons/ChartBar.vue'
import IconSettings from 'vue-material-design-icons/Cogs.vue'

import { useDeckStore } from '@/stores/deck'
import { useSettingsStore } from '@/stores/settings'
import { useAutoSave } from '@/composables/useAutoSave'

const route = useRoute()
const deckStore = useDeckStore()
const settingsStore = useSettingsStore()
const navigationOpen = ref(true)

const dueCount = computed(() => {
    const val = deckStore.totalDue
    if (typeof val !== 'number' || !Number.isFinite(val) || val < 0) return 0
    return val
})

// Auto-hide navigation on mobile when navigating to any section
const isMobile = computed(() => window.innerWidth <= 768)
watch(() => route.name, (newRoute) => {
    // Close navigation on mobile when selecting any menu item
    if (isMobile.value && newRoute) {
        navigationOpen.value = false
    }
})

console.log('[flashcards] v2.0.2 loaded')

useAutoSave()

onMounted(async () => {
    await settingsStore.load()
    await deckStore.loadDecks()
})
</script>

<style lang="scss">
// Global app styles
.flashcards-page {
    padding: 20px 32px;
    max-width: 1200px;
    margin: 0 auto;
}

.flashcards-page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;

    h2 {
        margin: 0;
        font-size: 1.5em;
        font-weight: 700;
    }
}
</style>
