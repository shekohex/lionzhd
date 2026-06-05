# External API Authentication

Users create API tokens from `/settings/tokens`. The plaintext token is shown once and is not persisted beyond Sanctum's hashed token storage.

Send API requests with:

```http
Authorization: Bearer <token>
Accept: application/vnd.api+json
Content-Type: application/json
```

Scopes:

- `read`: Catalog, discovery, search, profile, watchlists, and token metadata.
- `server-download`: Create downloads.
- `download-operations`: Pause, resume, remove, or cancel downloads.
- `monitoring:admin`: Manage automatic series monitoring and schedules.
- `admin`: Administrative settings and cache operations.
- `super-admin`: Super-admin user role changes.

Token creation uses `TokenAbilityRegistry` to prevent escalation. API-created tokens cannot receive abilities outside the current token or account permissions. Web-created tokens are limited by account permissions.

The API rate limit is 120 requests per minute per authenticated user/token. Rate-limit responses use JSON:API error objects with status `429`.

Security rules:

- Never log or display plaintext tokens after creation.
- Never return `aria2.secret` or `xtreamcodes.password` in settings API responses.
- Super-admin demotion and last-admin removal protections are centralized in `UpdateUserSettings`.
