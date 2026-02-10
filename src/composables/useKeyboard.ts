/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Flashcards v2 — useKeyboard composable (reused from v1)
 */

import { onMounted, onUnmounted } from 'vue'

export interface KeyBinding {
    key: string
    ctrl?: boolean
    shift?: boolean
    alt?: boolean
    handler: (e: KeyboardEvent) => void
    ignoreInput?: boolean
}

const INPUT_TAGS = new Set(['INPUT', 'TEXTAREA', 'SELECT'])

function isInputFocused(): boolean {
    const el = document.activeElement
    if (!el) return false
    return INPUT_TAGS.has(el.tagName) || (el as HTMLElement).isContentEditable
}

export function useKeyboard(bindings: KeyBinding[]): void {
    function handler(e: KeyboardEvent) {
        for (const b of bindings) {
            if (e.key !== b.key) continue
            if (b.ctrl && !e.ctrlKey) continue
            if (b.shift && !e.shiftKey) continue
            if (b.alt && !e.altKey) continue
            if (b.ignoreInput !== false && isInputFocused()) continue
            e.preventDefault()
            b.handler(e)
            return
        }
    }
    onMounted(() => window.addEventListener('keydown', handler))
    onUnmounted(() => window.removeEventListener('keydown', handler))
}
