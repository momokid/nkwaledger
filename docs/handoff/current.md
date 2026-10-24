# Current handoff

Written: 2026-10-21 (after slice 5). Rewrite this at every stopping point.

## Where we are
- Branch now: `feature/loan-paid-from-loan` (see Active task). Below is about `feature/ai-health-review`, which `develop` was merged into it (conflicts resolved). The owner pushes it. The full PostgreSQL suite passed on 22/10 (3686 passed, 9 skipped; sequential-pg 6 passed). The branch is ready for the owner to push.
- Last code work: slice 5, the admin page for health categories ([task](../tasks/ai-review-slice-5-category-admin.md)). The owner pushes. Run `git log --oneline -5` for the latest commits.

## Active task
[tasks/credit-score-and-loan-use.md](../tasks/credit-score-and-loan-use.md). Task 1 is done (web loan form with farms and amounts; new table `loan_farm_allocations`, new `farm_units.uuid`; run `php artisan migrate`). Tasks 2 ("Paid from a loan", per-farm figures) and 3 (alert, "something else" uses, reminders) are built on `feature/loan-paid-from-loan` (cut from `feature/ai-health-review`; run `php artisan migrate`). Next is task 4: agent loans name the farms (see "What the later tasks will need" in the task file). Ask the owner before any new column or wording.

Earlier work:
Adviser and vet menu items are all built (dashboard, Weather, Trends, Consultations; new column `responded_at`, run `php artisan migrate` after deploy). The full PostgreSQL suite passed on 23/10 (3702 passed, 9 skipped; sequential-pg 6 passed), so the branch is ready for the owner to push. Left: the news feed (source not chosen) and the credit score plan (todo 4c).

## Waiting on the owner
- Railway had a build pause on 21/10; hold the staging checks until it clears.
- Confirm a queue worker runs on Railway; the AI job needs one.
- [tasks/verify-bucket-on-staging.md](../tasks/verify-bucket-on-staging.md): set `PHOTO_DISK=s3` on staging, submit one test report, check the photo loads.
- The EPA pesticide list as a CSV.
- Approval of any new wording (photo-quality message, officer screen labels).
- Whether to add a fallback model to the AI adapter.
- Whether the docs commits should stay on this branch or move to their own.

## Other branches (not this session)
- `feature/ussd-base`: USSD built, not pushed. See [domain/ussd/context.md](../domain/ussd/context.md) and the USSD section of [todo.md](../todo.md).

## Read first in a new session
[how-it-works.md](how-it-works.md), then the active task file above.
