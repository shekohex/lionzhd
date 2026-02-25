# Project Research Summary

**Project:** LionzHD Streaming Platform Enhancements
**Domain:** Xtream-based VOD/Series streaming companion (Laravel 12 + Inertia React) with multi-user access control, user-scoped downloads, and watchlist-driven automation (aria2-backed)
**Researched:** 2026-02-25
**Confidence:** MEDIUM

## Executive Summary

This milestone is a brownfield “streaming companion” for Xtream VOD/Series (not Live): fast category-based discovery, safe multi-user access, and reliable server-side downloading (aria2) with automation (watchlist → new episodes → downloads). Successful implementations treat external systems (Xtream + aria2) as flaky dependencies, keep the database as the source of truth for user intent/ownership, and harden scheduled work with idempotency + locks.

Recommended approach: extend the existing Laravel monolith boundaries (Controllers → Actions → Jobs → Integrations) and ship in phases that establish invariants early: (1) authorization + ownership/tenancy and a fixture-based test strategy, (2) category identity + idempotent sync + stable pagination to fix mobile infinite-scroll, (3) download lifecycle reconciliation + safe storage boundaries, then (4) timezone-aware automation with strict dedupe/limits and provider rate controls.

Main risks are correctness and trust killers: category collisions across provider/content types, RBAC/tenant isolation enforced only in UI/app code, and non-reconciled aria2 state causing stuck/phantom downloads. Mitigate with composite identity keys + DB constraints, centralized policy enforcement + canary isolation tests, and a DB-first download model with a periodic reconciler job (plus secure per-tenant paths and bounded retries).

## Key Findings

### Recommended Stack

Stack is Laravel-native and operationally pragmatic: Redis-backed queues with Horizon for visibility, optional Reverb/Echo for real-time progress (otherwise controlled polling), and a first-party integration style (Saloon connectors) for Xtream + aria2 JSON-RPC. Avoid stale aria2 PHP clients; implement a small internal RPC client so reliability semantics (retry/backoff, batching, idempotency) match product needs.

**Core technologies:**
- Redis OSS (7.x): queue backend + scheduler coordination + WS scaling — standard for Laravel Horizon/Reverb in production.
- Laravel Horizon: queue observability + worker tuning — required once sync/reconcile/automation fan-out lands.
- Laravel Reverb: first-party WebSocket server — best fit for download progress updates without third-party WS infra (optional if polling-only).
- aria2c: download engine with resumable transfers — mature, RPC-driven, supports pause/resume and rich status.

### Expected Features

The “trust” surface is dominated by discovery correctness (categories + mobile infinite scroll) and download correctness (ownership + lifecycle). Differentiation comes from per-user, timezone-aware series automation with strict guardrails.

**Must have (table stakes):**
- Categories browsing/filtering for VOD + Series (exclude Live) — discovery at scale.
- Mobile category UX + infinite-scroll correctness — prevents skipped/duplicated items and UI jank.
- RBAC (Admin vs Member) + Internal vs External policy — admin surfaces locked; external users constrained.
- User-scoped downloads (visibility + ownership + authorization) — prevents privacy/correctness issues.
- Download queue UX (pause/cancel/retry) + lifecycle reliability (progress/abort/resume) — core value.

**Should have (competitive):**
- Per-user series auto-download schedules (hourly/daily/weekly) — watchlist becomes “set and forget”.
- New-episode detection using Xtream episode IDs — deterministic automation trigger.
- Smart episode rules (limits, unplayed-only) + self-healing retries — prevents runaway automation/support load.
- External-member safe sharing (signed links + auditing/quotas) — controlled external access.

**Defer (v2+):**
- Bulk/offline library management (auto-download entire series/library) — high risk of storage/queue blowups.
- Advanced integrity verification/repair workflows — only if corruption becomes common.

### Architecture Approach

Keep the current monolith pattern and formalize key boundaries: controllers authorize + validate, actions implement business rules (idempotency, dedupe, path rules), jobs handle slow/flaky work, integrations own external API contracts. For downloads, prefer a DB-first intent model plus a reconciler that converges DB state with aria2, rather than treating aria2 as the source of truth.

