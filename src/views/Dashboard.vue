<template>
    <div class="flashcards-page">
        <div class="flashcards-page-header">
            <h2>{{ t('flashcards', 'Dashboard') }}</h2>
        </div>

        <div v-if="loading" class="dashboard-loading">
            <NcLoadingIcon :size="44" />
        </div>

        <div v-else class="dashboard-grid">
            <!-- Summary cards -->
            <div class="summary-cards">
                <div class="summary-card summary-due" @click="goToDecks">
                    <div class="summary-number">{{ stats?.totalDue ?? 0 }}</div>
                    <div class="summary-label">{{ t('flashcards', 'Cards due today') }}</div>
                </div>
                <div class="summary-card summary-new">
                    <div class="summary-number">{{ stats?.totalNew ?? 0 }}</div>
                    <div class="summary-label">{{ t('flashcards', 'New cards') }}</div>
                </div>
                <div class="summary-card summary-total">
                    <div class="summary-number">{{ stats?.totalCards ?? 0 }}</div>
                    <div class="summary-label">{{ t('flashcards', 'Total cards') }}</div>
                </div>
                <div class="summary-card summary-decks">
                    <div class="summary-number">{{ stats?.totalDecks ?? 0 }}</div>
                    <div class="summary-label">{{ t('flashcards', 'Decks') }}</div>
                </div>
            </div>

            <!-- Due decks list -->
            <div class="due-decks" v-if="dueDecks.length > 0">
                <h3>{{ t('flashcards', 'Ready to review') }}</h3>
                <div class="deck-list">
                    <div v-for="deck in dueDecks"
                        :key="deck.path"
                        class="deck-item"
                        @click="startStudy(deck.path)">
                        <div class="deck-name">{{ deck.name }}</div>
                        <div class="deck-counts">
                            <span class="count-due">{{ deck.due }} {{ t('flashcards', 'due') }}</span>
                            <span v-if="deck.new > 0" class="count-new">
                                +{{ deck.new }} {{ t('flashcards', 'new') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="no-due">
                <p>🎉 {{ t('flashcards', 'All caught up! No cards due today.') }}</p>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { translate as t } from '@nextcloud/l10n'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { useStatsStore } from '@/stores/stats'

const router = useRouter()
const statsStore = useStatsStore()

const loading = ref(true)
const stats = computed(() => statsStore.overview)

const dueDecks = computed(() =>
    statsStore.dueCounts.filter(d => d.due > 0 || d.new > 0)
        .sort((a, b) => b.due - a.due),
)

function goToDecks() {
    router.push({ name: 'decks' })
}

function startStudy(path: string) {
    router.push({ name: 'study', params: { path } })
}

onMounted(async () => {
    try {
        await Promise.all([
            statsStore.loadOverview(),
            statsStore.loadDueCounts(),
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

.summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
    margin-bottom: 32px;
}

.summary-card {
    background: var(--color-background-dark);
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    cursor: pointer;
    transition: transform 0.15s, box-shadow 0.15s;

    &:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
}

.summary-number {
    font-size: 2.2em;
    font-weight: 700;
    line-height: 1.2;
}

.summary-label {
    font-size: 0.9em;
    color: var(--color-text-maxcontrast);
    margin-top: 4px;
}

.summary-due .summary-number { color: $flashcards-warning; }
.summary-new .summary-number { color: $card-new; }
.summary-total .summary-number { color: var(--color-text-light); }
.summary-decks .summary-number { color: var(--color-primary); }

.due-decks h3 {
    margin-bottom: 12px;
}

.deck-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.deck-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    background: var(--color-background-dark);
    border-radius: 8px;
    cursor: pointer;
    transition: background 0.15s;

    &:hover {
        background: var(--color-background-hover);
    }
}

.deck-name {
    font-weight: 600;
}

.deck-counts {
    display: flex;
    gap: 12px;
}

.count-due {
    color: $flashcards-warning;
    font-weight: 600;
}

.count-new {
    color: $card-new;
}

.no-due {
    text-align: center;
    padding: 40px;
    font-size: 1.2em;
    color: var(--color-text-maxcontrast);
}
</style>
