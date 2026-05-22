<template>
    <div class="card-editor-form">
        <template v-if="type === 'basic'">
            <div class="form-group">
                <label>{{ t('flashcards', 'Front') }}</label>
                <NcTextField
                    :model-value="front ?? ''"
                    :placeholder="t('flashcards', 'Word or phrase')"
                    @update:model-value="$emit('update:front', String($event))" />
            </div>
            <div class="form-group">
                <label>{{ t('flashcards', 'Back') }}</label>
                <NcTextField
                    :model-value="back ?? ''"
                    :placeholder="t('flashcards', 'Translation')"
                    @update:model-value="$emit('update:back', String($event))" />
            </div>
            <div class="form-group">
                <label>{{ t('flashcards', 'Transcription (optional)') }}</label>
                <NcTextField
                    :model-value="transcription ?? ''"
                    placeholder="IPA"
                    @update:model-value="$emit('update:transcription', String($event))" />
            </div>
        </template>

        <template v-else>
            <div class="form-group">
                <label>{{ t('flashcards', 'Sentence with ==cloze==') }}</label>
                <NcTextField
                    :model-value="sentence ?? ''"
                    :placeholder="t('flashcards', 'I ==like==^[люблю] pizza')"
                    @update:model-value="$emit('update:sentence', String($event))" />
            </div>
            <div class="form-group">
                <label>{{ t('flashcards', 'Translation') }}</label>
                <NcTextField
                    :model-value="translation ?? ''"
                    :placeholder="t('flashcards', 'Я люблю пиццу')"
                    @update:model-value="$emit('update:translation', String($event))" />
            </div>
        </template>
    </div>
</template>

<script setup lang="ts">
import { translate as t } from '@nextcloud/l10n'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import type { CardType } from '@/types/card'

defineProps<{
    type: CardType
    front?: string
    back?: string
    transcription?: string
    sentence?: string
    translation?: string
}>()

defineEmits<{
    (e: 'update:front', value: string): void
    (e: 'update:back', value: string): void
    (e: 'update:transcription', value: string): void
    (e: 'update:sentence', value: string): void
    (e: 'update:translation', value: string): void
}>()
</script>

<style lang="scss" scoped>
.card-editor-form {
    display: flex;
    flex-direction: column;
    gap: 16px;
    padding: 8px 0;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 4px;

    label {
        font-weight: 600;
    }
}
</style>