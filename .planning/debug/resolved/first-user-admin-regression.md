---
status: resolved
trigger: "Investigate issue: first-user-admin-regression"
created: 2026-02-28T19:14:05+00:00
updated: 2026-02-28T19:18:18+00:00
---

## Current Focus

hypothesis: Verified fix restores first-user super-admin and preserves existing admin governance behavior.
test: Completed targeted and adjacent access-control suites.
expecting: All relevant tests pass.
next_action: complete

## Symptoms

expected: On existing deployments with existing users, the first user should remain/be set as super admin after deploy.
actual: After deployment, first user is no longer admin.
errors: No explicit runtime error reported; behavior/authorization regression.
reproduction: Deploy current version on an existing environment with pre-existing users, then check first user's role/permissions in app/admin access.
started: Started after recent deployment; previously first user had admin privileges.

## Eliminated

## Evidence

- timestamp: 2026-02-28T19:14:27+00:00
  checked: code search for super-admin and first-user assignment
  found: RegisteredUserController assigns admin/super-admin only when creating a user and checks first-user via User::exists().
  implication: Existing users are unaffected by this bootstrap path.

- timestamp: 2026-02-28T19:14:27+00:00
  checked: database/migrations directory scan
  found: Access-control schema migration exists, but no dedicated backfill migration for pre-existing users.
  implication: Existing deployments likely defaulted old users to member/non-super-admin.

- timestamp: 2026-02-28T19:15:40+00:00
  checked: app/Http/Controllers/Auth/RegisteredUserController.php
  found: First-user admin/super-admin assignment only runs inside registration create transaction.
  implication: Deploy-time upgrades for already-created users cannot recover super-admin without migration/backfill.

- timestamp: 2026-02-28T19:15:40+00:00
  checked: database/migrations/2026_02_25_000001_add_access_control_fields_to_users_table.php
  found: Migration only adds role/subtype/is_super_admin with defaults member/external/false.
  implication: Existing users get non-admin defaults unless explicitly backfilled.

- timestamp: 2026-02-28T19:16:48+00:00
  checked: implemented backfill and tests
  found: Added migration 2026_02_28_000001_backfill_first_user_super_admin.php and FirstUserSuperAdminBackfillTest coverage for no-super-admin and existing-super-admin scenarios.
  implication: Fix is targeted to deploy-time legacy-user restoration and guarded against overriding existing governance.

- timestamp: 2026-02-28T19:17:22+00:00
  checked: php artisan test tests/Feature/AccessControl/FirstUserSuperAdminBackfillTest.php
  found: 2 tests passed validating first-user promotion when none exists and no override when super-admin already exists.
  implication: Implemented migration behavior matches expected backfill invariants.

- timestamp: 2026-02-28T19:17:38+00:00
  checked: php artisan test tests/Feature/Controllers/UsersControllerTest.php
  found: 4 tests passed covering users settings access, super-admin restrictions, and subtype updates.
  implication: Backfill change did not regress existing user-role management behavior.

## Resolution

root_cause: Access-control schema migration added role/subtype/is_super_admin with defaults, which demoted legacy users to member/non-super-admin; no deploy-time backfill restored first user super-admin.
fix: Added idempotent data migration that promotes earliest user to admin+super-admin only when no super-admin exists, plus regression tests for both promotion and no-override paths.
verification: Verified by passing targeted backfill tests (2) plus adjacent users controller access-control tests (4).
files_changed: ["database/migrations/2026_02_28_000001_backfill_first_user_super_admin.php", "tests/Feature/AccessControl/FirstUserSuperAdminBackfillTest.php"]
