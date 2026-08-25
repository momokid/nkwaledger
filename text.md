# NkwaLedger — Handoff

## Before writing any code

- Project knowledge is behind the working tree. Ask me to paste any file under `app/Http/Controllers/` or `resources/js/` before rewriting it.
- TDD. Failing test first, every time.
- One file at a time. Explain, wait for approval, then write.
- Never create files. State the full path, give a code block, I copy it in.
- Show the artisan command on its own line above every generated file.
- Give the test run command under every test file.
- Two or more changes to one file means a full rewrite, not snippets.
- Commit as soon as the suite is green.
- Comments short and plain. Never "added", "changed", or "new".
- Explanations brief. No out-of-scope suggestions.
- PowerShell. Paths start from `backend`.
- Never give a destructive command without saying what it destroys. `migrate:fresh` wiped the dev database this session.

## State

Branch `feature/phase-2-foundation`. Suite green, everything committed.

**Done this session**

- Phone normalisation: `Phone::normalise()` stores local format (`0244445566`), accepts any spelling. Model mutator on `User`, `NormalisesPhone` trait on register / login / OTP login / staff invite. `phones:normalise` backfill command.
- Flash messages shared to Inertia (`success`, `error`, `status`) + `FlashMessages` component mounted in both layouts.
- Staff accounts: admin invites agent/vet/adviser/supplier. Null password, `invitation` OTP (1 hour), `/activate` → `/verify-otp` → `/set-password`. List page with status, resend, disable, enable, cancel.
- `OtpOutcomeResolver` — one place decides what a verified code of each type means. Replaced type branching in `OtpController`.
- Null-password guard: unactivated accounts cannot sign in, they go to `/set-password`.
- Security: `resend()` checks the account exists; `/register` throttled 5/hr/IP; login 20 failed/hr/IP (failures only); constant-time login. Registration enumeration message accepted as known risk.
- Audit log: `audit_logs` table, immutable model, `AuditableObserver` on 15 models, `AuditService` for security events, admin page with filters at `/admin/audit`.
- Accounting periods: model with close/reopen, overlap guard, `covering()`. Admin page. `reopen` is a separate permission from `close`.
- Typography lifted across admin, farmer and guest screens. `resources/js/theme/typography.ts` exists but only `Staff/Index.tsx` uses it — everything else is hardcoded.

## Outstanding

**Next up**

1. Default data seeders — roles, permissions, ledger classes/types/controls/categories, farm types, regions. Highest priority: a wiped database should recover in one command.
2. `transaction_templates` — maps each transaction category to a debit/credit account pair. Blocks Phase 4.

**Then Phase 3** — farmers and farms: `farmers`, `farm_units`, `farm_operations`, KYC (farm type + Ghana Card after phone OTP), segmentation. `farmers.view/create/update` permissions already exist and agents hold them.

**Deferred, not blocking**

- Railway has no scheduler, so `verification:expire` never runs in production.
- `auth/check` and `confirm-password` POST are unnamed routes.
- Skeleton placeholders missing on FarmTypeCategories, FarmTypes, Roles, Users, UserDetail.
- Admin sidebar shows every item regardless of permission — clicking gives 403.
- `UserDetail.tsx` has a React key warning.
- Convert remaining pages to typography tokens as they're touched.
- No WAF in front of Railway; no hard SMS spend cap set on Arkesel.
- Audit archiving: table will outgrow all others. Retention window not chosen — needs the Bank of Ghana figure.
- Usage analytics for farmers (separate `activity_logs` table) — agreed, deferred. Feeds a future "frequently used" shortcut.

## Decisions made

- Phone stored local (`0244445566`), normalised at every entry point. Arkesel accepts local, so no conversion at send.
- Invited staff set their own password. Admin never chooses one. Code lasts 1 hour, sent once at creation, reused by `/activate`.
- Activated accounts can be disabled, never deleted. Unactivated invitations can be cancelled.
- Audit log is immutable — no update, no delete, no `updated_at`. Farmer *usage* goes in a separate table later.
- Login: failures counted per account (5/hr) and per IP (20/hr). Success clears only the account counter.
- Periods can be reopened, by a separate permission. Closing and reopening both recorded.
- Password rule is `min:6` everywhere, matching registration. Staff deserve stronger — revisit.
- "Supervisor" means agent. No new role.

## Gotchas hit

- Tests run on in-memory SQLite while dev and production are Postgres. SQLite can't `ALTER TABLE ... ADD CONSTRAINT`, so a raw check constraint passes locally and fails the suite. Enforce those rules in the model, or guard the statement by driver.
- `static::observe()` inside a model's `boot` re-enters the boot cycle. Register observers in `AppServiceProvider`.
- A model default set only in the migration isn't visible on an unsaved instance — use `$attributes`.
- Spatie caches permissions for 24h. `permission:cache-reset` after any permission change, and in every deploy.
- Adding a permission to config doesn't grant it — re-run `PermissionsSeeder`.
- `psysh` evaluates tinker files as code, so no `<?php` tag and use fully-qualified class names.
- PowerShell mangles `\$` in `--execute`. Use a scratch file.
- Multi-line `Pick<>` generics break the Vite oxc parser. Keep on one line.
- Carbon 3 signs `diffInMinutes` — put `now()` on the left.