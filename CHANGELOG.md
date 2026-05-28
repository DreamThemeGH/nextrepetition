# Changelog

All notable changes to **Repetio** are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

## [2.0.5] – 2026-05-28

### Added
- Nextcloud 33 compatibility
- GitHub Sponsors donation link

### Fixed
- App disabled after Nextcloud major version upgrade (max-version bumped to 33)

### Changed
- SPDX licence identifier updated to `AGPL-3.0-or-later`
- Author metadata updated to DreamThemeGH

## [2.0.0] – 2026-05-22

### Added
- File-based deck storage (`.md` files — Obsidian SR compatible)
- SM-2 spaced repetition algorithm with scheduling written back to `.md`
- Card formats: Basic (`word:::translation`), Cloze (`==word==^[hint]`), Transcription
- Auto-save buffer (10-second interval)
- Text-to-Speech via Edge TTS + Web Speech API fallback
- Study statistics: due forecast chart, interval distribution, per-deck breakdown
- Git merge conflict auto-resolution for SR tags
- Dark / light theme following Nextcloud theme
- Keyboard shortcuts (Space to reveal, 1–5 to rate)
- Multilingual support: English, Russian, Serbian

## [0.3.1] – 2026-02-10

### Added
- Initial release of Flashcards v1 (database-backed)
- SM-2 algorithm with database storage
- Obsidian Sync folder importer
- Text-to-Speech support
- Study statistics (heatmap, retention charts)
- Import/Export: CSV, JSON, Obsidian SR Markdown
