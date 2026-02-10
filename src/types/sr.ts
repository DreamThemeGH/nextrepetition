/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Flashcards v2 — SR / Review Types
 */

import type { SREntry } from './card'

export interface ReviewAnswer {
    path: string
    cardIndex: number
    rating: Rating
    srIndex?: number
}

export type Rating = 0 | 1 | 2 | 3 | 4

export const RATING_LABELS: Record<Rating, string> = {
    0: 'Again',
    1: 'Hard',
    2: 'Good',
    3: 'Easy',
    4: 'Perfect',
}

export const RATING_COLORS: Record<Rating, string> = {
    0: '#c92a2a',
    1: '#e67700',
    2: '#2d8c3c',
    3: '#1971c2',
    4: '#6741d9',
}

export interface ReviewResult {
    sr: SREntry[]
    cardIndex: number
}

export interface IntervalPrediction {
    interval: number
    label: string
    date: string
}

export interface UserSettings {
    deckFolder: string
    cardLayout: 'classic' | 'compact' | 'minimal'
    buttonPosition: 'bottom' | 'right'
    showProgress: boolean
    autoPlayAudio: boolean
    keyboardShortcuts: boolean
    fullscreenMode: boolean
    autoSaveInterval: number
    theme: 'auto' | 'light' | 'dark'
    defaultLanguage: string
    ttsVoice: string
    cardsPerDay: number
    newCardsPerDay: number
}

export interface OverviewStats {
    totalDecks: number
    totalCards: number
    totalDue: number
    totalNew: number
    totalReviewed: number
    decks: Array<{
        name: string
        path: string
        total: number
        due: number
        new: number
    }>
}

export interface DeckStats {
    name: string
    path: string
    totalCards: number
    states: { new: number; due: number; review: number }
    conflicts: number
    averageInterval: number
    averageEase: number
    maxInterval: number
    dueForecast: Record<number, number>
    intervalDistribution: Record<string, number>
}

export interface DueCount {
    name: string
    path: string
    due: number
    new: number
    total: number
}
