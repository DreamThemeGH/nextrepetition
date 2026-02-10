<template>
    <div class="flashcards-page">
        <div class="flashcards-page-header">
            <div class="header-left">
                <NcButton @click="goBack">
                    <template #icon><IconBack :size="20" /></template>
                </NcButton>
                <h2>{{ deckName }}</h2>
            </div>
            <div class="header-right">
                <NcButton @click="showAddCard = true">
                    <template #icon><IconPlus :size="20" /></template>
                    {{ t('flashcards', 'Add card') }}
                </NcButton>
                <NcButton v-if="hasdue" type="primary" @click="startStudy">
                    {{ t('flashcards', 'Study') }} ({{ dueCount }})
                </NcButton>
            </div>
        </div>

        <div v-if="loading" class="loading-center">
            <NcLoadingIcon :size="44" />
        </div>

        <div v-else-if="cards.length === 0" class="empty-state">
            <p>{{ t('flashcards', 'This deck has no cards yet.') }}</p>
        </div>

        <!-- Card list -->
        <div v-else class="card-list">
            <div class="card-filters">
                <NcTextField :value.sync="search"
                    :placeholder="t('flashcards', 'Search cards...')"
                    class="search-input" />
                <select v-model="stateFilter" class="state-filter">
                    <option value="">{{ t('flashcards', 'All states') }}</option>
                    <option value="new">{{ t('flashcards', 'New') }}</option>
                    <option value="due">{{ t('flashcards', 'Due') }}</option>
                    <option value="review">{{ t('flashcards', 'Learned') }}</option>
                </select>
            </div>

            <div class="card-table">
                <div v-for="card in filteredCards"
                    :key="card.index"
                    class="card-row"
                    @click="selectedCard = card">
                    <div class="card-state-badge" :class="'state-' + card.state">
                        {{ card.state }}
                    </div>
                    <div class="card-preview">
                        <template v-if="card.type === 'basic'">
                            <span class="card-front-text">{{ (card as any).front }}</span>
                            <span class="card-separator">→</span>
                            <span class="card-back-text">{{ (card as any).back }}</span>
                        </template>
                        <template v-else>
                            <span class="card-front-text">{{ card.rawLine?.substring(0, 80) }}</span>
                        </template>
                    </div>
                    <div class="card-sr-info" v-if="card.sr.length > 0">
                        {{ card.sr[0].date }} · {{ card.sr[0].interval }}d
                    </div>
                </div>
            </div>
        </div>

        <!-- Add card dialog -->
        <NcDialog v-if="showAddCard"
            :name="t('flashcards', 'Add new card')"
            @closing="showAddCard = false">
            <div class="add-card-form">
                <div class="form-group">
                    <label>{{ t('flashcards', 'Type') }}</label>
                    <select v-model="newCardType">
                        <option value="basic">{{ t('flashcards', 'Basic (front:::back)') }}</option>
                        <option value="cloze">{{ t('flashcards', 'Cloze (==word==)') }}</option>
                    </select>
                </div>

                <template v-if="newCardType === 'basic'">
                    <div class="form-group">
                        <label>{{ t('flashcards', 'Front') }}</label>
                        <NcTextField :value.sync="newFront" :placeholder="t('flashcards', 'Word or phrase')" />
                    </div>
                    <div class="form-group">
                        <label>{{ t('flashcards', 'Back') }}</label>
                        <NcTextField :value.sync="newBack" :placeholder="t('flashcards', 'Translation')" />
                    </div>
                    <div class="form-group">
                        <label>{{ t('flashcards', 'Transcription (optional)') }}</label>
                        <NcTextField :value.sync="newTranscription" placeholder="IPA" />
                    </div>
                </template>

                <template v-else>
                    <div class="form-group">
                        <label>{{ t('flashcards', 'Sentence with ==cloze==') }}</label>
                        <NcTextField :value.sync="newSentence"
                            :placeholder="t('flashcards', 'I ==like==^[люблю] pizza')" />
                    </div>
                    <div class="form-group">
                        <label>{{ t('flashcards', 'Translation') }}</label>
                        <NcTextField :value.sync="newTranslation" :placeholder="t('flashcards', 'Я люблю пиццу')" />
                    </div>
                </template>
            </div>
            <template #actions>
                <NcButton @click="showAddCard = false">{{ t('flashcards', 'Cancel') }}</NcButton>
                <NcButton type="primary" @click="handleAddCard">{{ t('flashcards', 'Add') }}</NcButton>
            </template>
        </NcDialog>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { translate as t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import IconBack from 'vue-material-design-icons/ArrowLeft.vue'
import IconPlus from 'vue-material-design-icons/Plus.vue'

import { useDeckStore } from '@/stores/deck'
import * as api from '@/services/api'
import type { ParsedCard } from '@/types/card'

const props = defineProps<{ path: string }>()
const route = useRoute()
const router = useRouter()
const deckStore = useDeckStore()

const loading = ref(true)
const cards = ref<ParsedCard[]>([])
const search = ref('')
const stateFilter = ref('')
const selectedCard = ref<ParsedCard | null>(null)

// Add card form
const showAddCard = ref(false)
const newCardType = ref('basic')
const newFront = ref('')
const newBack = ref('')
const newTranscription = ref('')
const newSentence = ref('')
const newTranslation = ref('')

const deckName = computed(() => {
    const p = props.path || (route.params.path as string)
    return p ? p.split('/').pop()?.replace('.md', '') || 'Deck' : 'Deck'
})

const hasdue = computed(() => cards.value.some(c => c.state === 'due' || c.state === 'new'))
const dueCount = computed(() => cards.value.filter(c => c.state === 'due' || c.state === 'new').length)

const filteredCards = computed(() => {
    let result = cards.value

    if (stateFilter.value) {
        result = result.filter(c => c.state === stateFilter.value)
    }

    if (search.value.trim()) {
        const q = search.value.toLowerCase()
        result = result.filter(c => c.rawLine.toLowerCase().includes(q))
    }

    return result
})

function goBack() {
    deckStore.closeDeck()
    router.push({ name: 'decks' })
}

function startStudy() {
    router.push({ name: 'study', params: { path: props.path } })
}

async function handleAddCard() {
    const path = props.path || (route.params.path as string)
    if (!path) return

    try {
        if (newCardType.value === 'basic') {
            await api.createCard(path, {
                type: 'basic',
                front: newFront.value,
                back: newBack.value,
                transcription: newTranscription.value,
            })
        } else {
            await api.createCard(path, {
                type: 'cloze',
                sentence: newSentence.value,
                translation: newTranslation.value,
            })
        }

        // Refresh cards
        cards.value = await api.fetchCards(path)

        // Reset form
        showAddCard.value = false
        newFront.value = ''
        newBack.value = ''
        newTranscription.value = ''
        newSentence.value = ''
        newTranslation.value = ''
    } catch (e) {
        console.error('Failed to add card:', e)
    }
}

onMounted(async () => {
    const path = props.path || (route.params.path as string)
    if (!path) return

    try {
        await deckStore.openDeck(path)
        cards.value = deckStore.currentCards
    } catch {
        cards.value = []
    } finally {
        loading.value = false
    }
})

onUnmounted(() => {
    // Don't auto-close — user might navigate to study
})
</script>

<style lang="scss" scoped>
.header-left, .header-right {
    display: flex;
    align-items: center;
    gap: 12px;
}

.loading-center {
    display: flex;
    justify-content: center;
    padding: 60px;
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: var(--color-text-maxcontrast);
}

.card-filters {
    display: flex;
    gap: 12px;
    margin-bottom: 16px;
}

.search-input {
    flex: 1;
}

.state-filter {
    padding: 8px 12px;
    border: 1px solid var(--color-border);
    border-radius: 6px;
    background: var(--color-main-background);
}

.card-table {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.card-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 16px;
    background: var(--color-background-dark);
    border-radius: 8px;
    cursor: pointer;
    transition: background 0.15s;

    &:hover {
        background: var(--color-background-hover);
    }
}

.card-state-badge {
    font-size: 0.75em;
    font-weight: 700;
    text-transform: uppercase;
    padding: 2px 8px;
    border-radius: 4px;
    min-width: 50px;
    text-align: center;

    &.state-new { background: color-mix(in srgb, $card-new 20%, transparent); color: $card-new; }
    &.state-due { background: color-mix(in srgb, $flashcards-warning 20%, transparent); color: $flashcards-warning; }
    &.state-review { background: color-mix(in srgb, $flashcards-success 20%, transparent); color: $flashcards-success; }
}

.card-preview {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.card-front-text { font-weight: 600; }
.card-separator { color: var(--color-text-maxcontrast); margin: 0 8px; }
.card-back-text { color: var(--color-text-light); }

.card-sr-info {
    font-size: 0.85em;
    color: var(--color-text-maxcontrast);
    white-space: nowrap;
}

.add-card-form {
    display: flex;
    flex-direction: column;
    gap: 16px;
    padding: 8px 0;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 4px;

    label { font-weight: 600; }
    select {
        padding: 8px;
        border: 1px solid var(--color-border);
        border-radius: 6px;
    }
}
</style>
