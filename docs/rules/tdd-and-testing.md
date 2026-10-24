# TDD and testing

- Write the failing test first, confirm red, implement, confirm green.
- Generate tests with `php artisan make:test FileName --pest`. Use `make:` commands for any file type that has a generator.
- While working, run the affected tests in parallel. Run the full parallel suite once at the end: `php artisan test --parallel`.
- The full suite must pass before any commit.
- For frontend changes also run the TypeScript check and the production build.
- PostgreSQL: run the tests for the area touched with every task. Run the full local PostgreSQL suite once per batch of tasks and before pushing (see decisions/2026-10-17-postgres-once-per-batch.md). Say plainly when it is still pending.
- Local PostgreSQL needs a server and an empty database named `*_test`. The suite refuses any other name or a non-local host. Set `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5432 DB_DATABASE=nkwaledger_test DB_USERNAME=... DB_PASSWORD=...`, then `php artisan test --parallel --exclude-group=sequential-pg`.
- Concurrency test (PostgreSQL only): same variables, then `php artisan test --group=sequential-pg`. It skips itself on SQLite.
- `php artisan migrate` is needed when adding columns. Tests use fresh migrations each run, which hides missing columns.
- Tests must never call a real outside service (Arkesel, OpenRouter, MoMo). Fake the HTTP call or the disk.
