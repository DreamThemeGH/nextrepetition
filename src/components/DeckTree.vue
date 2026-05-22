<template>
    <div class="deck-tree">
        <div v-if="loading" class="deck-tree-loading">
            <NcLoadingIcon :size="32" />
        </div>

        <div v-else-if="treeNodes.length === 0" class="deck-tree-empty">
            <p>{{ t('flashcards', 'No decks found') }}</p>
        </div>

        <ul v-else class="deck-tree-list" role="tree" :aria-label="t('flashcards', 'Deck tree')">
            <DeckTreeNode
                v-for="node in treeNodes"
                :key="node.key"
                :node="node"
                :depth="0"
                @study="$emit('study', $event)"
                @browse="$emit('browse', $event)"
                @reset-progress="$emit('reset-progress', $event)" />
        </ul>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import DeckTreeNode from './DeckTreeNode.vue'
import type { DeckMeta } from '@/types/deck'

export interface TreeNode {
    key: string
    name: string
    type: 'folder' | 'deck'
    deck?: DeckMeta
    children: TreeNode[]
}

const props = defineProps<{
    decks: DeckMeta[]
    loading: boolean
}>()

defineEmits<{
    (e: 'study', path: string): void
    (e: 'browse', path: string): void
    (e: 'reset-progress', deck: DeckMeta): void
}>()

/**
 * Build a hierarchical tree from flat deck list based on folder paths.
 */
const treeNodes = computed<TreeNode[]>(() => {
    const root: TreeNode[] = []

    // Sort decks by path so folders group naturally
    const sorted = [...props.decks].sort((a, b) => a.path.localeCompare(b.path))

    for (const deck of sorted) {
        const parts = deck.path.split('/').filter(Boolean)

        // Remove the last part (filename)
        const fileName = parts.pop()
        if (!fileName) continue

        // Navigate/create folder structure
        let currentLevel = root

        for (let i = 0; i < parts.length; i++) {
            const folderName = parts[i]
            const folderKey = '/' + parts.slice(0, i + 1).join('/')

            let folderNode = currentLevel.find(
                n => n.type === 'folder' && n.name === folderName,
            )

            if (!folderNode) {
                folderNode = {
                    key: folderKey,
                    name: folderName,
                    type: 'folder',
                    children: [],
                }
                currentLevel.push(folderNode)
            }

            currentLevel = folderNode.children
        }

        // Add the deck as a leaf node
        currentLevel.push({
            key: deck.path,
            name: deck.name,
            type: 'deck',
            deck,
            children: [],
        })
    }

    // Sort each level: folders first, then decks by due count
    sortTree(root)
    return root
})

function sortTree(nodes: TreeNode[]) {
    nodes.sort((a, b) => {
        // Folders first
        if (a.type === 'folder' && b.type !== 'folder') return -1
        if (a.type !== 'folder' && b.type === 'folder') return 1
        // Within decks: due first
        if (a.deck && b.deck) {
            if ((a.deck.dueCards || 0) > 0 && (b.deck.dueCards || 0) === 0) return -1
            if ((b.deck.dueCards || 0) > 0 && (a.deck.dueCards || 0) === 0) return 1
        }
        return a.name.localeCompare(b.name)
    })

    for (const node of nodes) {
        if (node.children.length > 0) {
            sortTree(node.children)
        }
    }
}
</script>

<style lang="scss" scoped>
.deck-tree {
    width: 100%;
}

.deck-tree-loading {
    display: flex;
    justify-content: center;
    padding: 32px;
}

.deck-tree-empty {
    text-align: center;
    padding: 40px;
    color: var(--color-text-maxcontrast);
}

.deck-tree-list {
    list-style: none;
    margin: 0;
    padding: 0;
}
</style>
