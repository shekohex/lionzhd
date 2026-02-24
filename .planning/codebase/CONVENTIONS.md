# Coding Conventions

**Analysis Date:** 2026-02-24

## Naming Patterns

**Files (PHP):**
- Use `PascalCase.php` for class files under `app/`.
  - Examples: `app/Actions/CreateSignedDirectLink.php`, `app/Http/Controllers/DirectDownloadController.php`, `app/Http/Requests/Auth/LoginRequest.php`

**Files (frontend TS/TSX):**
- Use `kebab-case.tsx` / `kebab-case.ts` for components, hooks, and pages.
  - Examples: `resources/js/components/media-card.tsx`, `resources/js/components/search-input.tsx`, `resources/js/hooks/use-infinite-scroll.ts`, `resources/js/pages/auth/login.tsx`
- Use `index.ts` / `index.tsx` as a barrel or route leaf when needed.
  - Example: `resources/js/types/index.ts`

**Classes/Types (PHP):**
- Use `PascalCase` for classes and enums.
  - Examples: `app/Actions/SyncMedia.php`, `app/Enums/MediaDownloadAction.php`

**Functions/Methods (PHP):**
- Use `camelCase` for methods.
- Use `__invoke` for “action” classes and call via the `AsAction` trait.
  - Example action invokable: `app/Actions/CreateDownloadOut.php`
  - Example caller: `app/Jobs/RefreshMediaContents.php` (`SyncMedia::run();`)

**React components/hooks (TS/TSX):**
- Use `PascalCase` for components; use `useXxx` for hooks.
  - Component example: `resources/js/pages/search.tsx` (`export default function Search() { ... }`)
  - Hook example: `resources/js/hooks/use-appearance.tsx` (`export function useAppearance() { ... }`)

**Variables/Constants:**
- Use `camelCase` for locals.
- Use `SCREAMING_SNAKE_CASE` for constants.
  - Example: `resources/js/pages/search.tsx` (`const FILTER_OPTIONS = { ... }`)

**Data fields (API/DB alignment):**
- Preserve upstream naming for serialized shapes (often `snake_case`) when it matches backend payloads.
  - Example TS interface fields: `resources/js/types/index.ts` (`email_verified_at`, `created_at`, `updated_at`)
  - Example frontend usage: `resources/js/pages/search.tsx` (`movie.stream_id`, `series.series_id`)

## Code Style

**Formatting (repo-wide):**
- Indent with 4 spaces.
  - Source: `.editorconfig`

**Formatting (frontend):**
- Use Prettier with:
  - semicolons
  - single quotes
  - `printWidth: 120`
  - `tabWidth: 4`
  - import organization + Tailwind class sorting
  - Source: `.prettierrc`
- Run via `pnpm run format` (formats `resources/`).
  - Source: `package.json`

**Formatting (PHP):**
- Use Laravel Pint preset `laravel` plus stricter rules:
  - `declare_strict_types`
  - `final_class`
  - `global_namespace_import`
  - `ordered_class_elements`
  - strict comparisons/params, etc.
  - Source: `pint.json`

**Linting (frontend):**
- Use ESLint flat config with:
  - `@eslint/js` recommended
  - `typescript-eslint` recommended
  - `eslint-plugin-react` recommended + JSX runtime config
  - `eslint-plugin-react-hooks` rules-of-hooks + exhaustive-deps
  - `eslint-config-prettier` to disable conflicting formatting rules
  - Source: `eslint.config.js`
- Run via `pnpm run lint`.
  - Source: `package.json`

**Static analysis (PHP):**
- Use PHPStan (Larastan) at `level: 5` over `app`, `database`, `routes`.
  - Source: `phpstan.neon`

## Import Organization

**Frontend (TS/TSX):**
1. Use path alias imports for internal modules: `@/…`
   - Alias config: `tsconfig.json` (`"@/*": ["./resources/js/*"]`)
2. Use type-only imports where applicable.
   - Example: `resources/js/pages/search.tsx` (`import { type BreadcrumbItem } from '@/types';`)
3. Let Prettier organize imports.
   - Source: `.prettierrc` (`prettier-plugin-organize-imports`)

