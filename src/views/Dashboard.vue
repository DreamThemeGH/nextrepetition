<template>
    <div class="flashcards-page">
        <div class="flashcards-page-header">
            <h2>{{ t('flashcards', 'Dashboard') }}</h2>
        </div>

        <div v-if="loading" class="dashboard-loading">
            <NcLoadingIcon :size="44" />
        </div>

        <div v-else class="dashboard-content">
            <!-- Global summary cards -->
            <div class="summary-cards">
                <div class="summary-card summary-due"
                    role="button"
                    tabindex="0"
                    :aria-label="t('flashcards', 'Cards due today') + ': ' + (stats?.totalDue ?? 0)"
                    @click="goToDecks"
                    @keydown.enter="goToDecks"
                    @keydown.space.prevent="goToDecks">
                    <div class="summary-icon" aria-hidden="true">
                        <IconCards :size="32" />
                    </div>
                    <div class="summary-number">{{ stats?.totalDue ?? 0 }}</div>
                    <div class="summary-label">{{ t('flashcards', 'Cards due today') }}</div>
                </div>
                <div class="summary-card summary-new"
                    role="status"
                    :aria-label="t('flashcards', 'New cards') + ': ' + (stats?.totalNew ?? 0)">
                    <div class="summary-icon" aria-hidden="true">
                        <IconStar :size="32" />
                    </div>
                    <div class="summary-number">{{ stats?.totalNew ?? 0 }}</div>
                    <div class="summary-label">{{ t('flashcards', 'New cards') }}</div>
                </div>
                <div class="summary-card summary-total"
                    role="status"
                    :aria-label="t('flashcards', 'Total cards') + ': ' + (stats?.totalCards ?? 0)">
                    <div class="summary-icon" aria-hidden="true">
                        <IconFolder :size="32" />
                    </div>
                    <div class="summary-number">{{ stats?.totalCards ?? 0 }}</div>
                    <div class="summary-label">{{ t('flashcards', 'Total cards') }}</div>
                </div>
                <div class="summary-card summary-decks"
                    role="status"
                    :aria-label="t('flashcards', 'Decks') + ': ' + (stats?.totalDecks ?? 0)">
                    <div class="summary-icon" aria-hidden="true">
                        <IconFolderOpen :size="32" />
                    </div>
                    <div class="summary-number">{{ stats?.totalDecks ?? 0 }}</div>
                    <div class="summary-label">{{ t('flashcards', 'Decks') }}</div>
                </div>
                <div class="summary-card summary-reviewed"
                    role="status"
                    :aria-label="t('flashcards', 'Reviewed') + ': ' + (stats?.totalReviewed ?? 0)">
                    <div class="summary-icon" aria-hidden="true">
                        <IconCheck :size="32" />
                    </div>
                    <div class="summary-number">{{ stats?.totalReviewed ?? 0 }}</div>
                    <div class="summary-label">{{ t('flashcards', 'Reviewed') }}</div>
                </div>
            </div>

            <!-- Quick action -->
            <div class="quick-actions">
                <NcButton variant="primary" wide @click="goToDecks">
                    {{ hasDue
                        ? t('flashcards', 'Start studying ({count} due)', { count: stats?.totalDue ?? 0 })
                        : t('flashcards', 'Browse decks')
                    }}
                </NcButton>
            </div>

            <!-- All caught up message -->
            <div v-if="!hasDue" class="no-due">
                <p>🎉 {{ t('flashcards', 'All caught up! No cards due today.') }}</p>
            </div>

            <div class="dashboard-sections">
                <section class="dashboard-section">
                    <div class="section-header">
                        <h3>{{ t('flashcards', 'Recent decks') }}</h3>
                        <span class="section-meta">{{ t('flashcards', 'Top 3 last studied') }}</span>
                    </div>

                    <div v-if="recentDecks.length === 0" class="section-empty">
                        {{ t('flashcards', 'No study sessions yet.') }}
                    </div>

                    <div v-else class="deck-shortlist">
                        <article v-for="deck in recentDecks" :key="deck.path" class="deck-shortlist-card">
                            <div>
                                <div class="deck-shortlist-title">{{ deck.name }}</div>
                                <div class="deck-shortlist-meta">
                                    {{ formatLastStudied(deck.lastStudied) }}
                                </div>
                                <div class="deck-shortlist-stats">
                                    {{ deck.dueCards }} {{ t('flashcards', 'due') }} ·
                                    {{ deck.newCards }} {{ t('flashcards', 'new') }} ·
                                    {{ deck.totalCards }} {{ t('flashcards', 'cards') }}
                                </div>
                            </div>
                            <div class="deck-shortlist-actions">
                                <NcButton variant="tertiary" @click="goToBrowse(deck.path)">
                                    {{ t('flashcards', 'Browse') }}
                                </NcButton>
                                <NcButton v-if="deck.dueCards > 0 || deck.newCards > 0" variant="primary" @click="goToStudy(deck.path)">
                                    {{ t('flashcards', 'Study') }}
                                </NcButton>
                            </div>
                        </article>
                    </div>
                </section>

                <section class="dashboard-section">
                    <div class="section-header">
                        <h3>{{ t('flashcards', 'Favorite decks') }}</h3>
                        <span class="section-meta">{{ t('flashcards', 'Pinned for quick access') }}</span>
                    </div>

                    <div v-if="favoriteDecks.length === 0" class="section-empty">
                        {{ t('flashcards', 'No favorite decks yet.') }}
                    </div>

                    <div v-else class="deck-shortlist">
                        <article v-for="deck in favoriteDecks" :key="deck.path" class="deck-shortlist-card favorite">
                            <div>
                                <div class="deck-shortlist-title">{{ deck.name }}</div>
                                <div class="deck-shortlist-stats">
                                    {{ deck.dueCards }} {{ t('flashcards', 'due') }} ·
                                    {{ deck.newCards }} {{ t('flashcards', 'new') }} ·
                                    {{ deck.totalCards }} {{ t('flashcards', 'cards') }}
                                </div>
                            </div>
                            <div class="deck-shortlist-actions">
                                <NcButton variant="tertiary" @click="goToBrowse(deck.path)">
                                    {{ t('flashcards', 'Browse') }}
                                </NcButton>
                                <NcButton v-if="deck.dueCards > 0 || deck.newCards > 0" variant="primary" @click="goToStudy(deck.path)">
                                    {{ t('flashcards', 'Study') }}
                                </NcButton>
                            </div>
                        </article>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { translate as t } from '@nextcloud/l10n'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcButton from '@nextcloud/vue/components/NcButton'
