# Phase 05: Download Lifecycle Reliability - Research

**Researched:** 2026-02-26
**Domain:** aria2 JSON-RPC lifecycle semantics + Laravel reliability (state, retry/backoff, safe cleanup, tests)
**Confidence:** HIGH (aria2 RPC + error codes), MEDIUM (error classification heuristics + restart behavior)

## Summary

This phase is mostly about defining an **app-owned lifecycle state machine** on top of aria2’s runtime-only state, then enforcing it with **background workers** (scheduler + queue) so reliability holds even when nobody is watching the Downloads page.

aria2 provides the primitives we need: `tellStatus` for progress (including file paths), `pause`/`unpause` for resumable pausing, `remove`/`forceRemove` for cancel (status becomes `removed`), and `removeDownloadResult` for freeing aria2 memory. aria2 also uses per-download control files (`*.aria2`) next to the target file to enable resume; deleting these is the key “restart from 0” lever.

The core planning decision: **store retry/cooldown + “desired paused” in DB**, and run a scheduled job that (a) hydrates status for non-terminal downloads, (b) classifies failures as transient/permanent, and (c) schedules retries (5 attempts, exponential backoff with cap) via delayed queue jobs. Controller actions must respect DB cooldown and never silently resume paused downloads.

**Primary recommendation:** Treat aria2 as an execution engine; persist lifecycle intent + retry schedule in `media_download_refs`, and enforce via a scheduled monitor + per-download retry job.

## Standard Stack

### Core
| Library/Tool | Version (repo/docs) | Purpose | Why Standard |
|---|---:|---|---|
| aria2 (JSON-RPC) | docs show 1.37.0 | download engine; progress + pause/cancel; resume via control file | authoritative API + stable semantics |
| saloonphp/saloon | ^3.11 | HTTP client for aria2 JSON-RPC | already integrated; supports MockClient |
| Laravel Queue | Laravel 12 | delayed retries + background processing | correct place for backoff + concurrency control |
| Laravel Scheduler | Laravel 12 | periodic monitoring/enforcement | ensures auto-retry works without UI polling |
| Pest | ^3.8 | automated tests | repo standard |

### Supporting
| Library/Tool | Version | Purpose | When to Use |
|---|---:|---|---|
| saloonphp MockClient/MockResponse | (Saloon 3) | mock aria2 JSON-RPC in tests | all lifecycle tests |
| Cache locks (`Cache::lock`) | Laravel | per-download exclusivity | retry/enforce loops |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|---|---|---|
| scheduler-driven monitoring | request-time polling side-effects | breaks RELY-04 when no UI traffic |
| DB-persisted retry schedule | cache-only retry schedule | loses state on restart; hard to test deterministically |

## Architecture Patterns

### Recommended Project Structure (Phase 05 additions)
```
app/
├── Actions/
│   ├── Downloads/
│   │   ├── HydrateDownloadsStatus.php
│   │   ├── ClassifyDownloadFailure.php
│   │   ├── ComputeRetryBackoff.php
│   │   ├── CancelDownload.php
│   │   ├── RetryDownload.php
│   │   └── DeleteDownloadFiles.php
├── Jobs/
│   ├── MonitorDownloads.php
│   └── RetryDownload.php
├── Data/
│   └── (extend existing DTOs with reliability fields)
└── Enums/
    └── (failure kinds / local lifecycle state if needed)
```

### Pattern 1: “App-owned lifecycle state” over “aria2 status hydration”
**What:** Persist user intent + reliability state (paused sticky, canceled terminal, retry attempt/cooldown) in DB; hydrate *progress numbers* from aria2 when available.

**When to use:** Always (RELY-02..05). aria2 state is not sufficient because it’s mutable, may be purged (`removeDownloadResult`), and can be lost/reordered on daemon restart.

**Example (state overlay):**
```php
// Pseudocode: compute effective display state
if ($ref->canceled_at) return 'canceled';
if ($ref->retry_next_at && now()->lt($ref->retry_next_at)) return 'retrying';
if ($ref->desired_paused) return 'paused';
return $aria2Status ?? 'unknown';
```

### Pattern 2: Scheduler monitors; queue executes retries
**What:** A scheduler job runs frequently (e.g., every minute) to hydrate statuses and decide actions; retries are executed by a delayed queue job so backoff is precise and testable.

**When to use:** Auto-retry and “sticky pause enforcement” (RELY-03/04).

