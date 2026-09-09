# NkwaLedger — Project Context for Claude

## Purpose

NkwaLedger is an agricultural fintech platform for Ghanaian smallholder farmers that converts farm activity into bankable financial records. It serves farmers, agents, admins, vets, and advisers across web (primary), USSD, and mobile (Phase 9) channels.

## Stack

- **Backend:** Laravel 12 (`backend/`), Pest for TDD, Spatie Permission
- **Frontend:** Inertia.js + React/TypeScript, Vite (oxc parser)
- **Database:** PostgreSQL on Railway (production/staging), SQLite for tests
- **Deployment:** Railway (production + staging), GitHub Actions CI, Docker (php-fpm + nginx + supervisor)
- **SMS/OTP:** Arkesel (sandbox flag for staging)
- **Mobile (Phase 9, not started):** React Native, planned at `mobile/`, offline-first

## Phase Roadmap

- Phase 5 — Dashboard and reports *(current)*
- Phase 6 — Credit and loans
- Phase 7 — USSD
- Phase 8 — Marketplace and suppliers
- Phase 9 — Mobile app (React Native, offline-first)
- Phase 10 — AI modules (VetAI, CropAI)
- Phase 11 — Weather and messaging
- Phase 12 — Payments and MoMo collections

**Future scope (architecture only, rules decided later):** autonomous in-app AI agent; cosmetic reward/credit system (points live outside the double-entry ledger, domain events + reward listener pattern, earning trigger is logging transactions).

---

## Current State

Active branch: `feature/phase-5-dashboard-reports`

### Completed in Phase 5 so far

