<template>
    <li class="folder-tree-node" role="treeitem" :aria-expanded="expanded">
        <div class="folder-tree-node-row"
            :class="{
                'is-selected': isSelected,
                'is-ancestor': isAncestorOfSelected,
            }"
            :style="{ paddingLeft: (depth * 20 + 4) + 'px' }"
            tabindex="0"
            :aria-selected="isSelected"
            @click="select"
            @keydown.enter="select"
            @keydown.space.prevent="select">
            <button class="expand-toggle"
                :class="{ 'is-expanded': expanded }"
                @click.stop="toggle"
                :aria-label="expanded ? t('flashcards', 'Collapse') : t('flashcards', 'Expand')">
                <span v-if="loadingChildren" class="spinner">⟳</span>
                <span v-else class="chevron">{{ expanded ? '▼' : '▶' }}</span>
            </button>
            <span class="folder-icon">📁</span>
            <span class="folder-name" :title="node.path">{{ node.name }}</span>
            <span v-if="isSelected" class="check-mark">✓</span>
        </div>

        <ul v-if="expanded && node.children.length > 0" class="folder-tree-children" role="group">
            <FolderTreeNode
                v-for="child in node.children"
                :key="child.path"
                :node="child"
                :selected-path="selectedPath"
                :depth="depth + 1"
                @select="$emit('select', $event)"
                @toggle="$emit('toggle', $event)" />
        </ul>
    </li>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import { listFolders, type FolderNode } from '@/services/webdav'

const props = defineProps<{
    node: FolderNode
    selectedPath: string
    depth: number
}>()

const emit = defineEmits<{
    (e: 'select', path: string): void
    (e: 'toggle', node: FolderNode): void
}>()

const expanded = ref(false)
const loadingChildren = ref(false)

const isSelected = computed(() => props.selectedPath === props.node.path)
const isAncestorOfSelected = computed(() =>
    props.selectedPath.startsWith(props.node.path + '/'),
)

// Auto-expand if this node is an ancestor of the selected path
watch(() => props.selectedPath, (newPath) => {
    if (newPath.startsWith(props.node.path + '/')) {
        expanded.value = true
    }
}, { immediate: true })

async function toggle() {
    if (!props.node.loaded) {
        loadingChildren.value = true
        try {
            props.node.children = await listFolders(props.node.path)
            props.node.loaded = true
        } catch {
            props.node.children = []
            props.node.loaded = true
        } finally {
            loadingChildren.value = false
        }
    }
    expanded.value = !expanded.value
}

function select() {
    emit('select', props.node.path)
}
</script>

<style lang="scss" scoped>
.folder-tree-node {
    list-style: none;
}

.folder-tree-node-row {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 4px 8px;
    border-radius: 6px;
    cursor: pointer;
    transition: background-color 0.15s;
    user-select: none;

    &:hover {
        background: var(--color-background-hover);
    }

    &.is-selected {
        background: var(--color-primary-element-light);
        font-weight: 700;
    }

    &.is-ancestor {
        font-weight: 600;
    }
}

.expand-toggle {
    background: none;
    border: none;
    cursor: pointer;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    color: var(--color-text-maxcontrast);
    flex-shrink: 0;
    padding: 0;

    .spinner {
        animation: spin 0.8s linear infinite;
    }
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.folder-icon {
    font-size: 14px;
    flex-shrink: 0;
}

.folder-name {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.95em;
}

.check-mark {
    color: var(--color-primary-element);
    font-weight: 700;
    font-size: 14px;
    flex-shrink: 0;
}

.folder-tree-children {
    list-style: none;
    margin: 0;
    padding: 0;
}
</style>