**Major components:**
1. Catalog (VOD/Series) — local cached catalog + search + stable pagination.
2. Categories — scoped category identity + sync + facet filtering for browse.
3. Access/RBAC — Admin vs Member plus Internal vs External policy gates.
4. Downloads — user-owned download intents + UI state model + storage ownership.
5. aria2 lifecycle — RPC client + reconciliation + anomaly handling + backoff.
6. Automation (Series) — per-user subscriptions + scheduler coordinator + fan-out jobs.

### Critical Pitfalls

1. **Category identity collisions** — scope categories by `{provider_account_id, content_type, remote_category_id}` and enforce with DB constraints; never key by name.
2. **Non-idempotent sync/upsert** — define stable natural keys, use deterministic upserts + mark-and-sweep cleanup, and lock single-flight sync per provider/account.
3. **RBAC/tenancy enforced in UI or ad-hoc checks** — centralize policies/middleware and add table-driven permission tests + cross-tenant canary tests.
4. **Scheduled automation without idempotency/locking (plus DST bugs)** — at-least-once handlers, unique locks/dedupe keys, run tracking, timezone-aware next-run computation, DST tests.
5. **aria2 divergence + unsafe download paths** — supervise aria2, secure RPC, periodic reconciliation, per-tenant root dirs, server-generated filenames, and path traversal tests.

## Implications for Roadmap

Based on research, suggested phase structure:

### Phase 0: Test/ops scaffolding (enables safe refactors)
**Rationale:** All major work touches external integrations + multi-user safety; flaky “real service” tests will stall delivery and mask regressions.
**Delivers:** Fixture-based Xtream payloads, aria2 RPC mocking boundary (or ephemeral local daemon in a small integration suite), baseline job observability (Horizon/Schedule monitor) in non-local envs.
**Addresses:** Reliability hardening preconditions; protects every subsequent phase.
**Avoids:** PITFALLS #11 (flaky tests), #6 (overlap untested).

### Phase 1: Access + ownership/tenancy invariants (multi-user safety)
**Rationale:** RBAC and ownership are cross-cutting; shipping features before invariants creates pervasive rework and silent leaks.
**Delivers:** Admin vs Member roles, Internal vs External policy gates, admin-only route protection, user-scoped download ownership model, backfill/migration plan, permission matrix tests + cross-tenant canary tests.
**Addresses:** FEATURES table stakes: RBAC, Internal/External, user-scoped downloads.
**Avoids:** PITFALLS #3/#4/#5/#12.

### Phase 2: Discovery UX (categories + stable pagination + mobile infinite scroll)
**Rationale:** Discovery is the first trust touchpoint; correctness depends on stable server pagination and properly-scoped category identity.
**Delivers:** Category tables with scoped identity, idempotent category sync, item↔category mapping, sidebar browse/filter for movies + series, stable paginator contract (`withQueryString`, deterministic ordering), mobile infinite-scroll boundary fix (optionally add list virtualization).
**Uses:** spatie/laravel-query-builder (allow-listed filters/sorts/includes), React Query/Virtual where appropriate.
**Avoids:** PITFALLS #1/#2 and “infinite scroll without stable ordering”.

### Phase 3: Download lifecycle hardening (aria2 reconciliation + safe storage)
**Rationale:** The product’s core value collapses if downloads are flaky; hardening requires DB-first orchestration, safe path rules, and reconciliation semantics.
**Delivers:** DB-first download intent (unique by user+media), secure aria2 JSON-RPC client, pause/cancel/retry/resume semantics, periodic reconciliation job with anomaly handling, safe per-tenant/per-user storage roots + server-generated filenames + atomic moves, improved error visibility in UI; optional Reverb/Echo real-time progress.
**Uses:** Redis + Horizon; (optional) Reverb + Echo + Pusher protocol.
**Avoids:** PITFALLS #8/#9.

