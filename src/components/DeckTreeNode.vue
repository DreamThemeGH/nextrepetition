<template>
    <li class="deck-tree-node" role="treeitem"
        :aria-expanded="node.type === 'folder' ? expanded : undefined">
        <!-- Folder node -->
        <div v-if="node.type === 'folder'"
            class="tree-row tree-folder"
            role="button"
            tabindex="0"
            :aria-expanded="expanded"
            :aria-label="t('flashcards', 'Toggle folder') + ' ' + node.name"
            :style="{ paddingLeft: (depth * 24 + 8) + 'px' }"
            @click="expanded = !expanded"
            @keydown.enter="expanded = !expanded"
            @keydown.space.prevent="expanded = !expanded">
            <span class="expand-icon">{{ expanded ? '▼' : '▶' }}</span>
            <span class="folder-icon">{{ expanded ? '📂' : '📁' }}</span>
            <span class="node-name">{{ node.name }}</span>
            <span class="folder-badge" v-if="folderDueCount > 0">
                {{ folderDueCount }} {{ t('flashcards', 'due') }}
            </span>
        </div>

        <!-- Deck node -->
        <div v-else
            class="tree-row tree-deck"
            :class="{ 'has-due': hasDue }"
            :style="{ paddingLeft: (depth * 24 + 8) + 'px' }">
            <!-- Line 1: Icon + Name -->
            <div class="deck-header">
                <span class="deck-icon">📄</span>
                <span class="node-name deck-name" :title="node.name">{{ node.name }}</span>
            </div>
            
            <!-- Line 2: Stats (left) + Actions (right) -->
            <div class="deck-footer">
                <div class="deck-stats">
                    <span class="stat-study">{{ studyCount }} {{ t('flashcards', 'to study') }}</span>
                    <span class="stat-total">{{ totalCount }} {{ t('flashcards', 'total') }}</span>
                    <span v-if="hasDue" class="stat-due">{{ dueCount }} {{ t('flashcards', 'due') }}</span>
                    <span v-if="hasNew" class="stat-new">+{{ newCount }} {{ t('flashcards', 'new') }}</span>
                </div>

                <div class="deck-actions">
                    <NcButton
                        variant="tertiary"
                        class="favorite-button"
                        :ariaLabel="isFavorite ? t('flashcards', 'Remove from favorites') : t('flashcards', 'Add to favorites')"
                        @click.stop="toggleFavorite">
                        <template #icon>
                            <IconStar v-if="isFavorite" :size="18" />
                            <IconStarOutline v-else :size="18" />
                        </template>
                    </NcButton>
                    <NcButton v-if="hasDue || hasNew"
                        variant="primary"
                        :aria-label="t('flashcards', 'Study')"
                        @click.stop="$emit('study', node.deck!.path)">
                        {{ t('flashcards', 'Study') }}
                    </NcButton>
                    <NcButton
                        :aria-label="t('flashcards', 'Browse')"
                        @click.stop="$emit('browse', node.deck!.path)">
                        {{ t('flashcards', 'Browse') }}
                    </NcButton>
                    <NcButton
                        variant="tertiary"
                        class="reset-button"
                        :aria-label="t('flashcards', 'Reset progress')"
                        @click.stop="$emit('reset-progress', node.deck!)">
                        {{ t('flashcards', 'Reset') }}
                    </NcButton>
                </div>
            </div>
        </div>

        <!-- Children -->
        <ul v-if="node.type === 'folder' && expanded && node.children.length > 0"
            class="tree-children" role="group">
            <DeckTreeNode
                v-for="child in node.children"
                :key="child.key"
                :node="child"
                :depth="depth + 1"
                @study="$emit('study', $event)"
                @browse="$emit('browse', $event)"
                @reset-progress="$emit('reset-progress', $event)" />
        </ul>
    </li>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import { showError } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import IconStar from 'vue-material-design-icons/Star.vue'
import IconStarOutline from 'vue-material-design-icons/StarOutline.vue'
import type { TreeNode } from './DeckTree.vue'
import type { DeckMeta } from '@/types/deck'
import { useSettingsStore } from '@/stores/settings'

