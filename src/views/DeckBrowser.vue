<template>
    <div class="flashcards-page deck-browser-page">
        <div class="deck-browser-container">
        <div class="flashcards-page-header">
            <h2>{{ t('flashcards', 'Decks') }}</h2>
            <NcButton variant="primary" @click="showCreate = true">
                <template #icon><IconPlus :size="20" /></template>
                {{ t('flashcards', 'New deck') }}
            </NcButton>
        </div>

        <div v-if="deckStore.loading && deckStore.decks.length === 0" class="loading-center">
            <NcLoadingIcon :size="44" />
        </div>

        <div v-else-if="deckStore.decks.length === 0" class="empty-state">
            <h3>{{ t('flashcards', 'No decks found') }}</h3>
            <p>{{ t('flashcards', 'Place .md files with flashcards in your configured folder, or create a new deck.') }}</p>
        </div>

        <DeckTree
            v-else
            :decks="deckStore.decks"
            :loading="deckStore.loading"
            @study="startStudy"
            @browse="browseDeck"
            @reset-progress="confirmResetProgress" />

        <!-- Create deck dialog -->
        <NcDialog v-if="showCreate"
            :name="t('flashcards', 'Create new deck')"
            @closing="showCreate = false">
            <div class="create-form">
                <label>{{ t('flashcards', 'Deck name') }}</label>
                <NcTextField :value="newDeckName"
                    :placeholder="t('flashcards', 'My flashcards')"
                    @update:value="updateNewDeckName" />

                <label>{{ t('flashcards', 'Subfolder (optional)') }}</label>
                <NcTextField :value="newDeckFolder"
                    :placeholder="t('flashcards', 'e.g. Serbian learning')"
                    @update:value="updateNewDeckFolder" />
            </div>
            <template #actions>
                <NcButton @click="showCreate = false">{{ t('flashcards', 'Cancel') }}</NcButton>
                <NcButton variant="primary" @click="handleCreate" :disabled="!newDeckName.trim()">
                    {{ t('flashcards', 'Create') }}
                </NcButton>
            </template>
        </NcDialog>

        <NcDialog
            v-if="deckToReset"
            :name="t('flashcards', 'Reset progress')"
            @closing="deckToReset = null">
            <p>
                {{ t('flashcards', 'Reset all study progress for "{name}"?', { name: deckToReset.name }) }}
            </p>
            <p>
                {{ t('flashcards', 'This will remove all spaced repetition timestamps from the deck file and mark the deck as not started.') }}
            </p>
            <template #actions>
                <NcButton @click="deckToReset = null">{{ t('flashcards', 'Cancel') }}</NcButton>
                <NcButton variant="error" @click="handleResetProgress">
                    {{ t('flashcards', 'Reset progress') }}
                </NcButton>
            </template>
        </NcDialog>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { translate as t } from '@nextcloud/l10n'
import { showError, showSuccess } from '@nextcloud/dialogs'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import IconPlus from 'vue-material-design-icons/Plus.vue'

import DeckTree from '@/components/DeckTree.vue'
import { useDeckStore } from '@/stores/deck'
import type { DeckMeta } from '@/types/deck'
import * as api from '@/services/api'

const router = useRouter()
const deckStore = useDeckStore()

const showCreate = ref(false)
const newDeckName = ref('')
const newDeckFolder = ref('')
const deckToReset = ref<DeckMeta | null>(null)

function updateNewDeckName(value: string) {
    newDeckName.value = value
}

function updateNewDeckFolder(value: string) {
    newDeckFolder.value = value
}

function startStudy(path: string) {
    router.push({ name: 'study', params: { path } })
}

function browseDeck(path: string) {
    router.push({ name: 'cards', params: { path } })
}

function confirmResetProgress(deck: DeckMeta) {
    deckToReset.value = deck
}

async function handleCreate() {
    if (!newDeckName.value.trim()) return
    try {
        await deckStore.createDeck(newDeckName.value, newDeckFolder.value || undefined)
        showCreate.value = false
        newDeckName.value = ''
        newDeckFolder.value = ''
    } catch (e) {
        console.error('Failed to create deck:', e)
    }
}

async function handleResetProgress() {
    if (!deckToReset.value) return

    const path = deckToReset.value.path

    try {
        await api.resetDeckProgress(path)
        if (deckStore.currentPath === path) {
            await deckStore.openDeck(path)
        }
        await deckStore.loadDecks()
        deckToReset.value = null
        showSuccess(t('flashcards', 'Deck progress reset'))
    } catch (e) {
        showError(e instanceof Error ? e.message : t('flashcards', 'Failed to reset deck progress'))
    }
}

onMounted(() => {
    deckStore.loadDecks()
})
</script>

<style lang="scss" scoped>
.deck-browser-page {
    max-width: 100%;
}

.deck-browser-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 16px;
}

@media (max-width: 768px) {
    .deck-browser-container {
        padding: 0 8px;
    }
}

.loading-center {
    display: flex;
    justify-content: center;
    padding: 60px;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--color-text-maxcontrast);
}

.create-form {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 12px 0;

    label {
        font-weight: 600;
    }
}
</style>
