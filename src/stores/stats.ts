/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Flashcards v2 — Stats Store
 */

import { defineStore } from 'pinia'
import { ref } from 'vue'
import { showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import type { OverviewStats, DeckStats, DueCount } from '@/types/sr'
import * as api from '@/services/api'

export const useStatsStore = defineStore('stats', () => {
    const overview = ref<OverviewStats | null>(null)
    const deckStats = ref<DeckStats | null>(null)
    const dueCounts = ref<DueCount[]>([])
    const loading = ref(false)

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

    return {
        overview,
        deckStats,
        dueCounts,
        loading,
        loadOverview,
        loadDeckStats,
        loadDueCounts,
    }
})
