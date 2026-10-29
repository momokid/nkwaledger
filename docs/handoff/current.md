# Current handoff

Written: 2026-10-29 (after the account menu work). Rewrite this at every stopping point.

## Latest: account menu (branch `feature/account-menu`, cut from the loan branch, not pushed)
Profile, Settings, Help, the quick tour (farmer pictures) and the AI help assistant are built and committed. Task file: [tasks/account-menu-help-and-assistant.md](../tasks/account-menu-help-and-assistant.md). The owner pushes with `git push -u origin feature/account-menu:feature/account-menu-loans` (the remote already has an old `feature/account-menu`; never force push). After deploy: `php artisan migrate` (new tables `app_settings`, `assistant_messages`) and set the OpenRouter key. Next: retake the tour pictures with demo data, the agent tour pictures, other roles' tours (wording first), the USSD topic and practice screen. Cut new branches from the branch that holds the needed work, not `develop` (see memory).

## Where we are (loan work, earlier)
- Branch: `feature/loan-paid-from-loan` (see Active task). Below is about `feature/ai-health-review`, which `develop` was merged into it (conflicts resolved). The owner pushes it. The full PostgreSQL suite passed on 22/10 (3686 passed, 9 skipped; sequential-pg 6 passed). The branch is ready for the owner to push.
- Last code work: slice 5, the admin page for health categories ([task](../tasks/ai-review-slice-5-category-admin.md)). The owner pushes. Run `git log --oneline -5` for the latest commits.

## Active task
[tasks/credit-score-and-loan-use.md](../tasks/credit-score-and-loan-use.md). Task 1 is done (web loan form with farms and amounts; new table `loan_farm_allocations`, new `farm_units.uuid`; run `php artisan migrate`). Tasks 2 ("Paid from a loan", per-farm figures) and 3 (alert, "something else" uses, reminders) are built on `feature/loan-paid-from-loan` (cut from `feature/ai-health-review`; run `php artisan migrate`). Task 4 (agent loans name the farms) and task 4b (the purpose of each farm amount) are built too (new columns on `loan_farm_allocations`, run `php artisan migrate`). Tasks 5a (offline records keep "Paid from a loan") and 5b (the agent's USSD money loan names farms and purposes; Twi lines are drafts for a native-speaker review) are built too. Task 6 (the credit score, version 1) and the loan statement report (`/loans/{loan}/statement`) are built too. Nothing in this task is left except the open items at the end of the task file (tune the weights with real data; lawyer review and consent before any lender sees a score). Ask the owner before any new column or wording.

Earlier work:
Adviser and vet menu items are all built (dashboard, Weather, Trends, Consultations; new column `responded_at`, run `php artisan migrate` after deploy). The full PostgreSQL suite passed on 23/10 (3702 passed, 9 skipped; sequential-pg 6 passed), so the branch is ready for the owner to push. Left: the news feed (source not chosen) and the credit score plan (todo 4c).

## Done: code review of this branch (2026-10-25). Next: browser look, then the owner pushes
The branch `feature/loan-paid-from-loan` (cut from `feature/ai-health-review`) was reviewed before pushing. Finding 1 is fixed (26/10: `Loan::refusalForPurchase` refuses a loan unless the record is an expense; message "Only money spent can be paid from a loan." approved by the owner; full suite passed 3801). All five findings are done (PHP suite 3803 passed; 569 frontend tests passed):
1. ~~A loan can be linked to income or loss records.~~ Fixed. No test yet for a loss record with stock, or for the offline path (both go through `PostingService`).
2. ~~`CreditScoreService::scaled()` rounding~~ Fixed (the lines now add up to the score, the maximums to 100).
3. ~~Other use: no lock~~ Fixed (the amount left is checked under a lock on the loan row).
4. ~~Record form kept the loan after a save~~ Fixed (`loan` is in the reset list).
5. ~~Smaller items~~ Done (26/10): 5c and 5d fixed (`Loan::NOT_AVAILABLE_REFUSAL`; farmer looked up once in `StoreFarmerLoanRequest`). 5a (dashboard recompute) and 5b (no-farm record on a loan farm) left as they are, on purpose. 5e: the owner approved the statement wording "{what} – {farm}" and "{record name}: GHS {amount}".
The full local PostgreSQL suite passed on 26/10 (3808 passed, 9 skipped; sequential-pg 6 passed), so nothing blocks the push.
Also not done: a push, and a look at the new screens in a browser (run `npm run build` first, `public/build` is ignored by git). Local dev database already ran the four new migrations of this branch by mistake (additive only; `php artisan migrate:rollback` once undoes that batch).

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
