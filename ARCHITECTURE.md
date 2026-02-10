# Flashcards v2 — Архитектура (MD-файлы как источник правды)

## 1. Принцип

**Единственный источник правды — .md файлы в Nextcloud Files.**

- Карточки хранятся ТОЛЬКО в .md файлах формата Obsidian Spaced Repetition
- SM-2 метаданные (`<!--SR:!date,interval,ease-->`) пишутся ОБРАТНО в .md файл
- БД используется ТОЛЬКО для `user_settings` (настройки интерфейса)
- Статистика вычисляется при чтении из SR-метаданных в файлах

## 2. Формат данных (по реальным файлам пользователя)

### 2.1 Простые карточки (word:::translation)

```markdown
#flashcards/serbian/words/first_387
da:::yes, да
<!--SR:!2026-06-03,367,366!2026-05-31,364,361-->
biti:::to be, быть (verb)
<!--SR:!2026-07-14,408,388!2026-05-13,346,366-->
```

- Формат: `front:::back`
- `<!--SR:!date,interval,ease!date,interval,ease-->` — 2 SR-записи: front→back и back→front
- Тег `#flashcards/path/to/deck` = путь колоды

### 2.2 Cloze карточки (==word==^[hint])

```markdown
#flashcards/english/English_flashcards_1-200

My cat sleeps ==as==^[как] long as my dog does.
Мой кот спит так же долго, как и моя собака.
<!--SR:!2025-10-10,4,270-->
```

- `==word==` — скрытое слово (cloze deletion)
- `^[hint]` — подсказка (перевод слова)
- `^{transcription}` — IPA транскрипция (расширение)
- Следующая строка = перевод предложения
- Один `<!--SR:-->` на cloze-элемент

### 2.3 Многострочные карточки с контекстом

```markdown
sam:::[I] am, [я] есть (verb)

Ja sam srećan
I am happy
я счастлив

Ja sam ovde
I am here
я здесь
```

- После `word:::translation` идут примеры использования
- Примеры: 3 строки (оригинал, English, русский)
- Пустая строка = разделитель примеров
- Следующая `word:::` = новая карточка

## 3. Архитектура приложения

### 3.1 Принцип работы

```
Пользователь                Nextcloud Flashcards v2                    Nextcloud Files
    │                              │                                        │
    ├─ Открывает приложение ──────►│                                        │
    │                              ├─ Читает список .md файлов ──────────► │
    │                              │◄─ Список файлов с тегами ─────────── │
    │ ◄──── Показывает колоды ─────┤                                        │
    │                              │                                        │
    ├─ Открывает колоду ──────────►│                                        │
    │                              ├─ Читает .md файл ──────────────────► │
    │                              │◄─ Содержимое файла ─────────────── │
    │                              ├─ Парсит карточки + SR-метаданные      │
    │ ◄── Карточки в памяти ──────┤  (буфер в PHP-сервисе)                  │
    │                              │                                        │
    ├─ Учит карточки ────────────►│                                        │
    │                              ├─ Обновляет SR-метаданные в буфере     │
    │ ◄── Следующая карточка ──────┤                                        │
    │                              │                                        │
    ├─ [каждые 10с или закрытие]──►│                                        │
    │                              ├─ Сохраняет .md файл ──────────────► │
    │ ◄── Подтверждение ──────────┤                                        │
```

### 3.2 Буферизация

- При открытии колоды: весь .md файл читается в memory (PHP backend)
- Парсится в массив карточек с SR-метаданными
- При каждом ответе: обновляется только SR в буфере (быстро)
- Автосохранение: каждые 10 секунд если `dirty=true` → сериализация обратно в .md
- При закрытии колоды: финальное сохранение
- Файлы 20-100 КБ: чтение/запись < 50ms (приемлемо)

### 3.3 Детектор конфликтов

Реальные файлы содержат git merge конфликты (`<<<<<<< HEAD`).
Парсер должен:
1. Обнаружить конфликты при загрузке
2. Показать UI для разрешения (выбрать HEAD или origin)
3. Или автоматически: взять SR-запись с бо́льшим интервалом (пользователь знает лучше)

## 4. Структура файлов

