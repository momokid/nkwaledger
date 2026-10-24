# USSD language

- Menu item 5 (Choose Language) offers 1 English and 2 Twi. The choice is saved on the user (`ussd_language`) and used from then on, including the next dial.
- After choosing, the user returns to the menu.
- Twi text is a draft; the ten lines added for the agent's USSD loan farms and purposes (`loan_farm_pick`, `loan_farm_amount`, `loan_farm_too_much`, `loan_purpose_pick`, `more`, `loan_purpose_other`, `loan_purpose_note`, `loan_purpose_note_empty`, `loan_farm_more`, `loan_no_farm`) are drafts too.
- Twi text is a draft. A native-speaker review is still to do. Some Twi lines still use the old wording for Pending Approvals (see [../../todo.md](../../todo.md)).
- Wording lives in two files: see [text-and-simulator.md](text-and-simulator.md). Never invent wording; the owner approves it.
