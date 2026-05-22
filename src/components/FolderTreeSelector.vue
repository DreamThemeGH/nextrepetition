<template>
    <div class="folder-tree-selector">
        <div class="folder-tree-header">
            <NcButton variant="tertiary-no-background"
                @click="refreshTree"
                :disabled="loading">
                <template #icon>
                    <NcLoadingIcon v-if="loading" :size="20" />
                    <span v-else class="icon-refresh">↻</span>
                </template>
                {{ t('flashcards', 'Refresh') }}
            </NcButton>
        </div>

        <div v-if="loading && !tree.length" class="folder-tree-loading">
            <NcLoadingIcon :size="32" />
        </div>

        <ul v-else class="folder-tree-list" role="tree" :aria-label="t('flashcards', 'Folder tree')">
            <FolderTreeNode
                v-for="node in tree"
                :key="node.path"
                :node="node"
                :selected-path="modelValue"
                :depth="0"
                @select="selectFolder"
                @toggle="toggleNode" />
        </ul>

        <p v-if="!loading && !tree.length" class="folder-tree-empty">
            {{ t('flashcards', 'No folders found. Check your Nextcloud files.') }}
        </p>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import FolderTreeNode from './FolderTreeNode.vue'
import { listFolders, type FolderNode } from '@/services/webdav'

const props = defineProps<{
    modelValue: string
}>()

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void
}>()

const tree = ref<FolderNode[]>([])
const loading = ref(false)

async function loadChildren(parentPath: string, nodes: FolderNode[]): Promise<void> {
    for (const node of nodes) {
        if (node.path === parentPath) {
            if (!node.loaded) {
                node.children = await listFolders(node.path)
                node.loaded = true
            }
            return
        }
        if (parentPath.startsWith(node.path + '/') && node.children.length > 0) {
            await loadChildren(parentPath, node.children)
            return
        }
    }
}

async function toggleNode(node: FolderNode) {
    if (!node.loaded) {
        try {
            node.children = await listFolders(node.path)
            node.loaded = true
        } catch {
            node.children = []
            node.loaded = true
        }
    }
    // Toggle expanded state by toggling loaded flag isn't ideal;
    // we use a separate expanded tracking in the node component
}

function selectFolder(path: string) {
    emit('update:modelValue', path)
}

async function refreshTree() {
    loading.value = true
    try {
        tree.value = await listFolders('/')
    } catch {
        tree.value = []
    } finally {
        loading.value = false
    }
}

/** Pre-expand the tree to reveal the currently selected path */
async function expandToPath(targetPath: string) {
    if (!targetPath || targetPath === '/') return

    const parts = targetPath.split('/').filter(Boolean)
    let currentPath = ''

    for (const part of parts) {
        currentPath += '/' + part

        // Find this node in the tree
        const node = findNode(currentPath, tree.value)
        if (node && !node.loaded) {
            try {
                node.children = await listFolders(node.path)
                node.loaded = true
            } catch {
                break
            }
        }
    }
}

function findNode(path: string, nodes: FolderNode[]): FolderNode | null {
    for (const node of nodes) {
        if (node.path === path) return node
        if (path.startsWith(node.path + '/')) {
            const found = findNode(path, node.children)
            if (found) return found
        }
    }
    return null
}

onMounted(async () => {
    await refreshTree()
    if (props.modelValue && props.modelValue !== '/') {
        await expandToPath(props.modelValue)
    }
})
</script>

<style lang="scss" scoped>
.folder-tree-selector {
    border: 1px solid var(--color-border);
    border-radius: 8px;
    padding: 8px;
    max-height: 400px;
    overflow-y: auto;
    background: var(--color-main-background);
}

.folder-tree-header {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 4px;
}

.folder-tree-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.folder-tree-loading {
    display: flex;
    justify-content: center;
    padding: 24px;
}

.folder-tree-empty {
    text-align: center;
    color: var(--color-text-maxcontrast);
    padding: 16px;
}

.icon-refresh {
    font-size: 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
}
</style>
