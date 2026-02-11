# Flashcards v2 — Bug Fixes and Improvements Plan

**Date:** 2026-02-11  
**Status:** Planning Phase  
**Goal:** Fix critical bugs and implement UX improvements based on user feedback

---

## 🔴 CRITICAL BUGS

### 1. TypeError: can't access property "toString", t.count is undefined
**Location:** `flashcards-main.js:62`  
**Impact:** App crashes when rendering decks  
**Root Cause:** StatsStore or DeckStore returning undefined count  
**Fix Required:**
- Check all computed properties accessing `.count`
- Add null/undefined guards before `.toString()`
- Verify API responses have proper structure

### 2. @nextcloud/vue appName/appVersion warnings
**Status:** Partially fixed  
**Current Issue:** appConfig initialization doesn't set appName/appVersion correctly  
**Fix Required:**
```typescript
// In main.ts, before Vue mount:
declare global {
  interface Window {
    appName?: string
    appVersion?: string
  }
}
window.appName = 'flashcards'
window.appVersion = '2.0.0'
```

### 3. Settings checkboxes not working
**Impact:** User cannot toggle settings  
**Root Cause:** Settings form not bound to store properly  
**Fix Required:**
- Check v-model bindings in Settings.vue
- Verify SettingsStore mutations
- Add @change handlers if needed

---

## 🎨 UX IMPROVEMENTS

### 4. App icon color (black → white)
**Current:** Icon is black on dark theme  
**Required:** White icon like v1  
**Files to check:**
- `img/app.svg` — ensure fill="white" or currentColor
- `appinfo/info.xml` — verify icon reference

### 5. Folder tree selector for deck folders
**Current:** Plain text input for folder path  
**Required:** Tree view with checkboxes like Nextcloud Files  
**Implementation:**
- Use `@nextcloud/vue` NcTreeView component
- Fetch folder structure via WebDAV API
- Allow multi-folder selection with checkboxes
- Store selected paths as array in settings

### 6. Dashboard simplification
**Current:** Multiple dashboard views  
**Required:** Single unified dashboard with overall statistics  
**Changes:**
- Remove per-folder/tag dashboards
- Show global stats: total cards, due today, studied today, accuracy
- Add charts: daily study count, retention curve

### 7. Decks view with folder tree
**Current:** Flat list of decks  
**Required:** Hierarchical folder tree with expandable folders  
**Implementation:**
- Group decks by folder path
- Show folder tree with +/- expand icons
- Display deck cards inside folders
- Match Nextcloud Files UI style

---

## 📋 DETAILED IMPLEMENTATION PLAN

### Phase 1: Critical Bug Fixes (Priority 1)

#### Task 1.1: Fix count.toString() error
**Files:** `src/stores/decks.ts`, `src/stores/stats.ts`, `src/views/Decks.vue`
**Steps:**
1. Add null checks: `deck.stats?.count ?? 0`
2. Initialize all count fields to 0 in store
3. Verify API response structure matches expected format

#### Task 1.2: Fix @nextcloud/vue warnings
**File:** `src/main.ts`
**Code:**
```typescript
window.appName = 'flashcards'
window.appVersion = '2.0.0'
// Or set in OC.appswebroots
if (window.OC) {
    window.OC.appswebroots = window.OC.appswebroots || {}
    window.OC.appswebroots.flashcards = OC.webroot + '/custom_apps/flashcards'
}
```

#### Task 1.3: Fix settings checkboxes
**File:** `src/views/Settings.vue`
**Steps:**
1. Check v-model bindings use settingsStore getters/setters
2. Add proper @update:modelValue handlers
3. Test with Vue devtools

### Phase 2: Icon Fix (Priority 2)

#### Task 2.1: Update app icon to white
**File:** `img/app.svg`
**Changes:**
- Ensure SVG uses `fill="currentColor"` or `fill="#fff"`
- Test on dark/light themes
- Regenerate from v1 if needed

