<template>
    <div class="flashcards-page">
        <div class="flashcards-page-header">
            <h2>Statistics</h2>
        </div>

        <!-- Loading spinner -->
        <div v-if="statsStore.loading && !statsStore.aggregated" class="loading-center">
            <NcLoadingIcon :size="44" />
        </div>

        <div v-else class="stats-content">
            <!-- ── Top-N selector ─────────────────────────────── -->
            <div class="stats-controls">
                <span class="controls-label">Showing statistics for top:</span>
                <div class="topn-buttons" role="group" aria-label="Select number of top decks">
                    <button
                        v-for="opt in TOP_N_OPTIONS"
                        :key="opt.value"
                        :class="['topn-btn', { active: selectedTopN === opt.value }]"
                        :aria-pressed="selectedTopN === opt.value"
                        @click="setTopN(opt.value)">
                        {{ opt.label }}
                    </button>
                </div>
                <span class="controls-label">decks</span>
                <button
                    v-if="selectedTopN !== DEFAULT_TOP_N"
                    class="reset-btn"
                    @click="resetTopN">
                    Reset to Top 3
                </button>
            </div>

            <!-- Scope label -->
            <div v-if="statsStore.aggregated" class="stats-scope-label">
                <template v-if="isAllDecks">
                    All {{ statsStore.aggregated.totalDecks }} decks
                </template>
                <template v-else>
                    Top {{ effectiveTopN }} most active decks
                    (of {{ statsStore.aggregated.totalDecks }} total) — sorted by due + new cards
                </template>
            </div>

            <!-- Aggregating indicator (background refresh) -->
            <div v-if="statsStore.aggregating" class="aggregating-indicator">
                <NcLoadingIcon :size="20" />
                <span>Loading statistics…</span>
            </div>

            <!-- ── Summary cards ───────────────────────────────── -->
            <div class="stats-overview">
                <div class="stat-card">
                    <div class="stat-number">{{ topSummary.totalCards }}</div>
                    <div class="stat-label">Total cards</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number reviewed">{{ topSummary.totalReviewed }}</div>
                    <div class="stat-label">Reviewed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number due">{{ topSummary.totalDue }}</div>
                    <div class="stat-label">Due today</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number new-cards">{{ topSummary.totalNew }}</div>
                    <div class="stat-label">New</div>
                </div>
            </div>

            <template v-if="statsStore.aggregated">
                <!-- ── Due Forecast ────────────────────────────────── -->
                <div class="chart-section">
                    <h3>Due Forecast (30 days)</h3>
                    <div class="chart-subtitle">
                        {{ isAllDecks ? 'All ' + statsStore.aggregated.totalDecks + ' decks' : 'Top ' + effectiveTopN + ' most active decks' }}
                    </div>
                    <div class="chart-container">
                        <Bar :data="forecastChartData" :options="chartOptions" />
                    </div>
                </div>

                <!-- ── Interval Distribution ───────────────────────── -->
                <div class="chart-section">
                    <h3>Interval Distribution</h3>
                    <div v-if="isAllDecks" class="chart-container">
                        <Bar :data="allIntervalChartData" :options="chartOptions" />
                    </div>
                    <div v-else class="dual-charts">
                        <div class="dual-chart-item">
                            <div class="chart-subtitle">Top {{ effectiveTopN }} decks</div>
                            <div class="chart-container">
                                <Bar :data="topIntervalChartData" :options="chartOptions" />
                            </div>
                        </div>
                        <div class="dual-chart-item">
                            <div class="chart-subtitle">All {{ statsStore.aggregated.totalDecks }} decks</div>
                            <div class="chart-container">
                                <Bar :data="allIntervalChartData" :options="chartOptions" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Deck table ──────────────────────────────────── -->
                <div class="deck-stats-table">
                    <h3>
                        Deck Breakdown
                        <span class="table-subtitle">
                            ({{ isAllDecks ? 'all' : 'top ' + effectiveTopN }} decks, most active first)
                        </span>
                    </h3>
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Deck</th>
                                <th scope="col">Total</th>
                                <th scope="col">Due</th>
                                <th scope="col">New</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(deck, idx) in statsStore.aggregated.topDecks" :key="deck.path">
                                <td class="td-rank">{{ idx + 1 }}</td>
                                <td>{{ deck.name }}</td>
                                <td>{{ deck.total }}</td>
                                <td class="td-due">{{ deck.due }}</td>
                                <td class="td-new">{{ deck.new }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>

            <!-- Empty state (no decks yet) -->
            <div v-else-if="!statsStore.aggregating" class="stats-empty">
                <p>No flashcard decks found. Create a deck to start tracking statistics.</p>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
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

// ── Top-N selector ────────────────────────────────────────────────────────────

const TOP_N_OPTIONS = [
    { label: '1',   value: 1 },
    { label: '3',   value: 3 },
    { label: '5',   value: 5 },
    { label: '7',   value: 7 },
    { label: '10',  value: 10 },
    { label: '15',  value: 15 },
    { label: '20',  value: 20 },
    { label: '25',  value: 25 },
    { label: '50',  value: 50 },
    { label: 'All', value: 9999 },
] as const

const DEFAULT_TOP_N = 3
const STORAGE_KEY = 'fc-stats-topN'

const storedTopN = parseInt(localStorage.getItem(STORAGE_KEY) ?? String(DEFAULT_TOP_N), 10)
const selectedTopN = ref<number>(
    TOP_N_OPTIONS.some(o => o.value === storedTopN) ? storedTopN : DEFAULT_TOP_N,
)

function setTopN(n: number) {
    selectedTopN.value = n
    localStorage.setItem(STORAGE_KEY, String(n))
    statsStore.loadAggregated(n)
}

function resetTopN() {
    setTopN(DEFAULT_TOP_N)
}

// ── Derived state ─────────────────────────────────────────────────────────────

const isAllDecks = computed(() => selectedTopN.value >= 9999)

const effectiveTopN = computed(() => {
    if (isAllDecks.value) return statsStore.aggregated?.totalDecks ?? 0
    return Math.min(selectedTopN.value, statsStore.aggregated?.totalDecks ?? selectedTopN.value)
})

const topSummary = computed(() =>
    statsStore.aggregated?.topSummary ?? { totalCards: 0, totalDue: 0, totalNew: 0, totalReviewed: 0 },
)

// ── Chart options ─────────────────────────────────────────────────────────────

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: 'rgba(0, 0, 0, 0.85)',
            cornerRadius: 8,
            padding: 10,
            titleFont: { size: 13 },
            bodyFont: { size: 12 },
        },
    },
    scales: {
        x: {
            grid: { display: false },
            ticks: { color: '#e0e0e0' },
        },
        y: {
            beginAtZero: true,
            grid: { color: 'rgba(128, 128, 128, 0.15)' },
            ticks: { color: '#e0e0e0', precision: 0 },
        },
    },
}))

