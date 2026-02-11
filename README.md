# Nextcloud Flashcards v2

Spaced-repetition flashcard app for Nextcloud. Study markdown-based flashcard decks directly from your Nextcloud files.

## Features

- **File-based decks** — Uses `.md` files stored in your Nextcloud files. No external database for cards.
- **SM-2 Algorithm** — Industry-standard spaced repetition with configurable intervals.
- **Hierarchical deck browser** — Tree view of your deck folders, like Nextcloud Files.
- **Folder tree selector** — Visual folder picker in Settings using WebDAV.
- **Study sessions** — Flip cards, rate difficulty (Again / Hard / Good / Easy).
- **Statistics** — Due forecast charts, interval distribution, per-deck breakdown.
- **Dashboard** — Global stats at a glance with quick-start study button.
- **Card formats** — Basic (Q/A) and Cloze deletions.
- **TTS** — Text-to-speech via Edge TTS service.
- **Auto-save** — Configurable auto-save interval.
- **Accessibility** — ARIA tree roles, keyboard navigation, screen reader support.
- **Dark theme** — Fully compatible with Nextcloud dark mode (currentColor icon).

## Architecture

```
Frontend: Vue 3 + Vite (IIFE) + Pinia + vue-router + @nextcloud/vue
Backend:  PHP (OCSController) + file-based deck storage
DB:       Single table (oc_flashcards_user_settings) for user preferences
Build:    Vite → js/flashcards-main.js (~855KB) + css/nextcloud-flashcards.css (~74KB)
```

### Key directories

```
src/
├── components/       DeckTree, DeckTreeNode, FolderTreeSelector, FolderTreeNode
├── views/            Dashboard, DeckBrowser, StudySession, CardBrowser, Statistics, Settings
├── stores/           Pinia stores: deck, settings, stats, study
├── services/         api.ts (OCS HTTP), webdav.ts (PROPFIND folder listing)
├── types/            TypeScript interfaces: deck, card, sr
├── composables/      useAutoSave, useKeyboard
└── assets/styles/    SCSS variables, global styles

lib/
├── Controller/       DeckController, SettingsController, StatsController
├── Service/          DeckFileService, CardParser, SM2Service, BufferService, etc.
├── Db/               UserSettings entity + mapper
└── Listener/         CspListener
```

## Development

```bash
# Install dependencies
npm install

# Dev build with watch
npm run dev

# Production build
npm run build

# Run tests
npm test
```

## Deployment

```bash
# Build
npm run build

# Copy to NC container
docker cp js/flashcards-main.js nextcloud-aio-nextcloud:/var/www/html/custom_apps/flashcards/js/
docker cp css/nextcloud-flashcards.css nextcloud-aio-nextcloud:/var/www/html/custom_apps/flashcards/css/
```

## License

AGPL-3.0-or-later
