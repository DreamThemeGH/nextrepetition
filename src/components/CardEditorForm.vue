<template>
    <div class="card-editor-form">
        <template v-if="type === 'basic'">
            <div class="form-group">
                <label>{{ t('flashcards', 'Front') }}</label>
                <NcTextField
                    :value="front"
                    :placeholder="t('flashcards', 'Word or phrase')"
                    @update:value="$emit('update:front', $event)" />
            </div>
            <div class="form-group">
                <label>{{ t('flashcards', 'Back') }}</label>
                <NcTextField
                    :value="back"
                    :placeholder="t('flashcards', 'Translation')"
                    @update:value="$emit('update:back', $event)" />
            </div>
            <div class="form-group">
                <label>{{ t('flashcards', 'Transcription (optional)') }}</label>
                <NcTextField
                    :value="transcription"
                    placeholder="IPA"
                    @update:value="$emit('update:transcription', $event)" />
            </div>
        </template>

        <template v-else>
            <div class="form-group">
                <label>{{ t('flashcards', 'Sentence with ==cloze==') }}</label>
                <NcTextField
                    :value="sentence"
                    :placeholder="t('flashcards', 'I ==like==^[люблю] pizza')"
                    @update:value="$emit('update:sentence', $event)" />
            </div>
            <div class="form-group">
                <label>{{ t('flashcards', 'Translation') }}</label>
                <NcTextField
                    :value="translation"
                    :placeholder="t('flashcards', 'Я люблю пиццу')"
                    @update:value="$emit('update:translation', $event)" />
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