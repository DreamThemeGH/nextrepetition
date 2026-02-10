<template>
    <div class="flashcards-page">
        <div class="flashcards-page-header">
            <h2>{{ t('flashcards', 'Statistics') }}</h2>
        </div>

        <div v-if="statsStore.loading" class="loading-center">
            <NcLoadingIcon :size="44" />
        </div>

        <div v-else-if="statsStore.overview" class="stats-content">
            <!-- Overview -->
            <div class="stats-overview">
                <div class="stat-card">
                    <div class="stat-number">{{ statsStore.overview.totalCards }}</div>
                    <div class="stat-label">{{ t('flashcards', 'Total cards') }}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number reviewed">{{ statsStore.overview.totalReviewed }}</div>
                    <div class="stat-label">{{ t('flashcards', 'Reviewed') }}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number due">{{ statsStore.overview.totalDue }}</div>
                    <div class="stat-label">{{ t('flashcards', 'Due today') }}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number new-cards">{{ statsStore.overview.totalNew }}</div>
                    <div class="stat-label">{{ t('flashcards', 'New') }}</div>
                </div>
            </div>

            <!-- Due Forecast Chart -->
            <div class="chart-section" v-if="deckStatsData">
                <h3>{{ t('flashcards', 'Due Forecast (30 days)') }}</h3>
                <div class="chart-container">
                    <Bar :data="forecastChartData" :options="chartOptions" />
                </div>
            </div>

            <!-- Interval Distribution -->
            <div class="chart-section" v-if="deckStatsData">
                <h3>{{ t('flashcards', 'Interval Distribution') }}</h3>
                <div class="chart-container">
                    <Bar :data="intervalChartData" :options="chartOptions" />
                </div>
            </div>

            <!-- Per-deck table -->
            <div class="deck-stats-table">
                <h3>{{ t('flashcards', 'Decks') }}</h3>
                <table>
                    <thead>
                        <tr>
                            <th>{{ t('flashcards', 'Deck') }}</th>
                            <th>{{ t('flashcards', 'Total') }}</th>
                            <th>{{ t('flashcards', 'Due') }}</th>
                            <th>{{ t('flashcards', 'New') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="deck in statsStore.overview.decks"
                            :key="deck.path"
                            class="deck-stat-row"
                            @click="loadDeckDetails(deck.path)">
                            <td>{{ deck.name }}</td>
                            <td>{{ deck.total }}</td>
                            <td class="td-due">{{ deck.due }}</td>
                            <td class="td-new">{{ deck.new }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import { Bar } from 'vue-chartjs'
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    BarElement,
    Title,
    Tooltip,
    Legend,
} from 'chart.js'

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend)

import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { useStatsStore } from '@/stores/stats'

const statsStore = useStatsStore()
const deckStatsData = ref<any>(null)

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
}

const forecastChartData = computed(() => {
    if (!deckStatsData.value?.dueForecast) {
        return { labels: [], datasets: [] }
    }
    const forecast = deckStatsData.value.dueForecast
    return {
        labels: Object.keys(forecast).map(d => d === '0' ? t('flashcards', 'Today') : `+${d}d`),
        datasets: [{
            data: Object.values(forecast),
            backgroundColor: '#e67700',
            borderRadius: 4,
        }],
    }
})

const intervalChartData = computed(() => {
    if (!deckStatsData.value?.intervalDistribution) {
        return { labels: [], datasets: [] }
    }
    const dist = deckStatsData.value.intervalDistribution
    return {
        labels: Object.keys(dist),
        datasets: [{
            data: Object.values(dist),
            backgroundColor: '#1971c2',
            borderRadius: 4,
        }],
    }
})

async function loadDeckDetails(path: string) {
    await statsStore.loadDeckStats(path)
    deckStatsData.value = statsStore.deckStats
}

onMounted(async () => {
    await statsStore.loadOverview()
    // Auto-load first deck if available
    if (statsStore.overview?.decks?.length) {
        await loadDeckDetails(statsStore.overview.decks[0].path)
    }
})
</script>

<style lang="scss" scoped>
.loading-center {
    display: flex;
    justify-content: center;
    padding: 60px;
}

.stats-overview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 16px;
    margin-bottom: 32px;
}

.stat-card {
    background: var(--color-background-dark);
    border-radius: 12px;
    padding: 20px;
    text-align: center;
}

.stat-number {
    font-size: 2em;
    font-weight: 700;

    &.reviewed { color: $flashcards-success; }
    &.due { color: $flashcards-warning; }
    &.new-cards { color: $card-new; }
}

.stat-label {
    color: var(--color-text-maxcontrast);
    margin-top: 4px;
}

.chart-section {
    margin-bottom: 32px;

    h3 { margin-bottom: 12px; }
}

.chart-container {
    height: 250px;
    background: var(--color-background-dark);
    border-radius: 12px;
    padding: 16px;
}

.deck-stats-table {
    h3 { margin-bottom: 12px; }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    th, td {
        padding: 10px 16px;
        text-align: left;
        border-bottom: 1px solid var(--color-border);
    }

    th {
        font-weight: 700;
        color: var(--color-text-maxcontrast);
    }

    .deck-stat-row {
        cursor: pointer;
        &:hover { background: var(--color-background-hover); }
    }

    .td-due { color: $flashcards-warning; font-weight: 600; }
    .td-new { color: $card-new; font-weight: 600; }
}
</style>
