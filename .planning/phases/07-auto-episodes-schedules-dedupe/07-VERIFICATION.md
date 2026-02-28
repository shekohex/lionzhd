---
phase: 07-auto-episodes-schedules-dedupe
verified: 2026-02-28T15:30:00Z
status: passed
score: 12/12 must-haves verified
---

# Phase 7: Auto Episodes (Schedules + Dedupe) Verification Report

**Phase Goal:** Users can schedule per-series monitoring that detects new episodes and auto-queues downloads without duplicates.

**Verified:** 2026-02-28T15:30:00Z

**Status:** passed

**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths

| #   | Truth                                                                                                    | Status     | Evidence                                                                                                          |
| --- | -------------------------------------------------------------------------------------------------------- | ---------- | ----------------------------------------------------------------------------------------------------------------- |
| 1   | External members can open Schedules settings page (visible-but-disabled UX)                              | VERIFIED   | Route GET /settings/schedules accessible to all auth users; controller returns can_manage_schedules flag          |
| 2   | Internal/admin users can enable monitoring for watchlisted series                                        | VERIFIED   | SeriesMonitoringController@store creates monitor with schedule config; requires watchlist first                   |
| 3   | User can configure hourly schedule                                                                       | VERIFIED   | MonitorScheduleType::Hourly supported; ComputeNextRunAt handles hourly logic; UI has hourly option                |
| 4   | User can configure daily-at-time schedule                                                                | VERIFIED   | MonitorScheduleType::Daily supported; preset times from config; UI shows daily time selector                      |
| 5   | User can configure weekly day+time schedule                                                              | VERIFIED   | MonitorScheduleType::Weekly supported; multi-day selection; UI has weekday checkboxes                             |
| 6   | System detects new episodes by comparing Xtream IDs                                                      | VERIFIED   | ScanSeriesForNewEpisodes syncs episodes to series_monitor_episodes table; tracks state per episode               |
| 7   | System auto-queues downloads oldest-first up to per-run cap                                              | VERIFIED   | QueueEpisodeDownload called in order; per_run_cap enforced; deferred events logged                                |
| 8   | System prevents duplicate queue entries for same episode/user                                            | VERIFIED   | QueueEpisodeDownload checks MediaDownloadRef existence; race-safe with cache locks                                |
| 9   | First scan establishes baseline without auto-backfill                                                    | VERIFIED   | last_successful_check_at === null triggers skip baseline; tests confirm                                           |
| 10  | User can trigger explicit recent-only backfill                                                           | VERIFIED   | SeriesMonitoringBackfillController@store with backfill_count; separate from enable                                |
| 11  | User can see monitoring activity (runs + events)                                                         | VERIFIED   | MonitoringPageController returns monitors/events; settings/schedules.tsx shows activity log with filters          |
| 12  | External members cannot mutate monitoring or run now                                                     | VERIFIED   | All mutation routes under can:auto-download-schedules middleware; tests verify 403 for external members           |

**Score:** 12/12 truths verified

### Required Artifacts

| Artifact | Expected | Status | Details |
| -------- | -------- | ------ | ------- |
| `app/Models/AutoEpisodes/SeriesMonitor.php` | Eloquent model for monitors | VERIFIED | 97 lines, all relationships defined, casts configured |
| `app/Models/AutoEpisodes/SeriesMonitorEpisode.php` | Known episodes tracking | VERIFIED | Tracks episode state, first/last seen, download ref |
| `app/Models/AutoEpisodes/SeriesMonitorRun.php` | Run persistence | VERIFIED | Tracks trigger, window, counts, status |
| `app/Models/AutoEpisodes/SeriesMonitorEvent.php` | Per-episode outcomes | VERIFIED | Tracks type, reason, meta per event |
| `app/Actions/AutoEpisodes/ComputeNextRunAt.php` | Schedule math | VERIFIED | 160 lines, handles hourly/daily/weekly with timezone |
| `app/Actions/AutoEpisodes/ScanSeriesForNewEpisodes.php` | Core scan pipeline | VERIFIED | 374 lines, diffing, cap, baseline, event logging |
| `app/Actions/AutoEpisodes/QueueEpisodeDownload.php` | Race-safe queueing | VERIFIED | 133 lines, lock-based dedupe, unsafe ID handling |
| `app/Jobs/AutoEpisodes/DispatchDueMonitors.php` | Minute dispatcher | VERIFIED | ShouldBeUnique, respects user pause state |
| `app/Jobs/AutoEpisodes/RunMonitorScan.php` | Per-monitor scan | VERIFIED | Lock-guarded, dispatches ScanSeriesForNewEpisodes |
| `config/auto_episodes.php` | Presets + defaults | VERIFIED | Preset times, backfill counts, caps, cooldowns |
| `routes/console.php` | Scheduler wiring | VERIFIED | everyMinute() for DispatchDueMonitors |
| `routes/settings.php` | GET /settings/schedules | VERIFIED | Open to all auth, mutations gated |
| `routes/web.php` | Series monitoring routes | VERIFIED | All under can:auto-download-schedules |
| `app/Http/Controllers/AutoEpisodes/SeriesMonitoringController.php` | CRUD endpoints | VERIFIED | 234 lines, watchlist validation, schedule compute |
| `app/Http/Controllers/AutoEpisodes/MonitoringPageController.php` | Page props | VERIFIED | 84 lines, returns typed DTOs |
| `app/Data/AutoEpisodes/SeriesMonitorData.php` | Monitor DTO | VERIFIED | TypeScript exported, 86 lines |
| `app/Data/AutoEpisodes/MonitoringPageData.php` | Page DTO | VERIFIED | Includes monitors, events, presets |
| `resources/js/components/auto-episodes/schedule-editor-dialog.tsx` | Schedule picker | VERIFIED | 513 lines, hourly/daily/weekly + seasons + backfill |
| `resources/js/components/auto-episodes/monitoring-card.tsx` | Monitor status card | VERIFIED | 275 lines, shows last/next run, run-now cooldown |
| `resources/js/pages/settings/schedules.tsx` | Management page | VERIFIED | 435 lines, bulk actions, pause/resume, activity log |
| `resources/js/pages/series/show.tsx` | Series detail integration | VERIFIED | Monitoring card wired with enable/edit/disable/run-now |
| Database migrations (5 files) | Schema | VERIFIED | All 2026_02_27 migrations present and aligned |

