# Credit score and loan use

Status: plan only. Nothing built. Waiting on the owner's answers below.

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
