---
phase: 12
slug: detail-page-category-context
status: ready
nyquist_compliant: true
wave_0_complete: true
created: 2026-03-22
---

# Phase 12 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest ^4.4 + pest-plugin-laravel ^4.1 + pest-plugin-browser ^4.3 |
| **Config file** | `phpunit.xml` and `tests/Pest.php` |
| **Quick run command** | `./vendor/bin/pest tests/Feature/Jobs/SyncCategoriesTest.php tests/Feature/Actions/ResolveDetailPageCategoriesTest.php -x` |
| **Full suite command** | `php artisan typescript:transform && ./vendor/bin/pest tests/Feature/Jobs/SyncCategoriesTest.php tests/Feature/Jobs/RefreshMediaContentsTest.php tests/Feature/Actions/ResolveDetailPageCategoriesTest.php tests/Feature/Controllers/VodStreamDetailCategoryContextTest.php tests/Feature/Controllers/SeriesDetailCategoryContextTest.php tests/Browser/DetailPageCategoryContextTest.php --stop-on-failure && bun run lint && bun run build` |
| **Estimated runtime** | ~90 seconds |

---

## Sampling Rate

- **After every task commit:** Run the task-specific `<automated>` verify command, then the shared smoke `./vendor/bin/pest tests/Feature/Jobs/SyncCategoriesTest.php tests/Feature/Actions/ResolveDetailPageCategoriesTest.php -x`
- **After every plan wave:** Run `php artisan typescript:transform && ./vendor/bin/pest tests/Feature/Jobs/SyncCategoriesTest.php tests/Feature/Jobs/RefreshMediaContentsTest.php tests/Feature/Actions/ResolveDetailPageCategoriesTest.php tests/Feature/Controllers/VodStreamDetailCategoryContextTest.php tests/Feature/Controllers/SeriesDetailCategoryContextTest.php tests/Browser/DetailPageCategoryContextTest.php --stop-on-failure`
- **Before `/gsd-verify-work`:** Full suite must be green
- **Max feedback latency:** 30 seconds per task verify, ~90 seconds per wave gate

---

## Per-task Verification Map

| task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 12-01-01 | 01 | 1 | CTXT-01, CTXT-02 | feature scaffold | `php -l tests/Feature/Jobs/SyncCategoriesTest.php && php -l tests/Feature/Jobs/RefreshMediaContentsTest.php` | ✅ existing | ⬜ pending |
| 12-01-02 | 01 | 1 | CTXT-01, CTXT-02 | feature | `./vendor/bin/pest tests/Feature/Jobs/SyncCategoriesTest.php tests/Feature/Jobs/RefreshMediaContentsTest.php --stop-on-failure` | ✅ existing | ⬜ pending |
| 12-05-01 | 05 | 2 | CTXT-01, CTXT-02 | feature scaffold | `php -l tests/Feature/Actions/ResolveDetailPageCategoriesTest.php` | ❌ task creates | ⬜ pending |
| 12-05-02 | 05 | 2 | CTXT-01, CTXT-02 | feature + transform | `php artisan typescript:transform && ./vendor/bin/pest tests/Feature/Actions/ResolveDetailPageCategoriesTest.php --stop-on-failure` | ✅ after 12-05-01 | ⬜ pending |
| 12-02-01 | 02 | 3 | CTXT-01 | feature scaffold | `php -l tests/Feature/Controllers/VodStreamDetailCategoryContextTest.php` | ❌ task creates | ⬜ pending |
| 12-02-02 | 02 | 3 | CTXT-01 | feature + transform | `php artisan typescript:transform && ./vendor/bin/pest tests/Feature/Controllers/VodStreamDetailCategoryContextTest.php --stop-on-failure` | ✅ after 12-02-01 | ⬜ pending |
| 12-03-01 | 03 | 3 | CTXT-02 | feature scaffold | `php -l tests/Feature/Controllers/SeriesDetailCategoryContextTest.php` | ❌ task creates | ⬜ pending |
| 12-03-02 | 03 | 3 | CTXT-02 | feature + transform | `php artisan typescript:transform && ./vendor/bin/pest tests/Feature/Controllers/SeriesDetailCategoryContextTest.php --stop-on-failure` | ✅ after 12-03-01 | ⬜ pending |
| 12-04-01 | 04 | 4 | CTXT-01, CTXT-02 | frontend static | `bun run lint && bun run build` | ✅ existing | ⬜ pending |
| 12-04-02 | 04 | 4 | CTXT-01, CTXT-02 | browser smoke | `./vendor/bin/pest tests/Browser/DetailPageCategoryContextTest.php --filter="shows hero category chips and browse navigation for both media types" --stop-on-failure` | ❌ task creates | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

None. Every planned task already has an explicit `<automated>` command, and each new test file is created by the same task that immediately verifies it. No separate Wave 0 scaffold is required for Phase 12.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Hero row reads as a separate, unlabeled category context row without visually merging into genres | CTXT-01, CTXT-02 | Visual separation and hierarchy still need human judgment beyond DOM assertions | Open one movie and one series detail page, confirm category chips appear in the hero before scrolling, and confirm there is no `Categories` heading/icon and no merge into the genre row. |
| Mobile hero row remains compact while still showing the full assigned category set without truncation | CTXT-01, CTXT-02 | Final responsive readability still benefits from human visual confirmation | Open a movie and series detail page on a mobile viewport, confirm every assigned category chip is visible/wrapped, and confirm long names are not ellipsized or hidden behind a `+N` pattern. |

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references
- [x] No watch-mode flags
- [x] Feedback latency < 90s at wave gates and < 30s for task verifies
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
