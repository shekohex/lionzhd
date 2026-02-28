---
status: resolved
trigger: "Investigate and fix issue: schedule-weekly-days-validation"
created: 2026-02-28T22:58:23+00:00
updated: 2026-02-28T23:00:37+00:00
---

## Current Focus

hypothesis: Root cause fixed and verified.
test: n/a
expecting: n/a
next_action: session archived

## Symptoms

expected: Creating a new monitoring of a series should succeed when submitted with valid schedule inputs.
actual: Request fails with validation error.
errors: "The schedule weekly days field must have at least 1 items." and "Error in schedule_weekly_days: The schedule weekly days field must have at least 1 items."
reproduction: Attempt to create a new monitoring for a series via the normal creation flow.
started: Reported currently; prior working state unknown.

## Eliminated

## Evidence

- timestamp: 2026-02-28T22:58:46+00:00
  checked: global search for schedule_weekly_days usage
  found: StoreSeriesMonitorRequest has rule schedule_weekly_days => ['nullable','array','list','min:1']
  implication: empty array can fail validation before conditional weekly-only validation runs

- timestamp: 2026-02-28T22:59:06+00:00
  checked: store controller + frontend schedule payload behavior
  found: controller expects [] for non-weekly schedules; schedule editor sends schedule_weekly_days as [] when scheduleType != weekly
  implication: UI creates valid non-weekly payloads that include schedule_weekly_days key, activating unconditional min:1 validation

- timestamp: 2026-02-28T22:59:30+00:00
  checked: feature tests
  found: added coverage for daily store payload with schedule_weekly_days=[] to match reproduction path
  implication: test should go red if unconditional min:1 validation is the root cause

- timestamp: 2026-02-28T22:59:45+00:00
  checked: targeted test execution
  found: new test fails with session error "The schedule weekly days field must have at least 1 items."
  implication: hypothesis confirmed; root cause is unconditional min:1 rule during store validation

- timestamp: 2026-02-28T23:00:37+00:00
  checked: store request rule update
  found: removed min:1 from StoreSeriesMonitorRequest schedule_weekly_days base rule
  implication: non-weekly payloads with empty weekly days no longer fail base validation

- timestamp: 2026-02-28T23:00:37+00:00
  checked: php artisan test tests/Feature/AutoEpisodes/SeriesMonitorValidationTest.php
  found: 14 passed (85 assertions), including new daily store empty weekly-days test
  implication: fix works and no regression in related schedule validation cases

## Resolution

root_cause: "StoreSeriesMonitorRequest has schedule_weekly_days rule with unconditional min:1, rejecting non-weekly creation payloads that include an empty weekly list."
fix: "Remove min:1 from base store rule and keep weekly-only requirement in withValidator branch for weekly schedule_type."
verification: "Ran SeriesMonitorValidationTest suite; all 14 tests pass including new daily create empty-weekly-days regression test."
files_changed: ["tests/Feature/AutoEpisodes/SeriesMonitorValidationTest.php", "app/Http/Requests/AutoEpisodes/StoreSeriesMonitorRequest.php"]
