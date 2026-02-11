/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Flashcards v2 — Study Store
 */

import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import type { ParsedCard } from '@/types/card'
import type { Rating, IntervalPrediction } from '@/types/sr'
import * as api from '@/services/api'
import { useDeckStore } from './deck'

export const useStudyStore = defineStore('study', () => {
    const deckStore = useDeckStore()

    const queue = ref<ParsedCard[]>([])
    const currentIndex = ref(0)
    const isFlipped = ref(false)
    const isReversed = ref(false) // back→front direction
    const predictions = ref<Record<number, IntervalPrediction>>({})
    const sessionStats = ref({ reviewed: 0, correct: 0, again: 0 })
    const loading = ref(false)

    const currentCard = computed<ParsedCard | null>(() =>
        queue.value[currentIndex.value] ?? null,
    )

    const progress = computed(() => ({
        current: currentIndex.value + 1,
        total: queue.value.length,
        percent: queue.value.length > 0
            ? Math.round(((currentIndex.value) / queue.value.length) * 100)
            : 0,
    }))

    const isSessionComplete = computed(() =>
        queue.value.length > 0 && currentIndex.value >= queue.value.length,
    )

    async function startSession(path: string) {
        loading.value = true
        try {
            // Ensure deck is open
            if (deckStore.currentPath !== path) {
                await deckStore.openDeck(path)
            }

            const dueCards = await api.fetchDueCards(path)

            // Shuffle due cards
            queue.value = shuffleArray(dueCards)
            currentIndex.value = 0
            isFlipped.value = false
            isReversed.value = false
            sessionStats.value = { reviewed: 0, correct: 0, again: 0 }

            // Load predictions for first card
            if (queue.value.length > 0) {
                await loadPredictions()
            }
        } catch (e) {
            showError(e instanceof Error ? e.message : t('flashcards', 'Failed to start study session'))
            throw e
        } finally {
            loading.value = false
        }
    }

    async function loadPredictions() {
        const card = currentCard.value
        if (!card || !deckStore.currentPath) return

        try {
            const srIndex = isReversed.value ? 1 : 0
            predictions.value = await api.predictIntervals(
                deckStore.currentPath,
                card.index,
                srIndex,
            )
        } catch {
            predictions.value = {}
        }
    }

    function flipCard() {
        isFlipped.value = true
    }

    async function submitRating(rating: Rating) {
        const card = currentCard.value
        if (!card || !deckStore.currentPath) return

        loading.value = true
        try {
            const result = await api.submitReview({
                path: deckStore.currentPath,
                cardIndex: card.index,
                rating,
                srIndex: isReversed.value ? 1 : 0,
            })

            // Update local card data
            const updatedCard = { ...card, sr: result.sr }
            deckStore.updateCardLocally(card.index, updatedCard)

            // Track stats
            sessionStats.value.reviewed++
            if (rating >= 2) {
                sessionStats.value.correct++
            } else {
                sessionStats.value.again++
                // Re-queue "Again" cards at the end with updated SR data
                queue.value.push(updatedCard)
            }

            // Move to next card
            currentIndex.value++
            isFlipped.value = false
            isReversed.value = false
            predictions.value = {}

            // Load predictions for next card
            if (!isSessionComplete.value) {
                await loadPredictions()
            }
        } finally {
            loading.value = false
        }
    }

    function toggleDirection() {
        isReversed.value = !isReversed.value
        loadPredictions()
    }

    function reset() {
        queue.value = []
        currentIndex.value = 0
        isFlipped.value = false
        isReversed.value = false
        predictions.value = {}
        sessionStats.value = { reviewed: 0, correct: 0, again: 0 }
    }

    return {
        queue,
        currentIndex,
        currentCard,
        isFlipped,
        isReversed,
        predictions,
        sessionStats,
        progress,
        isSessionComplete,
        loading,
        startSession,
        flipCard,
        submitRating,
        toggleDirection,
        loadPredictions,
        reset,
    }
})

function shuffleArray<T>(array: T[]): T[] {
    const arr = [...array]
    for (let i = arr.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [arr[i], arr[j]] = [arr[j], arr[i]]
    }
    return arr
}
