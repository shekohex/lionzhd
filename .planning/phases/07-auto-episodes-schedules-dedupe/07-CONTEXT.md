# Phase 7: Auto Episodes (Schedules + Dedupe) - Context

**Gathered:** 2026-02-27
**Status:** Ready for planning

<domain>
## Phase Boundary

Per-user watchlist + monitoring schedules detect new series episodes and auto-queue downloads, while preventing duplicate queue entries for the same episode/user.

In scope: enabling monitoring for watchlisted series, hourly/daily/weekly schedules, season scoping, dedupe rules, and user-facing visibility/control for automation outcomes.

Out of scope: new notification channels (email/push), recommendation systems, or broader watch tracking beyond what monitoring needs.

</domain>

<decisions>
## Implementation Decisions

### Watchlist + Monitoring UX
- Watchlist exists; monitoring is a separate toggle on a watchlisted series (monitored subset).
- Entry points: series detail supports watchlist + monitoring actions; also a central Watchlist/Monitoring management page exists.
- Watchlist add/remove is available from: series list cards, series detail, and the watchlist page.
- Adding a series to Watchlist prompts the user to optionally enable monitoring (schedule required to enable).
- Enabling monitoring requires choosing a schedule immediately (no implicit default schedule).
- Removing a series from Watchlist disables monitoring.
- Disabling monitoring prompts: disable only vs disable + remove from Watchlist.
- Per-series schedule is the model (no global default schedule).
- Per-series schedule edits happen via a modal/drawer from the management page.
- Bulk actions exist on the management page, including applying schedule presets to multiple series.
- Season scoping exists: users pick which seasons are monitored per series.

### Schedule Semantics
- Supported schedule types: hourly, daily-at-time, weekly day+time.
- Time zone: schedules run in the user's time zone.
- Hourly schedule: cron-style on-the-hour (not rolling from enable time).
- Daily schedule time: preset times (not arbitrary HH:MM).
- Weekly schedule: supports multiple days.
- No automatic run on enable/change; expose a manual "Run now".
- "Run now" is allowed with a cooldown.
- Missed runs: next run performs a single catch-up scan covering the full gap since last successful check.
- Errors during monitoring should be visible (status/log) but automation keeps trying on the next scheduled run.

### Dedupe + Auto-Queue Rules
- First-time enable baseline: offer "backfill recent only" (not full historical backfill, not strictly from-now only).
- Backfill UX: offer both preset counts and an advanced season-based backfill option.
- If an episode is already queued/in-progress, do not queue a duplicate; record it as a duplicate detection in activity.
- If an episode was successfully downloaded, do not re-queue unless user explicitly chooses to backfill again.
- If an episode previously failed: auto-monitor may auto-retry.
- If an episode was canceled: keep manual-only (no auto-retry).
- When multiple new episodes are detected, queue oldest-first.
- Guardrail: cap auto-queued episodes per series per run; remaining episodes are deferred to next run.

### User Feedback + Control Surface
- Activity visibility: both (1) monitoring page log and (2) downloads page annotations.
- Notifications: in-app toast + a UI badge/count when new episodes are auto-queued.
- Per-series status shows last run (time/result) and next scheduled run.
- Global controls: a master pause/resume for automation exists, in addition to per-series toggles.
- Badge counts auto-queued items since last seen (visiting Downloads/Monitoring resets).
- Activity log retention is time-based (window length TBD).
- External members: monitoring UI is visible, but schedule/monitoring controls are disabled with an explanation.

### OpenCode's Discretion
- Exact preset times list for daily/weekly scheduling.
- Cooldown duration and UX around "Run now" limits.
- Per-series cap value (N) and any global safety caps.
- Activity log retention window length.
- Exact badge placement/wording and the downloads-page annotation design.

</decisions>

<specifics>
## Specific Ideas

- Modal/drawer schedule editor from the management page.
- Duplicate detections should be visible in activity (not silently ignored).
- Backfill has both simple presets and an advanced season-based option.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 07-auto-episodes-schedules-dedupe*
*Context gathered: 2026-02-27*
