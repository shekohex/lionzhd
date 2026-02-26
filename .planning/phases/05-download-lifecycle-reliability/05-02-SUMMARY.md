---
phase: 05-download-lifecycle-reliability
plan: 02
subsystem: api
tags: [laravel, aria2, json-rpc, downloads, pest]

# Dependency graph
requires:
  - phase: 05-download-lifecycle-reliability
    provides: persisted lifecycle columns and DTO contract from 05-01
provides:
  - gid-keyed aria2 status hydration with per-gid error-safe mapping
  - persisted cancel lifecycle action with optional safe partial-file cleanup
  - sticky pause/resume intent persistence and regression coverage for cancel/pause semantics
affects: [05-03, 05-04, retry-backoff, downloads-reliability-ui]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - map aria2 batch responses by request id=gid to avoid response-order coupling
    - enforce filesystem cleanup allowlist via configured aria2 download root + realpath guards

key-files:
  created:
    - app/Actions/Downloads/CancelDownload.php
    - app/Actions/Downloads/DeleteDownloadFiles.php
    - tests/Feature/Downloads/CancelDownloadLifecycleTest.php
    - tests/Feature/Downloads/DownloadProgressHydrationTest.php
    - .planning/phases/05-download-lifecycle-reliability/05-02-SUMMARY.md
  modified:
    - app/Actions/GetDownloadStatus.php
    - app/Actions/GetActiveDownloads.php
    - app/Http/Integrations/Aria2/Requests/TellStatusRequest.php
    - app/Http/Controllers/MediaDownloadsController.php
    - app/Data/EditMediaDownloadData.php
    - config/services.php

key-decisions:
  - "Set tellStatus JSON-RPC id to gid and hydrate status rows by gid while skipping per-gid error entries."
  - "Treat DELETE downloads route as terminal cancel that persists canceled_at and never deletes the download row."
  - "Allow delete-partial only inside services.aria2.download_root and surface user-facing errors for out-of-root cleanup attempts."

patterns-established:
  - "Cancel lifecycle actions snapshot files, force-remove aria2, then persist terminal state in DB."
  - "Pause/resume writes desired_paused only after successful RPC so intent mirrors real aria2 state transitions."

# Metrics
duration: 9 min
completed: 2026-02-26
---

# Phase 5 Plan 02: Persisted cancel + sticky pause semantics Summary

**Downloads now hydrate progress deterministically by gid, persist terminal cancel state, and safely gate optional partial-file cleanup to the configured aria2 download root.**

## Performance

- **Duration:** 9 min
- **Started:** 2026-02-26T21:57:09Z
- **Completed:** 2026-02-26T22:06:48Z
- **Tasks:** 3
- **Files modified:** 10

## Accomplishments
- Made aria2 status hydration deterministic by forcing `TellStatusRequest` JSON-RPC ids to gids and handling per-gid batch errors without cross-wiring.
- Added `CancelDownload` + `DeleteDownloadFiles` actions to persist cancel state, stop aria2 with `forceRemove`, and safely delete partial artifacts only within an allowlisted root.
- Added feature coverage for cancel via DELETE/PATCH, sticky pause intent toggling, cancel failure behavior, hydration out-of-order mapping, and out-of-root delete safety.

## Task Commits

Each task was committed atomically:

1. **Task 1: Make status hydration key-based and per-gid error aware** - `4e4ca0f` (fix)
2. **Task 2: Implement persisted cancel + sticky pause intent** - `c15af14` (feat)
3. **Task 3: Add feature tests for progress hydration + cancel + sticky pause semantics** - `2c1b89e` (test)

**Plan metadata:** pending (created in docs commit for this plan)

## Files Created/Modified
- `app/Http/Integrations/Aria2/Requests/TellStatusRequest.php` - sets JSON-RPC id to gid for deterministic batch correlation.
- `app/Actions/GetDownloadStatus.php` - returns gid+error entries and supports explicit status key selection.
- `app/Actions/GetActiveDownloads.php` - filters out errored status entries before DTO hydration.
- `app/Http/Controllers/MediaDownloadsController.php` - wires persisted cancel/pause semantics and safe cancel flow for PATCH/DELETE.
- `app/Actions/Downloads/CancelDownload.php` - snapshots files, force-removes aria2 download, persists terminal canceled state, and optionally invokes cleanup.
- `app/Actions/Downloads/DeleteDownloadFiles.php` - guards file deletions with realpath-inside-root checks and `.aria2` companion cleanup.
- `app/Data/EditMediaDownloadData.php` - adds `delete_partial` payload support.
- `config/services.php` - adds `services.aria2.download_root` allowlist configuration.
- `tests/Feature/Downloads/CancelDownloadLifecycleTest.php` - regression coverage for cancel/pause lifecycle and deletion safety behavior.
- `tests/Feature/Downloads/DownloadProgressHydrationTest.php` - regression coverage for out-of-order/error batch hydration correctness.

## Decisions Made
- Mapped status hydration by gid (JSON-RPC id) and intentionally dropped per-gid errored entries from UI hydration to keep placeholder behavior deterministic.
- Enforced terminal cancel persistence by requiring successful `forceRemove` before setting `canceled_at` and preserving the DB row.
- Restricted server-side partial-file cleanup to `services.aria2.download_root` with an explicit user-facing failure path for out-of-root directories.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Avoided Scout/Meilisearch network dependency in new download feature tests**
- **Found during:** Task 3 (feature test authoring)
- **Issue:** Creating `VodStream` via Eloquent triggered Scout indexing and failed tests when Meilisearch was unavailable.
- **Fix:** Seeded `vod_streams` fixtures with direct `DB::table()->insert(...)` in tests to keep feature tests hermetic and deterministic.
- **Files modified:** `tests/Feature/Downloads/CancelDownloadLifecycleTest.php`, `tests/Feature/Downloads/DownloadProgressHydrationTest.php`
- **Verification:** `php artisan test --filter CancelDownloadLifecycleTest && php artisan test --filter DownloadProgressHydrationTest`
- **Committed in:** `2c1b89e`

---

**Total deviations:** 1 auto-fixed (1 blocking)
**Impact on plan:** Required to execute planned coverage without external search service coupling; no scope creep.

## Issues Encountered
None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- Ready for `05-03-PLAN.md` (auto-retry/backoff) with cancel and sticky pause primitives now persisted and covered.
- No blockers.

---
*Phase: 05-download-lifecycle-reliability*
*Completed: 2026-02-26*
