/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Flashcards v2 — Settings Store
 */

import { defineStore } from 'pinia'
import { ref } from 'vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import type { RecentDeckEntry, UserSettings } from '@/types/sr'
import * as api from '@/services/api'

const DEFAULT_SETTINGS: UserSettings = {
    deckFolder: '/ObsidianSync',
    cardLayout: 'classic',
    buttonPosition: 'bottom',
    showProgress: true,
    autoPlayAudio: false,
    keyboardShortcuts: true,
    fullscreenMode: false,
    autoSaveInterval: 10,
    theme: 'auto',
    defaultLanguage: '',
    ttsVoice: '',
    cardsPerDay: 50,
    newCardsPerDay: 20,
    favoriteDecks: [],
    recentDecks: [],
}

function normalizeRecentDecks(entries: RecentDeckEntry[]): RecentDeckEntry[] {
    const deduped = new Map<string, RecentDeckEntry>()

    for (const entry of entries) {
        if (!entry?.path || typeof entry.lastStudied !== 'number') {
            continue
        }

        const existing = deduped.get(entry.path)
        if (!existing || existing.lastStudied < entry.lastStudied) {
            deduped.set(entry.path, entry)
        }
    }

    return [...deduped.values()]
        .sort((left, right) => right.lastStudied - left.lastStudied)
        .slice(0, 20)
}

export const useSettingsStore = defineStore('settings', () => {
    const settings = ref<UserSettings>({ ...DEFAULT_SETTINGS })
    const loading = ref(false)
    const loaded = ref(false)

    async function load() {
        if (loaded.value) return
        loading.value = true
        try {
            settings.value = {
                ...DEFAULT_SETTINGS,
                ...await api.fetchSettings(),
            }
            settings.value.recentDecks = normalizeRecentDecks(settings.value.recentDecks)
            loaded.value = true
        } catch (e) {
            showError(t('flashcards', 'Failed to load settings, using defaults'))
            settings.value = { ...DEFAULT_SETTINGS }
        } finally {
            loading.value = false
        }
    }

    async function save(updates: Partial<UserSettings>, quiet = false) {
        loading.value = true
        try {
            settings.value = await api.updateSettings(updates)
            settings.value = {
                ...DEFAULT_SETTINGS,
                ...settings.value,
            }
            settings.value.recentDecks = normalizeRecentDecks(settings.value.recentDecks)
            if (!quiet) {
                showSuccess(t('flashcards', 'Settings saved'))
            }
        } catch (e) {
            showError(e instanceof Error ? e.message : t('flashcards', 'Failed to save settings'))
        } finally {
            loading.value = false
        }
    }

    async function toggleFavoriteDeck(path: string) {
        const favorites = settings.value.favoriteDecks.includes(path)
            ? settings.value.favoriteDecks.filter(deckPath => deckPath !== path)
            : [...settings.value.favoriteDecks, path]

        await save({ favoriteDecks: favorites }, true)
    }

    async function touchRecentDeck(path: string) {
        const nextRecent = normalizeRecentDecks([
            { path, lastStudied: Date.now() },
            ...settings.value.recentDecks.filter(entry => entry.path !== path),
        ])

        await save({ recentDecks: nextRecent }, true)
    }

    function get<K extends keyof UserSettings>(key: K): UserSettings[K] {
        return settings.value[key]
    }

    return {
        settings,
        loading,
        loaded,
        load,
        save,
        get,
        toggleFavoriteDeck,
        touchRecentDeck,
    }
})
