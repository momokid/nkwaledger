# Current handoff

Written: 2026-10-21 (after slice 5). Rewrite this at every stopping point.

## Where we are
- Branch now: `feature/loan-paid-from-loan` (see Active task). Below is about `feature/ai-health-review`, which `develop` was merged into it (conflicts resolved). The owner pushes it. The full PostgreSQL suite passed on 22/10 (3686 passed, 9 skipped; sequential-pg 6 passed). The branch is ready for the owner to push.
- Last code work: slice 5, the admin page for health categories ([task](../tasks/ai-review-slice-5-category-admin.md)). The owner pushes. Run `git log --oneline -5` for the latest commits.

## Active task
[tasks/credit-score-and-loan-use.md](../tasks/credit-score-and-loan-use.md). Task 1 is done (web loan form with farms and amounts; new table `loan_farm_allocations`, new `farm_units.uuid`; run `php artisan migrate`). Tasks 2 ("Paid from a loan", per-farm figures) and 3 (alert, "something else" uses, reminders) are built on `feature/loan-paid-from-loan` (cut from `feature/ai-health-review`; run `php artisan migrate`). Task 4 (agent loans name the farms) and task 4b (the purpose of each farm amount) are built too (new columns on `loan_farm_allocations`, run `php artisan migrate`). Tasks 5a (offline records keep "Paid from a loan") and 5b (the agent's USSD money loan names farms and purposes; Twi lines are drafts for a native-speaker review) are built too. Task 6 (the credit score, version 1) and the loan statement report (`/loans/{loan}/statement`) are built too. Nothing in this task is left except the open items at the end of the task file (tune the weights with real data; lawyer review and consent before any lender sees a score). Ask the owner before any new column or wording.

Earlier work:
Adviser and vet menu items are all built (dashboard, Weather, Trends, Consultations; new column `responded_at`, run `php artisan migrate` after deploy). The full PostgreSQL suite passed on 23/10 (3702 passed, 9 skipped; sequential-pg 6 passed), so the branch is ready for the owner to push. Left: the news feed (source not chosen) and the credit score plan (todo 4c).

## Open: code review of this branch (2026-10-25)
The branch `feature/loan-paid-from-loan` (cut from `feature/ai-health-review`) was reviewed before pushing. Finding 1 is fixed (26/10: `Loan::refusalForPurchase` refuses a loan unless the record is an expense; message "Only money spent can be paid from a loan." approved by the owner; full suite passed 3801). Findings 2 and 3 are fixed too (full suite 3803 passed). Findings 4 and 5 are not fixed yet:
1. ~~A loan can be linked to income or loss records.~~ Fixed. No test yet for a loss record with stock, or for the offline path (both go through `PostingService`).
2. ~~`CreditScoreService::scaled()` rounding~~ Fixed (the lines now add up to the score, the maximums to 100).
3. ~~Other use: no lock~~ Fixed (the amount left is checked under a lock on the loan row).
4. `Transactions/Create.tsx` `resetEnteredFields` does not reset `loan`, so the next record stays linked.
5. Smaller: score and loan alerts recomputed on every dashboard load; a no-farm record paid from a loan gets a farm and can become provisional; `SyncSubmissionService::loanIdOf` calls `Loan::refusalForPurchase(null, 0, null, false)` for a message; `StoreFarmerLoanRequest::farmer()` queried three times; the statement adds "{what} - {farm}" and per-kind total lines that were not in the approved wording (owner to decide).
Also not done: the PostgreSQL batch run, a push, and a look at the new screens in a browser (run `npm run build` first, `public/build` is ignored by git). Local dev database already ran the four new migrations of this branch by mistake (additive only; `php artisan migrate:rollback` once undoes that batch).

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
