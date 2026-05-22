/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Flashcards v2 — Deck Store
 */

import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import type { DeckMeta, FolderInfo } from '@/types/deck'
import type { ParsedCard } from '@/types/card'
import * as api from '@/services/api'

export const useDeckStore = defineStore('deck', () => {
    const decks = ref<DeckMeta[]>([])
    const folders = ref<FolderInfo[]>([])
    const loading = ref(false)
    const error = ref<string | null>(null)

    // Currently open deck
    const currentPath = ref<string | null>(null)
    const currentCards = ref<ParsedCard[]>([])
    const currentTag = ref('')
    const dirty = ref(false)

    const totalDue = computed(() => {
        if (!decks.value || decks.value.length === 0) return 0
        return decks.value.reduce((sum, d) => {
            const val = Number(d.dueCards)
            return sum + (Number.isFinite(val) ? val : 0)
        }, 0)
    })

    const totalNew = computed(() => {
        if (!decks.value || decks.value.length === 0) return 0
        return decks.value.reduce((sum, d) => {
            const val = Number(d.newCards)
            return sum + (Number.isFinite(val) ? val : 0)
        }, 0)
    })

    const currentDeck = computed(() =>
        decks.value.find(d => d.path === currentPath.value) ?? null,
    )

    function toSafeNumber(value: unknown): number {
        const n = Number(value)
        return Number.isFinite(n) ? n : 0
    }

    function normalizeDeck(deck: DeckMeta): DeckMeta {
        return {
            ...deck,
            size: toSafeNumber(deck.size),
            modified: toSafeNumber(deck.modified),
            totalCards: toSafeNumber(deck.totalCards),
            dueCards: toSafeNumber(deck.dueCards),
            newCards: toSafeNumber(deck.newCards),
        }
    }

    async function loadDecks() {
        loading.value = true
        error.value = null
        try {
            const fetched = await api.fetchDecks()
            decks.value = fetched.map(normalizeDeck)
        } catch (e) {
            error.value = e instanceof Error ? e.message : 'Failed to load decks'
            showError(error.value)
        } finally {
            loading.value = false
        }
    }

    async function loadFolders(subPath?: string) {
        try {
            folders.value = await api.fetchFolders(subPath)
        } catch {
            folders.value = []
        }
    }

    async function openDeck(path: string) {
        loading.value = true
        error.value = null
        try {
            const result = await api.openDeck(path)
            currentPath.value = path
            currentCards.value = result.cards
            currentTag.value = result.tag
            dirty.value = false
            return result
        } catch (e) {
            error.value = e instanceof Error ? e.message : 'Failed to open deck'
            showError(error.value)
            throw e
        } finally {
            loading.value = false
        }
    }

    async function saveDeck() {
        if (!currentPath.value) return
        try {
            await api.saveDeck(currentPath.value)
            dirty.value = false
        } catch (e) {
            error.value = e instanceof Error ? e.message : 'Failed to save'
            showError(error.value)
        }
    }

    async function closeDeck() {
        if (!currentPath.value) return
        try {
            await api.closeDeck(currentPath.value)
        } finally {
            currentPath.value = null
            currentCards.value = []
            currentTag.value = ''
            dirty.value = false
        }
    }

    async function createDeck(name: string, folder?: string) {
        try {
            const result = await api.createDeck(name, folder)
            showSuccess(t('flashcards', 'Deck "{name}" created', { name }))
            await loadDecks()
            return result
        } catch (e) {
            showError(e instanceof Error ? e.message : t('flashcards', 'Failed to create deck'))
            throw e
        }
    }

    async function deleteDeck(path: string) {
        try {
            await api.deleteDeck(path)
            showSuccess(t('flashcards', 'Deck deleted'))
            if (currentPath.value === path) {
                currentPath.value = null
                currentCards.value = []
            }
            await loadDecks()
        } catch (e) {
            showError(e instanceof Error ? e.message : t('flashcards', 'Failed to delete deck'))
            throw e
        }
    }

    function updateCardLocally(index: number, card: ParsedCard) {
        if (index >= 0 && index < currentCards.value.length) {
            currentCards.value[index] = card
            dirty.value = true
        }
    }

    function updateDueCount(path: string, dueCount: number) {
        const deck = decks.value.find(d => d.path === path)
        if (deck) {
            deck.dueCards = toSafeNumber(dueCount)
        }
    }

    return {
        decks,
        folders,
        loading,
        error,
        currentPath,
        currentCards,
        currentTag,
        currentDeck,
        dirty,
        totalDue,
        totalNew,
        loadDecks,
        loadFolders,
        openDeck,
        saveDeck,
        closeDeck,
        createDeck,
        deleteDeck,
        updateCardLocally,
        updateDueCount,
    }
})
