# USSD agent menu

Agent menu: 1 Work on a farmer · 5 Choose Language · 6 Change PIN · 7 Exit

## Work on a farmer
- The agent enters a farmer phone number. Only the agent own farmers are found; any other number, or none, gets the same answer, so numbers cannot be probed.
- Menu while working on a farmer: 1 Summary · 2 Loans · 3 Log income · 4 Log expenditure · 5 Set farmer PIN · 6 Loans and repayments.
- Summary, Loans and logging work as in [farmer-menu.md](farmer-menu.md), for that farmer.

## Set farmer PIN
The agent confirms the farmer, and a temporary PIN is shown once, to that agent only. The farmer must change it at the next dial (see [sign-in-and-pin.md](sign-in-and-pin.md)).

## Loans and repayments
The agent records a loan or a repayment for the farmer. A new money loan also names the farms it will be used on, an amount for each and what each is for (the farmer's expense records, or a typed note); a farmer with no farm cannot be given one. No code is involved. It is saved as a pending entry, the farmer is sent an SMS, and the farmer accepts or declines it (see [pending-approvals.md](pending-approvals.md)). The loan rules are in [../loans.md](../loans.md).
