---
phase: 16
slug: restore-search-history-state
status: draft
nyquist_compliant: true
wave_0_complete: true
created: 2026-03-27
---

# Phase 16 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest ^4.4 + pest-plugin-browser ^4.3 + Playwright 1.54.1 |
| **Config file** | `tests/Pest.php`, `phpunit.xml` |
| **Quick run command** | `./vendor/bin/pest tests/Feature/Controllers/SearchControllerTest.php --filter="shared page param keeps mixed search sections aligned" --stop-on-failure` |
| **Higher-confidence browser gate** | `bun run test:browser:prepare && ./vendor/bin/pest tests/Browser/SearchModeUxTest.php --filter="restores search state across refresh and history" --stop-on-failure` |
| **Full suite command** | `bun run test:browser:prepare && ./vendor/bin/pest tests/Feature/Controllers/SearchControllerTest.php tests/Browser/SearchModeUxTest.php --stop-on-failure` |
| **Estimated runtime** | Quick smoke <30 seconds; browser gate ~60 seconds |

---

## Sampling Rate

- **After every task commit:** Run `./vendor/bin/pest tests/Feature/Controllers/SearchControllerTest.php --filter="shared page param keeps mixed search sections aligned" --stop-on-failure`
- **Before closing task 2:** Run `bun run test:browser:prepare && ./vendor/bin/pest tests/Browser/SearchModeUxTest.php --filter="restores search state across refresh and history" --stop-on-failure`
- **After every plan wave:** Run `bun run test:browser:prepare && ./vendor/bin/pest tests/Feature/Controllers/SearchControllerTest.php tests/Browser/SearchModeUxTest.php --stop-on-failure`
- **Before `/gsd-verify-work`:** Full suite must be green
- **Max feedback latency:** <30 seconds for the smoke loop; ~60 seconds for the browser gate

---

## Per-task Verification Map

| task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 16-01-01 | 01 | 1 | SRCH-04 | feature smoke | `./vendor/bin/pest tests/Feature/Controllers/SearchControllerTest.php --filter="shared page param keeps mixed search sections aligned" --stop-on-failure` | ✅ | ⬜ pending |
| 16-01-02 | 01 | 1 | SRCH-04 | feature smoke -> browser gate | `./vendor/bin/pest tests/Feature/Controllers/SearchControllerTest.php --filter="shared page param keeps mixed search sections aligned" --stop-on-failure` | ✅ | ⬜ pending |

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
- [x] Sub-30s smoke loop documented; browser gate retained separately
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