**Example (repo-aligned job backoff style):**
```php
final class RetryDownload implements ShouldQueue, ShouldBeUnique {
    public $tries = 1;

    public function handle(): void {
        // load row, check cooldown, lock, call aria2.addUri, update gid/attempts
    }
}
```

Source pattern: existing job backoff/uniqueness usage in `app/Jobs/RefreshMediaContents.php`.

### Pattern 3: Batch `tellStatus` with explicit keys
**What:** Use `aria2.tellStatus(gid, keys[])` to fetch only the fields needed for progress + cleanup.

**When to use:** Monitor job and Downloads index to avoid large payloads.

**Keys needed for Phase 05:** `gid,status,totalLength,completedLength,downloadSpeed,errorCode,errorMessage,dir,files`.

Source: aria2 manual `aria2.tellStatus()` supports optional keys.

### Anti-Patterns to Avoid
- **Driving auto-retry from the Downloads UI poll:** fails RELY-04 when no one is on the page.
- **Deleting DB rows on Cancel/Retry:** you lose terminal canceled/failed state and cooldown/attempt metadata.
- **Calling `removeDownloadResult` for active downloads:** it only applies to completed/error/removed results; cancel must use `remove`/`forceRemove` first.
- **Deleting files using unvalidated paths:** always guard against path traversal; delete only inside the aria2 download root.

## aria2 RPC Semantics (needed for planning)

### Status values and transitions (HIGH)
From `aria2.tellStatus`:
- `active`: currently downloading/seeding
- `waiting`: queued/not started
- `paused`: paused (not eligible to start)
- `error`: stopped due to error
- `complete`: stopped successfully
- `removed`: removed by user

Key transition notes:
- `aria2.pause(gid)` sets status to `paused`; if it was `active`, it is placed at the **front** of the waiting queue.
- `aria2.unpause(gid)` changes `paused` → `waiting` (not necessarily `active` immediately).
- `aria2.remove(gid)` stops if needed and sets status to `removed`.
- `aria2.forceRemove(gid)` behaves like `remove` but skips slow actions (mainly relevant to BitTorrent).

### Cancel vs removeDownloadResult (HIGH)
- **Cancel/Abort (stop work):** use `aria2.remove` (or `aria2.forceRemove`). Resulting aria2 status becomes `removed`.
- **Forget result (free aria2 memory):** use `aria2.removeDownloadResult(gid)`; valid for `complete`/`error`/`removed` items.

Planning implication: to show terminal **Canceled** reliably, you should persist `canceled_at` in DB and not depend on aria2 retaining the removed item forever.

### File paths for safe deletion (HIGH)
`tellStatus` includes:
- `dir`: directory to save files
- `files`: list of file structs (same as `aria2.getFiles`) with `path`.

For cleanup, treat `files[*].path` as the authoritative target file(s).

### Control file (`*.aria2`) and resume behavior (HIGH)
aria2 uses a **control file** to track download progress:
- Control file is located next to the download file.
- Control file name is `downloaded_file_name + ".aria2"`.
- Control file is usually deleted once download completes.
- If control file is missing, resume is typically impossible.

Planning implication:
- **Pause** preserves resumability by preserving file + control file.
- **Cancel with “delete partial data”** must delete both the partial file and its `*.aria2` control file.
- **Restart from 0** can be implemented by deleting the control file (and usually the partial file) before re-adding.

### Error codes usable for classification (HIGH)
`tellStatus.errorCode` references aria2 exit-status codes. Relevant ones for Phase 05:
- `2` timeout
- `6` network problem
- `19` name resolution failed
- `29` remote server temporarily overloaded/maintenance
- `3`/`4` not found (permanent)
- `8` resume required but server doesn’t support resume (often requires restart-from-0 flow)

## Recommended Reliability Design (concrete)

### DB fields to add (MEDIUM)
Add columns to `media_download_refs` (single-row state; no history UI required):
- `desired_paused` (bool, default false) — sticky pause intent
- `canceled_at` (timestamp, nullable)
- `cancel_delete_partial` (bool, default false) — what the user chose
- `last_error_code` (int, nullable)
- `last_error_message` (text, nullable)
- `retry_attempt` (int, default 0)
- `retry_next_at` (timestamp, nullable)
- `retry_max_attempts` (int, default 5) (or constant in code)
- `retry_cooldown_seconds` (int, nullable) (optional; can be derived)
- `download_files` (json, nullable) — snapshot of `files[*].path` for safe cleanup if aria2 forgets the gid

Rationale:
- must support: sticky pause, terminal cancel, retry countdown/attempt count, safe deletion, and deterministic tests.

