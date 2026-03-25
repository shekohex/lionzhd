---
phase: 15
slug: refresh-detail-page-category-browser-proof
status: draft
nyquist_compliant: true
wave_0_complete: true
created: 2026-03-25
---

# Phase 15 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest `^4.4` + `pestphp/pest-plugin-browser` `^4.3` + Playwright `1.54.1` |
| **Config file** | `phpunit.xml`, `tests/Pest.php`, `tests/Browser/Support/browser-auth.php` |
| **Quick run command** | `php -l tests/Browser/DetailPageCategoryContextTest.php && bun run test:browser:prepare && ./vendor/bin/pest tests/Browser/DetailPageCategoryContextTest.php --filter="shows hero category chips and browse navigation for both media types" --stop-on-failure` |
| **Full suite command** | `php -l tests/Browser/DetailPageCategoryContextTest.php && bun run test:browser:prepare && ./vendor/bin/pest tests/Browser/DetailPageCategoryContextTest.php tests/Feature/Controllers/VodStreamDetailCategoryContextTest.php tests/Feature/Controllers/SeriesDetailCategoryContextTest.php --stop-on-failure` |
| **Estimated runtime** | ~30 seconds quick probe; ~60 seconds phase gate |

---

## Sampling Rate

- **After every task commit:** Run `php -l tests/Browser/DetailPageCategoryContextTest.php && bun run test:browser:prepare && ./vendor/bin/pest tests/Browser/DetailPageCategoryContextTest.php --filter="shows hero category chips and browse navigation for both media types|keeps hero category chips readable on mobile without truncate affordances" --stop-on-failure`
- **After every plan wave:** Run `php -l tests/Browser/DetailPageCategoryContextTest.php && bun run test:browser:prepare && ./vendor/bin/pest tests/Browser/DetailPageCategoryContextTest.php --stop-on-failure`
- **Before `/gsd-verify-work`:** Full suite must be green
- **Max feedback latency:** 30 seconds

---

## Per-task Verification Map

| task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 15-01-01 | 01 | 1 | CTXT-01, CTXT-02 | browser | `php -l tests/Browser/DetailPageCategoryContextTest.php && bun run test:browser:prepare && ./vendor/bin/pest tests/Browser/DetailPageCategoryContextTest.php --filter="shows hero category chips and browse navigation for both media types" --stop-on-failure` | ✅ | ⬜ pending |
| 15-01-02 | 01 | 1 | CTXT-01, CTXT-02 | browser | `php -l tests/Browser/DetailPageCategoryContextTest.php && bun run test:browser:prepare && ./vendor/bin/pest tests/Browser/DetailPageCategoryContextTest.php --filter="keeps hero category chips readable on mobile without truncate affordances" --stop-on-failure` | ✅ | ⬜ pending |

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
- [x] Feedback latency < 30s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
