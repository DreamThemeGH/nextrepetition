/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Flashcards v2 — useAutoSave composable
 *
 * Periodically saves the open deck buffer to .md file.
 */

import { ref, onMounted, onUnmounted, watch } from 'vue'
import { useDeckStore } from '@/stores/deck'
import { useSettingsStore } from '@/stores/settings'

export function useAutoSave() {
    const deckStore = useDeckStore()
    const settingsStore = useSettingsStore()
    const saving = ref(false)
    let timer: ReturnType<typeof setInterval> | null = null

    function start() {
        stop()
        const interval = settingsStore.get('autoSaveInterval') * 1000
        if (interval <= 0) return

        timer = setInterval(async () => {
            if (deckStore.dirty && deckStore.currentPath && !saving.value) {
                saving.value = true
                try {
                    await deckStore.saveDeck()
                } finally {
                    saving.value = false
                }
            }
        }, interval)
    }

    function stop() {
        if (timer) {
            clearInterval(timer)
            timer = null
        }
    }

    onMounted(start)
    onUnmounted(stop)

    // Restart when interval setting changes
    watch(() => settingsStore.settings.autoSaveInterval, () => {
        start()
    })

    return { saving, start, stop }
}
