# Requirements: LionzHD Streaming Platform Enhancements

**Defined:** 2026-02-25
**Core Value:** Users can quickly find the right movie/series and reliably get their own downloads with correct permissions and automation.

## v1 Requirements

Requirements for initial release. Each maps to roadmap phases.

### Discovery & Categories

- [x] **DISC-01**: User can browse movies by category using a sidebar on movies pages
- [x] **DISC-02**: User can browse series by category using a sidebar on series pages
- [x] **DISC-03**: User can filter movies to a selected category, including an explicit Uncategorized option
- [x] **DISC-04**: User can filter series to a selected category, including an explicit Uncategorized option
- [ ] **DISC-05**: Admin can sync VOD and series categories from Xtream while excluding Live categories
- [ ] **DISC-06**: User can open content that remains correctly categorized based on synced category relationships

### Access Control

- [ ] **ACCS-01**: First registered user is created as Admin by default
- [ ] **ACCS-02**: Subsequent users are created as Member by default
- [ ] **ACCS-03**: Admin can mark members as Internal or External
- [ ] **ACCS-04**: Member cannot access admin-only areas (user management, system settings, sync/import controls, download operations, analytics/monitoring)
- [ ] **ACCS-05**: External member can only use direct-download links and cannot use server-download actions
- [ ] **ACCS-06**: External member cannot configure or run auto-download schedules

### Download Ownership

- [x] **DOWN-01**: User can see only their own downloads in downloads pages and APIs when role is Member
- [x] **DOWN-02**: User can operate only on their own downloads (pause, resume, cancel, retry) when role is Member
- [x] **DOWN-03**: Admin can view and operate on downloads across all users
- [x] **DOWN-04**: Each new download is owned by the initiating user and persisted with that ownership

### Auto Episodes

- [x] **AUTO-01**: User can enable automatic new-episode monitoring for a watched series
- [x] **AUTO-02**: User can configure an hourly monitoring schedule
- [x] **AUTO-03**: User can configure a daily monitoring schedule at a specific time
- [x] **AUTO-04**: User can configure a weekly monitoring schedule with day and time
- [x] **AUTO-05**: System detects new episodes by comparing Xtream episode IDs against known episode IDs for that user-series
- [x] **AUTO-06**: System auto-queues download of newly detected episodes and prevents duplicate queue entries for the same episode/user

### Download Reliability

- [ ] **RELY-01**: User can see accurate progress updates for active downloads
- [ ] **RELY-02**: User can abort a download and see a correct terminal canceled state
- [ ] **RELY-03**: User can resume a paused or interrupted download and continue from prior progress where possible
- [ ] **RELY-04**: System retries transient download failures using bounded backoff rules
- [ ] **RELY-05**: User can see actionable failure states and retry from failed states
- [ ] **RELY-06**: Download lifecycle behavior (progress, abort, resume, retry) is covered by automated tests

### Mobile Pagination

- [x] **MOBL-01**: User does not miss the last item when mobile infinite scroll crosses page boundaries
- [x] **MOBL-02**: User sees deterministic ordering across mobile infinite-scroll pagination
- [x] **MOBL-03**: Mobile infinite-scroll boundary behavior is covered by automated regression tests

## v2 Requirements

Deferred to future release. Tracked but not in current roadmap.

### Automation Enhancements

- **AUTX-01**: User can restrict auto-downloads to unplayed episodes only
- **AUTX-02**: User can limit maximum queued episodes per series
- **AUTX-03**: User can define per-series caps for storage or download count

### External Governance

- **EXTN-01**: Admin can configure quotas or rate limits for external users
- **EXTN-02**: Admin can audit external direct-link usage history

## Out of Scope

Explicitly excluded. Documented to prevent scope creep.

| Feature | Reason |
|---------|--------|
| Live categories and live playback | Product scope is currently VOD and series only |
| Replace aria2 with a different downloader | Current decision is to harden aria2 first |
| Mobile Load More pagination replacement | Chosen approach is to keep infinite scroll and fix boundary logic |
| Auto-download entire libraries/series in bulk | High risk of storage and queue runaway; not needed for v1 |
| Complex multi-role hierarchy beyond Admin/Member + Internal/External | Added complexity and testing burden without immediate value |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| DISC-01 | Phase 4 | Complete |
| DISC-02 | Phase 4 | Complete |
| DISC-03 | Phase 4 | Complete |
| DISC-04 | Phase 4 | Complete |
| DISC-05 | Phase 3 | Complete |
| DISC-06 | Phase 3 | Complete |
| ACCS-01 | Phase 1 | Complete |
| ACCS-02 | Phase 1 | Complete |
| ACCS-03 | Phase 1 | Complete |
| ACCS-04 | Phase 1 | Complete |
| ACCS-05 | Phase 1 | Complete |
| ACCS-06 | Phase 1 | Complete |
| DOWN-01 | Phase 2 | Complete |
| DOWN-02 | Phase 2 | Complete |
| DOWN-03 | Phase 2 | Complete |
| DOWN-04 | Phase 2 | Complete |
| AUTO-01 | Phase 7 | Complete |
| AUTO-02 | Phase 7 | Complete |
| AUTO-03 | Phase 7 | Complete |
| AUTO-04 | Phase 7 | Complete |
| AUTO-05 | Phase 7 | Complete |
| AUTO-06 | Phase 7 | Complete |
| RELY-01 | Phase 5 | Complete |
| RELY-02 | Phase 5 | Complete |
| RELY-03 | Phase 5 | Complete |
| RELY-04 | Phase 5 | Complete |
| RELY-05 | Phase 5 | Complete |
| RELY-06 | Phase 5 | Complete |
| MOBL-01 | Phase 6 | Complete |
| MOBL-02 | Phase 6 | Complete |
| MOBL-03 | Phase 6 | Complete |

**Coverage:**
- v1 requirements: 31 total
- Mapped to phases: 31
- Unmapped: 0

---
*Requirements defined: 2026-02-25*
*Last updated: 2026-02-28 after Phase 7 completion*