**Backend (PHP):**
- Use explicit `use` imports after the namespace and keep files strict-types.
  - Example: `app/Http/Controllers/DirectDownloadController.php`
- Prefer imported global classes/functions enabled by Pint (e.g., `use InvalidArgumentException;`).
  - Example: `app/Actions/CreateDownloadDir.php`
- Rector enforces import names and removes unused imports.
  - Source: `rector.php` (`->withImportNames(removeUnusedImports: true)`)

## Error Handling

**HTTP-layer “not found/disabled” paths:**
- Use `abort(404)` for feature-disabled/missing resources.
  - Example: `app/Http/Controllers/DirectDownloadController.php`

**Controller flow errors (validation/actions):**
- Use `back()->withErrors([...])` / `to_route(...)->withErrors(...)` for user-facing errors.
  - Example: `app/Http/Controllers/MediaDownloadsController.php` (action errors)
  - Example: `app/Http/Controllers/WatchlistController.php` (missing model)

**Domain invariants:**
- Use `throw_if(...)` with typed exceptions for invalid call contracts.
  - Example: `app/Actions/CreateDownloadOut.php`
  - Example: `app/Actions/CreateDownloadDir.php`

**Optional integrations / best-effort operations:**
- Wrap non-critical operations in `try/catch (Throwable)` and log structured context.
  - Example: `app/Actions/SyncMedia.php` (search index operations)

**Auth request errors:**
- Throw `ValidationException::withMessages(...)` for invalid credentials/rate limiting.
  - Example: `app/Http/Requests/Auth/LoginRequest.php`

## Logging

**Framework:**
- Use `Illuminate\Support\Facades\Log` with structured context arrays.
  - Example (info + context): `app/Actions/CreateSignedDirectLink.php`
  - Example (hit/miss logging): `app/Http/Controllers/DirectDownloadController.php`
  - Example (debug/warn during sync): `app/Actions/SyncMedia.php`

**Guideline:**
- Log event-style messages with stable keys (`token`, `content_type`, `content_id`, `result`, etc.) for queryability.
  - Example: `app/Http/Controllers/DirectDownloadController.php`

## Comments

**PHP docblocks:**
- Use docblocks to express generic types and action “static run” signatures.
  - Example: `app/Actions/BatchCreateSignedDirectLinks.php` (`@method static Collection<int, string> run(...)`)
  - Example: `app/Models/User.php` (`@return HasMany<Watchlist,$this>`)

**TypeScript comments:**
- Use inline comments sparingly for UX/behavior rationale.
  - Example: `resources/js/hooks/use-infinite-scroll.ts` (options documentation)

## Function Design

**PHP:**
- Prefer small, typed methods with clear responsibilities.
- Use `match` for closed-set branching.
  - Example: `app/Actions/CreateDownloadOut.php`

**TS/TSX:**
- Prefer pure helpers for parsing/transforming and keep UI components focused.
  - Example helper: `resources/js/pages/search.tsx` (`parseSearchQuery`)
- Use hooks to encapsulate behavior (`useCallback`, `useMemo`, `useEffect`).
  - Example: `resources/js/components/search-input.tsx`

## Module Design

**PHP “Actions” pattern:**
- Implement business operations as invokable classes under `app/Actions/`.
- Mix in `app/Concerns/AsAction.php` to provide `::make()` and `::run()` entrypoints.
  - Example: `app/Actions/CreateSignedDirectLink.php` (invokable + `@method static` doc)

**Immutability by default:**
- Use `final readonly class` for DTO-like and action classes when possible.
  - Example: `app/Http/Integrations/LionzTv/Responses/VodInformation.php`
  - Example: `app/Actions/SyncMedia.php`

**Frontend exports:**
- Pages use `default export` components resolved by Inertia.
  - Example: `resources/js/pages/search.tsx`
- Shared UI components often use named exports.
  - Example: `resources/js/components/ui/button.tsx` (`export { Button, buttonVariants };`)

**Barrel files:**
- Centralize shared TS types in a barrel.
  - Example: `resources/js/types/index.ts`

---

*Convention analysis: 2026-02-24*
