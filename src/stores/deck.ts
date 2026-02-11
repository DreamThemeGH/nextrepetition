/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Flashcards v2 — Deck Store
 */

import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
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

    const totalDue = computed(() =>
        decks.value.reduce((sum, d) => sum + (d.dueCards || 0), 0),
    )

    const totalNew = computed(() =>
        decks.value.reduce((sum, d) => sum + (d.newCards || 0), 0),
    )

    const currentDeck = computed(() =>
        decks.value.find(d => d.path === currentPath.value) ?? null,
    )

    async function loadDecks() {
        loading.value = true
        error.value = null
        try {
            decks.value = await api.fetchDecks()
        } catch (e) {
            error.value = e instanceof Error ? e.message : 'Failed to load decks'
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
        const result = await api.createDeck(name, folder)
        await loadDecks()
        return result
    }

    async function deleteDeck(path: string) {
        await api.deleteDeck(path)
        if (currentPath.value === path) {
            currentPath.value = null
            currentCards.value = []
        }
        await loadDecks()
    }

    function updateCardLocally(index: number, card: ParsedCard) {
        if (index >= 0 && index < currentCards.value.length) {
            currentCards.value[index] = card
            dirty.value = true
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
    }
})