// ── Chart data ────────────────────────────────────────────────────────────────

const forecastChartData = computed(() => {
    const forecast = statsStore.aggregated?.topDueForecast ?? {}
    const entries = Object.entries(forecast)
    if (!entries.length) return { labels: [], datasets: [] }
    const values = entries.map(([, v]) => v as number)
    return {
        labels: entries.map(([d]) => d === '0' ? 'Today' : `+${d}d`),
        datasets: [{
            data: values,
            backgroundColor: values.map((_, i) =>
                i === 0 ? 'rgba(230, 119, 0, 0.9)' : 'rgba(230, 119, 0, 0.5)',
            ),
            borderRadius: 6,
            borderSkipped: false,
        }],
    }
})

const topIntervalChartData = computed(() => {
    const dist = statsStore.aggregated?.topIntervalDistribution ?? {}
    return {
        labels: Object.keys(dist),
        datasets: [{
            data: Object.values(dist),
            backgroundColor: 'rgba(25, 113, 194, 0.65)',
            hoverBackgroundColor: 'rgba(25, 113, 194, 0.9)',
            borderRadius: 6,
            borderSkipped: false,
        }],
    }
})

const allIntervalChartData = computed(() => {
    const dist = statsStore.aggregated?.allIntervalDistribution ?? {}
    return {
        labels: Object.keys(dist),
        datasets: [{
            data: Object.values(dist),
            backgroundColor: 'rgba(60, 180, 100, 0.65)',
            hoverBackgroundColor: 'rgba(60, 180, 100, 0.9)',
            borderRadius: 6,
            borderSkipped: false,
        }],
    }
})

