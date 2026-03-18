---
phase: 09
slug: ignored-discovery-filters
status: ready
nyquist_compliant: true
wave_0_complete: false
created: 2026-03-18
---

# Phase 09 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest `^4.4` + PHPUnit + pest-plugin-browser `^4.3` |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `./vendor/bin/pest tests/Feature/Discovery/MoviesPersonalCategoryControlsTest.php tests/Feature/Discovery/SeriesPersonalCategoryControlsTest.php tests/Feature/Controllers/CategoryPreferenceControllerTest.php tests/Feature/Actions/BuildPersonalizedCategorySidebarTest.php` |
| **Full suite command** | `./vendor/bin/pest` |
| **Estimated runtime** | ~30 seconds |

---

## Sampling Rate

- **After every task commit:** Run the task-specific `<automated>` verify command, then the shared smoke `./vendor/bin/pest tests/Feature/Discovery/MoviesPersonalCategoryControlsTest.php tests/Feature/Discovery/SeriesPersonalCategoryControlsTest.php tests/Feature/Controllers/CategoryPreferenceControllerTest.php tests/Feature/Actions/BuildPersonalizedCategorySidebarTest.php`
- **After every plan wave:** Run `./vendor/bin/pest`
- **Before `/gsd-verify-work`:** Full suite must be green
- **Max feedback latency:** 30 seconds

---

## Per-task Verification Map

| task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 09-W0-01 | 00 | 0 | IGNR-02 | browser | `php artisan test tests/Browser/IgnoredDiscoveryFiltersTest.php` | ❌ W0 | ⬜ pending |
| 09-01-01 | 01 | 2 | IGNR-02 | contract | `php artisan typescript:transform` | ✅ | ⬜ pending |
| 09-01-02 | 01 | 2 | IGNR-02 | feature | `./vendor/bin/pest tests/Feature/Actions/BuildPersonalizedCategorySidebarTest.php --filter=ignored --stop-on-failure` | ✅ | ⬜ pending |
| 09-06-01 | 06 | 1 | IGNR-01 | feature | `./vendor/bin/pest tests/Feature/Controllers/CategoryPreferenceControllerTest.php --filter=ignored --stop-on-failure` | ✅ | ⬜ pending |
| 09-02-01 | 02 | 3 | IGNR-01 | feature | `./vendor/bin/pest tests/Feature/Discovery/MoviesPersonalCategoryControlsTest.php --filter=ignored --stop-on-failure` | ✅ | ⬜ pending |
| 09-02-02 | 02 | 3 | IGNR-02 | feature | `./vendor/bin/pest tests/Feature/Discovery/MoviesPersonalCategoryControlsTest.php --filter=recovery --stop-on-failure` | ✅ | ⬜ pending |
| 09-03-01 | 03 | 3 | IGNR-01 | feature | `./vendor/bin/pest tests/Feature/Discovery/SeriesPersonalCategoryControlsTest.php --filter=ignored --stop-on-failure` | ✅ | ⬜ pending |
| 09-03-02 | 03 | 3 | IGNR-02 | feature | `./vendor/bin/pest tests/Feature/Discovery/SeriesPersonalCategoryControlsTest.php --filter=recovery --stop-on-failure` | ✅ | ⬜ pending |
| 09-04-01 | 04 | 3 | IGNR-01 | lint | `pnpm lint` | ✅ | ⬜ pending |
| 09-04-02 | 04 | 3 | IGNR-02 | lint | `pnpm lint` | ✅ | ⬜ pending |
| 09-05-01 | 05 | 4 | IGNR-02 | browser | `php artisan test tests/Browser/IgnoredDiscoveryFiltersTest.php --filter=movie --stop-on-failure` | ❌ W0 | ⬜ pending |
| 09-05-02 | 05 | 4 | IGNR-02 | browser | `php artisan test tests/Browser/IgnoredDiscoveryFiltersTest.php --filter=series --stop-on-failure` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Browser/IgnoredDiscoveryFiltersTest.php` — desktop/mobile ignore affordances and recovery CTA visibility
- [ ] `php artisan test tests/Browser/IgnoredDiscoveryFiltersTest.php` — browser harness smoke for ignored discovery flows
- [ ] `php artisan typescript:transform` — required whenever sidebar/filter DTO shape changes

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Ignored rows stay muted, visible, selectable, and sorted below normal visible rows on desktop browse | IGNR-01, IGNR-02 | Final visual distinction and row-density behavior are easier to confirm interactively than in feature tests | Open `/movies` and `/series`, ignore a visible category, confirm the row stays in the main list with muted styling at the bottom, select it, and verify the page stays on that category with recovery UI instead of results. |
| Mobile category sheet manage mode exposes ignore/unignore recovery without moving users into a separate flow | IGNR-02 | Touch-sheet affordances and recovery CTA prominence are best judged manually | Open mobile viewport, open the category sheet on `/movies` and `/series`, ignore categories from manage mode, confirm `All categories` empty state points back into manage mode, then unignore from the ignored-category recovery state and verify results restore on the same URL. |

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references
- [x] No watch-mode flags
- [x] Feedback latency < 60s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
