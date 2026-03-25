---
phase: 14
slug: refresh-ignored-discovery-browser-recovery-proof
status: draft
nyquist_compliant: true
wave_0_complete: true
created: 2026-03-25
---

# Phase 14 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest `^4.4` + `pestphp/pest-plugin-browser` `^4.3` + Playwright `1.54.1` |
| **Config file** | `phpunit.xml`, `tests/Pest.php`, `tests/Browser/Support/browser-auth.php` |
| **Quick run command** | `php -l tests/Browser/IgnoredDiscoveryFiltersTest.php && ./vendor/bin/pest tests/Feature/Discovery/MoviesPersonalCategoryControlsTest.php tests/Feature/Discovery/SeriesPersonalCategoryControlsTest.php --filter=ignored --stop-on-failure` |
| **Full suite command** | `bun run test:browser:prepare && ./vendor/bin/pest --stop-on-failure` |
| **Estimated runtime** | ~15 seconds quick probe; ~90 seconds full browser proof |

---

## Sampling Rate

- **After every task commit:** Run `php -l tests/Browser/IgnoredDiscoveryFiltersTest.php && ./vendor/bin/pest tests/Feature/Discovery/MoviesPersonalCategoryControlsTest.php tests/Feature/Discovery/SeriesPersonalCategoryControlsTest.php --filter=ignored --stop-on-failure`
- **After every plan wave:** Run `bun run test:browser:prepare && ./vendor/bin/pest tests/Browser/IgnoredDiscoveryFiltersTest.php tests/Feature/Discovery/MoviesPersonalCategoryControlsTest.php tests/Feature/Discovery/SeriesPersonalCategoryControlsTest.php --filter=ignored --stop-on-failure`
- **Before `/gsd-verify-work`:** Full suite must be green
- **Max feedback latency:** 15 seconds for task-level smoke, ~90 seconds for end-of-wave browser proof

---

## Per-task Verification Map

| task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 14-01-01 | 01 | 1 | IGNR-01 | feature smoke + browser | `php -l tests/Browser/IgnoredDiscoveryFiltersTest.php && ./vendor/bin/pest tests/Feature/Discovery/MoviesPersonalCategoryControlsTest.php --filter=ignored --stop-on-failure && bun run test:browser:prepare && ./vendor/bin/pest tests/Browser/IgnoredDiscoveryFiltersTest.php --filter="movie" --stop-on-failure` | ✅ | ⬜ pending |
| 14-01-02 | 01 | 1 | IGNR-02 | feature smoke + browser | `php -l tests/Browser/IgnoredDiscoveryFiltersTest.php && ./vendor/bin/pest tests/Feature/Discovery/SeriesPersonalCategoryControlsTest.php --filter=ignored --stop-on-failure && bun run test:browser:prepare && ./vendor/bin/pest tests/Browser/IgnoredDiscoveryFiltersTest.php --filter="series" --stop-on-failure` | ✅ | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

Existing infrastructure covers all phase requirements.

---

## Manual-Only Verifications

All phase behaviors have automated verification.

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references
- [x] No watch-mode flags
- [x] Feedback latency < 30s for task-level smoke
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
