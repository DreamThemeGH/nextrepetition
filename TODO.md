# Flashcards v2 — Implementation TODO

**Created:** 2026-02-11  
**Status:** 🚀 Ready to Execute

---

## 🔴 CRITICAL FIXES (Tasks 1-6)

### ✅ TODO #1: Fix count.toString() TypeError
**Priority:** P0 — BLOCKER  
**File:** `src/stores/decks.ts`, `src/views/Decks.vue`  
**Description:** Add null guards for all `.count.toString()` calls  
**Acceptance Criteria:**
- No TypeError in browser console when loading Decks page
- All count displays show "0" instead of crashing
**Implementation:**
```typescript
// In computed properties:
const totalCards = computed(() => deck.value?.stats?.count ?? 0)
const formattedCount = totalCards.value.toString()
```

---

### ✅ TODO #2: Fix @nextcloud/vue appName/appVersion warnings
**Priority:** P0 — BLOCKER  
**File:** `src/main.ts`  
**Description:** Properly set window.appName and window.appVersion globals  
**Acceptance Criteria:**
- No @nextcloud/vue warnings in console
- appName === 'flashcards', appVersion === '2.0.0'
**Implementation:**
```typescript
// Before createApp:
window.appName = 'flashcards'
window.appVersion = '2.0.0'
```

---

### ✅ TODO #3: Fix settings checkboxes not toggling
**Priority:** P0 — BLOCKER  
**File:** `src/views/Settings.vue`, `src/stores/settings.ts`  
**Description:** Debug v-model bindings for all checkbox inputs  
**Steps:**
1. Check if settingsStore has proper getters/setters for each setting
2. Verify v-model uses correct property names
3. Add @update:modelValue handlers if needed
4. Test toggle with Vue DevTools
**Acceptance Criteria:**
- All checkboxes toggle on click
- Settings persist after reload

---

### ✅ TODO #4: Update app icon to white
**Priority:** P1 — HIGH  
**File:** `img/app.svg`  
**Description:** Change icon color from black to white for dark theme visibility  
**Steps:**
1. Check current SVG fill color
2. Change to `fill="currentColor"` or `fill="#fff"`
3. Test on both dark/light themes
4. Compare with v1 icon if needed
**Acceptance Criteria:**
- Icon visible on dark theme top bar
- Icon color matches Nextcloud standard (white/currentColor)

---

### ✅ TODO #5: Investigate CSP warnings
**Priority:** P2 — MEDIUM  
**File:** Various, check browser console  
**Description:** Identify source of 2 CSP warnings and add proper nonces/hashes  
**Steps:**
1. Check exact CSP error messages in console
2. Identify violating script/style sources
3. Add to lib/Listener/CspListener.php if needed
**Acceptance Criteria:**
- 0 CSP warnings in console

---

### ✅ TODO #6: Add comprehensive error handling to API calls
**Priority:** P1 — HIGH  
**Files:** `src/services/api.ts`, all stores  
**Description:** Wrap all API calls in try-catch, show user-friendly errors  
**Acceptance Criteria:**
- All fetch() calls have error handlers
- User sees toast notification on API failure
- No unhandled promise rejections

---

## 🎨 UX IMPROVEMENTS (Tasks 7-14)

### ✅ TODO #7: Create FolderTreeSelector component
**Priority:** P1 — HIGH  
**New File:** `src/components/FolderTreeSelector.vue`  
**Description:** Reusable component for selecting folders with tree view + checkboxes  
**Features:**
- Fetch folder tree via WebDAV PROPFIND
- Recursive tree rendering with expand/collapse
- Checkboxes for each folder
- Emit array of selected paths
- Show loading state while fetching
**Dependencies:**
- @nextcloud/vue NcTree or custom implementation
- WebDAV service for folder listing
**Acceptance Criteria:**
- Shows full folder tree from user's Files
- Can select multiple folders
- Emits `update:selected` with string array

---

### ✅ TODO #8: Create WebDAV service for folder operations
**Priority:** P1 — HIGH  
**New File:** `src/services/webdav.ts`  
**Description:** Service for WebDAV operations (list folders, read files, etc.)  
**Methods:**
```typescript
export async function listFolders(path: string): Promise<FolderNode[]>
export async function readFile(path: string): Promise<string>
export async function writeFile(path: string, content: string): Promise<void>
export async function searchFiles(path: string, pattern: string): Promise<string[]>
```
**Acceptance Criteria:**
- Can list all folders recursively
- Handles authentication via NC session
- Returns proper TypeScript types

---

