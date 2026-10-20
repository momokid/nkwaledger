# Environment variables and seeders

## Variables worth knowing
- `PHOTO_DISK`: where photos and voice notes go (`local`, `public` or `s3`). Production needs `s3`.
- `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_ENDPOINT`, `AWS_USE_PATH_STYLE_ENDPOINT`: the Railway Bucket credentials.
- `OPENROUTER_API_KEY`, `OPENROUTER_MODEL`: the AI review. Without a key the review quietly does nothing.
- `ARKESEL_API_KEY`, `ARKESEL_SENDER_ID`: SMS.
- `USSD_CALLBACK_SECRET`: part of the USSD callback URL.
- `REPORT_SECRET`: signs report verification codes.
- `IDENTITY_PEPPER`: used in identity hashing.
- Never paste a secret value into chat. Names are enough.

## Seeders on staging and production
- Commands must be idempotent and use `--force`. After permission seeders also run `php artisan permission:cache-reset`.
- `HealthCategorySeeder` must be run once after the AI-review branch deploys: `php artisan db:seed --class=HealthCategorySeeder --force`. It skips a farm category that already has a list.
- The transaction template seeder change that makes `produce_sale` require a farm unit has not been re-seeded on staging or production yet.
- A cast without a matching `$fillable` entry silently drops the value on `create()`. Check both together when adding a column.
