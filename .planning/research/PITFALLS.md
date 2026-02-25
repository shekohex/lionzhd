# Pitfalls Research

**Domain:** Xtream-based VOD/series streaming companion app (multi-tenant personal/team), with categories, RBAC, per-user isolation, scheduled automation, aria2-based downloads
**Researched:** 2026-02-25
**Confidence:** MEDIUM

## Critical Pitfalls

### Pitfall 1: Category identity collisions (not scoping by provider + content type)

**What goes wrong:**
Categories merge or overwrite each other across providers/accounts and/or across content types (VOD vs Series). Users see wrong groupings, missing items, or mixed catalogs.

**Why it happens:**
Teams treat `category_id` (or category name) as globally unique, and/or store categories in a single table without `source/provider/account` and `content_type` scoping.

**How to avoid:**
- Model category identity as a composite key: `{provider_account_id, content_type, remote_category_id}`.
- Persist both `remote_category_id` and `remote_category_name` (plus a local `display_name` override if needed).
- Make item→category relationships reference the composite identity (or a local surrogate ID that is unique per composite).
- Treat “category rename” as a normal update; treat “category id reuse” as possible and detect anomalies.

**Warning signs:**
- After sync, category counts shrink unexpectedly or “duplicate category names” spike.
- Items show up under categories from a different provider/account.
- Support reports: “my categories changed after I added another playlist/team.”

**Phase to address:**
Phase 1 — Categories + schema changes (introduce scoped identity + migrations + resync).

---

### Pitfall 2: Non-idempotent sync/upsert (duplicates, orphaned rows, stale joins)

**What goes wrong:**
Repeated syncs create duplicate categories/items, stale rows remain forever, and category→item joins drift. Resync becomes slow and “fixes” require manual DB cleanup.

**Why it happens:**
Sync logic is implemented as “insert everything” or “delete all then insert” without transactional boundaries, stable natural keys, or soft-delete semantics.

**How to avoid:**
- Define stable natural keys for each remote entity (category, VOD item, series, season/episode if tracked) scoped by provider/account.
- Use deterministic upsert (`INSERT … ON CONFLICT DO UPDATE`) and mark missing remote entities as soft-deleted per sync run.
- Store `last_seen_at`/`sync_run_id` and periodically hard-delete only after a grace period.
- Make resync safe to run twice concurrently (or enforce single-flight with locks).

**Warning signs:**
- DB uniqueness constraints absent on remote keys.
- Sync runtime grows roughly linearly with number of syncs.
- “Fix is to drop tables and resync.”

**Phase to address:**
Phase 1 — Categories/catalog sync foundation (idempotency + constraints + migrations).

---

### Pitfall 3: “RBAC by UI” or scattered ad-hoc checks (inconsistent enforcement)

**What goes wrong:**
Users can hit APIs they shouldn’t (or jobs perform actions across teams) because authorization is inconsistently enforced. You’ll ship “looks locked” UI but backend is permissive.

**Why it happens:**
RBAC is added late, enforced in the frontend, or implemented as scattered `if user.isAdmin` checks without a single policy layer.

**How to avoid:**
- Centralize authorization in one layer (policy functions/middleware) used by HTTP handlers *and* background jobs.
- Build a role→permission matrix up front; keep it small (Owner/Admin/Member/Viewer) unless proven otherwise.
- Add permission tests as a table-driven suite that asserts every endpoint/action is allowed/denied for each role.

**Warning signs:**
- Backend endpoints lack explicit authorization checks.
- “We hide the button” is used as security rationale.
- New features land without updating a permission matrix/test file.

**Phase to address:**
Phase 2 — RBAC design + enforcement + test matrix.

---

### Pitfall 4: Per-user / per-team data isolation enforced only in application code

**What goes wrong:**
One missed `WHERE tenant_id = ?` leaks data across users/teams (favorites, history, downloads, scheduled jobs, or content mappings). These are often silent and discovered late.

**Why it happens:**
Existing codebase predates multi-tenancy; engineers rely on “service layer filtering” without DB constraints or systematic test coverage.

**How to avoid:**
- Add `tenant_id` (and if needed `user_id`) to every user-owned table; make it NOT NULL.
- Add composite unique indexes that include `tenant_id` (prevents cross-tenant collisions).
- Prefer DB-enforced isolation when possible (e.g., Postgres Row-Level Security) or at least a repository interface that *requires* tenant context for all queries.
- Add a “canary” test: create two tenants/users with similar data and assert cross-tenant queries return 0 rows across every read path.