### Key Link Verification

| From | To | Via | Status | Details |
| ---- | -- | --- | ------ | ------- |
| `routes/console.php` | `DispatchDueMonitors` | Schedule::job | WIRED | everyMinute() with withoutOverlapping() |
| `DispatchDueMonitors` | `RunMonitorScan` | dispatch() | WIRED | Queued per monitor with trigger |
| `RunMonitorScan` | `ScanSeriesForNewEpisodes` | action run | WIRED | Lock-guarded execution |
| `ScanSeriesForNewEpisodes` | `QueueEpisodeDownload` | action run | WIRED | Called per candidate episode |
| `QueueEpisodeDownload` | `MediaDownloadRef` | DB query | WIRED | Checks existing refs for dedupe |
| `SeriesMonitoringController` | `ComputeNextRunAt` | action run | WIRED | Computes next_run_at on enable/update |
| `settings/schedules.tsx` | `MonitoringPageController` | Inertia props | WIRED | Typed DTOs consumed in UI |
| `series/show.tsx` | `SeriesMonitoringController` | router.post/patch/delete | WIRED | All monitoring actions wired |

### Requirements Coverage

| Requirement | Status | Evidence |
| ----------- | ------ | -------- |
| AUTO-01: Enable automatic new-episode monitoring | SATISFIED | SeriesMonitoringController@store, monitoring-card.tsx |
| AUTO-02: Configure hourly schedule | SATISFIED | MonitorScheduleType::Hourly, ComputeNextRunAt, schedule-editor-dialog.tsx |
| AUTO-03: Configure daily schedule | SATISFIED | MonitorScheduleType::Daily, preset times, UI |
| AUTO-04: Configure weekly schedule | SATISFIED | MonitorScheduleType::Weekly, multi-day selection, UI |
| AUTO-05: Detect new episodes by comparing Xtream IDs | SATISFIED | ScanSeriesForNewEpisodes syncs to series_monitor_episodes |
| AUTO-06: Auto-queue without duplicates | SATISFIED | QueueEpisodeDownload dedupe with locks, tests prove no duplicates |

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
| ---- | ---- | ------- | -------- | ------ |
| None | — | — | — | No blockers found |

### Human Verification Required

None — all automated checks pass. Manual verification checkpoints from 07-08 and 07-09 plans are for UX refinement but do not block goal achievement.

### Gaps Summary

No gaps found. All 12 must-have truths verified. All required artifacts present and substantive. All key links wired. All tests pass (157 passed).

---

**Verification Summary:**

- **Backend**: Complete monitoring pipeline with scheduler (every minute), per-monitor scan jobs with locks, dedupe logic, and activity logging
- **Frontend**: Series detail integration with schedule editor, central management page with bulk actions and activity log
- **Access Control**: External members see UI but cannot mutate; all mutations gated by can:auto-download-schedules
- **Tests**: 46 auto-episodes specific tests pass + 157 total tests pass
- **Type Safety**: All DTOs TypeScript-exported and consumed in UI

_Verified: 2026-02-28_
_Verifier: OpenCode (gsd-verifier)_
