# Accounting

## Model
- The farmer sees single-entry. Storage is double-entry underneath: `transaction_templates` map each transaction type to a debit and credit account pair.
- Money is stored in pesewas as integers through the `Money` support class.
- Transactions, journal entries and journal lines are immutable: no `updated_at`, no soft delete. A correction is a new transaction dated today that points at the original.
- `PostingService` is the only door into the books. It wraps everything in a database transaction and ends with `assertBalanced()`.
- Closing an accounting period is permanent. Corrections after a close land in today's open period.

## Ledger structure (locked)
`ledger_classes` (Dr/Cr) -> `ledger_categories` -> `ledger_subcategories` -> `ledger_accounts` (name, account_code nullable, control_id, subcategory_id, type_id, is_system, is_active). The Dr/Cr class is worked out live through subcategory -> category -> class and is never stored.

## Money shown to farmers
- The farmer's typed cedi amount is the only money figure anywhere.
- Loss and sale quantities carry no computed cost or valuation.
- Cancelled money in and out is sent by the server but only "My records" shows it (open: Reports, CSV and print; see todo.md).

## Periods
Periods can be reopened, with a separate permission from closing. Closing and reopening are both recorded.