**Warning signs:**
- Queries join on `id` without also joining/scoping by tenant.
- Background jobs run “as system” without a tenant context.
- Manual QA uses only one tenant.

**Phase to address:**
Phase 2 — Tenancy/isolation baseline (schema + repositories + cross-tenant tests).

---

### Pitfall 5: Membership/role changes not reflected promptly (stale auth state)

**What goes wrong:**
Removed users keep access; promoted users can’t access; scheduled jobs keep running under an old team context.

**Why it happens:**
Roles are cached in long-lived sessions/tokens, or computed once at login, with no invalidation strategy.

**How to avoid:**
- If using JWT/session caching: include a `membership_version` / `roles_updated_at` claim and force re-auth or re-fetch when it changes.
- Keep access checks authoritative server-side (read current membership in DB) for sensitive actions.
- Add tests for “remove user from team” and “change role” affecting authorization immediately.

**Warning signs:**
- “Log out and in” fixes permission issues.
- Support sees access anomalies after role changes.

**Phase to address:**
Phase 2 — RBAC + tenancy (auth state invalidation + tests).

---

### Pitfall 6: Scheduled automation without idempotency + single-flight locking

**What goes wrong:**
Jobs overlap (multiple workers, retries, deploy restarts) causing duplicated work: double syncs, duplicate notifications, repeated aria2 adds, or conflicting writes.

**Why it happens:**
Schedulers are “fire and forget”; retries are enabled by default; job handlers assume exactly-once execution.

**How to avoid:**
- Design jobs as at-least-once: handlers must be idempotent.
- Implement job-level uniqueness (e.g., key by `{tenant_id, job_type}`) and/or distributed locks with TTL.
- Persist job runs with `run_id`, `started_at`, `finished_at`, `status`, `attempt`, `dedupe_key`.
- Add a test harness that simulates retry/overlap (run handler twice, assert no duplicates).

**Warning signs:**
- Duplicate rows created by “automation” features.
- Cron-style schedules run multiple times after deploy.
- Job queue backlog grows with no clear cause.

**Phase to address:**
Phase 3 — Scheduler/jobs foundation (idempotency + locking + run tracking + tests).

---

### Pitfall 7: Time zone / DST errors for schedules (and “run-at” drift)

**What goes wrong:**
User-scheduled jobs run an hour early/late, twice, or not at all around DST changes; “daily at 02:30” becomes ambiguous.

**Why it happens:**
Schedules are stored as naive local timestamps, or cron expressions are interpreted in server time while users expect local time.

**How to avoid:**
- Store: user’s IANA time zone + schedule intent (e.g., local time-of-day) and compute next run in UTC.
- For cron: explicitly set timezone in scheduler if supported; otherwise compute next run yourself.
- Add tests for at least one DST transition (spring-forward and fall-back) using a fixed timezone.

**Warning signs:**
- “It runs at the wrong time for EU/US users.”
- Around DST weeks: spikes in “missed sync” reports.

**Phase to address:**
Phase 3 — Scheduler/jobs (time model + DST tests).

---

### Pitfall 8: aria2 treated as a “library call” (no supervision, no reconciliation)

**What goes wrong:**
Downloads get stuck in “running” forever after restarts; state in DB diverges from aria2; partial files accumulate; users see phantom downloads.

**Why it happens:**
aria2 is a long-running process with its own queue/state. If you don’t supervise it and reconcile state, your app becomes unreliable after crashes/deploys.

**How to avoid:**
- Run aria2 as a supervised service (systemd/docker/k8s) and treat it as an external dependency.
- Use RPC with a secret token; assume RPC can fail and implement retries/backoff.
- Persist aria2 `gid` per download and implement a periodic reconciler job:
  - `tellActive`/`tellWaiting`/`tellStopped` and map results back to your DB.
  - Detect “DB says running but aria2 doesn’t know gid” and transition to failed/retryable.
- On controlled shutdowns, consider `aria2.saveSession` to persist state (and configure session files if you rely on it).

