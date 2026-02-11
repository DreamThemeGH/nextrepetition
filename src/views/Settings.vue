<template>
    <div class="flashcards-page">
        <div class="flashcards-page-header">
            <h2>{{ t('flashcards', 'Settings') }}</h2>
        </div>

        <div v-if="settingsStore.loading" class="loading-center">
            <NcLoadingIcon :size="44" />
        </div>

        <div v-else class="settings-content">
            <!-- Deck folder -->
            <div class="settings-section">
                <h3>{{ t('flashcards', 'File Storage') }}</h3>

                <div class="setting-row folder-setting">
                    <label>{{ t('flashcards', 'Deck folder path') }}</label>
                    <div class="setting-input">
                        <div class="current-path">
                            <span class="path-label">{{ t('flashcards', 'Current:') }}</span>
                            <code>{{ localSettings.deckFolder || '/' }}</code>
                        </div>
                        <FolderTreeSelector
                            :model-value="localSettings.deckFolder"
                            @update:model-value="v => { localSettings.deckFolder = v; markDirty() }" />
                        <p class="setting-help">
                            {{ t('flashcards', 'Select the folder containing your .md flashcard files from the tree above.') }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Study settings -->
            <div class="settings-section">
                <h3>{{ t('flashcards', 'Study') }}</h3>

                <div class="setting-row">
                    <label for="cards-per-day">{{ t('flashcards', 'Cards per session') }}</label>
                    <input type="number" id="cards-per-day" v-model.number="localSettings.cardsPerDay"
                        min="1" max="500" @change="markDirty" />
                </div>

                <div class="setting-row">
                    <label for="new-cards-per-day">{{ t('flashcards', 'New cards per day') }}</label>
                    <input type="number" id="new-cards-per-day" v-model.number="localSettings.newCardsPerDay"
                        min="0" max="200" @change="markDirty" />
                </div>

                <div class="setting-row">
                    <label for="auto-save-interval">{{ t('flashcards', 'Auto-save interval (seconds)') }}</label>
                    <input type="number" id="auto-save-interval" v-model.number="localSettings.autoSaveInterval"
                        min="5" max="120" @change="markDirty" />
                </div>
            </div>

            <!-- UI settings -->
            <div class="settings-section">
                <h3>{{ t('flashcards', 'Interface') }}</h3>

                <div class="setting-row">
                    <label for="card-layout">{{ t('flashcards', 'Card layout') }}</label>
                    <select id="card-layout" v-model="localSettings.cardLayout" @change="markDirty">
                        <option value="classic">{{ t('flashcards', 'Classic') }}</option>
                        <option value="compact">{{ t('flashcards', 'Compact') }}</option>
                        <option value="minimal">{{ t('flashcards', 'Minimal') }}</option>
                    </select>
                </div>

                <div class="setting-row">
                    <label for="button-position">{{ t('flashcards', 'Button position') }}</label>
                    <select id="button-position" v-model="localSettings.buttonPosition" @change="markDirty">
                        <option value="bottom">{{ t('flashcards', 'Bottom') }}</option>
                        <option value="right">{{ t('flashcards', 'Right side') }}</option>
                    </select>
                </div>

                <div class="setting-row">
                    <NcCheckboxRadioSwitch :model-value="localSettings.showProgress"
                        @update:model-value="v => { localSettings.showProgress = v; markDirty() }">
                        {{ t('flashcards', 'Show progress bar') }}
                    </NcCheckboxRadioSwitch>
                </div>

                <div class="setting-row">
                    <NcCheckboxRadioSwitch :model-value="localSettings.keyboardShortcuts"
                        @update:model-value="v => { localSettings.keyboardShortcuts = v; markDirty() }">
                        {{ t('flashcards', 'Enable keyboard shortcuts') }}
                    </NcCheckboxRadioSwitch>
                </div>

                <div class="setting-row">
                    <NcCheckboxRadioSwitch :model-value="localSettings.fullscreenMode"
                        @update:model-value="v => { localSettings.fullscreenMode = v; markDirty() }">
                        {{ t('flashcards', 'Fullscreen study mode') }}
                    </NcCheckboxRadioSwitch>
                </div>
            </div>

            <!-- TTS settings -->
            <div class="settings-section">
                <h3>{{ t('flashcards', 'Text-to-Speech') }}</h3>

                <div class="setting-row">
                    <NcCheckboxRadioSwitch :model-value="localSettings.autoPlayAudio"
                        @update:model-value="v => { localSettings.autoPlayAudio = v; markDirty() }">
                        {{ t('flashcards', 'Auto-play audio for cards') }}
                    </NcCheckboxRadioSwitch>
                </div>

                <div class="setting-row">
                    <label>{{ t('flashcards', 'Default language') }}</label>
                    <NcTextField :value="localSettings.defaultLanguage"
                        placeholder="en-US"
                        @update:value="v => { localSettings.defaultLanguage = v; markDirty() }" />
                </div>

                <div class="setting-row">
                    <label>{{ t('flashcards', 'TTS voice') }}</label>
                    <NcTextField :value="localSettings.ttsVoice"
                        placeholder="en-US-AriaNeural"
                        @update:value="v => { localSettings.ttsVoice = v; markDirty() }" />
                </div>
            </div>

            <!-- Save button -->
            <div class="settings-actions">
                <NcButton type="primary"
                    @click="saveSettings"
                    :disabled="!isDirty || settingsStore.loading">
                    {{ t('flashcards', 'Save settings') }}
                </NcButton>
                <span v-if="saveSuccess" class="save-success">
                    ✓ {{ t('flashcards', 'Saved!') }}
                </span>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { translate as t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import FolderTreeSelector from '@/components/FolderTreeSelector.vue'
import { useSettingsStore } from '@/stores/settings'
import type { UserSettings } from '@/types/sr'

const settingsStore = useSettingsStore()
const isDirty = ref(false)
const saveSuccess = ref(false)

const localSettings = reactive<UserSettings>({
    deckFolder: '/ObsidianSync',
    cardLayout: 'classic',
    buttonPosition: 'bottom',
    showProgress: true,
    autoPlayAudio: false,
    keyboardShortcuts: true,
    fullscreenMode: false,
    autoSaveInterval: 10,
    theme: 'auto',
    defaultLanguage: '',
    ttsVoice: '',
    cardsPerDay: 50,
    newCardsPerDay: 20,
})

function markDirty() {
    isDirty.value = true
    saveSuccess.value = false
}

async function saveSettings() {
    await settingsStore.save({ ...localSettings })
    isDirty.value = false
    saveSuccess.value = true
    setTimeout(() => { saveSuccess.value = false }, 3000)
}

onMounted(async () => {
    await settingsStore.load()
    Object.assign(localSettings, settingsStore.settings)
})
</script>

<style lang="scss" scoped>
.loading-center {
    display: flex;
    justify-content: center;
    padding: 60px;
}

.settings-section {
    margin-bottom: 32px;

    h3 {
        font-size: 1.15em;
        font-weight: 700;
        margin-bottom: 16px;
        padding-bottom: 8px;
        border-bottom: 1px solid var(--color-border);
    }
}

.setting-row {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 16px;

    > label {
        min-width: 200px;
        font-weight: 600;
        padding-top: 8px;
    }

    input[type="number"] {
        width: 100px;
        padding: 8px;
        border: 1px solid var(--color-border);
        border-radius: 6px;
    }

    select {
        padding: 8px 12px;
        border: 1px solid var(--color-border);
        border-radius: 6px;
        background: var(--color-main-background);
    }
}

.setting-input {
    flex: 1;
}

.folder-setting {
    flex-direction: column;

    > label {
        min-width: auto;
    }

    .setting-input {
        width: 100%;
    }
}

.current-path {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    padding: 6px 12px;
    background: var(--color-background-dark);
    border-radius: 6px;
    font-size: 0.9em;

    .path-label {
        font-weight: 600;
        color: var(--color-text-maxcontrast);
    }

    code {
        font-family: monospace;
        color: var(--color-primary-element);
    }
}

.setting-help {
    font-size: 0.85em;
    color: var(--color-text-maxcontrast);
    margin-top: 4px;
}

.settings-actions {
    display: flex;
    align-items: center;
    gap: 16px;
    padding-top: 16px;
    border-top: 1px solid var(--color-border);
}

.save-success {
    color: $flashcards-success;
    font-weight: 600;
}
</style>