- **My Farm page** (`/my-farm`): farmer-scoped route, expandable unit cards, per-unit financial analysis with date range filter.
- **Cumulative stock timeline**: every batch's movements merged into one flat, chronological list per farm unit with a running total. Labels: **Starts with** (very first entry ever) / **Added** (later "already had it") / **Bought** (purchase) / **Lost** / **Sold** / **Birth** / **Stolen** / **Died** / **Culled** / **Corrected**. Rejected entries show but don't count toward the running total. The old batch-grouped view is kept as a collapsible "Show batch details" section for reconciliation, visible to the farmer too.
- **Loss recording**: `quantity_lost` on `transactions`, `MovementReason::Loss`, split **proportionally** across all active stock batches by their share of the current count (not FIFO — no assumption is made about which batch actually lost the animal). The last batch in the split absorbs any rounding remainder so the total always adds up exactly. No cost-per-batch or valuation figure is ever computed or shown — the farmer's typed cedi amount is the only money figure anywhere.
- **Produce-sold tracking**: `is_produce_sale` on `transaction_templates`, `quantity_sold` on `transactions`. Same proportional-split logic as loss (shared via `PostingService::splitProportionally()`). Seeder marks `produce_sale`, `animal_sale`, `produce_of_animal_sale`, `fish_sale` as produce sales; `produce_sale` now **requires a farm unit** (previously didn't — this was the root cause of "Produce Sold" always showing 0 for crop farms). **Not yet re-seeded on Railway staging/production.**
- Correction narration replaced with a purple "Corrected" tag (not green — green means income/positive elsewhere in the UI). Only done on the farmer's own records page (`Transactions/Index.tsx`); not yet added to the agent/admin `Reports/Index.tsx`.
- Suite: 1568 tests passing, 9 skipped.

### Immediate next target

Farmer Dashboard — income/expense KPIs, net profit, livestock/crop summary. Not started — no route, controller, or test exists yet.

### Open/deferred items

- Capacity warning (`currentStockTotal()` / `isOverCapacity()` on `FarmUnit`) — never implemented
- Unit-of-measure placeholder variety (birds/acres/kg)
- "Waiting on somebody else" → "Pending for approval" text sweep
- Staff email requirement + email-first OTP (needs its own planning pass)
- Fish/aquatic loss model (deaths are silent, discovered only at harvest — doesn't fit the day-by-day movement model)
- Whether to add the "Corrected" tag to `Reports/Index.tsx` too

---

## Key Learnings & Principles

### Accounting model

- Farmer-facing experience is single-entry; storage is double-entry underneath via `transaction_templates` mapping to debit/credit account pairs.
- Money stored in pesewas as integers via `Money` support class.
- All transactions, journal entries, and journal lines are **immutable** — no `updated_at`, no soft delete; corrections are new transactions dated today pointing at the original.
- Loss and sale quantities do not carry a computed cost — no valuation is derived from which batch a loss/sale was allocated to. Only the farmer's typed amount is money.
- `PostingService` is the only door into the books; wraps everything in a DB transaction with `assertBalanced()` as the final gate.
- Closing a period is permanent; corrections after close land in today's open period.

### Ledger structure (locked)

`ledger_classes` (Dr/Cr) → `ledger_categories` → `ledger_subcategories` → `ledger_accounts` (name, account_code nullable, control_id, subcategory_id, type_id, is_system, is_active). Dr/Cr class derived live via subcategory → category → class, never stored.

### Identity and routing

- `farmer_profiles.uuid`; UUIDs in all URLs, row IDs never reach the browser.
- Admin routes: two groups both prefixed `admin`/named `admin.` — one for dashboard + permissions (role-gated), one for every other module (each action wrapped in `access:{module}.{action}`).

### Permissions

- `PermissionsSeeder` syncs permissions; `RolesAndPermissionsSeeder` must seed first in tests.
- Always use `$this->access->can()`, never Laravel's native `$user->can()`.

### Stock and movements

- `FarmUnitStock` (a "batch") has `opening_quantity`, `current_quantity`, `started_on`, `expected_ready_on`, `source` (OpeningBalance/Purchase).
- Every batch auto-creates one `Opening` movement at creation time, confirmed automatically.
- `FarmUnitStockMovement.is_increase` drives the direction; recalculated on save via `MovementReason::addsToCount()` except for `Correction`, which carries its own direction.
- A rejected movement/stock stops counting toward `current_quantity` (and the farmer-facing timeline running total) but stays visible, marked Rejected.
- Losses and sales are **proportionally split** across all active batches on a unit — never assumed to come from one specific batch (oldest, largest, etc.), since there's no way to know which animal/unit of produce was actually affected.

### Security

- Phone enumeration prevention: always redirect to `/verify-otp` regardless of whether phone exists.
- Reports carry a signed verification code (hash of report figures + `REPORT_SECRET`).

---

## Approach & Patterns

### Workflow (strict, non-negotiable)

1. Write failing test first → confirm red → implement → confirm green → commit.
2. Use `php artisan make:` commands for any file type with a generator; show the exact command on its own line before every code block.
3. **Never generate or create files directly.** State the full file path first, write code in a code block, let the developer copy it into VS Code.
4. When editing an existing file: show old snippet + new snippet with file path. **If an edit touches more than two separate places in the same file, rewrite the entire file instead.**
5. Every file path as a copy-pasteable `code` command on its own line — no "File:" label, no leading `backend/`.
6. Before asking for any file to be pasted, search project knowledge (GitHub snapshot) and past conversations first — **the snapshot lags behind local, uncommitted edits**, so verify before rewriting from it. Rewriting from a stale copy has silently deleted real logic more than once.
7. Always include the exact test command alongside any test file.
8. Never add user-facing text, wording, or labels without explicit approval — confirm the exact copy and colors before writing the code that uses them.
9. Railway-safe idempotent seeder commands (`--force`, plus `permission:cache-reset` for permission seeders) when touching production/staging.
10. Never give destructive commands (e.g. `migrate:fresh`) without stating what they destroy; prefer `migrate:rollback`.
11. When context-window pressure risks hallucination, proactively prompt for a new chat with a handoff summary.
12. **Minimize inline comments in code** — only comment genuinely non-obvious logic, keep it brief.

### Code design principles (every service, controller, and model)

Dependency injection · Pure functions · Separation of concerns · Loose coupling

### UX writing standard

Warm and encouraging, never blunt or accusatory. Exact in fact — never soften a failure message so much it reads as success.

### Explanations

Short and plain, simple enough for an 8-year-old. Answer, then stop.

### Testing patterns

- `php artisan make:test FileName --pest` always.
- Full suite (`php artisan test`) must pass before any git commit — not just the filtered subset touched by the change.

### Frontend patterns

- Every admin CRUD list page shows skeleton placeholders on initial load, full refetch, and inline row actions.
- Every form field shows its own validation error directly beneath it.
- `useTheme()` inside `ThemeContext.Provider` — outer `Index` wrapper + inner `IndexContent` pattern on every page.
- `Pick<>` generics on a single line in `.tsx` files (Vite oxc parser rejects multi-line generics).

### Git model

`feature/* → develop (staging) → master (production)` via reviewed PRs. Commit when the full suite is green.

---

## Tools & Resources

- **Backend:** Laravel 12, Pest, Spatie Permission, Inertia.js, `AccessControlService` + `CheckPermission` middleware
- **Frontend:** React/TypeScript, Vite (oxc parser), Tailwind CSS (zero border-radius), Inter font, Tabler Icons React
- **Brand colors:** `#1D9E75` (green, income/positive) / `#0F6E56` / `#B45309` (amber, expense/reduction) / `#B91C1C` (red, loss) / `#7C3AED` (purple, correction)
- **Database:** PostgreSQL (production/staging), SQLite (tests); `php artisan migrate` required when adding columns (tests use fresh migrations each run, masking missing columns)
- **Known gotcha:** Stale `public/hot` Vite file causes intermittent full-suite test failures on Windows.
- **Known gotcha:** `php artisan db:table` fails without the `intl` PHP extension; use `Schema::getColumnListing('table_name')` in tinker instead.
- **Known gotcha:** a Laravel `cast` without a matching `$fillable` entry silently drops the value on `create()` — check both together when adding a column.
