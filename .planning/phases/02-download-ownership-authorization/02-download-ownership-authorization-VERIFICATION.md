---
phase: 02-download-ownership-authorization
verified: 2026-02-25T12:45:00Z
status: passed
score: 4/4 must-haves verified
---

# Phase 02: Download Ownership Authorization Verification Report

**Phase Goal:** Download records and operations are user-owned and enforced consistently across pages and APIs.

**Verified:** 2026-02-25T12:45:00Z
**Status:** passed
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths

| #   | Truth                                                                           | Status     | Evidence                                                                                           |
| --- | ------------------------------------------------------------------------------- | ---------- | -------------------------------------------------------------------------------------------------- |
| 1   | Member sees only own downloads in pages/APIs                                    | VERIFIED   | `MediaDownloadsController::index()` line 38: member query scoped to `user_id`; test passes         |
| 2   | Member can operate only on own downloads (pause/resume/cancel/retry)            | VERIFIED   | `download-operations` gate lines 136-138 returns `denyAsNotFound()` for non-owned; routes use middleware |
| 3   | Admin can view/operate downloads across all users                               | VERIFIED   | Gate allows admin at line 120; controller shows all downloads to admin with owner metadata         |
| 4   | New downloads are owned by initiating user and persist ownership                | VERIFIED   | Migration adds `user_id` FK; creation controllers pass `$user` to `MediaDownloadRef` factory methods |

**Score:** 4/4 truths verified

### Required Artifacts

| Artifact                                                                                        | Expected                                | Status   | Details                                |
| ----------------------------------------------------------------------------------------------- | --------------------------------------- | -------- | -------------------------------------- |
| `database/migrations/2026_02_25_000002_add_user_id_to_media_download_refs_table.php`            | Ownership persistence column            | VERIFIED | 24 lines, nullable FK to users         |
| `app/Models/MediaDownloadRef.php`                                                               | Model with owner relation               | VERIFIED | 96 lines, `user_id` fillable, `owner()` relation, static factories accept `User\|int\|null` |
| `app/Providers/AppServiceProvider.php`                                                          | Authorization gates                     | VERIFIED | 159 lines, `download-operations` gate with ownership check (lines 119-139) |
| `app/Http/Controllers/MediaDownloadsController.php`                                             | List scoping and operations             | VERIFIED | 205 lines, member scoping at line 38, admin owner filters at lines 32, 37, 39 |
| `app/Http/Controllers/VodStream/VodStreamDownloadController.php`                                | Movie download creation with ownership  | VERIFIED | 82 lines, passes `$user` to factory at line 45 |
| `app/Http/Controllers/Series/SeriesDownloadController.php`                                      | Series download creation with ownership | VERIFIED | 207 lines, passes `$user` to factory at lines 59, 108 |
| `app/Actions/GetActiveDownloads.php`                                                            | Ownership-scoped dedupe                 | VERIFIED | 60 lines, member dedupe scoped by `user_id` at line 45 |
| `routes/web.php`                                                                                | Route middleware binding                | VERIFIED | 105 lines, `can:download-operations,model` at lines 91, 95 |
| `resources/js/pages/downloads.tsx`                                                              | UI ownership alignment                  | VERIFIED | 419 lines, `canOperate` flag (line 93), admin owner filters (lines 234-292) |
| `resources/js/components/download-info.tsx`                                                     | Owner display and confirmation          | VERIFIED | 290 lines, owner badges for admins (line 133), confirmation dialogs with owner info (lines 72-73) |

### Key Link Verification

| From                                         | To                        | Via                                    | Status   | Details                                      |
| -------------------------------------------- | ------------------------- | -------------------------------------- | -------- | -------------------------------------------- |
| `MediaDownloadsController::index()`         | Database                  | `->where('user_id', $user->id)`        | WIRED    | Line 38, member-only scoping                 |
| `download-operations` gate                   | `MediaDownloadRef` model  | `$download->user_id === $user->id`     | WIRED    | Lines 136-138, denies as 404 for non-owned   |
| Routes (`downloads.edit`, `downloads.destroy`) | Gate authorization       | `can:download-operations,model`        | WIRED    | Lines 91, 95 in web.php                      |
| Movie/Series controllers                     | MediaDownloadRef factory  | `fromVodStream()` / `fromSeriesAndEpisode()` with `$user` | WIRED | Lines 45, 59, 108                            |
| `GetActiveDownloads`                         | Database                  | `->where('user_id', $user->id)`        | WIRED    | Line 45, ownership-scoped dedupe             |
| Downloads UI                                 | Backend authorization     | `canOperate` flag from auth props      | WIRED    | Line 93 in downloads.tsx                     |

### Requirements Coverage

| Requirement | Status | Blocking Issue |
| ----------- | ------ | -------------- |
| DOWN-01: Scoped ownership for list endpoints | SATISFIED | None |
| DOWN-02: Ownership check before operations (pause/resume/cancel/retry) | SATISFIED | None |
| DOWN-03: Admin can view/operate on all downloads | SATISFIED | None |
| DOWN-04: New downloads persist ownership | SATISFIED | None |

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
| ---- | ---- | ------- | -------- | ------ |
| None found | — | — | — | — |

### Human Verification Required

None — all verifiable programmatically.

### Gaps Summary

No gaps found. All must-haves verified:

1. **Member sees only own downloads**: Enforced via query scoping in `MediaDownloadsController::index()` and `denyAsNotFound()` in gate for operations.

2. **Member can operate only on own downloads**: Enforced via `download-operations` gate with model binding; returns 404 (not 403) to prevent ID enumeration.

3. **Admin can view/operate across all users**: Enforced via role check at start of gate; admin sees owner metadata and can filter by owner.

4. **New downloads persist ownership**: Enforced via migration, model fillable, and factory methods accepting owner; all creation paths pass authenticated user.

### Test Evidence

```
PASS  Tests\Feature\AccessControl\ExternalDownloadRestrictionsTest
  ✓ it forbids external members from movie server downloads
  ✓ it forbids external members from download operations
  ✓ it applies gate assertions for download ownership and role access
  ✓ it scopes downloads list payload to member-owned rows only
  ✓ it applies admin owner filters with newest-first ordering and owner options payload
  ✓ it forwards safe return_to when retrying vod downloads and ignores unsafe values
  ✓ it forwards safe return_to when retrying series downloads and ignores unsafe values
  ✓ it returns not found when internal members operate on non-owned downloads
  ✓ it allows signed direct download resolution and redirects

Tests:    11 passed (37 assertions)
Duration: 0.56s
```

---
_Verified: 2026-02-25T12:45:00Z_
_Verifier: OpenCode (gsd-verifier)_
