<template>
    <div class="study-page"
        :class="[
            'layout-' + cardLayout,
            { 'study-fullscreen': fullscreenMode, 'buttons-right': buttonPosition === 'right' }
        ]">
        <!-- Loading -->
        <div v-if="studyStore.loading && !studyStore.currentCard" class="study-loading">
            <NcLoadingIcon :size="44" />
            <p>{{ t('flashcards', 'Loading cards...') }}</p>
        </div>

        <!-- Empty queue -->
        <div v-else-if="studyStore.queue.length === 0 && !studyStore.loading" class="study-empty">
            <h2>🎉 {{ t('flashcards', 'No cards to review!') }}</h2>
            <p>{{ t('flashcards', 'All cards in this deck are up to date.') }}</p>
            <NcButton type="primary" @click="goBack">
                {{ t('flashcards', 'Back to decks') }}
            </NcButton>
        </div>

        <!-- Session complete -->
        <div v-else-if="studyStore.isSessionComplete" class="study-complete">
            <h2>🏆 {{ t('flashcards', 'Session complete!') }}</h2>
            <div class="session-stats">
                <div class="session-stat">
                    <div class="session-stat-value">{{ studyStore.sessionStats.reviewed }}</div>
                    <div class="session-stat-label">{{ t('flashcards', 'Reviewed') }}</div>
                </div>
                <div class="session-stat">
                    <div class="session-stat-value correct">{{ studyStore.sessionStats.correct }}</div>
                    <div class="session-stat-label">{{ t('flashcards', 'Correct') }}</div>
                </div>
                <div class="session-stat">
                    <div class="session-stat-value again">{{ studyStore.sessionStats.again }}</div>
                    <div class="session-stat-label">{{ t('flashcards', 'Again') }}</div>
                </div>
            </div>
            <div class="session-actions">
                <NcButton @click="goBack">{{ t('flashcards', 'Back to decks') }}</NcButton>
                <NcButton type="primary" @click="restartSession">
                    {{ t('flashcards', 'Study again') }}
                </NcButton>
            </div>
        </div>

        <!-- Active study card -->
        <div v-else-if="studyStore.currentCard" class="study-active">
            <!-- Back button + deck name header -->
            <div class="study-header">
                <NcButton type="tertiary"
                    :aria-label="t('flashcards', 'Back to decks')"
                    @click="goBack"
                    class="back-button">
                    <template #icon>
                        <IconArrowLeft :size="20" />
                    </template>
                </NcButton>
                <span class="study-deck-name" :title="currentDeckName">{{ currentDeckName }}</span>
            </div>

            <!-- Progress bar -->
            <div class="study-progress" v-if="settingsStore.get('showProgress')"
                role="progressbar"
                :aria-valuenow="studyStore.progress.percent"
                :aria-valuemin="0"
                :aria-valuemax="100"
                :aria-label="t('flashcards', 'Study progress')">
                <div class="progress-bar">
                    <div class="progress-fill" :style="{ width: studyStore.progress.percent + '%' }"></div>
                </div>
                <span class="progress-text">
                    {{ studyStore.progress.current }} / {{ studyStore.progress.total }}
                </span>
            </div>

            <!-- Card display -->
            <div class="card-container"
                role="button"
                tabindex="0"
                :aria-label="studyStore.isFlipped ? t('flashcards', 'Flashcard (revealed)') : t('flashcards', 'Tap to reveal')"
                @click="handleCardClick"
                @keydown.enter="handleCardClick"
                @keydown.space.prevent="handleCardClick">
                <div class="flashcard"
                    :key="studyStore.currentIndex"
                    :class="{ flipped: studyStore.isFlipped }">
                    <!-- Front -->
                    <div class="card-face card-front">
                        <div class="card-content">
                            <template v-if="isBasic(studyStore.currentCard)">
                                <div class="card-word">
                                    {{ studyStore.isReversed
                                        ? studyStore.currentCard.back
                                        : studyStore.currentCard.front }}
                                </div>
                                <div v-if="!studyStore.isReversed && studyStore.currentCard.transcription"
                                    class="card-transcription">
                                    [{{ studyStore.currentCard.transcription }}]
                                </div>
                            </template>
                            <template v-else-if="isCloze(studyStore.currentCard)">
                                <div class="card-sentence" v-html="renderCloze(studyStore.currentCard, false)"></div>
                                <div v-if="studyStore.currentCard.translation" class="card-translation">
                                    {{ studyStore.currentCard.translation }}
                                </div>
                            </template>
                        </div>
                        <div class="card-hint">
                            {{ t('flashcards', 'Tap to reveal') }}
                        </div>
                    </div>

                    <!-- Back -->
                    <div class="card-face card-back">
                        <div class="card-content">
                            <template v-if="isBasic(studyStore.currentCard)">
                                <!-- Show original word/question first (smaller, at top) -->
                                <div class="card-word-original">
                                    {{ studyStore.isReversed
                                        ? studyStore.currentCard.back
                                        : studyStore.currentCard.front }}
                                </div>
                                <div v-if="!studyStore.isReversed && studyStore.currentCard.transcription"
                                    class="card-transcription-top">
                                    [{{ studyStore.currentCard.transcription }}]
                                </div>
                                
                                <!-- Then show the answer (larger, in center) -->
                                <div class="card-word-answer">
                                    {{ studyStore.isReversed
                                        ? studyStore.currentCard.front
                                        : studyStore.currentCard.back }}
                                </div>
                                <div v-if="studyStore.isReversed && studyStore.currentCard.transcription"
                                    class="card-transcription">
                                    [{{ studyStore.currentCard.transcription }}]
                                </div>
                                
                                <!-- Examples/Comments below -->
                                <div v-if="studyStore.currentCard.examples?.length"
                                    class="card-examples">
                                    <div v-for="(ex, i) in studyStore.currentCard.examples"
                                        :key="i"
                                        class="card-example">
                                        <div v-for="(line, j) in ex" :key="j">{{ line }}</div>
                                    </div>
                                </div>
                            </template>
                            <template v-else-if="isCloze(studyStore.currentCard)">
                                <!-- Show revealed sentence with answers -->
                                <div class="card-sentence-revealed" v-html="renderCloze(studyStore.currentCard, true)"></div>
                                <!-- Translation if present -->
                                <div v-if="studyStore.currentCard.translation" class="card-translation">
                                    {{ studyStore.currentCard.translation }}
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Study controls -->
            <div class="study-controls">
                <!-- Reverse direction button (basic cards only) -->
                <NcButton v-if="isBasic(studyStore.currentCard)"
                    :aria-label="t('flashcards', 'Reverse direction')"
                    :aria-pressed="String(studyStore.isReversed)"
                    @click="studyStore.toggleDirection()">
                    <template #icon>
                        <IconSwap :size="20" />
                    </template>
                </NcButton>
                <!-- TTS button -->
                <NcButton v-if="tts.supported.value"
                    @click="speakCard"
                    :disabled="tts.speaking.value"
                    :aria-label="t('flashcards', 'Read aloud')">
                    <template #icon>
                        <IconVolume :size="20" />
                    </template>
                </NcButton>
            </div>

            <!-- Rating buttons (shown after flip) -->
            <div v-if="studyStore.isFlipped" class="rating-container">
                <!-- Again button (separate, skips card) -->
                <button class="rating-btn rating-again"
                    :style="{ '--rating-color': againRating.color }"
                    :aria-label="againRating.label"
                    @click="submitRating(againRating.value)"
                    :disabled="studyStore.loading">
                    <span class="rating-label">{{ againRating.label }}</span>
                    <span class="rating-hint">{{ t('flashcards', 'Skip without changes') }}</span>
                </button>

                <!-- Main rating buttons -->
                <div class="rating-buttons">
                    <button v-for="rating in ratings"
                        :key="rating.value"
                        class="rating-btn"
                        :style="{ '--rating-color': rating.color }"
                        :aria-label="rating.label + (studyStore.predictions[rating.value] ? ' — ' + studyStore.predictions[rating.value].label : '')"
                        @click="submitRating(rating.value)"
                        :disabled="studyStore.loading">
                        <span class="rating-label">{{ rating.label }}</span>
                        <span class="rating-interval" v-if="studyStore.predictions[rating.value]">
                            {{ studyStore.predictions[rating.value].label }}
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { onMounted, onUnmounted, watch, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { translate as t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import IconVolume from 'vue-material-design-icons/VolumeHigh.vue'
import IconSwap from 'vue-material-design-icons/SwapHorizontal.vue'
import IconArrowLeft from 'vue-material-design-icons/ArrowLeft.vue'

import { useStudyStore } from '@/stores/study'
import { useSettingsStore } from '@/stores/settings'
import { useDeckStore } from '@/stores/deck'
import { useTTS } from '@/composables/useTTS'
import { useKeyboard } from '@/composables/useKeyboard'
import { isBasicCard, isClozeCard } from '@/types/card'
import type { BasicCard, ClozeCard, ParsedCard } from '@/types/card'
import type { Rating } from '@/types/sr'
import { RATING_LABELS, RATING_COLORS } from '@/types/sr'

const props = defineProps<{ path: string }>()
const route = useRoute()
const router = useRouter()
const studyStore = useStudyStore()
const settingsStore = useSettingsStore()
const deckStore = useDeckStore()
const tts = useTTS()

const cardLayout = computed(() => settingsStore.get('cardLayout') || 'classic')
const fullscreenMode = computed(() => settingsStore.get('fullscreenMode') === true)
const buttonPosition = computed(() => settingsStore.get('buttonPosition') || 'bottom')

const currentDeckName = computed(() => {
    const deck = deckStore.currentDeck
    if (deck) {
        // Extract just the folder name from the path
        const parts = deck.path.split('/')
        return parts[parts.length - 1] || deck.path
    }
    // Fallback: extract from route path
    const parts = props.path.split('/')
    return parts[parts.length - 1] || props.path
})

const ratings = [
    { value: 1 as Rating, label: t('flashcards', 'Hard'), color: RATING_COLORS[1] },
    { value: 2 as Rating, label: t('flashcards', 'Good'), color: RATING_COLORS[2] },
    { value: 3 as Rating, label: t('flashcards', 'Easy'), color: RATING_COLORS[3] },
]

const againRating = { value: 0 as Rating, label: t('flashcards', 'Again'), color: RATING_COLORS[0] }

function isBasic(card: ParsedCard): card is BasicCard {
    return isBasicCard(card)
}

function isCloze(card: ParsedCard): card is ClozeCard {
    return isClozeCard(card)
}

function renderCloze(card: ClozeCard, showAnswer: boolean): string {
    // Build HTML from the clozes array — no need to re-parse the sentence
    // Split the raw sentence around cloze patterns, escape non-cloze parts
    const clozePattern = /==([^=]+)==(?:\^\[([^\]]*)\])?/g
    const parts: string[] = []
    let lastIndex = 0
    let clozeIdx = 0
    let match: RegExpExecArray | null

    while ((match = clozePattern.exec(card.sentence)) !== null) {
        // Text before this cloze — escape it
        if (match.index > lastIndex) {
            parts.push(escapeHtml(card.sentence.slice(lastIndex, match.index)))
        }

        const cloze = card.clozes[clozeIdx] ?? { word: match[1], hint: match[2] || '' }
        if (showAnswer) {
            parts.push(`<span class="cloze-revealed">${escapeHtml(cloze.word)}</span>`)
        } else {
            const hint = cloze.hint
                ? `<span class="cloze-hint">${escapeHtml(cloze.hint)}</span>`
                : '...'
            parts.push(`<span class="cloze-blank">[${hint}]</span>`)
        }

        lastIndex = match.index + match[0].length
        clozeIdx++
    }

    // Remaining text after last cloze
    if (lastIndex < card.sentence.length) {
        parts.push(escapeHtml(card.sentence.slice(lastIndex)))
    }

    return parts.join('')
}

function escapeHtml(text: string): string {
    return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
}

function handleCardClick() {
    if (!studyStore.isFlipped) {
        studyStore.flipCard()
    }
}

function submitRating(rating: Rating) {
    studyStore.submitRating(rating)
}

async function speakCard() {
    const card = studyStore.currentCard
    if (!card) return

    let text = ''
    if (isBasicCard(card)) {
        text = studyStore.isReversed ? card.back : card.front
    } else if (isClozeCard(card)) {
        // Speak the full sentence with cloze words
        text = card.sentence.replace(/==([^=]+)==(?:\^\[[^\]]*\])?/g, '$1')
    }

    if (text) {
        await tts.speak(text, settingsStore.get('defaultLanguage') || 'en-US')
    }
}

