# USSD folder

One file per USSD feature. The code lives on branch `feature/ussd-base` and was not merged when this folder was written. Code is in `app/Services/Ussd/` and `app/Http/Controllers/Ussd/UssdController.php`.

| File | Read it when |
|---|---|
| callback.md | Touching the Arkesel request and reply, the secret URL, or what must never be logged |
| sign-in-and-pin.md | Touching first-dial PIN, wrong-PIN lock, change PIN, or the temporary PIN |
| farmer-menu.md | Touching what a farmer can do: summary, loans, log income or expenditure, sales |
| agent-menu.md | Touching what an agent can do for a farmer, including setting a PIN and recording loans |
| pending-approvals.md | Touching how a farmer accepts or declines what an agent recorded |
| language.md | Touching English and Twi |
| text-and-simulator.md | Touching the wording files or the local simulator |

Menu rule for every USSD menu: Exit is always the last option, and 0 is Back on every prompt after the PIN.
Open items for USSD (deploy, handset test, Twi review) are in [../../todo.md](../../todo.md).
