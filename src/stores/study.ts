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

    // Track which srIndex (direction) each queue entry should use
    const queueDirections = ref<number[]>([])

    async function startSession(path: string) {
        loading.value = true
        try {
            // Ensure deck is open
            if (deckStore.currentPath !== path) {
                await deckStore.openDeck(path)
            }

            const dueCards = await api.fetchDueCards(path)

            // Expand cards: if a card has multiple dueDirections, queue it once per direction
            const expanded: ParsedCard[] = []
            const directions: number[] = []
            for (const card of dueCards) {
                const dirs = (card as any).dueDirections ?? [0]
                for (const dir of dirs) {
                    expanded.push(card)
                    directions.push(dir)
                }
            }

            // Shuffle both arrays in sync
            const indices = expanded.map((_, i) => i)
            for (let i = indices.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [indices[i], indices[j]] = [indices[j], indices[i]]
            }
            queue.value = indices.map(i => expanded[i])
            queueDirections.value = indices.map(i => directions[i])

            currentIndex.value = 0
            isFlipped.value = false
            // Set direction from the first card's due direction
            isReversed.value = queueDirections.value[0] === 1
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

            // Update deck's due count if server returned it
            if (result.remainingDue !== undefined) {
                deckStore.updateDueCount(deckStore.currentPath, result.remainingDue)
            }

            // Track stats
            sessionStats.value.reviewed++
            if (rating >= 2) {
                sessionStats.value.correct++
            } else {
                sessionStats.value.again++
                // Re-queue "Again" cards at the end with same direction
                queue.value.push(updatedCard)
                queueDirections.value.push(isReversed.value ? 1 : 0)
            }

            // Move to next card
            currentIndex.value++
            isFlipped.value = false
            // Set direction from queue for next card
            isReversed.value = (queueDirections.value[currentIndex.value] ?? 0) === 1
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
        queueDirections.value = []
        currentIndex.value = 0
        isFlipped.value = false
        isReversed.value = false
        predictions.value = {}
        sessionStats.value = { reviewed: 0, correct: 0, again: 0 }
    }

    return {
        queue,
        queueDirections,
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