**Warning signs:**
- After deploy, many downloads become “stuck” without progress.
- aria2 RPC errors/connection resets increase.
- DB contains many downloads with no corresponding aria2 status.

**Phase to address:**
Phase 4 — aria2 lifecycle + reliability (supervision + reconciliation + failure semantics + tests).

---

### Pitfall 9: Unsafe download paths + cross-tenant file collisions

**What goes wrong:**
One user overwrites another user’s files; path traversal writes outside the intended directory; filenames break on different OS/filesystems.

**Why it happens:**
Teams build paths from remote titles (“Series Name S01E01”) or URLs without strict sanitization and per-tenant root enforcement.

**How to avoid:**
- Ensure per-tenant (and optionally per-user) download root directories; never share.
- Use server-generated stable filenames (IDs/hashes) and store display names separately.
- Validate paths are relative; reject `..`, absolute paths, and path separators in “filename” fields.
- Use atomic moves: download to a temp path, then rename into place once complete.

**Warning signs:**
- Duplicate filenames across different items cause overwrites.
- “Why did my download disappear after someone else downloaded?”
- Any code that concatenates paths with user-controlled strings.

**Phase to address:**
Phase 4 — Downloads + storage model (path safety + tenant isolation + tests).

---

### Pitfall 10: Provider variability + rate limiting ignored (sync/jobs can DOS the provider)

**What goes wrong:**
Automation and resync flood Xtream endpoints; provider blocks/bans the IP/account; app becomes slow and unreliable.

**Why it happens:**
Existing systems assume “fast and unlimited” upstream. Adding categories and scheduled sync multiplies calls (per tenant, per playlist, per job).

**How to avoid:**
- Add per-provider/account rate limiting and concurrency caps.
- Cache aggressively (ETag/If-Modified-Since if supported; otherwise hash responses and short-circuit processing).
- Prefer incremental refresh where possible; don’t pull full catalogs on every job.
- Make sync resilient: timeouts, retries with jitter, and circuit breaking per provider.

**Warning signs:**
- Sync job duration and error rate spike with user growth.
- Lots of 401/403/429/5xx from provider.
- “Works for one user, falls apart for teams.”

**Phase to address:**
Phase 3 — Jobs + sync hardening (rate limits, backoff, circuit breaker, tests with fake upstream).

---

### Pitfall 11: Test suite depends on real Xtream/aria2 instances (flaky + slow)

**What goes wrong:**
CI becomes unreliable; tests fail due to network/provider changes; engineers stop trusting tests and ship regressions in RBAC/isolation/jobs.

**Why it happens:**
Domain features are integration-heavy; it’s tempting to “just hit the real server.”

**How to avoid:**
- Use deterministic fixtures (recorded JSON responses) or a local fake Xtream server.
- For aria2, mock RPC at the client boundary or run an ephemeral local aria2 daemon in integration tests only.
- Add contract tests that validate parsing of “weird” provider payloads (missing fields, IDs as strings, empty categories).

**Warning signs:**
- CI failures correlate with time-of-day/network.
- Tests take minutes waiting on upstream.
- Engineers rerun CI until green.

**Phase to address:**
Phase 0/1 (immediately) — Testing strategy + fixtures; then applied in all phases.

---

### Pitfall 12: Backfill/migration plan skipped for new tenancy + RBAC fields

**What goes wrong:**
Deploy breaks existing “production-ish” data: NULL tenant IDs, orphaned rows, permissions undefined, scheduled jobs mis-scoped. Fix becomes emergency scripts + resync.

**Why it happens:**
Milestone pressure + “breaking changes acceptable” leads to under-specifying data migration paths.

**How to avoid:**
- Add explicit migration/backfill steps (even if resync is acceptable):
  - Create default tenant/team for existing users.
  - Assign deterministic initial roles.
  - Backfill `tenant_id`/`user_id` across tables.
- Add migration tests (schema + backfill) against a snapshot of representative old data.

**Warning signs:**
- Migrations add NOT NULL columns without backfill.
- “We’ll just resync later” is used without an actual resync mechanism.

**Phase to address:**
Phase 1–2 — Schema evolution + backfill + resync tooling.

---

## Technical Debt Patterns

Shortcuts that seem reasonable but create long-term problems.