### Phase 3: Folder Tree Selector (Priority 2)

#### Task 3.1: Create FolderTreeSelector component
**New File:** `src/components/FolderTreeSelector.vue`
**Features:**
- Fetch folders via WebDAV: `PROPFIND /remote.php/dav/files/{user}/`
- Render tree with NcCheckboxRadioSwitch for each folder
- Emit selected paths array
- Support recursive selection (select folder = select all subfolders)

#### Task 3.2: Integrate into Settings
**File:** `src/views/Settings.vue`
**Changes:**
- Replace `<input>` for deckFolder with `<FolderTreeSelector>`
- Store multiple paths: `deckFolders: string[]` instead of single path
- Update backend to scan multiple folders

### Phase 4: Dashboard Simplification (Priority 3)

#### Task 4.1: Create unified Dashboard.vue
**File:** `src/views/Dashboard.vue`
**Layout:**
```
┌─────────────────────────────────────┐
│  Today's Stats                      │
│  📊 15 cards studied | 12 due       │
├─────────────────────────────────────┤
│  Overall Progress                   │
│  📈 Graph: Daily study count (7d)   │
│  📉 Retention rate: 87%             │
├─────────────────────────────────────┤
│  Decks Overview                     │
│  • English: 105 due                 │
│  • Serbian: 39 due                  │
└─────────────────────────────────────┘
```

### Phase 5: Hierarchical Decks View (Priority 3)

#### Task 5.1: Create DeckTree component
**New File:** `src/components/DeckTree.vue`
**Features:**
- Group decks by folder path
- Expandable/collapsible folders with +/- icons
- Show folder stats (sum of all decks inside)
- Deck cards inside folders
- Match NC Files UI: wide layout, same colors/spacing

#### Task 5.2: Update Decks.vue
**File:** `src/views/Decks.vue`
**Changes:**
- Use `<DeckTree>` instead of flat grid
- Full-width layout
- Add search/filter bar at top

---

## 🧪 TESTING CHECKLIST

### Critical Bugs
- [ ] No TypeError on Decks page load
- [ ] No @nextcloud/vue warnings in console
- [ ] Settings checkboxes toggle on click
- [ ] Settings persist after page reload

### UX Improvements
- [ ] App icon is white/visible on dark theme
- [ ] Folder selector shows tree of all folders
- [ ] Can select multiple folders with checkboxes
- [ ] Dashboard shows unified stats, no per-folder views
- [ ] Decks view shows folder tree structure
- [ ] Folders can be expanded/collapsed
- [ ] Layout matches Nextcloud Files style

### Regression Tests
- [ ] Deck study session still works
- [ ] Card parsing from .md files works
- [ ] SM-2 scheduling updates .md files
- [ ] Statistics API returns correct data
- [ ] TTS still works
- [ ] Settings save/load correctly

---

## 📦 DELIVERABLES

1. **Bug fixes:**
   - count.toString() error resolved
   - @nextcloud/vue warnings eliminated
   - Settings checkboxes functional

2. **New components:**
   - `FolderTreeSelector.vue` — multi-folder picker
   - `DeckTree.vue` — hierarchical deck list

3. **Updated views:**
   - `Dashboard.vue` — simplified unified view
   - `Decks.vue` — uses DeckTree
   - `Settings.vue` — uses FolderTreeSelector

4. **Updated stores:**
   - `settings.ts` — support multiple deck folders
   - `decks.ts` — group by folder path

5. **Tests:**
   - Unit tests for new components
   - E2E test for full workflow

---

## 🔗 REFERENCE

- **v1 folder tree:** Check `NextRepitition/.github/todo.MD` for v1 implementation details
- **NC Components:** https://nextcloud-vue-components.netlify.app/
- **WebDAV API:** https://docs.nextcloud.com/server/latest/developer_manual/client_apis/WebDAV/

---

## ⏭️ NEXT STEPS

See `TODO.md` for detailed 20-step implementation plan.