// ── Lifecycle ─────────────────────────────────────────────────────────────────

onMounted(() => {
    statsStore.loadAggregated(selectedTopN.value)
})
</script>

<style lang="scss" scoped>
.loading-center {
    display: flex;
    justify-content: center;
    padding: 60px;
}

// ── Controls bar ──────────────────────────────────────────────────────────────

.stats-controls {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 12px;
    padding: 14px 18px;
    background: rgba(50, 50, 50, 0.35);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
}

.controls-label {
    color: var(--color-text-maxcontrast);
    font-size: 0.9em;
    white-space: nowrap;
}

.topn-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}

.topn-btn {
    padding: 4px 11px;
    border-radius: 6px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    background: transparent;
    color: var(--color-text-maxcontrast);
    cursor: pointer;
    font-size: 0.85em;
    transition: background 0.15s, color 0.15s, border-color 0.15s;

    &.active {
        background: var(--color-primary);
        color: #fff;
        border-color: var(--color-primary);
        font-weight: 600;
    }

    &:hover:not(.active) {
        background: rgba(255, 255, 255, 0.1);
        color: #fff;
    }
}

.reset-btn {
    padding: 4px 12px;
    border-radius: 6px;
    border: 1px solid rgba(255, 200, 100, 0.4);
    background: rgba(255, 200, 100, 0.1);
    color: #ffd43b;
    cursor: pointer;
    font-size: 0.82em;
    transition: background 0.15s;

    &:hover {
        background: rgba(255, 200, 100, 0.2);
    }
}

.stats-scope-label {
    color: var(--color-text-maxcontrast);
    font-size: 0.88em;
    margin-bottom: 20px;
    padding-left: 2px;
}

.aggregating-indicator {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--color-text-maxcontrast);
    font-size: 0.88em;
    margin-bottom: 12px;
}

// ── Summary cards ─────────────────────────────────────────────────────────────

.stats-overview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 16px;
    margin-bottom: 32px;
}

.stat-card {
    background: rgba(50, 50, 50, 0.5);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 20px;
    text-align: center;
}

.stat-number {
    font-size: 2em;
    font-weight: 700;
    color: #ffffff;

    &.reviewed { color: $flashcards-success; }
    &.due       { color: $flashcards-warning; }
    &.new-cards { color: $card-new; }
}

.stat-label {
    color: #cccccc;
    margin-top: 4px;
    font-size: 0.9em;
}

// ── Chart sections ────────────────────────────────────────────────────────────

.chart-section {
    margin-bottom: 32px;

    h3 {
        margin-bottom: 4px;
        color: #ffffff;
    }
}

.chart-subtitle {
    color: var(--color-text-maxcontrast);
    font-size: 0.85em;
    margin-bottom: 10px;
}

.chart-container {
    height: 280px;
    background: rgba(50, 50, 50, 0.5);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 20px;

    @media (max-width: 768px) {
        height: 200px;
        padding: 12px;
    }
}

// ── Dual charts ───────────────────────────────────────────────────────────────

.dual-charts {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;

    @media (max-width: 768px) {
        grid-template-columns: 1fr;
    }
}

.dual-chart-item {
    // chart-subtitle + chart-container live inside
}

// ── Deck table ────────────────────────────────────────────────────────────────

.deck-stats-table {
    h3 {
        margin-bottom: 12px;
        color: #ffffff;

        .table-subtitle {
            font-size: 0.75em;
            font-weight: 400;
            color: var(--color-text-maxcontrast);
            margin-left: 6px;
        }
    }

    table {
        width: 100%;
        border-collapse: collapse;
        background: rgba(50, 50, 50, 0.3);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        overflow: hidden;
    }

    th, td {
        padding: 10px 16px;
        text-align: left;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }

    th {
        font-weight: 700;
        color: #cccccc;
        background: rgba(255, 255, 255, 0.05);
    }

    td { color: #e0e0e0; }

    .td-rank  { color: var(--color-text-maxcontrast); font-size: 0.85em; width: 40px; }
    .td-due   { color: $flashcards-warning; font-weight: 600; }
    .td-new   { color: $card-new; font-weight: 600; }
}

// ── Empty state ───────────────────────────────────────────────────────────────

.stats-empty {
    text-align: center;
    padding: 60px 20px;
    color: var(--color-text-maxcontrast);
}
</style>