| Shortcut | Immediate Benefit | Long-term Cost | When Acceptable |
|----------|-------------------|----------------|-----------------|
| Use category name as identifier | Easy mapping | Renames break favorites/history; duplicates collide | Never |
| Keep a single global downloads directory | Simple filesystem layout | Cross-tenant collisions, privacy leaks, cleanup pain | Never |
| Implement RBAC as booleans (`is_admin`, `is_owner`) scattered in code | Fast to ship | Inconsistent enforcement; hard to test/extend | Only as a temporary shim during migration, removed same milestone |
| Run scheduled jobs inside the web process without run tracking | No extra worker infra | Unbounded retries, overlap, and invisible failures | Only for dev; never for production-ish |
| Store remote payload blobs without schema/versioning | Easy debugging | Migrations become hard; parsing assumptions drift | Acceptable if versioned + size-limited + not used for queries |
| “Delete all + resync” as the only recovery | Simple mental model | Expensive at scale; breaks user data (favorites, downloads) | Only if user-owned data is separable and explicitly preserved |

## Integration Gotchas

Common mistakes when connecting to external services.

| Integration | Common Mistake | Correct Approach |
|-------------|----------------|------------------|
| Xtream provider APIs | Assume stable schemas/types (IDs always numbers, fields always present) | Use tolerant parsing + validation; coerce numeric strings; handle missing/empty categories; add fixtures for edge payloads |
| Xtream sync | Sync “per user action” + “per schedule” without coordination | Centralize sync triggers; dedupe by `{provider_account_id, sync_kind}`; rate limit and backoff |
| aria2 RPC | Expose RPC without a secret / bind publicly | Use RPC secret token; bind to localhost/private network; restrict firewall |
| aria2 state | Assume DB state == aria2 state | Implement reconciler + state machine; treat aria2 as source of truth for progress |
| Filesystem | Trust remote titles for filenames | Server-generated filenames; sanitize strictly; store display title separately |

## Performance Traps

Patterns that work at small scale but fail as usage grows.

| Trap | Symptoms | Prevention | When It Breaks |
|------|----------|------------|----------------|
| Full catalog resync on every schedule tick | High DB write volume; slow sync; provider throttling | Incremental sync, caching, backoff, dedupe; cap concurrency | Breaks quickly with teams + multiple providers |
| N+1 metadata fetch per category/item | API latency spikes; huge request counts | Bulk endpoints where possible; batch requests; cache | Becomes obvious at 1k–10k items |
| Unbounded job retries | Same failures repeat; queue grows forever | Retry budget + dead-letter; alerting on repeated failure | As soon as upstream is flaky |
| Keeping “download progress polling” too frequent | CPU/network waste; aria2 load | Adaptive polling; event-driven notifications if available; per-tenant caps | Moderate user growth |

## Security Mistakes

Domain-specific security issues beyond general web security.

| Mistake | Risk | Prevention |
|---------|------|------------|
| Missing server-side authorization for downloads/history/favorites | Cross-tenant data leaks | Enforce tenancy in data access; add cross-tenant tests; consider DB-level controls (e.g., RLS) |
| Logging Xtream credentials or full URLs with tokens | Credential leakage (logs, error trackers) | Redact secrets; structured logging with allowlist fields |
| Exposing aria2 RPC externally | Remote control of downloads; data exfil | Bind to localhost/private; RPC secret; firewall |
| Path traversal in download destination | Write arbitrary files | Strict path validation; per-tenant root; no user-provided paths |
| “System jobs” bypass RBAC/tenant rules | Privilege escalation via automation | Run jobs with explicit tenant context; apply same policy layer as HTTP |

## UX Pitfalls

Common user experience mistakes in this domain.

| Pitfall | User Impact | Better Approach |
|---------|-------------|-----------------|
| Categories reorder/rename unexpectedly after sync | Users lose “muscle memory”; favorites feel broken | Preserve stable ordering; show “last sync changed categories” note; allow pinning/custom ordering |
| Permission errors are silent (items disappear) | Confusing “bug” reports | Clear 403 states; show role restrictions; audit log for admin actions |
| Schedules described ambiguously (“daily at 2”) | Users think jobs are broken | Show timezone explicitly; show next-run time; warn about DST |
| Downloads get stuck with no recovery UI | Users retry randomly; storage fills | Provide retry/cancel; surface aria2 errors; “reconcile” action |

## "Looks Done But Isn't" Checklist