```
flashcards-v2/
├── appinfo/
│   ├── info.xml
│   └── routes.php
│
├── lib/
│   ├── AppInfo/
│   │   └── Application.php
│   │
│   ├── Controller/
│   │   ├── PageController.php        # Рендеринг SPA
│   │   ├── DeckController.php        # Список колод (= список .md файлов)
│   │   ├── CardController.php        # Получение/обновление карточек из буфера
│   │   ├── ReviewController.php      # SM-2 ответ → обновить SR в буфере
│   │   ├── StatsController.php       # Статистика (из SR-метаданных)
│   │   ├── SettingsController.php    # Пользовательские настройки (БД)
│   │   └── TTSController.php         # Text-to-Speech
│   │
│   ├── Service/
│   │   ├── DeckFileService.php       # Чтение/запись .md файлов через NC Files API
│   │   ├── CardParserService.php     # Парсинг .md → карточки + SR
│   │   ├── CardSerializerService.php # Карточки + SR → .md (обратная сериализация)
│   │   ├── BufferService.php         # In-memory буфер открытых колод
│   │   ├── SM2Service.php            # SM-2 алгоритм (переиспользуем)
│   │   ├── StatsService.php          # Подсчет статистики из SR-метаданных
│   │   ├── ConflictResolver.php      # Обработка git-конфликтов в .md
│   │   └── TTSService.php            # Text-to-Speech (переиспользуем)
│   │
│   ├── Db/
│   │   ├── UserSettings.php          # Entity: настройки юзера
│   │   └── UserSettingsMapper.php    # Mapper
│   │
│   ├── Migration/
│   │   └── Version001000Date20260210.php  # Одна таблица: user_settings
│   │
│   └── Listener/
│       └── CspListener.php           # CSP для TTS (переиспользуем)
│
├── src/                              # Vue 3 + TypeScript frontend
│   ├── main.ts
│   ├── App.vue
│   ├── router.ts
│   │
│   ├── stores/
│   │   ├── deck.ts                   # Список колод (= файлов)
│   │   ├── study.ts                  # Текущая сессия обучения
│   │   ├── settings.ts              # Настройки
│   │   └── stats.ts                 # Статистика
│   │
│   ├── views/
│   │   ├── Dashboard.vue            # Главная с due-карточками
│   │   ├── DeckBrowser.vue          # Список .md файлов = колод
│   │   ├── StudySession.vue         # Обучение
│   │   ├── CardBrowser.vue          # Просмотр/редактирование карточек
│   │   ├── Statistics.vue           # Статистика
│   │   └── Settings.vue             # Настройки
│   │
│   ├── components/
│   │   ├── deck/
│   │   ├── card/
│   │   ├── study/
│   │   └── stats/
│   │
│   ├── composables/
│   │   ├── useStudy.ts              # Логика обучения
│   │   ├── useTTS.ts                # Text-to-Speech
│   │   ├── useKeyboard.ts          # Горячие клавиши
│   │   └── useAutoSave.ts          # Автосохранение каждые 10с
│   │
│   ├── services/
│   │   └── api.ts                   # HTTP клиент
│   │
│   └── types/
│       ├── card.ts                  # ParsedCard, ClozeCard, BasicCard
│       ├── deck.ts                  # DeckFile, DeckMeta
│       └── sr.ts                    # SRMetadata, ReviewResult
│
├── l10n/                            # Переиспользуем en.json, ru.json, sr.json
├── img/                             # Переиспользуем иконки
├── css/
├── templates/
│   └── main.php
│
├── tests/
│   ├── Unit/
│   │   ├── Service/
│   │   │   ├── CardParserServiceTest.php
│   │   │   ├── CardSerializerServiceTest.php
│   │   │   ├── ConflictResolverTest.php
│   │   │   └── SM2ServiceTest.php
│   │   └── Db/
│   ├── vitest/
│   │   ├── stores/
│   │   └── composables/
│   └── phpunit.xml
│
├── composer.json
├── package.json
├── tsconfig.json
├── vite.config.ts
└── Makefile
```

## 5. База данных

### ОДНА таблица: `oc_flashcards_user_settings`

```sql
CREATE TABLE oc_flashcards_user_settings (
    user_id     VARCHAR(64) PRIMARY KEY,
    settings    TEXT,          -- JSON: все настройки
    created_at  TIMESTAMP,
    updated_at  TIMESTAMP
);
```

**Содержимое `settings` JSON:**
```json
{
  "deckFolder": "/ObsidianSync",
  "cardLayout": "classic",
  "buttonPosition": "bottom",
  "showProgress": true,
  "autoPlayAudio": false,
  "keyboardShortcuts": true,
  "fullscreenMode": false,
  "autoSaveInterval": 10,
  "theme": "auto",
  "defaultLanguage": "",
  "ttsVoice": "en-US-AriaNeural",
  "cardsPerDay": 50,
  "newCardsPerDay": 20
}
```

**Всё! Больше никаких таблиц.** SM-2 данные живут в `<!--SR:-->` тегах внутри .md файлов.

## 6. API Endpoints

### Колоды (= .md файлы)
```
GET    /api/v1/decks                    # Список .md файлов из deckFolder
GET    /api/v1/decks/{path}             # Открыть колоду = прочитать + парсить .md
POST   /api/v1/decks/{path}/save        # Сохранить буфер обратно в .md
POST   /api/v1/decks                    # Создать новый .md файл
DELETE /api/v1/decks/{path}             # Удалить .md файл
```

### Карточки (из буфера)
```
GET    /api/v1/decks/{path}/cards       # Все карточки из открытой колоды
GET    /api/v1/decks/{path}/due         # Карточки, которые пора повторять
POST   /api/v1/decks/{path}/cards       # Добавить карточку (в буфер)
PUT    /api/v1/decks/{path}/cards/{idx} # Редактировать карточку
DELETE /api/v1/decks/{path}/cards/{idx} # Удалить карточку
```

