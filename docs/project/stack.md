# Stack

- Backend: Laravel 12 in `backend/`, Pest for tests, Spatie Permission.
- Frontend: Inertia.js + React + TypeScript, Vite (oxc parser), Tailwind CSS (zero border-radius), Inter font, Tabler Icons React.
- Database: PostgreSQL on Railway (production and staging). SQLite for local tests; CI runs PostgreSQL.
- Hosting: Railway (production and staging), Docker (php-fpm + nginx + supervisor).
- CI: GitHub Actions, one job named `test` (the check a pull request waits for).
- SMS and OTP: Arkesel (sandbox flag for staging).
- AI review: Qwen vision model through OpenRouter (see domain/health-ai-review.md).
- Photo storage: `PHOTO_DISK` (see ops/env-and-seeders.md).
- Git model: `feature/* -> develop (staging) -> master (production)` through reviewed pull requests.
