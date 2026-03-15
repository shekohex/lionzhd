# Requirements: LionzHD Streaming Platform Enhancements

**Defined:** 2026-03-15
**Core Value:** Users can quickly find the right movie/series and reliably get their own downloads with correct permissions and automation.

## v1.1 Requirements

Requirements for milestone `v1.1 Category Personalization & Search UX`. Each maps to exactly one roadmap phase.

### Category Personalization

- [ ] **PERS-01**: User can keep separate category preferences for movies and series
- [ ] **PERS-02**: User can reorder visible categories for a media type and see that order persist across sessions
- [ ] **PERS-03**: User can pin up to 5 categories per media type and pinned categories stay above non-pinned categories
- [ ] **PERS-04**: User can hide a category from sidebar or navigation for a media type without affecting other users
- [ ] **PERS-05**: User can reset category preferences for a media type back to the default synced order and visibility

### Ignore Filters

- [ ] **IGNR-01**: User can ignore a category for a media type so matching titles are excluded from catalog listings for that user
- [ ] **IGNR-02**: User gets a recovery path when hidden or ignored preferences leave no visible categories or results

### Search & Navigation

- [ ] **NAVG-01**: User can search categories within sidebar or navigation on web and mobile
- [ ] **SRCH-01**: User can switch search media type between all, movies, and series and see UI state stay in sync with the URL
- [ ] **SRCH-02**: User sees only matching media-type results when search is filtered to movies or series
- [ ] **SRCH-03**: User sees movie-only or series-only search results in a full-width result mode
- [ ] **SRCH-04**: User can refresh, deep-link, and use back or forward navigation without losing correct search mode behavior

### Detail Context

- [ ] **CTXT-01**: User can see all assigned categories on movie detail pages
- [ ] **CTXT-02**: User can see all assigned categories on series detail pages

## Future Requirements

Deferred beyond `v1.1`.

### Automation Enhancements

- **AUTX-01**: User can restrict auto-downloads to unplayed episodes only
- **AUTX-02**: User can limit maximum queued episodes per series
- **AUTX-03**: User can define per-series caps for storage or download count

### External Governance

- **EXTN-01**: Admin can configure quotas or rate limits for external users
- **EXTN-02**: Admin can audit external direct-link usage history

### Discovery Expansion

- **PERS-06**: User can bulk manage many categories in a dedicated management flow
- **PERS-07**: User can see onboarding guidance that explains the difference between hiding and ignoring a category

## Out of Scope

Explicitly excluded from `v1.1`.

| Feature | Reason |
|---------|--------|
| Global category order or visibility changes | Personalization must remain per user and must not mutate shared taxonomy |
| Unlimited pinned categories | Pinning loses meaning and mobile navigation becomes cluttered |
| Ignored categories hiding direct detail pages, watchlist items, or admin access | Ignore applies to discovery listings, not all access paths |
| Separate search implementations for All, Movies, and Series | One shared search contract is required to avoid behavior drift |
| Live TV discovery or playback work | Still outside current product scope |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| PERS-01 | — | Pending |
| PERS-02 | — | Pending |
| PERS-03 | — | Pending |
| PERS-04 | — | Pending |
| PERS-05 | — | Pending |
| IGNR-01 | — | Pending |
| IGNR-02 | — | Pending |
| NAVG-01 | — | Pending |
| SRCH-01 | — | Pending |
| SRCH-02 | — | Pending |
| SRCH-03 | — | Pending |
| SRCH-04 | — | Pending |
| CTXT-01 | — | Pending |
| CTXT-02 | — | Pending |

**Coverage:**
- v1.1 requirements: 14 total
- Mapped to phases: 0
- Unmapped: 14 ⚠️

---
*Requirements defined: 2026-03-15*
*Last updated: 2026-03-15 after initial milestone definition*
