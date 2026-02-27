# Phase 5: Download Lifecycle Reliability - Context

**Gathered:** 2026-02-26
**Status:** Ready for planning

<domain>
## Phase Boundary

Make server-side downloads behave reliably across the full lifecycle: accurate progress updates, correct cancel/pause/resume/retry behavior, and automated test coverage for these behaviors.

</domain>

<decisions>
## Implementation Decisions

### Progress reporting
- Active downloads show: percent + bytes + speed.
- If total size is unknown/unreliable: indeterminate progress + bytes (and speed if available), no percent.
- Target progress freshness: ~5s default update cadence.
- If aria2/status hydration fails temporarily: show spinner/shimmer placeholders while retrying in background (resilient retries).
- During refresh failure/retry: hide numeric values (placeholders), do not show last-known values.
- Progress can decrease (do not force monotonic/clamped-to-max behavior).
- No special "Stalled" state; if 0 speed/no progress for a while, keep showing "Downloading".
- If aria2 reports 100% but server is still finalizing, UI can show Complete immediately (no "Finalizing" state).
- On start before reliable numbers: show "Starting..." state.
- Percent precision: 1 decimal.
- If progress numbers are inconsistent/invalid (e.g., >100%, total=0): clamp to sane range and keep rendering.
- If speed is 0/unavailable: hide speed (do not show 0 B/s).

### Cancel/pause semantics
- Pause preserves partial data and is resumable.
- Pause is sticky across refresh/reloads; never auto-resume.
- Cancel/Abort always requires confirmation.
- Cancel confirmation includes a "Delete partial data" checkbox.
- "Delete partial data" default: unchecked.
- If canceled without deleting partial data: keep partial file(s) on disk where they are.
- Canceled is terminal (no further actions like retry).
- If Resume cannot continue from prior progress (partial missing/corrupt): prompt user to restart from 0.

### Retry + backoff policy
- Auto-retry is enabled only for transient failures.
- Transient by default includes: network/timeouts and upstream/provider 5xx.
- Auto-retry limit: 5 attempts per download, then terminal Failed.
- Backoff: exponential with a cap.
- Manual Retry respects cooldown/backoff (no bypass).

### Failure states + messaging
- Failure detail is role-based: members see friendly summary; admins can expand for technical details.
- Auto-retry state is explicit: "Retrying" with countdown and attempt count.
- Terminal Failed shows reason inline on the row (persistent).
- After max auto-retries, do not message "gave up after N"; show Failed with Retry available.
- During cooldown/backoff, Retry is disabled with a countdown.
- No attempt history UI; latest failure only.

### OpenCode's Discretion
- None explicitly.

</decisions>

<specifics>
## Specific Ideas

- Use spinner/shimmer placeholders during temporary status-refresh failures while background retry runs.
- Cancel confirmation includes an optional "Delete partial data" checkbox (default unchecked).

</specifics>

<deferred>
## Deferred Ideas

None - discussion stayed within phase scope.

</deferred>

---

*Phase: 05-download-lifecycle-reliability*
*Context gathered: 2026-02-26*
