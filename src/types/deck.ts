/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Flashcards v2 — Deck Types
 */

export interface DeckMeta {
    path: string
    name: string
    folder?: string
    size: number
    modified: number
    totalCards: number
    dueCards: number
    newCards: number
}

export interface DeckOpenResult {
    cards: import('./card').ParsedCard[]
    tag: string
    totalCards: number
    conflictsResolved: number
}

export interface FolderInfo {
    name: string
    path: string
}