function goBack() {
    // Navigate back to deck browser - the deck store will keep the current deck highlighted
    router.push({ name: 'decks' })
}

async function restartSession() {
    await studyStore.startSession(props.path)
}

// Keyboard shortcuts (space is handled in template to avoid duplication)
// 1=Again, 2=Hard, 3=Good, 4=Easy
useKeyboard([
    { key: '1', handler: () => { if (studyStore.isFlipped) submitRating(0) } },
    { key: '2', handler: () => { if (studyStore.isFlipped) submitRating(1) } },
    { key: '3', handler: () => { if (studyStore.isFlipped) submitRating(2) } },
    { key: '4', handler: () => { if (studyStore.isFlipped) submitRating(3) } },
    { key: 'r', handler: () => speakCard() },
])

// Auto-play audio when a new card appears
watch(() => studyStore.currentCard, (card) => {
    if (card && settingsStore.get('autoPlayAudio') && tts.supported.value) {
        speakCard()
    }
})

onMounted(async () => {
    await tts.init()
    const path = props.path || (route.params.path as string)
    if (path) {
        await studyStore.startSession(path)
    }
})

onUnmounted(() => {
    tts.stop()
})
</script>

<style lang="scss" scoped>
.study-page {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 20px;
    min-height: calc(100vh - 50px);
}