### Retry/backoff algorithm (MEDIUM)
Implement bounded exponential backoff for both auto and manual retry:
- max attempts: 5
- backoff: `base * 2^(attempt-1)` with cap (e.g., 5s, 15s, 30s, 60s, 120s; cap 5m)
- manual retry must respect `retry_next_at` (no bypass)

Use DB as source of truth:
- on transient error detection: set `retry_attempt++`, compute `retry_next_at`, persist, dispatch delayed `RetryDownload` job for that time.
- on success (download becomes `active/waiting/complete`): clear retry scheduling fields.
- after attempt 5: stop auto scheduling; keep terminal failed state with manual Retry available.

### Transient vs permanent classification (MEDIUM)
**High-confidence classification:** based on `errorCode`:
- transient: `2, 6, 19, 29`
- permanent: `3, 4, 8, 9, 13, 24, 32, ...` (treat unknown as permanent unless proven transient)

**Low-confidence enhancement:** parse `errorMessage` for HTTP `5xx` when present (aria2 messages vary by protocol/build). Keep as best-effort only.

### Retry execution (HIGH for mechanism, MEDIUM for resume outcome)
aria2 has no “restart this gid” RPC. Retry should:
1) `aria2.removeDownloadResult(old_gid)` (best-effort; ignore failure)
2) `aria2.addUri([url], options)` using the same `out` (and `dir` if needed)
3) Update the row’s `gid` to the new one; clear error fields if immediate success

Resume vs restart:
- default retry: attempt to resume (keep partial + control file).
- if failure indicates resume impossible (e.g., errorCode `8`, missing control file, or user chose restart): delete control+partial and re-add (restart-from-0 flow).

### Cancel execution + safe cleanup (HIGH)
Cancel (terminal) should:
1) Snapshot file paths: hydrate `tellStatus` (keys include `files`) and persist `download_files`.
2) Stop download: `aria2.forceRemove(gid)` (or `remove`).
3) Mark DB as canceled (`canceled_at=now()`), store whether user requested deletion.
4) If delete requested: delete each file path + corresponding `*.aria2` control file, guarded by download-root allowlist.
5) Optionally `removeDownloadResult(gid)` after status becomes `removed` (best-effort).

### Sticky pause enforcement (MEDIUM)
Set `desired_paused=true` on pause action; `false` on resume.

Monitor job should enforce:
- if `desired_paused=true` and aria2 reports `active/waiting`, call `pause(gid)`.
- never auto-unpause.

## Don’t Hand-Roll

| Problem | Don’t Build | Use Instead | Why |
|---|---|---|---|
| Backoff scheduling | custom sleep loops in HTTP requests | delayed queue jobs + persisted `retry_next_at` | reliable without UI traffic; testable |
| Concurrency control | ad-hoc “if not running” flags | `Cache::lock` / `ShouldBeUnique` | prevents double-retry/cancel races |
| RPC mocking | bespoke HTTP stubs | Saloon `MockClient` + `MockResponse` | already used in repo |
| Path safety | string prefix checks only | `realpath` + download-root allowlist | prevents deleting arbitrary files |

## Common Pitfalls

### Pitfall 1: Using the wrong RPC for Cancel
**What goes wrong:** calling `removeDownloadResult` doesn’t stop an active download.
**How to avoid:** Cancel uses `remove`/`forceRemove`; `removeDownloadResult` is only for memory cleanup.

### Pitfall 2: Losing terminal states by deleting rows
**What goes wrong:** UI can’t show “Canceled” or retry countdown after refresh.
**How to avoid:** keep `MediaDownloadRef` row; store terminal/cooldown fields in DB.

### Pitfall 3: Resume failures caused by missing control file
**What goes wrong:** retry/resume keeps failing even though partial file exists.
**How to avoid:** snapshot + preserve `*.aria2` control file; detect missing control file and switch to restart-from-0 UX.

### Pitfall 4: Status hydration error handling mismatch
**What goes wrong:** `GetDownloadStatus` may return `['error'=>...]` entries; mapping them into `MediaDownloadStatusData::from(...)` will break if not guarded.
**How to avoid:** treat per-gid errors as hydration failures and drive placeholder UI + background retry.

### Pitfall 5: Heavy DB writes from UI polling
**What goes wrong:** polling every few seconds updates DB constantly.
**How to avoid:** only persist lifecycle intent/errors/retry schedule; keep progress hydration in-memory response.

## Code Examples (verified)