### Phase 4: Watchlist automation (timezone-aware + rate-limited)
**Rationale:** Automation multiplies load and failure modes; only ship once RBAC/ownership + download reliability are stable.
**Delivers:** Per-user schedules (hourly/daily/weekly) with explicit timezone and next-run UX, coordinator + fan-out jobs with dedupe/locks, new-episode detection by Xtream episode IDs, smart rules (limits/unplayed), bounded retries + dead-letter/alerts, provider rate limiting/backoff/circuit breaking.
**Addresses:** Differentiators: per-user auto-download scheduling + new-episode detection + rules.
**Avoids:** PITFALLS #6/#7/#10.

### Phase Ordering Rationale

- Establish invariants first (authorization + ownership + test harness) so later features can be implemented once and verified.
- Categories and mobile browsing correctness depend on stable pagination and correct identity scoping; do them before performance micro-optimizations.
- Download hardening requires its own lifecycle model and reconciliation loop; treat it as a subsystem with explicit states and storage rules.
- Automation is last because it amplifies provider load and operational risk; it must lean on idempotent download intents and hardened jobs.

### Research Flags

Phases likely needing deeper research during planning:
- **Phase 3 (Downloads hardening):** aria2 RPC edge cases (restart/session, missing GIDs), reconciliation semantics, safe file naming/path model, and whether to use WS (Reverb) vs controlled polling.
- **Phase 4 (Automation):** timezone/DST modeling, schedule intent UX, provider variability in episode IDs, rate limiting strategy per provider/account.

Phases with standard patterns (skip research-phase):
- **Phase 1 (RBAC/ownership):** standard Laravel policies/gates + DB constraints + table-driven tests.
- **Phase 2 (Categories + pagination):** standard sync + allow-listed filtering + cursor/page invariants.

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | MEDIUM | Versions validated via registries/Packagist; final choices depend on current runtime (PHP 8.2/8.3 vs 8.4) and existing infra. |
| Features | MEDIUM | Table stakes/differentiators grounded in Plex/Emby/Jellyfin patterns; still needs validation against LionzHD’s specific user workflows. |
| Architecture | MEDIUM | Aligns with existing monolith boundaries; details depend on current schema (teams/tenancy) and existing download model. |
| Pitfalls | MEDIUM | Mix of official docs (aria2/Laravel/OWASP) and field experience; broadly consistent with multi-tenant + downloader systems. |

**Overall confidence:** MEDIUM

### Gaps to Address

- **Tenancy model reality:** confirm whether “team/tenant” is already modeled; if not, define minimal `tenant_id` strategy and backfill plan (or explicitly choose single-tenant + per-user isolation).
- **Provider-account concept:** categories and sync keys assume `{provider_account_id, content_type, remote_id}`; validate how LionzHD represents Xtream credentials/accounts today.
- **Progress delivery mechanism:** decide WS (Reverb) vs polling based on ops constraints; ensure UI state is consistent under aria2 restarts either way.
- **Filesystem/runtime constraints:** confirm deployment environment for downloads (permissions, disk quotas, container volumes) to design safe per-tenant roots and cleanup policies.

## Sources

### Primary (HIGH confidence)
- Laravel docs (12.x) — scheduling, authorization: https://laravel.com/docs/12.x/scheduling , https://laravel.com/docs/12.x/authorization
- aria2 manual — RPC interface + session concepts: https://aria2.github.io/manual/en/html/aria2c.html#rpc-interface
- Packagist — Horizon/Reverb/Pulse/Spatie packages version constraints (see STACK.md sources)
- npm registry — Echo/Pusher/React Query/React Virtual versions (see STACK.md sources)

### Secondary (MEDIUM confidence)
- Plex Support — downloads UX/constraints: https://support.plex.tv/articles/downloads-overview/ (and related FAQ/iOS/Android pages)
- Emby docs — offline access + auto-download semantics/limits: https://emby.media/support/articles/Offline-Access.html , https://emby.media/support/articles/Sync.html
- Jellyfin docs — users/admin concept: https://jellyfin.org/docs/general/server/users/
- PostgreSQL docs — Row Level Security concept (optional hardening): https://www.postgresql.org/docs/current/ddl-rowsecurity.html

### Tertiary (LOW confidence)
- Team experience patterns referenced in PITFALLS.md where not directly backed by official docs (treat as hypotheses to validate)

---
*Research completed: 2026-02-25*
*Ready for roadmap: yes*
