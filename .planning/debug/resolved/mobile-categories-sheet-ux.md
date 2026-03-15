---
status: diagnosed
trigger: "[Current branch: main]You are diagnosing a single UAT gap (do NOT implement fixes yet)."
created: 2026-02-26T00:00:00Z
updated: 2026-02-26T00:15:00Z
---

## Current Focus

hypothesis: CategorySidebar mobile variant hardcodes left-side sheet and forwards selection without closing mobile open state.
test: Validate SheetContent side prop and category option onSelect handlers.
expecting: side="left" plus onSelectCategory(...) calls that never invoke setIsMobileSheetOpen(false).
next_action: return diagnosis with evidence and missing checklist for UAT gap

## Symptoms

expected: On mobile, category sheet should be a bottom sheet and close immediately when user selects a category.
actual: Category panel appears from side and stays open after category selection.
errors: none reported
reproduction: Open mobile browse page, open categories, select category.
started: UAT gap request for UX update

## Eliminated

## Evidence

- timestamp: 2026-02-26T00:10:00Z
  checked: .planning/phases/04-category-browse-filter-ux/04-UAT.md
  found: Gap explicitly asks for mobile close-on-select and bottom-sheet behavior.
  implication: Existing implementation is functional but UX contract changed.

- timestamp: 2026-02-26T00:12:00Z
  checked: resources/js/components/category-sidebar.tsx
  found: Mobile sheet uses <SheetContent side="left" ...>.
  implication: Component forces side drawer, not bottom sheet.

- timestamp: 2026-02-26T00:14:00Z
  checked: resources/js/components/category-sidebar.tsx
  found: Category option handlers call onSelectCategory(...) only; no setIsMobileSheetOpen(false) or SheetClose wrapper.
  implication: Selecting category triggers Inertia visit but leaves sheet open state unchanged.

- timestamp: 2026-02-26T00:14:30Z
  checked: resources/js/components/ui/sheet.tsx
  found: SheetContent defaults side='right' unless overridden.
  implication: Direction is controlled by passed side prop; current left behavior is explicit, not accidental.

- timestamp: 2026-02-26T00:14:50Z
  checked: resources/js/pages/movies/index.tsx and resources/js/pages/series/index.tsx
  found: Both pages pass onSelectCategory handlers that only trigger router.visit(...); they do not control mobile sheet open state.
  implication: Closing logic must live in CategorySidebar mobile click path.
## Resolution

root_cause: "Mobile category drawer behavior is hardcoded in CategorySidebar: it explicitly renders SheetContent with side=left and category selection callbacks never close isMobileSheetOpen, so the drawer remains open after select and cannot behave as a bottom sheet."
fix: ""
verification: ""
files_changed: []
