<template>
    <div class="flashcards-page deck-browser-page">
        <div class="deck-browser-container">
        <div class="flashcards-page-header">
            <h2>{{ t('flashcards', 'Decks') }}</h2>
            <NcButton type="primary" @click="showCreate = true">
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
            @browse="browseDeck" />

        <!-- Create deck dialog -->
        <NcDialog v-if="showCreate"
            :name="t('flashcards', 'Create new deck')"
            @closing="showCreate = false">
            <div class="create-form">
                <label>{{ t('flashcards', 'Deck name') }}</label>
                <NcTextField :value="newDeckName"
                    :placeholder="t('flashcards', 'My flashcards')"
                    @update:value="v => newDeckName = v" />

                <label>{{ t('flashcards', 'Subfolder (optional)') }}</label>
                <NcTextField :value="newDeckFolder"
                    :placeholder="t('flashcards', 'e.g. Serbian learning')"
                    @update:value="v => newDeckFolder = v" />
            </div>
            <template #actions>
                <NcButton @click="showCreate = false">{{ t('flashcards', 'Cancel') }}</NcButton>
                <NcButton type="primary" @click="handleCreate" :disabled="!newDeckName.trim()">
                    {{ t('flashcards', 'Create') }}
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

import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import IconPlus from 'vue-material-design-icons/Plus.vue'

import DeckTree from '@/components/DeckTree.vue'
import { useDeckStore } from '@/stores/deck'

const router = useRouter()
const deckStore = useDeckStore()

const showCreate = ref(false)
const newDeckName = ref('')
const newDeckFolder = ref('')

function startStudy(path: string) {
    router.push({ name: 'study', params: { path } })
}

function browseDeck(path: string) {
    router.push({ name: 'cards', params: { path } })
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
