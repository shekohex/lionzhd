# Adding External API Endpoints

1. Add the route under `routes/api.php` inside the `/api/v1` group.
2. Gate the route with the narrowest Sanctum ability, for example `abilities:read` or `abilities:admin`.
3. Use an API FormRequest for validation on mutating endpoints. Extend `App\Http\Requests\Api\ApiRequest` so validation failures return JSON:API `errors` objects.
4. Reuse existing actions, policies, and DTOs. If web and API behavior must match, extract shared logic into an action instead of copying controller code.
5. Return a `JsonApiResource` or `JsonApiResource` collection with stable `id`, `type`, and `attributes`.
6. Keep secret fields write-only. Use redacted booleans such as `secret_configured` or `password_configured` in responses.
7. Add Pest feature tests for auth, scope denial, validation errors, not-found behavior, JSON:API shape, pagination, and policy rules.
8. Export and inspect OpenAPI with `php artisan scramble:export --path=/tmp/openapi.json --silent`.
9. Run focused verification: `./vendor/bin/pint --test`, `./vendor/bin/phpstan analyse --memory-limit=512M`, relevant `./vendor/bin/pest` tests, and frontend checks if React changed.

Example response shape:

```json
{
  "data": {
    "type": "movies",
    "id": "123",
    "attributes": {
      "name": "Example"
    }
  }
}
```

Example validation error shape:

```json
{
  "errors": [
    {
      "status": "422",
      "title": "Invalid Attribute",
      "detail": "The port field must be between 1 and 65535.",
      "source": { "parameter": "port" }
    }
  ]
}
```
