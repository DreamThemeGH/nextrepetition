/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Flashcards v2 — Main entry point
 */

import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { router } from './router'
import App from './App.vue'

// Set app metadata for @nextcloud/vue
if (window && window.OC) {
    window.OC.appConfig = window.OC.appConfig || {}
    window.OC.appConfig.flashcards = window.OC.appConfig.flashcards || {}
}

const app = createApp(App)
app.use(createPinia())
app.use(router)
app.mount('#flashcards-app')