.study-loading, .study-empty, .study-complete {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px 20px;
    text-align: center;
    gap: 16px;
}

.session-stats {
    display: flex;
    gap: 32px;
    margin: 20px 0;
}

.session-stat-value {
    font-size: 2em;
    font-weight: 700;

    &.correct { color: $flashcards-success; }
    &.again { color: $flashcards-danger; }
}

.session-stat-label {
    color: var(--color-text-maxcontrast);
}

.session-actions {
    display: flex;
    gap: 12px;
    margin-top: 16px;
}

.study-active {
    width: 100%;
    max-width: $card-max-width;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.study-header {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 4px;
    margin-bottom: 8px;

    .back-button {
        flex-shrink: 0;
        min-width: 36px !important;
        min-height: 36px !important;
        padding: 0 !important;
    }

    .study-deck-name {
        font-size: 0.9em;
        color: var(--color-text-maxcontrast);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        min-width: 0;
    }
}

.study-progress {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
}

.progress-bar {
    flex: 1;
    height: 6px;
    background: var(--color-background-dark);
    border-radius: 3px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: var(--color-primary);
    border-radius: 3px;
    transition: width $transition-normal;
}

.progress-text {
    font-size: 0.85em;
    color: var(--color-text-maxcontrast);
    white-space: nowrap;
}

.card-container {
    width: 100%;
    min-height: $card-min-height;
    perspective: 1000px;
    cursor: pointer;
    margin-bottom: 20px;
}

.flashcard {
    width: 100%;
    min-height: $card-min-height;
    position: relative;
    transform-style: preserve-3d;
    transition: transform $transition-flip;

    &.flipped {
        transform: rotateX(180deg);
    }
}

.card-face {
    width: 100%;
    min-height: $card-min-height;
    position: absolute;
    top: 0;
    left: 0;
    backface-visibility: hidden;
    background: var(--color-main-background);
    border: 2px solid var(--color-border);
    border-radius: $card-border-radius;
    padding: $card-padding;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
}

.card-back {
    transform: rotateX(180deg);
}

.card-content {
    text-align: center;
    width: 100%;
}

.card-word-original {
    font-size: 1.1em;
    font-weight: 500;
    margin-bottom: 8px;
    color: var(--color-text-maxcontrast);
    word-break: break-word;
}

.card-transcription-top {
    font-size: 0.9em;
    color: var(--color-text-maxcontrast);
    font-style: italic;
    margin-bottom: 16px;
}

.card-word-answer {
    font-size: 2em;
    font-weight: 700;
    margin-bottom: 8px;
    margin-top: 12px;
    word-break: break-word;
    color: var(--color-primary);
}

.card-word {
    font-size: 1.8em;
    font-weight: 700;
    margin-bottom: 8px;
    word-break: break-word;
}

.card-transcription {
    font-size: 1.1em;
    color: var(--color-text-maxcontrast);
    font-style: italic;
}

.card-sentence {
    font-size: 1.3em;
    line-height: 1.6;
}

.card-sentence-revealed {
    font-size: 1.4em;
    line-height: 1.6;
    font-weight: 600;
}

.card-translation {
    font-size: 1em;
    color: var(--color-text-maxcontrast);
    margin-top: 12px;
}

.card-examples {
    margin-top: 20px;
    font-size: 0.95em;
    color: var(--color-text-light);
}

.card-example {
    margin-bottom: 8px;
    padding: 8px;
    background: var(--color-background-dark);
    border-radius: 6px;
}

.card-hint {
    font-size: 0.85em;
    color: var(--color-text-maxcontrast);
    margin-top: 16px;
}

:deep(.cloze-blank) {
    display: inline-block;
    min-width: 60px;
    padding: 2px 8px;
    background: var(--color-primary-element-light);
    border-radius: 4px;
    font-weight: 600;
}

:deep(.cloze-hint) {
    font-style: italic;
    font-size: 0.9em;
}

:deep(.cloze-revealed) {
    font-weight: 700;
    color: var(--color-primary);
    text-decoration: underline;
}

.study-controls {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-bottom: 12px;
}

.rating-container {
    display: flex;
    flex-direction: column;
    gap: 12px;
    width: 100%;
    align-items: center;
}

.rating-again {
    width: 100%;
    max-width: 600px;
    opacity: 0.85;

    .rating-hint {
        font-size: 0.75em;
        color: var(--color-text-maxcontrast);
        font-weight: 400;
    }
}

.rating-buttons {
    display: flex;
    gap: 8px;
    width: 100%;
    max-width: 600px;
    justify-content: center;
    flex-wrap: wrap;
}

.rating-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 12px 20px;
    border: 2px solid var(--rating-color);
    border-radius: 10px;
    background: transparent;
    color: var(--color-main-text);
    cursor: pointer;
    transition: background $transition-fast, transform $transition-fast;
    min-width: 80px;

    &:hover {
        background: color-mix(in srgb, var(--rating-color) 15%, transparent);
        transform: translateY(-2px);
    }

    &:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
}

