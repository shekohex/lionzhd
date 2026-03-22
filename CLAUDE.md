# CLAUDE

## Testing and Previews

- For QA sessions, keep long-running services in background PTYs instead of blocking shell commands.
- Start the app stack with `composer dev` and report the PTY id plus the app URL `http://127.0.0.1:8000` and Vite URL `http://127.0.0.1:5173`.
- Search QA requires Meilisearch running on `http://127.0.0.1:7700` with the local app config.
- Download QA requires `aria2c` RPC running on port `6800` with the local app config.
- When reproducing QA issues, inspect the active PTY output first, then check `storage/logs/laravel.log` and `storage/logs/browser.log` if needed.
- If search works but returns stale or empty results, sync the search backend before deeper debugging: `php artisan scout:sync-index-settings`, then import the relevant searchable models.
