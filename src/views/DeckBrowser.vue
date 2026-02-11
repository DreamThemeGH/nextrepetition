<template>
    <div class="flashcards-page">
        <div class="flashcards-page-header">
            <h2>{{ t('flashcards', 'Decks') }}</h2>
            <NcButton type="primary" @click="showCreate = true">
                <template #icon><IconPlus :size="20" /></template>
                {{ t('flashcards', 'New deck') }}
            </NcButton>
        </div>

        <div v-if="deckStore.loading" class="loading-center">
            <NcLoadingIcon :size="44" />
        </div>

        <div v-else-if="deckStore.decks.length === 0" class="empty-state">
            <h3>{{ t('flashcards', 'No decks found') }}</h3>
            <p>{{ t('flashcards', 'Place .md files with flashcards in your configured folder, or create a new deck.') }}</p>
        </div>

        <div v-else class="deck-grid">
            <div v-for="deck in sortedDecks"
                :key="deck.path"
                class="deck-card"
                @click="openDeck(deck)">
                <div class="deck-card-header">
                    <h3 class="deck-card-name">{{ deck.name }}</h3>
                    <div class="deck-card-folder" v-if="deck.folder && deck.folder !== '.'">
                        {{ deck.folder }}
                    </div>
                </div>

                <div class="deck-card-stats">
                    <div class="stat">
                        <span class="stat-value">{{ deck.totalCards }}</span>
                        <span class="stat-label">{{ t('flashcards', 'cards') }}</span>
                    </div>
                    <div class="stat stat-due" v-if="deck.dueCards > 0">
                        <span class="stat-value">{{ deck.dueCards }}</span>
                        <span class="stat-label">{{ t('flashcards', 'due') }}</span>
                    </div>
                    <div class="stat stat-new" v-if="deck.newCards > 0">
                        <span class="stat-value">{{ deck.newCards }}</span>
                        <span class="stat-label">{{ t('flashcards', 'new') }}</span>
                    </div>
                </div>

                <div class="deck-card-actions">
                    <NcButton type="primary"
                        v-if="deck.dueCards > 0 || deck.newCards > 0"
                        @click.stop="startStudy(deck.path)">
                        {{ t('flashcards', 'Study') }}
                    </NcButton>
                    <NcButton @click.stop="browseDeck(deck.path)">
                        {{ t('flashcards', 'Browse') }}
                    </NcButton>
                </div>
            </div>
        </div>

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
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { translate as t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import IconPlus from 'vue-material-design-icons/Plus.vue'

import { useDeckStore } from '@/stores/deck'
import type { DeckMeta } from '@/types/deck'

const router = useRouter()
const deckStore = useDeckStore()

const showCreate = ref(false)
const newDeckName = ref('')
const newDeckFolder = ref('')

const sortedDecks = computed(() =>
    [...deckStore.decks].sort((a, b) => {
        // Due decks first
        if (a.dueCards > 0 && b.dueCards === 0) return -1
        if (b.dueCards > 0 && a.dueCards === 0) return 1
        // Then by name
        return a.name.localeCompare(b.name)
    }),
)

function openDeck(deck: DeckMeta) {
    if (deck.dueCards > 0 || deck.newCards > 0) {
        startStudy(deck.path)
    } else {
        browseDeck(deck.path)
    }
}

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

.deck-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
}

.deck-card {
    background: var(--color-background-dark);
    border-radius: 12px;
    padding: 20px;
    cursor: pointer;
    transition: transform 0.15s, box-shadow 0.15s;

    &:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
    }
}

.deck-card-name {
    margin: 0 0 4px;
    font-size: 1.15em;
}

.deck-card-folder {
    font-size: 0.85em;
    color: var(--color-text-maxcontrast);
    margin-bottom: 12px;
}

.deck-card-stats {
    display: flex;
    gap: 16px;
    margin-bottom: 16px;
}

.stat {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.stat-value {
    font-size: 1.3em;
    font-weight: 700;
}

.stat-label {
    font-size: 0.8em;
    color: var(--color-text-maxcontrast);
}

.stat-due .stat-value { color: $flashcards-warning; }
.stat-new .stat-value { color: $card-new; }

.deck-card-actions {
    display: flex;
    gap: 8px;
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
