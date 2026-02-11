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
                    <div class="summary-icon" aria-hidden="true">📚</div>
                    <div class="summary-number">{{ stats?.totalDue ?? 0 }}</div>
                    <div class="summary-label">{{ t('flashcards', 'Cards due today') }}</div>
                </div>
                <div class="summary-card summary-new"
                    role="status"
                    :aria-label="t('flashcards', 'New cards') + ': ' + (stats?.totalNew ?? 0)">
                    <div class="summary-icon" aria-hidden="true">✨</div>
                    <div class="summary-number">{{ stats?.totalNew ?? 0 }}</div>
                    <div class="summary-label">{{ t('flashcards', 'New cards') }}</div>
                </div>
                <div class="summary-card summary-total"
                    role="status"
                    :aria-label="t('flashcards', 'Total cards') + ': ' + (stats?.totalCards ?? 0)">
                    <div class="summary-icon" aria-hidden="true">🗂️</div>
                    <div class="summary-number">{{ stats?.totalCards ?? 0 }}</div>
                    <div class="summary-label">{{ t('flashcards', 'Total cards') }}</div>
                </div>
                <div class="summary-card summary-decks"
                    role="status"
                    :aria-label="t('flashcards', 'Decks') + ': ' + (stats?.totalDecks ?? 0)">
                    <div class="summary-icon" aria-hidden="true">📂</div>
                    <div class="summary-number">{{ stats?.totalDecks ?? 0 }}</div>
                    <div class="summary-label">{{ t('flashcards', 'Decks') }}</div>
                </div>
                <div class="summary-card summary-reviewed"
                    role="status"
                    :aria-label="t('flashcards', 'Reviewed') + ': ' + (stats?.totalReviewed ?? 0)">
                    <div class="summary-icon" aria-hidden="true">✅</div>
                    <div class="summary-number">{{ stats?.totalReviewed ?? 0 }}</div>
                    <div class="summary-label">{{ t('flashcards', 'Reviewed') }}</div>
                </div>
            </div>

            <!-- Quick action -->
            <div class="quick-actions">
                <NcButton type="primary" wide @click="goToDecks">
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
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { translate as t } from '@nextcloud/l10n'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcButton from '@nextcloud/vue/components/NcButton'
import { useStatsStore } from '@/stores/stats'

const router = useRouter()
const statsStore = useStatsStore()

const loading = ref(true)
const stats = computed(() => statsStore.overview)
const hasDue = computed(() => (stats.value?.totalDue ?? 0) > 0)

function goToDecks() {
    router.push({ name: 'decks' })
}

onMounted(async () => {
    try {
        await statsStore.loadOverview()
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

.summary-due .summary-number { color: var(--color-warning); }
.summary-new .summary-number { color: var(--color-info); }
.summary-total .summary-number { color: var(--color-text-light); }
.summary-decks .summary-number { color: var(--color-primary); }
.summary-reviewed .summary-number { color: var(--color-success); }

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
</style>
