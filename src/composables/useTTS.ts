/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Flashcards v2 — useTTS composable (reused from v1)
 */

import { ref, computed } from 'vue'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

export type TTSEngine = 'edge-tts' | 'browser' | 'none'

export interface TTSVoice {
    id: string
    name: string
    language: string
    languageName: string
    gender: string
}

const OCS_BASE = '/ocs/v2.php/apps/flashcards/api/v1'

export function useTTS() {
    const speaking = ref(false)
    const loading = ref(false)
    const engine = ref<TTSEngine>('browser')
    const engineAvailable = ref(false)
    const voices = ref<TTSVoice[]>([])
    const error = ref<string | null>(null)
    const browserSupported = ref(typeof window !== 'undefined' && 'speechSynthesis' in window)

    let currentUtterance: SpeechSynthesisUtterance | null = null
    let currentAudio: HTMLAudioElement | null = null

    async function init(): Promise<void> {
        try {
            const url = generateUrl(OCS_BASE + '/tts/voices')
            const response = await axios.get(url, { headers: { 'OCS-APIRequest': 'true' } })
            const data = response.data?.ocs?.data ?? response.data
            engineAvailable.value = data.available ?? false
            engine.value = data.available ? 'edge-tts' : (browserSupported.value ? 'browser' : 'none')
            voices.value = data.voices ?? []
        } catch {
            engineAvailable.value = false
            engine.value = browserSupported.value ? 'browser' : 'none'
        }
    }

    const supported = computed(() => engine.value !== 'none')

    async function speak(text: string, lang = 'en-US', voice?: string): Promise<void> {
        if (!text.trim()) return
        stop()
        error.value = null
        if (engine.value === 'edge-tts') {
            await speakEdgeTTS(text, lang, voice)
        } else if (engine.value === 'browser') {
            speakBrowser(text, lang)
        }
    }

    async function speakEdgeTTS(text: string, lang: string, voice?: string): Promise<void> {
        loading.value = true
        try {
            const synthesizeUrl = generateUrl(OCS_BASE + '/tts/synthesize')
            const response = await axios.post(synthesizeUrl, {
                text, language: lang, voice: voice ?? undefined,
            }, { headers: { 'OCS-APIRequest': 'true' } })
            const data = response.data?.ocs?.data ?? response.data
            const audioId = data.id
            if (!audioId) throw new Error('No audio ID returned')
            const audioUrl = generateUrl(OCS_BASE + '/tts/audio/{id}', { id: audioId })
            await playAudioUrl(audioUrl)
        } catch (e) {
            error.value = e instanceof Error ? e.message : 'TTS synthesis failed'
            if (browserSupported.value) speakBrowser(text, lang)
        } finally {
            loading.value = false
        }
    }

    function speakBrowser(text: string, lang: string): void {
        if (!browserSupported.value) return
        const utterance = new SpeechSynthesisUtterance(text)
        utterance.lang = lang
        utterance.rate = 0.9
        utterance.onstart = () => { speaking.value = true }
        utterance.onend = () => { speaking.value = false }
        utterance.onerror = () => { speaking.value = false }
        currentUtterance = utterance
        speechSynthesis.speak(utterance)
    }

    function playAudioUrl(url: string): Promise<void> {
        return new Promise((resolve, reject) => {
            const audio = new Audio(url)
            currentAudio = audio
            audio.onplay = () => { speaking.value = true }
            audio.onended = () => { speaking.value = false; currentAudio = null; resolve() }
            audio.onerror = () => { speaking.value = false; currentAudio = null; reject(new Error('Audio playback failed')) }
            audio.play().catch(reject)
        })
    }

    function stop(): void {
        if (browserSupported.value) speechSynthesis.cancel()
        currentUtterance = null
        if (currentAudio) {
            currentAudio.pause()
            currentAudio.currentTime = 0
            currentAudio = null
        }
        speaking.value = false
    }

    function getBrowserVoices(lang?: string): SpeechSynthesisVoice[] {
        if (!browserSupported.value) return []
        const allVoices = speechSynthesis.getVoices()
        if (!lang) return allVoices
        return allVoices.filter(v => v.lang.startsWith(lang))
    }

    return { speaking, loading, supported, engine, engineAvailable, voices, error, init, speak, stop, getBrowserVoices }
}
