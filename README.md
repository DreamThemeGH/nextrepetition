# Repetio Flashcards

Repetio Flashcards is a file-based spaced-repetition flashcard app for Nextcloud. It keeps card content in Markdown files inside your Nextcloud Files, so decks stay portable, versionable, and compatible with common Markdown spaced-repetition tools.

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

## Backend tests (PHPUnit)

The PHP backend has a comprehensive test suite covering the spaced repetition algorithm,
SR metadata parsing/serialization, and end-to-end review pipelines.
All expected interval values are verified against the reference
[Obsidian Spaced Repetition](https://github.com/st3v3nmw/obsidian-spaced-repetition) implementation.

### Running tests

```bash
# Inside the Nextcloud container (or with PHP 8.1+ and vendor/autoload.php):
phpunit --bootstrap bootstrap.php --no-configuration tests/

# Or use the phpunit.xml configuration:
phpunit --bootstrap bootstrap.php
```

### Test structure

```
tests/
├── Unit/Service/Algorithms/
│   └── SM2AlgorithmTest.php       35 tests — all 4 ratings × new/overdue cards,
│                                   ease boundaries, maxInterval cap, multi-step sequences
├── Unit/Service/
│   ├── SM2ServiceTest.php         19 tests — processReview/predictReview,
│                                   dual-direction (srIndex 0/1), SR array expansion
│   ├── CardParserServiceTest.php  26 tests — SR parsing, dummy dates (2000-01-01),
│                                   mixed real+dummy entries, state detection, quickScan
│   └── CardSerializerServiceTest.php 20 tests — round-trip parse→serialize→reparse,
│                                   SR insertion/update, buildSRString, dual-direction
└── Integration/
    ├── ReviewPipelineTest.php     18 tests — full parse→review→serialize→verify cycle
    └── ReviewSequenceTest.php     15 tests — multi-step sequences:
                                    Good→Good→Good→Good, Good→Good→Easy→Good,
                                    Good→Good→Hard→Good, Good→Good→Again→Good,
                                    All Easy, All Again, mature card scenarios
```

### Key algorithm scenarios verified

| Sequence | Step 1 | Step 2 | Step 3 | Step 4 |
|----------|--------|--------|--------|--------|
| All Good | intv=3, e=250 | intv=8, e=250 | intv=20, e=250 | intv=50, e=250 |
| Easy→Good | intv=28, e=270 | intv=76, e=270 | — | — |
| Hard→Good | intv=4, e=230 | intv=9, e=230 | — | — |
| Again→Good | intv=1, e=230 | intv=2, e=230 | — | — |

### Algorithm formula (Obsidian-compatible)

```
Easy:  ease += 20;   interval = (interval + delayDays) * ease / 100 * 1.3
Good:  interval = (interval + delayDays/2) * ease / 100
Hard:  ease = max(130, ease - 20);  interval = max(1, (interval + delayDays/4) * 0.5)
Again: ease = max(130, ease - 20);  interval = 1
```

Constants: `DEFAULT_EASE=250`, `MIN_EASE=130`, `INITIAL_INTERVAL=1`, `MAX_INTERVAL=36525`

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
