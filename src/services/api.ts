/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Flashcards v2 — API Service
 *
 * HTTP client for all backend endpoints.
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import type { DeckMeta, DeckOpenResult, FolderInfo } from '@/types/deck'
import type { ParsedCard } from '@/types/card'
import type {
    ReviewAnswer,
    ReviewResult,
    IntervalPrediction,
    UserSettings,
    OverviewStats,
    DeckStats,
    DueCount,
} from '@/types/sr'

const OCS = '/ocs/v2.php/apps/flashcards/api/v1'
const HEADERS = { 'OCS-APIRequest': 'true' }

function url(path: string): string {
    return generateUrl(OCS + path)
}

function extract<T>(response: { data: any }): T {
    return response.data?.ocs?.data ?? response.data
}

// ─── Decks ────────────────────────────────────────────────

export async function fetchDecks(): Promise<DeckMeta[]> {
    const resp = await axios.get(url('/decks'), { headers: HEADERS })
    return extract<DeckMeta[]>(resp)
}

export async function openDeck(path: string): Promise<DeckOpenResult> {
    const resp = await axios.get(url('/decks/open'), {
        headers: HEADERS,
        params: { path },
    })
    return extract<DeckOpenResult>(resp)
}

export async function saveDeck(path: string): Promise<{ saved: boolean }> {
    const resp = await axios.post(url('/decks/save'), { path }, { headers: HEADERS })
    return extract(resp)
}

export async function closeDeck(path: string): Promise<void> {
    await axios.post(url('/decks/close'), { path }, { headers: HEADERS })
}

export async function createDeck(name: string, folder?: string): Promise<{ path: string }> {
    const resp = await axios.post(url('/decks'), { name, folder }, { headers: HEADERS })
    return extract(resp)
}

export async function deleteDeck(path: string): Promise<void> {
    await axios.delete(url('/decks'), { headers: HEADERS, params: { path } })
}

export async function fetchFolders(subPath?: string): Promise<FolderInfo[]> {
    const resp = await axios.get(url('/decks/folders'), {
        headers: HEADERS,
        params: { subPath },
    })
    return extract<FolderInfo[]>(resp)
}

// ─── Cards ────────────────────────────────────────────────

export async function fetchCards(path: string): Promise<ParsedCard[]> {
    const resp = await axios.get(url('/cards'), {
        headers: HEADERS,
        params: { path },
    })
    return extract<ParsedCard[]>(resp)
}

export async function fetchDueCards(path: string): Promise<ParsedCard[]> {
    const resp = await axios.get(url('/cards/due'), {
        headers: HEADERS,
        params: { path },
    })
    return extract<ParsedCard[]>(resp)
}

export async function createCard(path: string, data: Record<string, string>): Promise<ParsedCard> {
    const resp = await axios.post(url('/cards'), { path, ...data }, { headers: HEADERS })
    return extract<ParsedCard>(resp)
}

export async function updateCard(path: string, index: number, data: Record<string, string>): Promise<void> {
    await axios.put(url('/cards/' + index), { path, ...data }, { headers: HEADERS })
}

export async function deleteCard(path: string, index: number): Promise<void> {
    await axios.delete(url('/cards/' + index), { headers: HEADERS, params: { path } })
}

// ─── Review ───────────────────────────────────────────────

export async function submitReview(answer: ReviewAnswer): Promise<ReviewResult> {
    const resp = await axios.post(url('/review'), answer, { headers: HEADERS })
    return extract<ReviewResult>(resp)
}

export async function predictIntervals(
    path: string,
    cardIndex: number,
    srIndex = 0,
): Promise<Record<number, IntervalPrediction>> {
    const resp = await axios.get(url('/review/predict'), {
        headers: HEADERS,
        params: { path, cardIndex, srIndex },
    })
    return extract(resp)
}

// ─── Stats ────────────────────────────────────────────────

export async function fetchOverviewStats(): Promise<OverviewStats> {
    const resp = await axios.get(url('/stats/overview'), { headers: HEADERS })
    return extract<OverviewStats>(resp)
}

export async function fetchDeckStats(path: string): Promise<DeckStats> {
    const resp = await axios.get(url('/stats/deck'), {
        headers: HEADERS,
        params: { path },
    })
    return extract<DeckStats>(resp)
}

export async function fetchDueCounts(): Promise<DueCount[]> {
    const resp = await axios.get(url('/stats/due-counts'), { headers: HEADERS })
    return extract<DueCount[]>(resp)
}

// ─── Settings ─────────────────────────────────────────────

export async function fetchSettings(): Promise<UserSettings> {
    const resp = await axios.get(url('/settings'), { headers: HEADERS })
    return extract<UserSettings>(resp)
}

export async function updateSettings(settings: Partial<UserSettings>): Promise<UserSettings> {
    const resp = await axios.put(url('/settings'), settings, { headers: HEADERS })
    return extract<UserSettings>(resp)
}
