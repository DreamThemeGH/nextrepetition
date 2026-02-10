/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Flashcards v2 — Settings Store
 */

import { defineStore } from 'pinia'
import { ref } from 'vue'
import type { UserSettings } from '@/types/sr'
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
}

export const useSettingsStore = defineStore('settings', () => {
    const settings = ref<UserSettings>({ ...DEFAULT_SETTINGS })
    const loading = ref(false)
    const loaded = ref(false)

    async function load() {
        if (loaded.value) return
        loading.value = true
        try {
            settings.value = await api.fetchSettings()
            loaded.value = true
        } catch {
            settings.value = { ...DEFAULT_SETTINGS }
        } finally {
            loading.value = false
        }
    }

    async function save(updates: Partial<UserSettings>) {
        loading.value = true
        try {
            settings.value = await api.updateSettings(updates)
        } finally {
            loading.value = false
        }
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
    }
})
