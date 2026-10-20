# USSD sign-in and PIN

- Who gets in: an active farmer with a farmer profile, or an agent, with a verified phone number. Any other number gets a not-registered message. Phone handling is in [../identity-and-permissions.md](../identity-and-permissions.md).
- First dial: the user sets a 4-digit PIN and confirms it. A PIN may not be all the same digit or a run of digits.
- Later dials: the user enters the PIN. A wrong PIN counts toward a lock (30 minutes); the right PIN clears the count.
- Change PIN: the current PIN is checked first (a wrong one counts toward the lock). The new PIN must pass the same rules and must not be the one it replaces.
- Forgot the PIN: the farmer enters a lone 0 at the PIN prompt and a request goes to their agent. The agent sets a temporary PIN (see [agent-menu.md](agent-menu.md)).
- After a temporary PIN the farmer must choose a new PIN before reaching the menu. The temporary PIN cannot be reused.
- Never log PINs or `userData` (see [callback.md](callback.md)).
