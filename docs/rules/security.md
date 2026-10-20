# Security

- No numeric database id may reach a page prop, response, link or notification. Use public uuids.
- Phone enumeration prevention: always redirect to `/verify-otp` whether or not the phone exists.
- Never log passwords, OTPs, PINs, phone numbers, or USSD `userData`.
- Reports carry a signed verification code (hash of report figures plus `REPORT_SECRET`).
- Always check permissions with `$this->access->can()`, never Laravel's native `$user->can()`.
- Money, security and user data are the places to slow down and be explicit.
- Photos sent to the AI carry no name, phone or location (see domain/health-ai-review/context.md).