### ✅ TODO #9: Update Settings.vue to use FolderTreeSelector
**Priority:** P1 — HIGH  
**File:** `src/views/Settings.vue`  
**Description:** Replace text input with folder tree selector  
**Changes:**
- Replace `<input v-model="deckFolder">` with `<FolderTreeSelector>`
- Update settings store to accept `deckFolders: string[]` instead of single path
- Show selected folders as chips below selector
**Acceptance Criteria:**
- Settings page shows folder tree
- Can select multiple folders
- Selected folders saved to backend

---

### ✅ TODO #10: Update backend to scan multiple folders
**Priority:** P1 — HIGH  
**Files:** `lib/Service/DeckService.php`, `lib/Db/UserSettings.php`  
**Description:** Support scanning multiple folder paths for .md files  
**Changes:**
1. Update UserSettings.php: `deckFolder` → `deckFolders` (JSON array)
2. Update DeckService->scanDecks() to iterate over multiple paths
3. Merge results from all folders
**Acceptance Criteria:**
- Backend accepts array of folder paths
- Scans all folders recursively
- Deduplicates decks by file path

---

### ✅ TODO #11: Simplify Dashboard to single unified view
**Priority:** P2 — MEDIUM  
**File:** `src/views/Dashboard.vue`  
**Description:** Remove per-folder dashboards, show only global stats  
**Layout:**
```
┌─────────────────────────────────────┐
│ 📊 Today's Study Session            │
│ Cards studied: 15 | Due: 12         │
│ Time spent: 18 min | Accuracy: 87%  │
├─────────────────────────────────────┤
│ 📈 7-Day Study Trend                │
│ [Bar chart: cards/day]              │
├─────────────────────────────────────┤
│ 🎯 Top Decks by Due Cards           │
│ 1. English_flashcards: 105 due     │
│ 2. Serbian learning: 39 due         │
│ 3. Popular Words: 90 due            │
└─────────────────────────────────────┘
```
**Acceptance Criteria:**
- Only one dashboard view
- Shows aggregated stats across all decks
- Includes chart component

---

### ✅ TODO #12: Create DeckTree component for hierarchical deck display
**Priority:** P1 — HIGH  
**New File:** `src/components/DeckTree.vue`  
**Description:** Tree view of decks grouped by folder structure  
**Features:**
- Parse deck paths to build folder hierarchy
- Expandable/collapsible folders with +/- icons
- Show folder stats (sum of contained decks)
- Deck cards inside folders
- Match Nextcloud Files UI style
**Data Structure:**
```typescript
interface FolderNode {
  name: string
  path: string
  children: FolderNode[]
  decks: Deck[]
  stats: { total: number, due: number, new: number }
}
```
**Acceptance Criteria:**
- Decks grouped by folder
- Can expand/collapse folders
- Full-width layout like NC Files

---

### ✅ TODO #13: Update Decks.vue to use DeckTree
**Priority:** P1 — HIGH  
**File:** `src/views/Decks.vue`  
**Description:** Replace flat deck grid with hierarchical tree  
**Changes:**
- Remove current grid layout
- Add `<DeckTree :decks="allDecks" />`
- Add search bar at top
- Full-width container
**Acceptance Criteria:**
- Decks page shows folder tree
- Search filters both folders and decks
- "New deck" button still works

---

### ✅ TODO #14: Add charts to Dashboard
**Priority:** P2 — MEDIUM  
**File:** `src/views/Dashboard.vue`  
**Description:** Add Chart.js or similar for visual stats  
**Charts:**
1. Bar chart: cards studied per day (last 7 days)
2. Line chart: retention rate trend
3. Pie chart: deck distribution by category/folder
**Dependencies:**
- Install chart.js or vue-chartjs
- Create reusable Chart components
**Acceptance Criteria:**
- Dashboard shows 2-3 charts
- Charts update when data changes
- Responsive on mobile

---

## 🧪 TESTING & POLISH (Tasks 15-19)

### ✅ TODO #15: Write unit tests for new components
**Priority:** P2 — MEDIUM  
**Files:** `tests/unit/FolderTreeSelector.spec.ts`, `tests/unit/DeckTree.spec.ts`  
**Description:** Test all new Vue components  
**Coverage:**
- FolderTreeSelector: folder fetching, selection, emit events
- DeckTree: hierarchy building, expand/collapse, stats aggregation
**Acceptance Criteria:**
- All components have >80% coverage
- Tests pass in CI

---

### ✅ TODO #16: Add E2E test for complete workflow
**Priority:** P2 — MEDIUM  
**New File:** `tests/e2e/deck-study-flow.spec.ts`  
**Description:** End-to-end test covering full user journey  
**Steps:**
1. Login to NC
2. Open Flashcards app
3. Select folders in settings
4. Navigate to Decks
5. Expand folder in tree
6. Click "Study" on a deck
7. Review cards and rate
8. Verify stats update
**Tools:** Playwright or Cypress
**Acceptance Criteria:**
- E2E test passes on clean NC install

