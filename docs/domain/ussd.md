# USSD

The USSD code lives on branch `feature/ussd-base` and was not merged when this file was written.

## Callback
- Arkesel posts JSON to `/ussd/callback/{USSD_CALLBACK_SECRET}`. Handled by `app/Http/Controllers/Ussd/UssdController.php`.
- Request fields: `sessionID`, `userID`, `newSession` (bool), `msisdn` (233XXXXXXXXX), `userData` (the dial string on a new session, for example `*928*1#`), `network`.
- Reply JSON: `sessionID`, `userID` (echoed back), `msisdn`, `message` (up to 182 characters), `continueSession` (false ends the session).
- The meaning of the "100" response code in Arkesel's docs is not confirmed.
- Never log `userData`: it carries PINs.
- If Arkesel changes the schema or a handset test shows a mismatch, change only the controller.

## Where things are
`app/Services/Ussd/`: `UssdFlow`, `UssdLoanFlow`, `UssdScreens`, `UssdRecorder`, `UssdWaiting`, `UssdText`. Text lives in `resources/ussd.en.json` and `resources/ussd.tw.json` (the Twi is a draft awaiting a native-speaker review). A local simulator exists as the `ussd:simulate` command and as a page.

## Sign-in
A farmer with a verified number or an agent dials in, sets a PIN on the first dial, and uses it afterwards. A wrong PIN counts toward a lock. A farmer who forgot the PIN asks their agent, who sets a temporary PIN that the farmer must change at once.

## Menus (0 is Back on every prompt after the PIN)
- Farmer: 1 Summary, 2 Loans, 3 Log income, 4 Log expenditure, 5 Choose Language, 6 Change PIN, 7 Pending Approvals, 8 Exit.
- Agent: 1 Work on a farmer, 5 Choose Language, 6 Change PIN, 7 Exit.
- Agent working on a farmer: 1 Summary, 2 Loans, 3 Log income, 4 Log expenditure, 5 Set farmer PIN, 6 Loans and repayments.
- Exit is always the last option.
