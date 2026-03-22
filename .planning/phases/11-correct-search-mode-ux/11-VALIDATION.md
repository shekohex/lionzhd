---
phase: 11
slug: correct-search-mode-ux
status: ready
nyquist_compliant: true
wave_0_complete: false
created: 2026-03-21
---

# Phase 11 -- Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest `^4.4` + PHPUnit + pest-plugin-browser `^4.3` + Playwright `1.54.1` |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `./vendor/bin/pest tests/Feature/Controllers/SearchControllerTest.php -x` |
| **Full suite command** | `./vendor/bin/pest && bun run test:browser` |
| **Estimated runtime** | ~120 seconds |

---

## Sampling Rate

- **After every task commit:** Run the task-specific `<automated>` verify command, then the shared smoke `./vendor/bin/pest tests/Feature/Controllers/SearchControllerTest.php -x`
- **After every plan wave:** Run `./vendor/bin/pest tests/Feature/Controllers/SearchControllerTest.php tests/Browser/SearchModeUxTest.php`
- **Before `/gsd-verify-work`:** Full suite must be green
- **Max feedback latency:** 120 seconds

---

## Per-task Verification Map

| task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 11-W0-01 | 00 | 0 | SRCH-01 | browser | `./vendor/bin/pest tests/Browser/SearchModeUxTest.php --filter="syncs mode tabs with url" -x` | ❌ W0 | ⬜ pending |
| 11-W0-02 | 00 | 0 | SRCH-02 | feature | `./vendor/bin/pest tests/Feature/Controllers/SearchControllerTest.php --filter="filtered search returns only chosen media type" -x` | ❌ W0 | ⬜ pending |
| 11-W0-03 | 00 | 0 | SRCH-03 | browser | `./vendor/bin/pest tests/Browser/SearchModeUxTest.php --filter="renders filtered full width layout" -x` | ❌ W0 | ⬜ pending |
| 11-W0-04 | 00 | 0 | SRCH-04 | browser | `./vendor/bin/pest tests/Browser/SearchModeUxTest.php --filter="restores search state across refresh and history" -x` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Browser/SearchModeUxTest.php` -- tabs, filtered layout, refresh, deep-link, and back/forward coverage for `SRCH-01`..`SRCH-04`
- [ ] `tests/Feature/Controllers/SearchControllerTest.php` -- canonical `media_type` and `sort_by` params, filtered-only result assertions, and page-reset behavior
- [ ] Shared browser helpers for `/search` history navigation and location polling if the existing browser harness lacks stable primitives
- [ ] Explicit feature coverage for query normalization when raw `q` includes `type:` or `sort:` tokens

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Filtered `Movies` and `TV Series` modes feel roomier and more focused than mixed `All` mode on desktop and mobile | SRCH-03 | Layout density, visual hierarchy, and tab affordance quality are subjective even with browser assertions | Open `/search` with matching results, switch between `All`, `Movies`, and `TV Series`, confirm filtered modes render one full-width results surface with clearer summary text and no hidden-type hints. |
| Query, mode, sort, and pagination create intuitive history entries without noisy intermediate states | SRCH-01, SRCH-04 | Browser automation can prove correctness, but history ergonomics still need human judgment | Perform a search, change mode, change sort, paginate, then use back/forward and refresh; confirm each committed state restores cleanly and typing alone does not spam history. |

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references
- [x] No watch-mode flags
- [x] Feedback latency < 180s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
