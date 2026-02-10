/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Flashcards v2 — Card Types
 */

export interface SREntry {
    date: string       // YYYY-MM-DD
    interval: number   // days
    ease: number       // ease × 100 (e.g. 250 = 2.5)
}

export type CardType = 'basic' | 'cloze'
export type CardState = 'new' | 'due' | 'review'

export interface ClozeItem {
    word: string
    hint: string
}

export interface BaseCard {
    index: number
    type: CardType
    line: number
    rawLine: string
    sr: SREntry[]
    srRaw: string
    srLine: number
    state: CardState
    context: string[][]
}

export interface BasicCard extends BaseCard {
    type: 'basic'
    front: string
    back: string
    transcription?: string
    examples?: string[][]
}

export interface ClozeCard extends BaseCard {
    type: 'cloze'
    sentence: string
    clozes: ClozeItem[]
    translation?: string
}

export type ParsedCard = BasicCard | ClozeCard

export function isBasicCard(card: ParsedCard): card is BasicCard {
    return card.type === 'basic'
}

export function isClozeCard(card: ParsedCard): card is ClozeCard {
    return card.type === 'cloze'
}
