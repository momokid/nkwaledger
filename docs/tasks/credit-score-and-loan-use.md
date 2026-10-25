# Credit score and loan use

Status: tasks 1 (web loan form), 2 (paid from a loan), 3 (alert, other uses, reminders) 4 (agent loans) and 4b (purpose of each farm amount) built. 5a (offline "Paid from a loan") and 5b (USSD) built. Task 6 (the score) built, version 1.

## Goal
1. Every loan is recorded against a farm, with what the money was used for, so the app can see if the loan went into the farm.
2. A credit score built from the farmer's real records.

## What the owner said (2026-10-23)
- Score inputs, in this order: (1) the farmer's purchases, (2) his cash movements, (3) his credit: buying on credit, taking loans, and whether he pays early, on time or late.
- When a farmer takes a loan he must record it against a farm. The loan is expected to go into farm inputs or farming activity. The score looks at the amount taken and whether it is paid on time.
- The use of the loan is recorded with the same kinds of record the farmer already has under My Records ("I bought seed", "I bought animals", "I bought feed", and so on): what it was used for, on which farm, and how many when it is stock or inputs.
- Crop or livestock decides what is added to the farm produce (stock). A loan used to buy animals or seed can be the way the first farm produce is recorded, so a farmer can take a loan to start a farm.

## What the code already has
- Templates already support a farm unit (`requires_farm_unit`), buying on credit (`allows_credit`, `is_liability`) and stock purchases (`is_stock_purchase`, `stock_source`). A purchase makes a stock batch with source Purchase (see [domain/stock-and-movements.md](../domain/stock-and-movements.md)).
- A loan has no farm today (`loans` has no farm unit column). Loan kinds are `money` and `existing`.
- Repayments are separate transactions, so paid-early, on-time and late can be worked out from the repayment date and the due date.
- `FarmerEligibilityService::canBeCreditScored` already gates the score. The agent report shows "Not yet available" in place of a score.

## Proposed design (needs the owner's yes)
Slice A, data capture, first:
- A loan must name a farm unit.
- Spending is recorded afterwards with the existing "I bought ..." records, with a new "paid from this loan" choice that links the purchase to the loan. The purchase then adds stock to the farm as it does today.
- Loan page shows: amount taken, amount accounted for, amount not yet accounted for.
Slice B, the score: worked out when shown, from purchases, cash movements, credit and repayment timing, and loan use. Needs history, so it comes after A has collected data.

## Open questions for the owner
1. Loan form: one farm per loan, or can one loan be split over several farms?
2. Spend now or later: should the farmer list the uses when he takes the loan (the app posts the purchases at once), or link purchases to the loan afterwards? Recommended: afterwards, because money received and money spent are separate facts.
3. Loans already in the system and "existing" loans (no cash received now): must they name a farm, or only new money loans?
4. Loans recorded by an agent, by USSD or offline: do they ask for the farm at the same step? Needs the same rule everywhere.
5. Score weights and bands: to settle after A, using the first real data.
6. Wording for the farm choice, the "paid from this loan" choice and the "not yet accounted for" line: to propose after the answers above.

## Rules for this task
- Plan first. No column, route or wording without the owner's approval.
- Never decide a loan by the score alone. Lawyer review and consent text before any lender sees a score.
- Separate issue to report, not fix here: the farmer dashboard shows a made-up "Credit score (Soon)" of 720 (`Dashboard.tsx`).

