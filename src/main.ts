/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Flashcards v2 — Main entry point
 */

import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { router } from './router'
import App from './App.vue'

// Set app metadata required by @nextcloud/vue
;(window as any).appName = 'flashcards'
;(window as any).appVersion = '2.0.0'

const app = createApp(App)
app.use(createPinia())
app.use(router)
app.mount('#flashcards-app')
