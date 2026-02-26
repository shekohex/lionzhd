---
phase: 05-download-lifecycle-reliability
plan: 03
subsystem: api
tags: [laravel, aria2, queue, scheduler, retry, pest]

# Dependency graph
requires:
  - phase: 05-download-lifecycle-reliability
    provides: persisted cancel/pause/retry lifecycle columns and hydration safety from 05-02
provides:
  - scheduler-driven transient failure monitoring with bounded exponential backoff retries
  - delayed retry execution that re-adds downloads with resume-capable aria2 options
  - manual retry cooldown enforcement with optional restart-from-zero cleanup and regression coverage
affects: [05-04, downloads-reliability-ui, retry-countdown]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - run app-owned retry policy via scheduled monitor + delayed queue jobs instead of aria2 internal retries
    - preserve download rows during retry and mutate lifecycle state in place for durable UI metadata

key-files:
  created:
    - app/Actions/Downloads/ClassifyDownloadFailure.php
    - app/Actions/Downloads/ComputeRetryBackoff.php
    - app/Actions/Downloads/RetryDownload.php
    - app/Jobs/MonitorDownloads.php
    - app/Jobs/RetryDownload.php
    - tests/Feature/Downloads/DownloadRetryPolicyTest.php
    - .planning/phases/05-download-lifecycle-reliability/05-03-SUMMARY.md
  modified:
    - app/Actions/DownloadMedia.php
    - app/Actions/BatchDownloadMedia.php
    - app/Data/EditMediaDownloadData.php
    - app/Http/Controllers/MediaDownloadsController.php
    - routes/console.php
    - tests/Feature/AccessControl/ExternalDownloadRestrictionsTest.php

key-decisions:
  - "Schedule MonitorDownloads every minute and dispatch delayed RetryDownload jobs so retries run without UI polling."
  - "Manual retry runs in-place with cooldown gating and optional restart_from_zero file/control cleanup."

patterns-established:
  - "Transient failures are classified by aria2 errorCode plus best-effort HTTP 5xx message parsing."
  - "Retry actions reconstruct source URLs from persisted media refs and always reuse CreateDownloadOut for resume-compatible out paths."

# Metrics
duration: 8 min
completed: 2026-02-26
---

# Phase 5 Plan 03: App-owned retry lifecycle reliability Summary

**Downloads now auto-retry transient failures through scheduler+queue orchestration with deterministic backoff, and manual retries enforce cooldown while supporting restart-from-zero cleanup.**

## Performance

- **Duration:** 8 min
- **Started:** 2026-02-26T22:29:32Z
- **Completed:** 2026-02-26T22:38:22Z
- **Tasks:** 3
- **Files modified:** 12

## Accomplishments
- Added transient failure classification + deterministic exponential backoff (`5 * 2^(attempt-1)`, capped at 300s) and applied it in a scheduled monitor.
- Implemented `MonitorDownloads` + delayed `RetryDownload` jobs, scheduler wiring, sticky pause enforcement, and app-authoritative retry behavior (`max-tries=1`, `retry-wait=0`).
- Enforced manual retry cooldown in controller, added `restart_from_zero` payload support, and added feature coverage for retry scheduling, cooldown, sticky pause, restart cleanup, and resume-capable options.

## Task Commits

Each task was committed atomically:

1. **Task 1: Add retry policy helpers and monitor/retry jobs** - `d460617` (feat)
2. **Task 2: Enforce manual retry cooldown + restart-from-0 flag** - `bdfeb3f` (feat)
3. **Task 3: Add feature tests for auto-retry scheduling and cooldown enforcement** - `e5ee77e` (fix)

**Plan metadata:** pending (created in docs commit for this plan)

## Files Created/Modified
- `app/Actions/Downloads/ClassifyDownloadFailure.php` - classifies transient failures from aria2 codes and best-effort HTTP 5xx message parsing.
- `app/Actions/Downloads/ComputeRetryBackoff.php` - computes bounded deterministic retry delay.
- `app/Actions/Downloads/RetryDownload.php` - executes retries by reconstructing Xtream URL + `out`, optional restart-from-zero cleanup, and gid mutation.
- `app/Jobs/MonitorDownloads.php` - monitors non-canceled rows, persists error metadata/files snapshot, enforces sticky pause, and schedules delayed retries.
- `app/Jobs/RetryDownload.php` - executes delayed retries when cooldown is due and state guards pass.
- `routes/console.php` - schedules `MonitorDownloads` every minute.
- `app/Actions/DownloadMedia.php` - sets `max-tries=1` and `retry-wait=0` for app-managed retry authority.
- `app/Actions/BatchDownloadMedia.php` - aligns batched aria2 options with app-owned retry policy.
- `app/Data/EditMediaDownloadData.php` - adds `restart_from_zero` request flag.
- `app/Http/Controllers/MediaDownloadsController.php` - enforces cooldown and performs in-place manual retry without deleting rows.
- `tests/Feature/Downloads/DownloadRetryPolicyTest.php` - regression coverage for retry scheduling, cap, cooldown, sticky pause, restart cleanup, resume wiring, and payload fields.

## Decisions Made
- Kept retry orchestration app-owned by scheduling monitor runs and queue-delayed retries, while reducing aria2 internal retry knobs to a single attempt.
- Kept manual retry as an in-place lifecycle transition (no row deletion) to preserve retry/cooldown metadata needed by UI and follow-up automation.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Updated legacy access-control retry assertions after in-place retry contract change**
- **Found during:** Task 2 verification (`php artisan test`)
- **Issue:** Existing tests expected retry redirects to movie/series download routes and row deletion, which no longer applies once retries execute in place.
- **Fix:** Updated `ExternalDownloadRestrictionsTest` retry assertions to validate downloads-route behavior and cooldown gating while preserving rows.
- **Files modified:** `tests/Feature/AccessControl/ExternalDownloadRestrictionsTest.php`
- **Verification:** `php artisan test`
- **Committed in:** `bdfeb3f`

**2. [Rule 2 - Missing Critical] Made monitor job queue-safe by moving connector dependency injection to `handle`**
- **Found during:** Task 3 stabilization
- **Issue:** Constructor-injected connector would serialize with the queued/scheduled job and risk runtime failures.
- **Fix:** Switched `MonitorDownloads` to method injection for `JsonRpcConnector` and passed connector through pause-enforcement helper.
- **Files modified:** `app/Jobs/MonitorDownloads.php`
- **Verification:** `php artisan test --filter DownloadRetryPolicyTest && php artisan test`
- **Committed in:** `e5ee77e`

---

**Total deviations:** 2 auto-fixed (1 blocking, 1 missing critical)
**Impact on plan:** Both fixes were required for reliable execution and verification; no scope creep.

## Issues Encountered
- Full-suite verification initially failed because legacy retry redirect expectations conflicted with new in-place retry semantics.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- Ready for `05-04-PLAN.md` with persisted retry attempt/cooldown metadata and scheduler-driven retries in place.
- No blockers.

---
*Phase: 05-download-lifecycle-reliability*
*Completed: 2026-02-26*
