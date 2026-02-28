---
status: resolved
trigger: "Investigate issue: homepage-light-mode-hero-readability"
created: 2026-02-28T23:19:09+00:00
updated: 2026-02-28T23:31:54+00:00
---

## Current Focus

hypothesis: Root cause is confirmed and fix is validated by automation; final output should include manual visual follow-up constraint.
test: Archive debug session and capture completion details.
expecting: Session moves to resolved and includes root cause/fix/QA status.
next_action: move debug file to resolved directory and commit fix

## Symptoms

expected: Hero heading/meta text and CTA/header buttons are clearly readable in light mode regardless of selected background image.
actual: Foreground text/buttons have insufficient contrast and blend into the background image in light mode.
errors: No runtime errors reported.
reproduction: Open homepage in light mode, inspect hero section and header buttons over background image; readability drops on bright/high-detail regions.
started: Present currently; regression start time unknown.

## Eliminated

## Evidence

- timestamp: 2026-02-28T23:19:33+00:00
  checked: Initial codebase search for homepage/hero implementations
  found: Homepage hero section is implemented in resources/js/pages/welcome.tsx
  implication: Light-mode readability issue likely localized to welcome.tsx styling choices

- timestamp: 2026-02-28T23:19:33+00:00
  checked: Hero-related component search results
  found: media-hero(-section) components are used by movie/series detail pages, not homepage
  implication: Fix should target landing page hero/header styles, not media detail hero components

- timestamp: 2026-02-28T23:20:09+00:00
  checked: resources/js/pages/welcome.tsx
  found: Hero heading/kicker use text-primary; metadata/description use text-muted-foreground; secondary/login buttons use bg-white/10 with text-primary
  implication: Foreground in hero/header depends on generic theme tokens + low-opacity surfaces over variable images

- timestamp: 2026-02-28T23:20:09+00:00
  checked: resources/css/app.css theme vars and resources/js/components/ui/button.tsx variants
  found: Light mode primary/muted tokens are app accent + gray values; outline/secondary buttons are built for solid page backgrounds, not photos
  implication: Contrast can fail over bright/high-detail hero images, matching reported symptom

- timestamp: 2026-02-28T23:20:38+00:00
  checked: Hero overlay geometry in resources/js/pages/welcome.tsx
  found: Existing gradients prioritize bottom/left darkening, while top-right header area remains lightly shaded (black/10 to transparent)
  implication: Header button readability is especially vulnerable on bright top-right image regions

- timestamp: 2026-02-28T23:21:17+00:00
  checked: resources/js/pages/welcome.tsx patch
  found: Added stronger top overlay, contrast-safe light-mode text classes, and darker translucent hero/header secondary button treatments with shadow support
  implication: Foreground readability is protected independent of bright/high-detail image regions in light mode

- timestamp: 2026-02-28T23:22:06+00:00
  checked: QA automation run (bun run lint && bun run types && bun run build)
  found: All commands completed successfully; no lint/type/build regressions
  implication: Fix is technically sound and build-safe; remaining risk is visual-only confirmation

- timestamp: 2026-02-28T23:22:49+00:00
  checked: php artisan test
  found: Full test suite passed (160 tests, 694 assertions)
  implication: Backend/integration behavior unaffected by frontend readability fix

- timestamp: 2026-02-28T23:31:40+00:00
  checked: Human-verify checkpoint response
  found: Automated QA rerun passed (lint/types/build/tests), but Playwright light-mode screenshot is inconclusive locally because homepage hero data is empty in this runtime
  implication: Engineering validation is green; final visual readability confirmation must be completed on seeded/staging data with real featured hero content

## Resolution

root_cause: "Welcome hero uses theme token text/button styles intended for solid surfaces (text-primary/text-muted-foreground + low-opacity glass buttons) and lacks strong top/right overlay coverage, so light-mode foreground contrast collapses on bright/high-detail background images."
fix: "Updated welcome hero to use contrast-safe light-mode styling: stronger top overlay, white/readable hero typography with shadow, and darker translucent header/secondary CTA button treatments with preserved dark-mode tokens."
verification: "Automated checks passed (lint, types, build, php artisan test). Local Playwright visual check blocked by missing featured hero data; manual visual validation required on seeded/staging data."
files_changed:
  - resources/js/pages/welcome.tsx
