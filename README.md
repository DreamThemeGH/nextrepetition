# Repetio

Repetio is a file-based spaced-repetition flashcard app for Nextcloud. It keeps card content in Markdown files inside your Nextcloud Files, so decks stay portable, versionable, and compatible with common Markdown spaced-repetition tools.

## What it does

- Stores flashcards as `.md` files instead of using an external card database.
- Uses SM-2-style scheduling and writes review metadata back to the source files.
- Supports basic cards, cloze cards, transcription fields, and study statistics.
- Includes a folder picker, dashboard, study session, deck browser, and TTS support.
- Works with Nextcloud dark mode and keyboard navigation.

## Screens

If you want to add screenshots later, place them in a `screenshots/` folder and link them here.

## Tech stack

- Frontend: Vue 3, Vite, Pinia, vue-router, @nextcloud/vue
- Backend: PHP app controllers and file-based deck services
- Build: Vite bundle output into `js/` and `css/`

## Local development

```bash
npm ci
npm run build
npm run test
npm run typecheck
```

## Deploy to a local Nextcloud AIO instance

```bash
make deploy
```

If you need to refresh the app inside the container manually, the app id is `flashcards`.

## App Store packaging

The repository already includes the files needed for Nextcloud App Store preparation:

- `appinfo/info.xml` for metadata and compatibility
- `CHANGELOG.md` for release notes
- `.github/workflows/release.yml` for build and release automation
- `scripts/get-cert.sh` and `scripts/verify-cert.sh` for signing setup

To build a release archive locally:

```bash
make appstore
```

## Repository layout

```text
src/            Vue frontend
lib/            PHP backend
templates/      Nextcloud page templates
appinfo/        App metadata and routes
scripts/        Signing helpers
.github/        GitHub Actions workflow
```

## License

AGPL-3.0-or-later