## Log
- 2026-10-23: plan written from the owner's message.
- 2026-10-23: owner decisions. (1) One loan can be spread over several farms. (2) Spending is linked afterwards, but when the loan is taken the farmer says which farms it will be used on. (3) Existing loans in the system are practice data: ignore them (they may be linked to one of the farmer's farms, but no backfill and no rule for them). (4) An agent, USSD and offline loans follow the same rule everywhere.
- Still open: does the farmer give an amount per farm at loan time, and must the amounts add up to the loan? Is it enough for each farm to be named, with amounts left to the later purchases? Wording for the farm choice, "paid from this loan" and "not yet accounted for".
- 2026-10-23: more owner decisions. The farm amounts on a new money loan must add up exactly to the loan amount. While any amount is not yet accounted for, the farmer sees an alert (banner on the loan page and dashboard, plus a notification in the bell). Reminder after 7 days, then every 14 days until cleared. A purchase above the planned amount for a farm is allowed but flagged. A choice "I used some of it for something else" lets money be marked as not spent on the farm. The proposed wording is approved (see the table below). USSD comes after the web form, with its own proposal.
- Order of work: (1) loan form on web: farms and amounts that add up; (2) "Paid from a loan" on purchase records and the per-farm figures on the loan page; (3) the alert and reminders; (4) agent loans; (5) USSD and offline; (6) the score.

- 2026-10-24: task 2 design approved on branch `feature/loan-paid-from-loan` (cut from `feature/ai-health-review`). New nullable indexed column `transactions.loan_id`. A purchase sets it via "Paid from a loan". Checks: loan is the farmer's own, `money`, not cancelled; purchase has a farm unit that is one of the loan's allocated farms; not a credit purchase. Per-farm planned / accounted / not yet accounted are worked out when shown (accounted = non-cancelled purchases linked to the loan and farm); a purchase above plan is allowed and shown as over. "I used some of it for something else" moves to task 3 (needs its own storage). Waiting on the owner for wording: loan picker label (suggested "{lender} – GHS {amount}, {DD/MM/YYYY}"), the check error messages, the over-plan flag. No code written yet.

## Approved wording
| Where | Wording |
|---|---|
| Loan form, farms section heading | Which farms will use this loan? |
| Amount field for each farm | Amount for this farm |
| Running line while filling in | Left to place: GHS {amount} |
| Error if the amounts differ | The farm amounts must add up to the loan amount. |
| Choice on a purchase record | Paid from a loan |
| Loan page, per farm | {Farm}: planned GHS {a}, accounted GHS {b}, not yet accounted GHS {c} |
| Banner on the loan page and dashboard | You have not yet recorded how GHS {amount} of your loan was used. |
| Button on the banner | Record how I used it |
| Choice for money not spent on the farm | I used some of it for something else |
| Notification reminder | Please record how you used your loan. |

## Task 1 done (web loan form, 2026-10-23)
Owner approved: new table `loan_farm_allocations` (uuid, loan_id, farm_unit_id, amount_minor; one row per farm per loan) and a `uuid` column on `farm_units` (backfilled). Branch: stayed on `feature/ai-health-review`.
- Only the farmer's own web form (`StoreFarmerLoanRequest`, used by `LoanController`) asks for farms, and only for `money` loans. The farmer ticks farms, gives "Amount for this farm" for each; the amounts must add up to the loan. `LoanService::record` re-checks (own farms, no repeats, above zero, exact sum) when `farms` is sent.
- `existing` loans, old loans, and the agent, USSD and offline paths are unchanged. No backfill.
- Wording used: only the four approved lines. The farm names are the farmer's own data (checkbox labels).

### What the later tasks will need
- Agent loans (task 4): `AgentLoanController` uses the plain `StoreLoanRequest`. The farms must be stored on the pending entry (its payload) and passed to `LoanService::record` as `farms` on acceptance (`PendingEntryService::inputFrom`). The agent picks from the farmer's farm units; the same checks apply.
- USSD (task 5): `UssdLoanFlow` needs steps to pick farms and amounts, then the same `farms` array. Needs its own wording proposal. Farm units now have a uuid, but USSD screens use a menu number.
- Offline (task 5): the offline queue payload needs `farms` and the sync endpoint must use the farmer request rules.
- Task 2 reads `loan_farm_allocations` for planned vs accounted per farm.

## Task 2 done (paid from a loan, 2026-10-24)
Branch `feature/loan-paid-from-loan`. New column `transactions.loan_id` (nullable, restrict on delete); run `php artisan migrate`.
- Request field `loan` (the loan uuid) on `RecordTransactionRequest`; `PostingRequest.loanId`; the rule is `Loan::refusalForPurchase`, used by the request and again by `PostingService`.
- Loan page (`Loans/Show`) gets `farms` from `Loan::farmFigures()`: planned, accounted (live purchases only), not yet accounted, over. Never stored.
- Purchase form (`Transactions/Create`): checkbox "Paid from a loan", then a picker of the farmer's active money loans, shown for EXPENSE records that take money.
- Wording used: only the approved lines (see the 2026-10-24 log and table above); the over-plan line is "{Farm}: GHS {amount} more than planned".
- Not done, on purpose: "I used some of it for something else" (task 3); the offline queue and the sync path do not carry `loan` yet (task 5), so an offline purchase saves without the loan link; the server does not limit `loan` to EXPENSE templates, only the form shows it for them.
- Tests: `tests/Feature/Loans/LoanPaidFromTest.php`.

## Task 2 fix (2026-10-24)
A record that asks for no farm (for example labour) could not be paid from a loan: the farm was dropped and the loan check failed. Now, when "Paid from a loan" is ticked, the form always asks "Which farm did this happen on" and the farm is kept on the record. Without a loan such a record still keeps no farm.

## Task 3 done (alert, other uses, reminders, 2026-10-24)
Owner decisions: one "something else" row per use, each with an amount and a typed note (no fixed list); the 7-day clock starts the day the loan was recorded; alerts and reminders stop when the loan is repaid or cancelled. New tables `loan_other_uses` (uuid, loan_id, amount_minor, note, recorded_by, created_at; never edited) and `loan_reminders` (loan_id, created_at). Run `php artisan migrate`.
- Amount not yet accounted for = `Loan::unaccountedMinor()`: each farm's not-yet-accounted amount (floored at 0, so over-spending one farm does not hide money left on another) less the "something else" rows; 0 for a loan that is repaid, cancelled, not a money loan or without farm amounts. Never stored.
- Loan page: banner, the saved uses as "{note}: GHS {amount}", the choice "I used some of it for something else" opening a form (amount, note, Save). Dashboard: one banner per loan (`loan_alerts`). Endpoint `POST /loans/{loan}/other-uses` (the loan's own farmer only; others get 404).
- Reminder: command `loans:remind-use`, daily 04:15. First after 7 days, then every 14, only while something is unaccounted. Bell text "Please record how you used your loan.", links to the loan page.
- Wording added (approved 2026-10-24): "What did you use it for?", "Save", "Please say what you used it for.", "That is more than what is left to place.", "{note}: GHS {amount}". "How much, in cedis?" and "The amount needs to be more than zero." were reused.
- The banner button goes to the record form (`/my-records/create`) on both pages.
- Not done: agent, USSD and offline loans still have no farms, so they raise no alert (tasks 4 and 5).
- Tests: `tests/Feature/Loans/LoanUseTest.php`.

## Task 4 done (agent loans name the farms, 2026-10-24)
Owner approved the plan with four defaults: farms required on an agent's money loan; entries waiting before this change are accepted with no farm amounts; the farmer sees the split on the loan page after accepting (not on the waiting card); the agent's own loan page is unchanged. No new column, table, route or wording.
- The agent form (`AgentLoanController::store`) uses `StoreFarmerLoanRequest`, which now takes the farmer from the address (agent) or the signed-in user (farmer) and checks the farms against that farmer's units. The money form gets `farms` (uuid, name).
- `PendingEntryService::recordLoan` keeps the farms in the entry payload as `[{farm_unit: uuid, amount_minor}]`; `inputFrom` gives them back as `farms` on acceptance (app or SMS code), and `LoanService::record` re-checks them and writes `loan_farm_allocations`.
- Tests: `tests/Feature/Loans/LoanAgentFarmsTest.php`; `LoanEntryHttpTest` agent posts now carry farms; the old "agent form is not changed" test was removed.
- Still open: USSD and offline (task 5).

## Task 4b done (what each farm amount is for, 2026-10-24)
Owner approved: two new nullable columns on `loan_farm_allocations`, `purpose_template_id` (the chosen record type) and `purpose_note` (typed, for "something else"); one purpose per farm, required; the record names shown as they are (past tense). Run `php artisan migrate`.
- The choices are the expense records on the farmer's own record form (`TransactionTemplate::loanPurposesFor`), sent by slug, plus "other" with a note. A note typed beside a chosen record type is not kept. Older rows have neither column set.
- Required at the request layer on both the farmer's form and the agent's form (`StoreFarmerLoanRequest`); `LoanService::record` checks a purpose when one is sent and accepts none (older entries, USSD, offline until task 5).
- The agent's pending entry keeps `purpose` and `purpose_note` next to each farm in its payload; they are written when the farmer accepts.
- Loan page: the farm lines carry `purpose` (the record name or the note) and show "{Farm}: {purpose}".
- Wording used (approved 2026-10-24): "What will this money be used for?", "Something else", "What will you use it for?", "Please say what this money is for.", "Please say what you will use it for.", "{Farm}: {purpose}".
- Not done: matching later purchases to the planned purpose (the loan page does not compare them yet); USSD and offline (task 5).
- Tests: `tests/Feature/Loans/LoanPurposeTest.php`; the older farm tests now send a purpose.

## Task 5 split (2026-10-24)
Offline loans do not exist: the offline queue carries records and health reports only. USSD loans are recorded by the agent only (the farmer's USSD loans menu is read-only). So task 5 became 5a (offline) and 5b (USSD).

### Task 5a done (offline records keep "Paid from a loan")
No new column, table or wording. A record saved offline carries `loan` (the loan uuid) in its queued payload (`buildBatchRecord`, `QueuedBatchRecord`); `SyncBatchRequest` accepts `records.*.loan`; `SyncSubmissionService::postingRequest` turns it into `loanId`, and `PostingService` runs the same `Loan::refusalForPurchase` check as the web form. A refused record goes to "needs fixing" with the same approved text (loan not the farmer's, unknown, wrong farm, credit). Tests: `tests/Feature/Loans/OfflineLoanLinkTest.php`, `resources/js/lib/batchRecord.test.ts`.

### Task 5b done (2026-10-24): the agent's USSD money loan names farms and purposes
Owner defaults (2026-10-24): several farms in a loop, the last farm takes the remainder automatically; the purposes (the farmer's expense records) shown 5 at a time with "More", plus "Something else" with a typed note; Twi lines added as a marked draft like the existing Twi text. A farmer with no farm unit gets an end screen. The farms and purposes are kept in the pending entry payload, as for the web agent form (`PendingEntryService`). USSD text lives in two files (English and Twi): see [domain/ussd/text-and-simulator.md](../domain/ussd/text-and-simulator.md). Next step: propose the exact English and Twi lines for the owner to approve, then build with failing tests first. No new column or table expected.

Built as agreed (owner approved the English lines, the Twi drafts and the 20-character cut on 2026-10-24). In `UssdLoanFlow`, after the due-date step of a new money loan: pick a farm (the farmer's units, 5 to a page with "More") -> amount for that farm with "Left to place" (skipped when only one farm is left: it takes the rest) -> pick what it is for (the farmer's expense records, 5 to a page with "More"; "Something else" closes the last page and asks for a typed note) -> if money is left, "Another farm" or "Put the rest on this farm". A farmer with no farm unit ends at "This farmer has no farm yet, so a loan cannot be recorded." when the agent picks New loan. Names are cut at 20 characters. The farms and purposes go into `PendingEntryService::recordLoan` (payload), and are written on acceptance. Existing loans (menu 2) are unchanged. Back steps through the new screens; the new steps are in `UssdScreens::BACK_STEPS`.
- Wording: 10 new keys in `resources/ussd.en.json` and `resources/ussd.tw.json`: `loan_farm_pick`, `loan_farm_amount`, `loan_farm_too_much`, `loan_purpose_pick`, `more`, `loan_purpose_other`, `loan_purpose_note`, `loan_purpose_note_empty`, `loan_farm_more`, `loan_no_farm`. **The Twi for these ten is a draft and needs a native-speaker review**, like the rest of the Twi text.
- Tests: `tests/Feature/Ussd/UssdLoanFarmsTest.php`; `UssdLoanEntryTest` now walks the extra farm and purpose screens.
- Still open: task 6 (the score). Not done: comparing later purchases with the planned purpose.

## Task 6 done (the credit score, version 1, 2026-10-25)
Owner decisions (2026-10-24 and 25): the farmer, the agent and the admin see it (no lender); 0 to 100 with four bands; the last 12 calendar months with records in at least 3 of them; weights 30/30/30/10; no grace days on repayments; the made-up 720 replaced; a pillar with nothing to measure is left out and the others scale up to 100. The wording was approved as proposed, plus the two farmer lines.
- `App\Services\CreditScoreService` (settings in `config/credit_score.php`, all provisional): worked out whenever shown, nothing stored, no new column or table. `for($farmer)` gives status (`ok`, `not_enough`, `unavailable`), score, band and the pillars; `display($farmer, 'farmer'|'staff')` gives the words shown (`text`, `lines`, `note`); `bandFor`.
- Only records that count: live (not cancelled, not a correction row), not provisional, income / expense / loss; a produce sale does not count until `countsTowardCredit()`. Gate: `FarmerEligibilityService::canBeCreditScored` (identity verified).
- Pillars: **Farm purchases** (months with an expense record out of 12); **Money in and out** (months with both an income and an expense); **Credit and loans** (loan payments in the window made on or before the due date out of all payments, times 0.5 while a loan is overdue and 0 in default; counted only when there was a payment or an overdue loan); **Loan money used on the farm** (farm purchases linked to loans a week old or more, as a share of the farm amounts planned; counted only when such a loan exists).
- Shown on: the agent's farmer profile report and its print page (`AgentReportsController`), and the farmer dashboard (`FarmerDashboardController`, `Dashboard.tsx`). There is no separate admin screen for a farmer's score yet; the admin sees it where the agent report is open to them.
- Wording used (approved): bands Building / Fair / Good / Strong; "{score} out of 100 ({band})"; the four pillar lines and "not counted yet"; the two "Not enough records yet" lines; the two "Not yet available" lines; "This score is a guide only. It does not decide a loan."
- Tests: `tests/Feature/Loans/CreditScoreTest.php`.
- Not in version 1: the loan amount against income (the owner mentioned "the amount taken"; no approved line for it); credit purchases settled late; a saved snapshot of a score (needed before a lender can see one); lawyer review and consent text; tuning the weights and bands with real data.

## Loan statement report (2026-10-25)
Owner request: a separate report for one loan with the loan amount, its interest, the repayment dates and details, and what the money was used for (seed, animals, labour...). Plan and wording approved on 2026-10-25. No new column or table.
- Routes `GET /loans/{loan}/statement`, `/statement/print` and `/statement/pdf` (`LoanStatementController`, `LoanPolicy::statement`: the farmer who owns the loan, that farmer's current agent, and an admin; anyone else gets 404; a lender gets nothing). Data from `LoanStatementService`, read from the books each time.
- Sections: The loan (lender, amount received, date received, paid into, total interest agreed, due date, status, loan balance); Repayments (the same columns as the loan page, cancelled ones struck through); What the money was used for (for each farm its purpose and the planned / accounted / not yet accounted line, every live purchase marked "Paid from a loan" with its date, record name and farm, totals for each kind of record, the "something else" notes, and the three totals); footer "Prepared on {DD/MM/YYYY}" and the self-reported notice.
- A "Loan statement" link on the farmer's, the agent's and the admin's loan pages. Screen page `Pages/Loans/Statement.tsx`; print and PDF share `resources/views/loans/statement-print.blade.php`.
- Wording: only the approved lines; the farm name is added to the record name in the purchases table as "{what} – {farm}" (same separator as the loan picker), and the per-kind totals read "{record name}: GHS {amount}" (same pattern as the approved "{note}: GHS {amount}").
- Only purchases marked "Paid from a loan" appear. Spending recorded on the farmer's USSD record screens has no loan link, so it is not in the statement.
- Tests: `tests/Feature/Loans/LoanStatementTest.php`, `resources/js/Pages/Loans/Statement.test.tsx`.
- Found while adding it: the JS (vitest) tests of the loan pages and the loan form had not been run since tasks 2, 3 and 5b, and 13 of them failed because the pages gained props and the agent form now asks for farms. The test fixtures were updated, and tests were added for the purpose choice on the loan form.

- 2026-10-26: review finding 1 fixed. A loan is refused on any record that is not an expense (`Loan::refusalForPurchase`, one wording approved by the owner: "Only money spent can be paid from a loan."). Findings 2 to 5 are listed in docs/handoff/current.md.
- 2026-10-26: review finding 2 fixed. `CreditScoreService::scaled()` rounds the pillar lines together so they add up to the score and the maximums to 100.
- 2026-10-26: review finding 3 fixed. `LoanOtherUseController` checks the amount left inside a transaction with a lock on the loan row. A real double tap is not covered by a test (tests run one request at a time).