### Обучение
```
POST   /api/v1/review                   # Отправить ответ (rating 1-5)
                                        # → SM-2 пересчет → обновление SR в буфере
GET    /api/v1/review/session/{path}    # Начать сессию (due карточки из колоды)
```

### Статистика
```
GET    /api/v1/stats/overview           # Общая статистика (парсинг ВСЕХ .md файлов)
GET    /api/v1/stats/deck/{path}        # Статистика по одной колоде
GET    /api/v1/stats/due-counts         # Сколько карточек повторять по каждой колоде
```

### Настройки (единственное что в БД)
```
GET    /api/v1/settings
PUT    /api/v1/settings
```

### TTS
```
POST   /api/v1/tts/synthesize           # Text-to-Speech
```

## 7. Парсер карточек

### 7.1 Типы карточек

**Type 1: Basic (word:::translation)**
```
da:::yes, да
<!--SR:!2026-06-03,367,366!2026-05-31,364,361-->
```
→ `{ type: 'basic', front: 'da', back: 'yes, да', sr: [{date,interval,ease}, {date,interval,ease}] }`

**Type 2: Cloze (==word==^[hint])**
```
My cat sleeps ==as==^[как] long as my dog does.
Мой кот спит так же долго, как и моя собака.
<!--SR:!2025-10-10,4,270-->
```
→ `{ type: 'cloze', sentence: '...', translation: '...', clozes: [{word:'as', hint:'как'}], sr: [{...}] }`

**Type 3: Multi-cloze (несколько ==...== в одной строке)**
```
==Ja==^[I] ==jesam==^[am] sigurna u to.
I am sure about it.
<!--SR:!2026-05-21,415,250!2026-05-06,86,196-->
```
→ `{ type: 'cloze', clozes: [{word:'Ja', hint:'I'}, {word:'jesam', hint:'am'}], sr: [{...},{...}] }`

**Type 4: Word with transcription**
```
hot [ hɒt ] ::: горячий (adjective)
<!--SR:!2000-01-01,1,250!2025-10-10,4,270-->
```
→ `{ type: 'basic', front: 'hot', transcription: 'hɒt', back: 'горячий (adjective)', sr: [{...},{...}] }`

### 7.2 Парсинг алгоритм

```
1. Разбить файл на строки
2. Первая строка с #flashcards/ → извлечь тег = путь колоды
3. Для каждой строки:
   a. Если содержит `:::` → Basic карточка
   b. Если содержит `==...==` → Cloze карточка
   c. Если содержит `<!--SR:...-->` → привязать SR к предыдущей карточке
   d. Если содержит `<<<<<<` → git конфликт, пометить
   e. Иначе → контекст/перевод/пример текущей карточки
4. Вернуть: { tag, cards: [...], conflicts: [...] }
```

### 7.3 Сериализация (обратно в .md)

**Критически важно: сохранить формат пользователя!**
- НЕ переформатировать текст
- Обновлять ТОЛЬКО `<!--SR:-->` теги
- Сохранять пустые строки, переносы, пробелы как есть
- Если конфликт был разрешён — убрать маркеры `<<<<<<`

## 8. SM-2 алгоритм

Переиспользуем из v1:
- `easeFactor`: 2.5 по умолчанию, ×100 для хранения в SR тегах
- `interval`: в днях
- `nextReview`: дата YYYY-MM-DD
- Rating: 1=Again, 2=Hard, 3=Good, 4=Easy, 5=Perfect

## 9. Что переиспользуем из v1

| Компонент | Переиспользуем | Изменения |
|-----------|---------------|-----------|
| SM2Algorithm.php | ✅ Полностью | Нет |
| TTSService.php | ✅ Полностью | Нет |
| TTSController.php | ✅ Полностью | Rate limiting |
| CspListener.php | ✅ Полностью | Нет |
| UserSettings entity | ✅ Упрощаем | Один JSON-поле settings |
| SettingsController | ✅ Упрощаем | Нет DateTime полей |
| img/app.svg, app-dark.svg | ✅ Полностью | Нет |
| l10n/*.json | ✅ Частично | Убрать ключи deck/card CRUD |
| templates/main.php | ✅ Полностью | Нет |
| vite.config.ts | ✅ Полностью | Нет |
| composables/useTTS.ts | ✅ Полностью | Нет |
| composables/useKeyboard.ts | ✅ Полностью | Нет |
| Settings.vue (UI) | ✅ Частично | Добавить folder picker |
| StudySession.vue | ✅ Частично | Адаптировать под новые типы |
| Statistics.vue | ✅ Частично | Данные из файлов |

## 10. Производительность

| Операция | Размер файла | Время |
|----------|-------------|-------|
| Чтение .md файла | 80 KB | < 10ms |
| Парсинг 387 карточек | 80 KB | < 50ms |
| Обновление 1 SR-тега | in-memory | < 1ms |
| Сохранение .md файла | 80 KB | < 20ms |
| Сканирование 50 файлов (due counts) | ~2 MB total | < 500ms |

**Автосохранение каждые 10с:** файл 80KB — запись на диск ~20ms, приемлемо.
**Первая загрузка (все колоды):** сканирование всех .md файлов для подсчёта due — кешировать на 60с.
