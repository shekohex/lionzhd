---
phase: 10
slug: searchable-category-navigation
status: ready
nyquist_compliant: true
wave_0_complete: false
created: 2026-03-19
---

# Phase 10 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest `^4.4` + PHPUnit + pest-plugin-browser `^4.3` |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `./vendor/bin/pest tests/Feature/Actions/BuildPersonalizedCategorySidebarTest.php tests/Feature/Discovery/MoviesPersonalCategoryControlsTest.php tests/Feature/Discovery/SeriesPersonalCategoryControlsTest.php` |
| **Full suite command** | `./vendor/bin/pest tests/Feature/Actions/BuildPersonalizedCategorySidebarTest.php tests/Feature/Discovery/MoviesPersonalCategoryControlsTest.php tests/Feature/Discovery/SeriesPersonalCategoryControlsTest.php tests/Browser/SearchableCategoryNavigationTest.php` |
| **Estimated runtime** | ~45 seconds |

---

## Sampling Rate

- **After every task commit:** Run the task-specific `<automated>` verify command, then the shared smoke `./vendor/bin/pest tests/Feature/Actions/BuildPersonalizedCategorySidebarTest.php tests/Feature/Discovery/MoviesPersonalCategoryControlsTest.php tests/Feature/Discovery/SeriesPersonalCategoryControlsTest.php`
- **After every plan wave:** Run `./vendor/bin/pest tests/Feature/Actions/BuildPersonalizedCategorySidebarTest.php tests/Feature/Discovery/MoviesPersonalCategoryControlsTest.php tests/Feature/Discovery/SeriesPersonalCategoryControlsTest.php tests/Browser/SearchableCategoryNavigationTest.php`
- **Before `/gsd-verify-work`:** Full suite must be green
- **Max feedback latency:** 45 seconds

---

## Per-task Verification Map

| task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 10-W0-01 | 00 | 1 | NAVG-01 | feature | `./vendor/bin/pest tests/Feature/Actions/BuildPersonalizedCategorySidebarTest.php --filter="search"` | ✅ | ⬜ pending |
| 10-W0-02 | 00 | 1 | NAVG-01 | browser | `./vendor/bin/pest tests/Browser/SearchableCategoryNavigationTest.php --filter="desktop"` | ❌ W0 | ⬜ pending |
| 10-W0-03 | 00 | 1 | NAVG-01 | browser | `./vendor/bin/pest tests/Browser/SearchableCategoryNavigationTest.php --filter="mobile"` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/Actions/BuildPersonalizedCategorySidebarTest.php` — search-focused assertions for hidden exclusion, active-query `All categories` suppression, and matching `Uncategorized` last.
- [ ] `tests/Browser/SearchableCategoryNavigationTest.php` — desktop keyboard navigation and mobile sheet search flows.
- [ ] `tests/Browser/CategorySidebarScrollTest.php` baseline confirmed or stabilized before Phase 10 browser search assertions become a merge gate.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Desktop sidebar search feels fuzzy, highlights matches clearly, and preserves browse context after selecting a result | NAVG-01 | Ranking quality and visual emphasis are subjective even with browser coverage | Open `/movies` and `/series`, type partial/fuzzy queries in the desktop sidebar, use Arrow keys + Enter, confirm the selected category loads and the search-first presentation feels obvious. |
| Mobile sheet search resets when the sheet closes and remains available in both browse and manage modes | NAVG-01 | Touch interactions plus close/reopen affordances are easier to validate interactively | Open mobile viewport on `/movies` and `/series`, search from browse mode, select a result, reopen the sheet, confirm the query reset; then switch to manage mode and confirm search is still available. |

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references
- [x] No watch-mode flags
- [x] Feedback latency < 60s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
