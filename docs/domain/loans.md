# Loans

Facts below come from the owner's notes and the commit history. Check the code before relying on a detail.

## What exists
- Loans and repayments are recorded for a farmer. An agent can record one for a farmer; it waits as a pending entry until the farmer accepts or declines it (web Approvals page and USSD "Pending Approvals"). Entries are handled by `PendingEntryService`.
- The agent menu has a Credit group holding Loans, and a New Loan page with a search over the agent's own farmers.
- A loan is shown as Overdue the day after its due date and as Default 90 days past due. Both are worked out when shown, not stored.
- The repayment statement is sorted by date and shows the loan balance after each repayment.
- Every loan form and detail page has a back link. List pages are menu pages and have none.
- Lenders come from a Bank of Ghana institutions list loaded from a CSV (327 rows).
- Loan dates are shown as DD/MM/YYYY.

## Credit score
A guide score out of 100, shown to the farmer, the agent and the admin. Never decides a loan; no lender sees it. Worked out when shown by `CreditScoreService` from live, confirmed records over the last 12 months; settings in `config/credit_score.php`. See [../tasks/credit-score-and-loan-use.md](../tasks/credit-score-and-loan-use.md) (task 6) for the pillars, the exclusions and what is not in version 1.

## Loan statement
A separate report for one loan (screen, print and PDF) with the loan details, the repayments and what the money was used for. Opened from the loan page by the farmer, the farmer's agent and the admin. See the end of [../tasks/credit-score-and-loan-use.md](../tasks/credit-score-and-loan-use.md).

## Decided but not built
- Goods loans, and repayment in produce or animals, are parked. Recommended approach (Option A): the debt stays in cedis, the goods are kept as a description with an agreed cedi value, and stock effects come later. Open questions: cedi price or bags-for-bags, who lends goods, whether repayment reduces farm stock.

## Still to build
Task 3.9 (lender request button, admin queue, two-step loan form), penalties, corrections (LN-19), offline and agent recording. See todo.md.

## Owner-side checks with no record
Spot-check of the 327 institution names against the Bank of Ghana register, and lawyer review of lender names, default status and penalties.
