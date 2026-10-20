# AI review, slice 3: the queued job

Status: active, not started. Branch: `feature/ai-health-review`.

## Goal
After a disease report has its photo stored, a background job asks the AI for a draft review and saves it on the report. The officer can use it later (slice 4). The farmer never sees it.

## Read first
- [domain/health-ai-review.md](../domain/health-ai-review.md): the design and what is built
- [decisions/2026-10-20-ai-review-design.md](../decisions/2026-10-20-ai-review-design.md): what the owner decided
- [rules/tdd-and-testing.md](../rules/tdd-and-testing.md) and [rules/git-and-commits.md](../rules/git-and-commits.md)
- [ops/gotchas.md](../ops/gotchas.md): fake the photo disk in tests; DatabaseSeeder runs with model events off

## Already in place
- `HealthImageReviewer::review(bytes, mime, description, farmCategory, categories)` returns a `HealthReview` or null, never throws.
- `health_categories` table with `HealthCategory::activeFor($farmTypeCategory)`.
- Photo stored on the disk named in `disease_reports.media_disk`. Web path: `Farm/DiseaseReportController::createReport`. Offline path: `ReportReleaseService::release`.
- The queue default is `database` (`config/queue.php`). Check that a worker runs on Railway before relying on it.

## Steps (failing test first for each)
1. Migration for AI columns on `disease_reports`. Proposed: `ai_status` (null, `ready`, `low_confidence`, `unusable`, `failed`), `health_category_id` (nullable foreign key to `health_categories`, restrict on delete), `ai_confidence` (decimal, nullable), `ai_urgent` (boolean, default false), `ai_note` (text, nullable), `ai_model` (string, nullable), `ai_reviewed_at` (timestamp, nullable). Confirm the names with the owner before migrating if anything differs. The officer verdict columns belong to slice 4, where they are first used.
2. A queued job, unique per report, that loads the report (ignoring the waiting-for-photo scope), reads the photo from `media_disk`, asks the reviewer, and saves the result. No review when no key is set (leave `ai_status` null).
3. The 70% rule as a config value (default 0.70). At or above: `ready`. Below: `low_confidence`, and the draft category is not exposed to the officer. Urgent is saved whatever the confidence. Unusable photo: `unusable`. Reviewer returned null: `failed`.
4. Dispatch the job after the report is saved, in both paths (web form and offline release), after the database commit.
5. The job must never block or fail a report. A failure only sets `failed`.

## Tests to write
- Ready, low-confidence, unusable and failed outcomes saved correctly (fake the reviewer's HTTP and the photo disk).
- The job runs for a web report and for an offline report once its photo arrives.
- The farmer-facing pages never contain AI fields.
- No review and no HTTP call when the key is missing.

## Done when
Full suite green, committed, `docs/todo.md` ticked, this file's log updated, `docs/handoff/current.md` rewritten.

## Log
- 2026-10-20: task written. Nothing built yet.
