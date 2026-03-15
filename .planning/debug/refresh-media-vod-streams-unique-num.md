---
status: awaiting_human_verify
trigger: "Investigate issue: refresh-media-vod-streams-unique-num\n\nSummary: `RefreshMediaContents` queue job fails during `SyncMedia` with a SQLite unique constraint violation on `vod_streams.num`."
created: 2026-03-14T00:00:00Z
updated: 2026-03-15T00:33:00Z
---

## Current Focus

hypothesis: final narrowed fix is stable; only remaining validation is human verification in the real environment after deploy/migrate
test: user deploys code, runs the new vod_streams migration, and reruns RefreshMediaContents
expecting: queue completes without vod_streams.num unique failures
next_action: wait for human verification after deploy/migrate

## Symptoms

expected: RefreshMediaContents completes successfully and syncs media without errors.
actual: Queue job fails/retries during sync.
errors: PDOException(code: 23000): SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: vod_streams.num from app/Actions/SyncMedia.php:129 via Eloquent upsert().
reproduction: Happens on any refresh run.
started: Started after a recent change.

## Eliminated

- hypothesis: deduplicate/canonicalize incoming rows by num before upsert
  evidence: duplicate num rows can represent distinct media because they have different stream_id values; collapsing them would silently delete valid upstream items, while codebase shows no reliance on num uniqueness
  timestamp: 2026-03-14T23:29:00Z

- hypothesis: broaden the schema/test fix to series as well
  evidence: the reported issue and user correction are specifically about stream_id/vod_streams identity; after narrowing the change back to vod_streams-only, all VOD regressions still pass
  timestamp: 2026-03-15T00:27:00Z

## Evidence

- timestamp: 2026-03-14T00:04:00Z
  checked: app/Actions/SyncMedia.php and app/Jobs/RefreshMediaContents.php
  found: syncVodStreams() uses VodStream::query()->upsert($chunk, ['stream_id'], ['num', ...]) and job fails inside that path on every run
  implication: failure occurs during batched VOD persistence, specifically when inserting/updating rows keyed by stream_id while also writing num

- timestamp: 2026-03-14T00:07:00Z
  checked: database/migrations/2025_03_05_154156_create_vod_streams_table.php and app/Models/VodStream.php
  found: vod_streams has primary key stream_id and a separate unique index on num; model primary key is stream_id
  implication: database uniqueness no longer aligns with SyncMedia's upsert conflict key if provider feed can reuse num values

- timestamp: 2026-03-14T00:10:00Z
  checked: git history for SyncMedia-related files
  found: recent sync refactor commits include a22df32 refactor(sync): optimize media sync process, consistent with timeline that issue started after a recent change
  implication: regression likely introduced by the sync refactor interacting with existing unique num schema

- timestamp: 2026-03-14T00:14:00Z
  checked: git show a22df32 for app/Actions/SyncMedia.php
  found: refactor removed pre-sync delete/truncate behavior and now prunes missing rows only after per-chunk upserts
  implication: stale rows can survive long enough to collide with new rows on secondary unique indexes like vod_streams.num

- timestamp: 2026-03-14T00:17:00Z
  checked: focused RefreshMediaContents regression test with existing vod_stream(stream_id=30001,num=777) and upstream payload(stream_id=30002,num=777)
  found: current code throws the same sqlite UniqueConstraintViolationException on vod_streams.num from SyncMedia.php:129 during upsert(stream_id)
  implication: root cause confirmed: stale row survives until after upsert, so reused num collides before prune can delete the old row

- timestamp: 2026-03-14T00:20:00Z
  checked: php artisan test tests/Feature/Jobs/RefreshMediaContentsTest.php
  found: all 6 tests pass, including new regression covering stale vod_stream replacement when num is reused for a new stream_id
  implication: pruning stale rows before upsert fixes the reported collision and preserves existing refresh-media behavior covered by the job tests

- timestamp: 2026-03-14T23:17:00Z
  checked: user checkpoint response from real environment
  found: refresh still fails with same SQLite unique violation in syncVodStreams upsert, and failing SQL shows many rows in a single bulk insert batch
  implication: collisions are not limited to stale existing rows; duplicate num values may also exist within the incoming payload or chunk itself

