# USSD Pending Approvals

- Menu item 7 on the farmer menu. Handled by `UssdWaiting`; entries come from `PendingEntryService` (see [../loans.md](../loans.md)).
- It lists the loans and repayments an agent recorded for this farmer that are still pending. With none, it says so and ends.
- Opening one shows the kind, lender, amount and date (DD/MM/YYYY) and a notice that it does not count until the farmer accepts. The farmer answers 1 to accept or 2 to decline. 0 goes back one step at a time.
- Accepting creates the loan or repayment, marks it as accepted by USSD, and tells the agent. Declining creates nothing and tells the agent.
- Another farmer pending entries are never shown. A reply that is not on the list shows the list again.
- The farmer is sent an SMS when an agent records an entry. The wording uses the name Pending Approvals.
- The feature has no on/off switch any more; it is always on.