import IconCards from 'vue-material-design-icons/Cards.vue'
import IconStar from 'vue-material-design-icons/Star.vue'
import IconFolder from 'vue-material-design-icons/Folder.vue'
import IconFolderOpen from 'vue-material-design-icons/FolderOpen.vue'
import IconCheck from 'vue-material-design-icons/CheckCircle.vue'
import { useStatsStore } from '@/stores/stats'
import { useDeckStore } from '@/stores/deck'
import { useSettingsStore } from '@/stores/settings'
import type { DeckMeta } from '@/types/deck'

const router = useRouter()
const statsStore = useStatsStore()
const deckStore = useDeckStore()
const settingsStore = useSettingsStore()

const loading = ref(true)
const stats = computed(() => statsStore.overview)
const hasDue = computed(() => (stats.value?.totalDue ?? 0) > 0)
const favoriteDecks = computed(() => {
    const favorites = new Set(settingsStore.settings.favoriteDecks)
    return deckStore.decks.filter(deck => favorites.has(deck.path))
})
const recentDecks = computed(() => {
    const deckMap = new Map(deckStore.decks.map(deck => [deck.path, deck]))

    return settingsStore.settings.recentDecks
        .map(entry => {
            const deck = deckMap.get(entry.path)
            if (!deck) return null
            return {
                ...deck,
                lastStudied: entry.lastStudied,
            }
        })
        .filter((deck): deck is DeckMeta & { lastStudied: number } => deck !== null)
        .slice(0, 3)
})

function goToDecks() {
    router.push({ name: 'decks' })
}

function goToBrowse(path: string) {
    router.push({ name: 'cards', params: { path } })
}

function goToStudy(path: string) {
    router.push({ name: 'study', params: { path } })
}

function formatLastStudied(timestamp: number): string {
    return t('flashcards', 'Last studied') + ': ' + new Date(timestamp).toLocaleString()
}

onMounted(async () => {
    try {
        await Promise.all([
            statsStore.loadOverview(),
            settingsStore.load(),
            deckStore.loadDecks(),
        ])
    } finally {
        loading.value = false
    }
})
</script>

<style lang="scss" scoped>
.dashboard-loading {
    display: flex;
    justify-content: center;
    padding: 60px 0;
}

.dashboard-content {
    max-width: 800px;
    margin: 0 auto;
}

.summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 16px;
    margin-bottom: 32px;
}

.summary-card {
    background: var(--color-background-dark);
    border-radius: 12px;
    padding: 20px 16px;
    text-align: center;
    transition: transform 0.15s, box-shadow 0.15s;
}

.summary-due {
    cursor: pointer;

    &:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
}

.summary-icon {
    font-size: 1.8em;
    margin-bottom: 8px;
}

.summary-number {
    font-size: 2em;
    font-weight: 700;
    line-height: 1.2;
}

.summary-label {
    font-size: 0.85em;
    color: var(--color-text-maxcontrast);
    margin-top: 4px;
}

.summary-due .summary-number { color: #ff922b; }
.summary-new .summary-number { color: #74c0fc; }
.summary-total .summary-number { color: #e9ecef; }
.summary-decks .summary-number { color: #ffd43b; }
.summary-reviewed .summary-number { color: #69db7c; }

.quick-actions {
    display: flex;
    justify-content: center;
    margin-bottom: 32px;
}

.no-due {
    text-align: center;
    padding: 40px;
    font-size: 1.2em;
    color: var(--color-text-maxcontrast);
}

.dashboard-sections {
    display: grid;
    gap: 20px;
}

.dashboard-section {
    background: var(--color-background-dark);
    border-radius: 14px;
    padding: 20px;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 12px;
    margin-bottom: 16px;

    h3 {
        margin: 0;
    }
}

.section-meta,
.section-empty,
.deck-shortlist-meta,
.deck-shortlist-stats {
    color: var(--color-text-maxcontrast);
}

.deck-shortlist {
    display: grid;
    gap: 12px;
}

.deck-shortlist-card {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    align-items: center;
    padding: 14px 16px;
    background: var(--color-main-background);
    border-radius: 12px;
    border: 1px solid var(--color-border);

    &.favorite {
        border-color: color-mix(in srgb, #c59a1b 35%, var(--color-border));
    }
}

.deck-shortlist-title {
    font-size: 1.05em;
    font-weight: 700;
    margin-bottom: 4px;
}

.deck-shortlist-meta {
    margin-bottom: 4px;
}

.deck-shortlist-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: flex-end;
}

@media (max-width: 768px) {
    .deck-shortlist-card {
        flex-direction: column;
        align-items: stretch;
    }

    .deck-shortlist-actions {
        justify-content: stretch;
    }
}
</style>