- timestamp: 2026-03-14T23:24:00Z
  checked: focused RefreshMediaContents regression test with two incoming vod rows sharing num=888 and different stream_id values, with no preexisting rows
  found: current code still throws the same sqlite UniqueConstraintViolationException on vod_streams.num from SyncMedia.php:131 during one bulk upsert statement
  implication: root cause includes an intra-payload collision path; stale-row pruning alone cannot solve the issue

- timestamp: 2026-03-14T23:28:00Z
  checked: codebase usage search for media num fields
  found: app code only writes num and carries it in DTO/data objects; no queries, lookups, or business rules depend on num being unique
  implication: unique(num) is an unnecessary and incorrect schema constraint; removing it is lower-risk than dropping valid rows during sync

- timestamp: 2026-03-14T23:34:00Z
  checked: php artisan test tests/Feature/Jobs/RefreshMediaContentsTest.php after adding migration to drop media num unique indexes
  found: all 8 tests pass, including regressions for stale VOD replacement, duplicate VOD nums in one payload, and duplicate series nums in one payload
  implication: combined fix covers both confirmed failure modes and preserves existing refresh behavior under test

- timestamp: 2026-03-15T00:04:00Z
  checked: user correction on fix direction
  found: user confirmed stream_id is the canonical identity and asked to revise the solution to remove workaround framing and keep only schema-aligned changes that support stream_id identity
  implication: need to reassess whether prune-before-upsert is still justified or should be reverted now that num uniqueness is being removed

- timestamp: 2026-03-15T00:12:00Z
  checked: git status and git log for app/Actions/SyncMedia.php
  found: prune-before-upsert is already in HEAD via commit 0a26f29 fix(sync): prune stale media rows before upsert, while current working tree has no local diff for SyncMedia
  implication: reverting prune ordering requires an explicit working-tree edit; it is not part of the still-uncommitted schema/test changes

- timestamp: 2026-03-15T00:15:00Z
  checked: php artisan test tests/Feature/Jobs/RefreshMediaContentsTest.php after restoring prune-after-upsert
  found: all 8 tests still pass, including stale-row and duplicate-num regressions
  implication: prune-before-upsert was unnecessary workaround logic once unique(num) is removed; the schema change alone resolves the constraint failures while preserving stream_id/series_id identity

- timestamp: 2026-03-15T00:27:00Z
  checked: php artisan test tests/Feature/Jobs/RefreshMediaContentsTest.php after narrowing changes to vod_streams-only and restoring series prune-before-upsert
  found: all 7 tests pass, including stale-row replacement and duplicate-vod-num regressions, with syncVodStreams back on prune-after-upsert
  implication: the minimal fix for this issue is removing vod_streams.num uniqueness; the prune-before-upsert workaround is not needed for VOD once schema identity matches stream_id

- timestamp: 2026-03-15T00:32:00Z
  checked: php artisan test tests/Feature/Jobs/RefreshMediaContentsTest.php after renaming the migration to reflect vod_streams-only scope
  found: all 7 tests still pass
  implication: final working-tree state is stable and scoped correctly to the reported VOD issue

## Resolution

root_cause:
vod_streams enforces unique(num), but SyncMedia identifies VOD rows by stream_id and the provider can reuse or duplicate num values. this creates two failure modes: stale rows can collide before prune, and duplicate nums inside one payload can fail inside a single bulk upsert even with no stale rows.
fix:
remove the incorrect unique constraint on vod_streams.num so valid distinct rows sharing a num can persist while stream_id remains the canonical identity key; revert syncVodStreams to prune after upsert.
verification:
php artisan test tests/Feature/Jobs/RefreshMediaContentsTest.php
files_changed:
  - app/Actions/SyncMedia.php
  - database/migrations/2026_03_14_232000_drop_unique_num_index_from_vod_streams_table.php
  - tests/Feature/Jobs/RefreshMediaContentsTest.php
