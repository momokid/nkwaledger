# Gotchas

- A stale `public/hot` Vite file causes intermittent full-suite failures on Windows.
- `php artisan db:table` fails without the `intl` PHP extension. Use `Schema::getColumnListing('table_name')` in tinker.
- Tests run on SQLite while dev and production are PostgreSQL. SQLite cannot `ALTER TABLE ... ADD CONSTRAINT`, so a raw check constraint passes locally and fails the suite. Enforce such rules in the model or guard the statement by driver.
- `DatabaseSeeder` runs with model events off, so a `creating` hook (for example one that sets a uuid) does not fire from it. Set such values in the seeder.
- `static::observe()` inside a model `boot` re-enters the boot cycle. Register observers in `AppServiceProvider`.
- A model default set only in the migration is not visible on an unsaved instance. Use `$attributes`.
- `psysh` evaluates tinker files as code: no `<?php` tag, and use fully-qualified class names.
- PowerShell mangles `\$` in `--execute`. Use a scratch file.
- Carbon 3 signs `diffInMinutes`: put `now()` on the left.
- Multi-line `Pick<>` generics break the Vite oxc parser.
- Tests that submit disease reports write to the configured photo disk. Fake that disk, or they write real files into `storage/app/private`.
