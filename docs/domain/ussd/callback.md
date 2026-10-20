# USSD callback

- Arkesel posts JSON to `/ussd/callback/{USSD_CALLBACK_SECRET}`. Handled only by `app/Http/Controllers/Ussd/UssdController.php`.
- Request fields: `sessionID`, `userID`, `newSession` (bool), `msisdn` (233XXXXXXXXX), `userData` (the dial string on a new session, for example `*928*1#`, otherwise the user reply), `network`.
- Reply JSON: `sessionID`, `userID` (echoed back), `msisdn`, `message` (up to 182 characters), `continueSession` (false ends the session).
- The meaning of the "100" response code in Arkesel's docs is not confirmed.
- Never log `userData`: it carries PINs.
- If Arkesel changes the schema or a handset test shows a mismatch, change only the controller.
- Setting the secret and the callback URL in the Arkesel dashboard is an owner task (see [../../todo.md](../../todo.md)).
