---
phase: 13
slug: refresh-search-and-navigation-browser-auth-proof
status: draft
nyquist_compliant: true
wave_0_complete: false
created: 2026-03-25
---

# Phase 13 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest `^4.4` + pest-plugin-browser `^4.3` + Playwright `1.54.1` |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `bun run test:browser:prepare && ./vendor/bin/pest tests/Browser/SearchModeUxTest.php tests/Browser/SearchableCategoryNavigationTest.php -x` |
| **Full suite command** | `bun run test:browser:prepare && ./vendor/bin/pest tests/Feature/Controllers/SearchControllerTest.php tests/Browser/SearchModeUxTest.php tests/Browser/SearchableCategoryNavigationTest.php tests/Browser/CategorySidebarScrollTest.php` |
| **Estimated runtime** | ~120 seconds |

---

## Sampling Rate

- **After every task commit:** Run `bun run test:browser:prepare && ./vendor/bin/pest tests/Browser/SearchModeUxTest.php tests/Browser/SearchableCategoryNavigationTest.php -x`
- **After every plan wave:** Run `bun run test:browser:prepare && ./vendor/bin/pest tests/Feature/Controllers/SearchControllerTest.php tests/Browser/SearchModeUxTest.php tests/Browser/SearchableCategoryNavigationTest.php tests/Browser/CategorySidebarScrollTest.php`
- **Before `/gsd-verify-work`:** Full suite must be green
- **Max feedback latency:** 120 seconds

---

## Per-task Verification Map

| task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 13-TBD-01 | TBD | TBD | NAVG-01 | browser | `bun run test:browser:prepare && ./vendor/bin/pest tests/Browser/SearchableCategoryNavigationTest.php -x` | ✅ | ⬜ pending |
| 13-TBD-02 | TBD | TBD | SRCH-01 | browser | `bun run test:browser:prepare && ./vendor/bin/pest tests/Browser/SearchModeUxTest.php --filter="syncs mode tabs with url" -x` | ✅ | ⬜ pending |
| 13-TBD-03 | TBD | TBD | SRCH-02 | browser | `bun run test:browser:prepare && ./vendor/bin/pest tests/Browser/SearchModeUxTest.php --filter="renders filtered full width layout" -x` | ✅ | ⬜ pending |
| 13-TBD-04 | TBD | TBD | SRCH-03 | browser | `bun run test:browser:prepare && ./vendor/bin/pest tests/Browser/SearchModeUxTest.php --filter="renders filtered full width layout" -x` | ✅ | ⬜ pending |
| 13-TBD-05 | TBD | TBD | SRCH-04 | browser | `bun run test:browser:prepare && ./vendor/bin/pest tests/Browser/SearchModeUxTest.php --filter="restores search state across refresh and history" -x` | ✅ | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Browser/SearchModeUxTest.php` — refresh login bootstrap so current search assertions run.
- [ ] `tests/Browser/SearchableCategoryNavigationTest.php` — refresh login bootstrap so navigation assertions run.
- [ ] `tests/Browser/CategorySidebarScrollTest.php` or `tests/Browser/Support/*` — only if auth bootstrap is centralized.
- [ ] Minimal login-only smoke diagnostics if the blank `/login` render persists during execution.

---

## Manual-Only Verifications

All phase behaviors have automated verification.

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references
- [x] No watch-mode flags
- [x] Feedback latency < 120s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
