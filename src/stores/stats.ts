/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Flashcards v2 — Stats Store
 */

import { defineStore } from 'pinia'
import { ref } from 'vue'
import { showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import type { OverviewStats, DeckStats, DueCount, AggregatedStats } from '@/types/sr'
import * as api from '@/services/api'

export const useStatsStore = defineStore('stats', () => {
    const overview = ref<OverviewStats | null>(null)
    const deckStats = ref<DeckStats | null>(null)
    const dueCounts = ref<DueCount[]>([])
    const aggregated = ref<AggregatedStats | null>(null)
    const loading = ref(false)
    const aggregating = ref(false)

    async function loadOverview() {
        loading.value = true
        try {
            overview.value = await api.fetchOverviewStats()
        } catch (e) {
            showError(t('flashcards', 'Failed to load statistics'))
        } finally {
            loading.value = false
        }
    }

    async function loadDeckStats(path: string) {
        loading.value = true
        try {
            deckStats.value = await api.fetchDeckStats(path)
        } catch (e) {
            showError(t('flashcards', 'Failed to load deck statistics'))
        } finally {
            loading.value = false
        }
    }

    async function loadDueCounts() {
        try {
            dueCounts.value = await api.fetchDueCounts()
        } catch {
            dueCounts.value = []
        }
    }

    async function loadAggregated(topN: number) {
        aggregating.value = true
        try {
            aggregated.value = await api.fetchAggregatedStats(topN)
        } catch {
            showError(t('flashcards', 'Failed to load aggregated statistics'))
        } finally {
            aggregating.value = false
        }
    }

    return {
        overview,
        deckStats,
        dueCounts,
        aggregated,
        loading,
        aggregating,
        loadOverview,
        loadDeckStats,
        loadDueCounts,
        loadAggregated,
    }
})
