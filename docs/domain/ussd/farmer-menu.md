# USSD farmer menu

1 Summary · 2 Loans · 3 Log income · 4 Log expenditure · 5 Choose Language · 6 Change PIN · 7 Pending Approvals · 8 Exit

- Summary: the farmer picks a period (last 30 days, this month, this year) and reads a short summary.
- Loans: read-only list of the farmer loans.
- Log income and Log expenditure: pick a type, then an amount, then how it was paid (cash, MoMo or bank), then confirm. Posting goes through `PostingService` like every other record (see [../accounting.md](../accounting.md)).
- A farm sale also asks for the farm unit and the quantity, and can be paid by cash, MoMo, bank or credit. It follows the stock rules in [../stock-and-movements.md](../stock-and-movements.md).
- Choose Language: see [language.md](language.md). Change PIN: see [sign-in-and-pin.md](sign-in-and-pin.md). Pending Approvals: see [pending-approvals.md](pending-approvals.md).
- Exit is the last option. 0 is Back on every prompt after the PIN.