.rating-label {
    font-weight: 700;
    font-size: 0.95em;
    color: var(--rating-color);
}

.rating-interval {
    font-size: 0.8em;
    color: var(--color-text-maxcontrast);
    margin-top: 4px;
}

// Layout variants
.layout-compact {
    .flashcard {
        min-height: 200px;
    }
    .card-word {
        font-size: 1.4rem;
    }
}

.layout-minimal {
    .flashcard {
        min-height: 150px;
        box-shadow: none;
        border: 1px solid var(--color-border);
    }
    .card-word {
        font-size: 1.2rem;
    }
    .card-hint, .card-transcription, .card-translation {
        display: none;
    }
}

// Fullscreen mode
.study-fullscreen {
    position: fixed;
    inset: 0;
    z-index: 1000;
    background: var(--color-main-background);
    padding: 20px;
    overflow-y: auto;
}

// Buttons on the right
.buttons-right {
    .study-active {
        display: grid;
        grid-template-columns: 1fr auto;
        grid-template-rows: auto 1fr auto;
        gap: 16px;
    }
    .study-progress { grid-column: 1 / -1; }
    .card-container { grid-column: 1; grid-row: 2; }
    .rating-buttons {
        grid-column: 2;
        grid-row: 2;
        flex-direction: column;
        width: auto;
    }
}
</style>