const props = defineProps<{
    node: TreeNode
    depth: number
}>()

const settingsStore = useSettingsStore()

defineEmits<{
    (e: 'study', path: string): void
    (e: 'browse', path: string): void
    (e: 'reset-progress', deck: DeckMeta): void
}>()

const expanded = ref(true)  // Folders expanded by default

function num(value: unknown): number {
    const n = Number(value)
    return Number.isFinite(n) ? n : 0
}

const totalCount = computed(() => num(props.node.deck?.totalCards))
const dueCount = computed(() => num(props.node.deck?.dueCards))
const newCount = computed(() => num(props.node.deck?.newCards))
const studyCount = computed(() => dueCount.value + newCount.value)
const hasDue = computed(() => dueCount.value > 0)
const hasNew = computed(() => newCount.value > 0)
const isFavorite = computed(() => {
    const path = props.node.deck?.path
    return !!path && settingsStore.settings.favoriteDecks.includes(path)
})

/** Sum due cards in all descendants */
const folderDueCount = computed(() => {
    if (props.node.type !== 'folder') return 0
    return countDue(props.node)
})

function countDue(node: TreeNode): number {
    let count = 0
    if (node.deck) count += num(node.deck.dueCards)
    for (const child of node.children) {
        count += countDue(child)
    }
    return count
}

async function toggleFavorite() {
    const path = props.node.deck?.path
    if (!path) return

    try {
        await settingsStore.toggleFavoriteDeck(path)
    } catch (e) {
        showError(e instanceof Error ? e.message : t('flashcards', 'Failed to update favorites'))
    }
}
</script>

<style lang="scss" scoped>
.deck-tree-node {
    list-style: none;
}

.tree-row {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 8px;
    border-radius: 8px;
    transition: background-color 0.15s;
    cursor: default;

    &:hover {
        background: var(--color-background-hover);
    }
}

.tree-folder {
    flex-direction: row;
    align-items: center;
    cursor: pointer;
    font-weight: 600;
}

.tree-deck {
    gap: 6px;
    
    &.has-due {
        background: var(--color-primary-element-light);
        border-radius: 8px;
    }
}

.deck-header {
    display: flex;
    align-items: center;
    gap: 8px;
}

.deck-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
}

.expand-icon {
    font-size: 10px;
    width: 16px;
    text-align: center;
    color: var(--color-text-maxcontrast);
    flex-shrink: 0;
}

.folder-icon,
.deck-icon {
    font-size: 16px;
    flex-shrink: 0;
}

.node-name {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    min-width: 0;
}

.deck-name {
    font-weight: 500;
    font-size: 0.95em;
}

.folder-badge {
    font-size: 0.8em;
    padding: 2px 8px;
    border-radius: 10px;
    background: var(--color-warning);
    color: white;
    font-weight: 600;
    flex-shrink: 0;
}

.deck-stats {
    display: flex;
    gap: 8px;
    align-items: center;
    font-size: 0.85em;
}

.stat-total {
    color: #e0e0e0;
    font-weight: 500;
}

.stat-study {
    color: #f5f5f5;
    font-weight: 700;
}

.stat-due {
    color: #ff9800;
    font-weight: 600;
}

.stat-new {
    color: #4fc3f7;
    font-weight: 600;
}

.deck-actions {
    display: flex;
    gap: 4px;
    align-items: center;
}

.reset-button {
    color: var(--color-text-maxcontrast);
}

.favorite-button {
    color: #c59a1b;
}

/* Mobile responsive layout */
@media (max-width: 768px) {
    .tree-row {
        padding: 10px 8px;
    }
    
    .deck-footer {
        flex-wrap: wrap;
    }
    
    .deck-actions {
        gap: 4px;
        
        :deep(.button-vue) {
            padding: 6px 10px;
            font-size: 0.85em;
        }
    }
}

.tree-children {
    list-style: none;
    margin: 0;
    padding: 0;
}
</style>