### Cancel/Remove semantics
```text
aria2.remove(gid)
  - stops if in progress
  - status becomes "removed"

aria2.forceRemove(gid)
  - like remove, skips slow actions

aria2.removeDownloadResult(gid)
  - removes completed/error/removed result from memory
```
Source: aria2 manual (RPC Interface) `aria2.remove`, `aria2.forceRemove`, `aria2.removeDownloadResult`.

### tellStatus keys + statuses
```text
aria2.tellStatus(gid, ["gid","status","totalLength","completedLength","downloadSpeed","errorCode","errorMessage","dir","files"]) 
status ∈ {active, waiting, paused, error, complete, removed}
```
Source: aria2 manual `aria2.tellStatus()`.

### Control file location
```text
<target-file>.aria2 sits next to the target file and tracks progress.
Deleting it (and/or the partial) is required to restart from scratch.
```
Source: aria2 manual “Control File” section.

## Testing Strategy (repo-specific)

### Mocking aria2 JSON-RPC (HIGH)
Use Saloon fakes as already done in `tests/Feature/Actions/CreateDownloadDirTest.php`:
- bind `Aria2Config` in container
- create `JsonRpcConnector(...)->withMockClient(new MockClient([...]))`
- map request classes (e.g., `TellStatusRequest::class`) to `MockResponse::make([...])`

For batch status hydration tests, mock `JsonRpcBatchRequest` to return an array of JSON-RPC objects:
```php
MockResponse::make([
  ['jsonrpc'=>'2.0','id'=>'1','result'=>['gid'=>'...','status'=>'active', ...]],
  ['jsonrpc'=>'2.0','id'=>'2','error'=>['code'=>1,'message'=>'temporarily unavailable']],
]);
```

### Test layers to add (MEDIUM)
1) **Unit:**
   - failure classification by `errorCode`
   - backoff calculation (attempt→seconds)
   - safe path allowlist logic

2) **Feature (controller):**
   - PATCH `/downloads/{id}` pause/resume/cancel/retry respects allowed actions + cooldown
   - cancel with delete removes file + `.aria2`
   - retry blocked during cooldown and enabled after

3) **Job:**
   - monitor schedules retry on transient error
   - retry job updates gid and clears retry state on success

Use `Queue::fake()` where you only assert dispatch + delay; use real job execution in a focused test where needed.

## State of the Art

| Old Approach (current repo) | Current Approach (for Phase 05) | Impact |
|---|---|---|
| cancel deletes DB row + calls `removeDownloadResult` | cancel stops aria2 (`remove/forceRemove`) and persists terminal canceled | meets RELY-02 |
| retry deletes row and redirects to start new download | retry is an in-place lifecycle transition with cooldown + attempt tracking | meets RELY-04/05 |
| progress UI polls every 2s and renders 0% when status missing | poll ~5s; missing hydration shows placeholders and background retry | matches locked UX decisions |

## Open Questions

1) **How consistently does aria2 surface HTTP 5xx status in `errorMessage`?**
   - What we know: error codes include `29` for temporary overload; `retry-wait` mentions HTTP 503.
   - What’s unclear: mapping of arbitrary HTTP 5xx to `errorCode` vs message text.
   - Recommendation: rely primarily on `errorCode` mapping; treat message parsing as best-effort only.

2) **Should we override global `always-resume=true` for restart-from-0 attempts?**
   - What we know: `always-resume` can cause abort when resume isn’t possible.
   - Recommendation: for explicit restart-from-0, delete partial + control file before re-adding; optionally set per-download `always-resume=false` if needed.

## Sources

### Primary (HIGH confidence)
- aria2 manual (1.37.0): https://aria2.github.io/manual/en/html/aria2c.html
  - `aria2.remove`, `aria2.forceRemove`, `aria2.removeDownloadResult`, `aria2.tellStatus`, `aria2.getFiles`
  - “Control File” section
  - “EXIT STATUS” (error codes)

### Secondary (MEDIUM confidence)
- Repo code (Laravel 12 + Saloon + Pest) for patterns:
  - `app/Jobs/RefreshMediaContents.php` (backoff + unique)
  - `tests/Feature/Actions/CreateDownloadDirTest.php` (Saloon MockClient binding)

### Tertiary (LOW confidence)
- Community reports of `errorCode=29` tied to HTTP 503/5xx; useful only for heuristics, not as a spec.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH (repo + official docs)
- Architecture patterns: MEDIUM (design choice; validated by repo job patterns)
- Pitfalls: HIGH (directly observed in current controller/UI + aria2 spec)

**Research date:** 2026-02-26
**Valid until:** 2026-03-26
