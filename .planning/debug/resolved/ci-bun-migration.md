---
status: resolved
trigger: "Investigate issue: ci-bun-migration"
created: 2026-02-28T18:35:00+00:00
updated: 2026-02-28T18:37:44+00:00
---

## Current Focus

hypothesis: Bun migration is complete and valid across lint/test/release CI paths.
test: Verified with local Bun install/lint/build and scanned workflow/docker configs for stale pnpm references.
expecting: Successful Bun command execution and no pnpm references in active CI config.
next_action: mark resolved and archive debug session.

## Symptoms

expected: CI pipeline passes and uses Bun (runtime + package manager) instead of Node.js and pnpm.
actual: CI currently fails; pipeline still appears configured around Node.js/pnpm.
errors: CI failure reported by user; exact job log not yet provided.
reproduction: Trigger CI via push/PR on current branch.
started: Started after moving project tooling toward Bun.

## Eliminated

## Evidence

- timestamp: 2026-02-28T18:35:17+00:00
  checked: workflow file inventory
  found: .github/workflows/lint.yml, tests.yml, release.yml
  implication: CI behavior is controlled by three workflow files to inspect.

- timestamp: 2026-02-28T18:35:17+00:00
  checked: lockfiles and package manifest presence
  found: package.json exists, bun.lock exists, pnpm-lock.yaml absent
  implication: repository appears Bun-migrated; CI likely stale if using pnpm.

- timestamp: 2026-02-28T18:35:55+00:00
  checked: .github/workflows/lint.yml and tests.yml
  found: workflows install pnpm, setup Node with pnpm cache, and run pnpm install/run commands.
  implication: CI execution path still pinned to pnpm/Node tooling.

- timestamp: 2026-02-28T18:35:55+00:00
  checked: docker/Dockerfile frontend stage
  found: Dockerfile copies pnpm-lock.yaml and runs pnpm install/build despite repo using bun.lock.
  implication: release pipeline docker build path can fail due missing pnpm lockfile and stale package manager.

- timestamp: 2026-02-28T18:35:55+00:00
  checked: composer.json scripts
  found: development scripts use bun/bunx commands.
  implication: project runtime/tooling direction is Bun, confirming CI mismatch as root cause.

- timestamp: 2026-02-28T18:36:45+00:00
  checked: CI and build configuration updates
  found: migrated lint/tests workflows to oven-sh/setup-bun + bun install/run; migrated Docker frontend stage to bun image/install/build; updated package manager metadata to Bun.
  implication: stale pnpm/Node CI path replaced with Bun-compatible path.

- timestamp: 2026-02-28T18:37:30+00:00
  checked: local verification command
  found: bun install --frozen-lockfile, bun run lint, and bun run build all pass.
  implication: Bun toolchain works with current repository state.

- timestamp: 2026-02-28T18:37:30+00:00
  checked: stale tooling references in active CI configs
  found: no pnpm/setup-node references remain in .github/workflows or docker/Dockerfile.
  implication: CI paths are aligned to Bun instead of pnpm/Node setup flow.

## Resolution

root_cause: CI configs were not migrated with tooling switch; lint/tests workflows and Docker frontend stage still execute pnpm/Node flow and expect pnpm-lock.yaml while repo is Bun-based (bun.lock + bun scripts).
fix: Replaced pnpm/Node setup in lint/tests GitHub workflows with oven-sh/setup-bun and Bun install/run commands; migrated Docker frontend stage from Node+pnpm to Bun image with bun.lock install/build; updated package.json packageManager to Bun.
verification: bun --version, bun install --frozen-lockfile, bun run lint, bun run build all pass locally; grep checks confirm no pnpm/setup-node references remain in active CI workflow/docker paths.
files_changed: [.github/workflows/lint.yml, .github/workflows/tests.yml, docker/Dockerfile, package.json]
