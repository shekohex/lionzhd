# Phase 3: Categories Sync & Categorization Correctness - Context

**Gathered:** 2026-02-25
**Status:** Ready for planning

<domain>
## Phase Boundary

Admin syncs Xtream categories for VOD and Series only (Live excluded), and content keeps correct category relationships across re-syncs without categorization breakage or obvious duplicates.

</domain>

<decisions>
## Implementation Decisions

### Sync scope control
- Sync is a single combined action for VOD + Series together.
- Default behavior is effectively both sources included on every run.
- If one source succeeds and the other fails, keep successful changes and report partial outcome.
- After sync completes, stay on the same page.
- No confirmation step before sync.
- If a sync is already running, queue the next run.
- If provider returns zero categories for a source, warn and require explicit confirmation before applying.
- No UI cancel action for in-progress sync.

### Category identity rules
- Canonical identity is provider category ID.
- If same ID title changes, update title to provider value.
- If duplicates exist by name with different IDs, keep both entries.
- If a previously synced category is missing on re-sync, hard remove it.
- Same provider ID across VOD and Series is treated as one shared category.
- On hard removal, linked content is moved to Uncategorized.
- Invalid or missing category-ID rows are skipped and warned.
- Category display order is alphabetical by name.

### Uncategorized handling
- Content with no valid mapped category is auto-assigned to Uncategorized.
- Uncategorized is a persisted category record (not virtual-only).
- If a removed category reappears later, previously moved items auto-remap back.
- Uncategorized buckets are separate per content type (VOD vs Series).

### Sync feedback UX
- Default result detail is summary plus top issues.
- Sync results are shown via a history log page.
- Skipped Live categories are not mentioned in admin feedback.
- Runs with warnings are marked as success with warnings.

### OpenCode's Discretion
None explicitly delegated.

</decisions>

<specifics>
## Specific Ideas

No specific product/style references provided.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 03-categories-sync-categorization-correctness*
*Context gathered: 2026-02-25*