---

### ✅ TODO #17: Optimize bundle size
**Priority:** P3 — LOW  
**Files:** `vite.config.ts`, package dependencies  
**Description:** Reduce JS bundle size from 612KB  
**Actions:**
- Enable tree-shaking for @nextcloud/vue
- Lazy-load chart library
- Check for duplicate dependencies
- Use vite-plugin-compression
**Target:** Reduce to <400KB gzipped
**Acceptance Criteria:**
- Bundle size reduced by 30%+
- All features still work

---

### ✅ TODO #18: Add loading states and skeletons
**Priority:** P2 — MEDIUM  
**Files:** All views  
**Description:** Show skeleton loaders while fetching data  
**Changes:**
- Add `loading` state to all stores
- Use NcLoadingIcon or NcEmptyContent
- Show skeleton cards/trees while loading
**Acceptance Criteria:**
- No blank screens while loading
- User sees progress indication

---

### ✅ TODO #19: Accessibility audit and fixes
**Priority:** P2 — MEDIUM  
**Files:** All components  
**Description:** Ensure app is keyboard-navigable and screen-reader friendly  
**Checks:**
- All interactive elements have aria-labels
- Keyboard navigation works (Tab, Enter, Space)
- Focus indicators visible
- Color contrast meets WCAG AA
**Tools:** axe DevTools, Lighthouse
**Acceptance Criteria:**
- Lighthouse accessibility score >90
- Can complete study session with keyboard only

---

## 📚 DOCUMENTATION & HANDOFF (Task 20)

### ✅ TODO #20: Read and follow .github/todo.MD instructions
**Priority:** P0 — CRITICAL  
**File:** `/home/freya/DockerProjects/NextcloudAIO/NextRepitition/.github/todo.MD`  
**Description:** Review v1 implementation details and migration guidelines  
**Actions:**
1. Read entire todo.MD file
2. Note any v1 features missing in v2
3. Check for migration scripts needed
4. Follow any specific coding standards mentioned
5. Update ARCHITECTURE.md with final state
6. Write MIGRATION-v1-to-v2.md guide
**Acceptance Criteria:**
- All instructions from todo.MD followed
- No regression from v1 features
- Migration path documented

---

## 📊 PROGRESS TRACKER

| Task | Status | Priority | Assignee | ETA |
|------|--------|----------|----------|-----|
| #1 count.toString() fix | 🔴 TODO | P0 | - | 30min |
| #2 @nextcloud/vue warnings | 🔴 TODO | P0 | - | 15min |
| #3 Settings checkboxes | 🔴 TODO | P0 | - | 1h |
| #4 White icon | 🔴 TODO | P1 | - | 15min |
| #5 CSP warnings | 🔴 TODO | P2 | - | 30min |
| #6 Error handling | 🔴 TODO | P1 | - | 2h |
| #7 FolderTreeSelector | 🔴 TODO | P1 | - | 4h |
| #8 WebDAV service | 🔴 TODO | P1 | - | 3h |
| #9 Settings update | 🔴 TODO | P1 | - | 1h |
| #10 Backend multi-folder | 🔴 TODO | P1 | - | 2h |
| #11 Dashboard simplify | 🔴 TODO | P2 | - | 3h |
| #12 DeckTree component | 🔴 TODO | P1 | - | 5h |
| #13 Decks.vue update | 🔴 TODO | P1 | - | 1h |
| #14 Dashboard charts | 🔴 TODO | P2 | - | 3h |
| #15 Unit tests | 🔴 TODO | P2 | - | 3h |
| #16 E2E test | 🔴 TODO | P2 | - | 2h |
| #17 Bundle optimization | 🔴 TODO | P3 | - | 2h |
| #18 Loading states | 🔴 TODO | P2 | - | 2h |
| #19 Accessibility | 🔴 TODO | P2 | - | 3h |
| #20 Read .github/todo.MD | 🔴 TODO | P0 | - | 1h |

**Total Estimated Time:** ~40 hours  
**Critical Path:** Tasks #1-4, #7-10, #12-13, #20

---

## 🚀 EXECUTION ORDER

**Phase 1 (Blockers):** #1 → #2 → #3 → #4 → #6  
**Phase 2 (Backend):** #8 → #10  
**Phase 3 (Frontend):** #7 → #9 → #12 → #13  
**Phase 4 (Polish):** #11 → #14 → #18  
**Phase 5 (Quality):** #5 → #15 → #16 → #19  
**Phase 6 (Final):** #17 → #20

---

## 📝 NOTES

- All tasks should include git commit after completion
- Test each task in browser before marking complete
- Update this TODO.md with ✅ and commit hash when done
- Refer to BUGFIXES-AND-IMPROVEMENTS.md for detailed specs

**Let's ship it! 🚀**