Things that appear complete but are missing critical pieces.

- [ ] **Categories:** IDs scoped by provider+content type; resync idempotent; uniqueness constraints exist
- [ ] **RBAC:** Backend policy enforcement exists for *every* action; permission matrix tests cover endpoints and jobs
- [ ] **Tenancy:** Every user-owned table has `tenant_id`; cross-tenant canary test exists; no “system job” bypass
- [ ] **Scheduled jobs:** Single-flight/locking exists; retries are bounded; DST/timezone tests exist
- [ ] **aria2:** Supervised process; RPC secured; reconciler job exists; state machine handles missing GIDs
- [ ] **Downloads storage:** Per-tenant root; filename sanitization; atomic move; cleanup of partials

## Recovery Strategies

When pitfalls occur despite prevention, how to recover.

| Pitfall | Recovery Cost | Recovery Steps |
|---------|---------------|----------------|
| Category collisions / bad mapping | MEDIUM | Add scoping columns + backfill; rebuild category mapping tables; force catalog resync; invalidate caches |
| Duplicate/stale catalog rows | MEDIUM | Add unique constraints; write dedupe migration; mark-and-sweep cleanup; rerun idempotent sync |
| RBAC enforcement bugs | MEDIUM | Ship hotfix policy layer; add missing checks; add failing regression tests for the exploit paths |
| Cross-tenant data leak | HIGH | Immediately block affected endpoints; audit logs; rotate tokens if needed; run data integrity scans; add tenant constraints/RLS + tests |
| Job overlap causing duplicates | MEDIUM | Pause scheduler; add locks/dedupe keys; cleanup duplicate rows; re-enable with canary tenant |
| aria2 divergence/stuck downloads | MEDIUM | Restart supervised aria2; run reconciler to mark unknown GIDs failed; cleanup partial files; requeue downloads |

## Pitfall-to-Phase Mapping

How roadmap phases should address these pitfalls.

| Pitfall | Prevention Phase | Verification |
|---------|------------------|--------------|
| Category identity collisions | Phase 1 | Two providers with same category_id/name don’t collide; tests validate scoped uniqueness |
| Non-idempotent sync/upsert | Phase 1 | Running sync twice yields identical DB state; uniqueness constraints prevent duplicates |
| RBAC by UI / scattered checks | Phase 2 | Permission matrix tests; unauthorized requests reliably return 403 |
| App-only tenancy filtering | Phase 2 | Cross-tenant canary tests pass for all reads/writes; no query path lacks tenant scope |
| Stale membership/roles | Phase 2 | Role change takes effect immediately in tests; token/session invalidation covered |
| Jobs without idempotency/locking | Phase 3 | Simulated overlap/retry produces no duplicates; job run table shows one active run per key |
| Time zone/DST schedule bugs | Phase 3 | DST unit tests; UI shows next-run time and timezone |
| aria2 lifecycle unreliability | Phase 4 | Kill/restart aria2: reconciler converges DB state; downloads recover or fail deterministically |
| Unsafe download paths/collisions | Phase 4 | Path traversal tests; per-tenant directories enforced; no overwrites across users |
| Provider rate limit ignored | Phase 3 | Load tests with fake upstream show capped concurrency + backoff; error spikes don’t cascade |
| Flaky tests hitting real services | Phase 0/1 | CI runs offline; fixtures/fakes cover edge cases |
| Backfill/migration skipped | Phase 1–2 | Migration test from old snapshot succeeds; resync tooling verified |

## Sources

- aria2 manual (RPC methods, session, supervision considerations): https://aria2.github.io/manual/en/html/aria2c.html#rpc-interface
- aria2 manual (general options incl. input/session concepts): https://aria2.github.io/manual/en/html/aria2c.html
- PostgreSQL Row-Level Security (useful for DB-enforced tenant isolation): https://www.postgresql.org/docs/current/ddl-rowsecurity.html
- OWASP Top Ten (general web security baseline; not IPTV-specific): https://owasp.org/www-project-top-ten/
- Team experience patterns (multi-tenant RBAC, job idempotency, downloader supervision) — MEDIUM confidence where not backed by official docs

---
*Pitfalls research for: Xtream-based VOD/series streaming companion app milestone (categories, RBAC, isolation, scheduler, aria2)*
*Researched: 2026-02-25*
