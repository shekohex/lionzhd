---
phase: 08
slug: personal-category-controls
status: ready
nyquist_compliant: true
wave_0_complete: false
created: 2026-03-15
---

# Phase 08 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest `^4.4` + PHPUnit + pest-plugin-browser `^4.3` |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `./vendor/bin/pest tests/Feature/Discovery/MoviesPersonalCategoryControlsTest.php tests/Feature/Discovery/SeriesPersonalCategoryControlsTest.php --filter='hidden selected category read path smoke' -x` |
| **Full suite command** | `php artisan test` |
| **Estimated runtime** | ~20 seconds |

---

## Sampling Rate

- **After every task commit:** Run the task-specific `<automated>` verify command, then the shared smoke `./vendor/bin/pest tests/Feature/Discovery/MoviesPersonalCategoryControlsTest.php tests/Feature/Discovery/SeriesPersonalCategoryControlsTest.php --filter='hidden selected category read path smoke' -x`
- **After every plan wave:** Run `php artisan test`
- **Before `/gsd-verify-work`:** Full suite must be green
- **Max feedback latency:** 20 seconds

---

## Per-task Verification Map

| task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 08-W0-01 | 00 | 0 | PERS-01 | feature | `./vendor/bin/pest tests/Feature/Discovery/MoviesPersonalCategoryControlsTest.php tests/Feature/Discovery/SeriesPersonalCategoryControlsTest.php --filter=PERS-01 -x` | ❌ W0 | ⬜ pending |
| 08-W0-02 | 00 | 0 | PERS-02 | feature | `./vendor/bin/pest tests/Feature/Discovery/MoviesPersonalCategoryControlsTest.php tests/Feature/Discovery/SeriesPersonalCategoryControlsTest.php --filter=PERS-02 -x` | ❌ W0 | ⬜ pending |
| 08-W0-03 | 00 | 0 | PERS-03 | feature | `./vendor/bin/pest tests/Feature/Discovery/MoviesPersonalCategoryControlsTest.php tests/Feature/Discovery/SeriesPersonalCategoryControlsTest.php --filter=PERS-03 -x` | ❌ W0 | ⬜ pending |
| 08-W0-04 | 00 | 0 | PERS-04 | feature | `./vendor/bin/pest tests/Feature/Discovery/MoviesPersonalCategoryControlsTest.php tests/Feature/Discovery/SeriesPersonalCategoryControlsTest.php --filter=PERS-04 -x` | ❌ W0 | ⬜ pending |
| 08-W0-05 | 00 | 0 | PERS-05 | feature | `./vendor/bin/pest tests/Feature/Discovery/MoviesPersonalCategoryControlsTest.php tests/Feature/Discovery/SeriesPersonalCategoryControlsTest.php --filter=PERS-05 -x` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/Discovery/MoviesPersonalCategoryControlsTest.php` — stubs for PERS-01..05 movie flows
- [ ] `tests/Feature/Discovery/SeriesPersonalCategoryControlsTest.php` — stubs for PERS-01..05 series flows
- [ ] `app/Models/UserCategoryPreference.php` — persistence model for test helpers and write-path coverage
- [ ] `database/migrations/*create_user_category_preferences_table.php` — schema with composite unique index for safe upserts
- [ ] `php artisan typescript:transform` — required whenever sidebar DTO shape changes

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Desktop drag + hover/tap manage controls behave correctly in browse-attached UI | PERS-02, PERS-03, PERS-04 | Pointer drag/hover density behavior is brittle in feature tests and slower in browser automation | Open `/movies` and `/series`, enter manage mode, reorder pinned and non-pinned rows, pin up to limit, hide/unhide, refresh, confirm state persists and row actions remain separate from category navigation. |
| Mobile sheet manage mode supports drag handles, hidden recovery section, and instant-save mutations | PERS-02, PERS-04, PERS-05 | Touch drag inside sheet is best verified interactively | Open mobile viewport, open category sheet, switch to manage mode, reorder via drag handles, hide all visible categories, confirm hidden section + reset recovery, close/reopen sheet, verify persisted state. |

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references
- [x] No watch-mode flags
- [x] Feedback latency < 60s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
