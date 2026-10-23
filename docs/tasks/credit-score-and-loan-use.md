# Credit score and loan use

Status: task 1 (web loan form) built. Tasks 2 to 6 not started.

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
