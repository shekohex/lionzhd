---
status: resolved
trigger: "Investigate issue: monitoring-reset-after-media-sync\n\n**Summary:** Monitoring and series-related state gets reset after the daily media sync because the sync appears to wipe DB records and recreate them from scratch."
created: 2026-03-09T20:36:00Z
updated: 2026-03-09T20:56:23Z
---

## Current Focus

hypothesis: fixed locally; remaining uncertainty is only deferred real daily-sync verification.
test: focused regression suite passes; user requested provisional resolution before manual/production verification.
expecting: monitoring state persists on future real syncs unless new evidence shows otherwise.
next_action: reopen or move back to active debug if a later real sync reproduces the reset.

## Symptoms

expected: Series monitoring, actions, and related per-series state should persist across daily media sync runs, especially when upstream/stable IDs remain the same.
actual: After daily media sync, series-related state such as monitoring and actions is lost/reset.
errors: No explicit runtime error reported yet; bug manifests as state loss.
reproduction: Mark or modify monitoring/action state for a series, run the daily media sync, then inspect the same series again; state is gone or reset even when the series comes back with the same stable ID.
started: Happens during the recurring daily media sync flow; likely longstanding, not a one-off regression.

## Eliminated

## Evidence

- timestamp: 2026-03-09T20:38:00Z
  checked: app/Actions/SyncMedia.php
  found: SyncMedia unconditionally calls Series::query()->delete() before fetching and upserting series back in.
  implication: every sync removes all series rows first instead of updating in place.

- timestamp: 2026-03-09T20:38:30Z
  checked: database/migrations/2026_02_27_000110_create_series_monitors_table.php
  found: series_monitors.series_id is a foreign key to series.series_id with cascadeOnDelete.
  implication: deleting series rows also deletes per-series monitoring records automatically.

- timestamp: 2026-03-09T20:38:50Z
  checked: app/Jobs/RefreshMediaContents.php
  found: the daily sync job simply runs SyncMedia::run().
  implication: the destructive delete path is part of the recurring daily media sync flow, matching the reported symptom.

- timestamp: 2026-03-09T20:42:30Z
  checked: tests/Feature/Jobs/RefreshMediaContentsTest.php
  found: a regression test that syncs the same series_id fails because SeriesMonitor::find(monitor_id) returns null after RefreshMediaContents::handle().
  implication: the monitor is actually deleted during sync even when the series is re-imported with the same stable ID.

- timestamp: 2026-03-09T20:48:40Z
  checked: php artisan test tests/Feature/Jobs/RefreshMediaContentsTest.php
  found: all 5 tests pass, including new coverage proving same-ID series monitors survive sync and stale series are still removed.
  implication: the fix preserves monitoring state for unchanged series IDs without regressing stale-record pruning.

- timestamp: 2026-03-09T20:56:23Z
  checked: php artisan test tests/Feature/Jobs/RefreshMediaContentsTest.php
  found: reran the focused regression suite before provisional close; all 5 tests still pass (9 assertions).
  implication: the local fix remains verified at handoff; only real workflow verification is still pending.

## Resolution

root_cause: SyncMedia deletes every series row before re-importing, and series_monitors has a cascade delete FK to series.series_id, so recurring media syncs erase monitoring state even for unchanged series IDs.
fix: Update SyncMedia to sync in place via upsert on stable IDs and prune only records missing from the upstream payload instead of deleting the entire table first.
verification:
  - php artisan test tests/Feature/Jobs/RefreshMediaContentsTest.php
  - reran focused regression suite: 5 tests passed (9 assertions)
  - verified new regression: same-ID series monitor survives RefreshMediaContents
  - verified stale series are pruned when absent from upstream payload
  - manual/daily-sync verification is still pending; user requested provisional resolution for now
files_changed:
  - app/Actions/SyncMedia.php
  - tests/Feature/Jobs/RefreshMediaContentsTest.php
