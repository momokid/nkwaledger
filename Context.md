# NkwaLedger - Project Context & Status

**Last Updated:** [Date]  
**Project Status:** Not Started  
**Current Phase:** Phase 1 - Auth

---

## Project Overview

NkwaLedger is a digital financial ledger for Ghanaian peasant farmers. It tracks income/expenses, livestock health, provides credit scoring, and integrates with WhatsApp and MTN MoMo.

**Target Users:** 100k+ small-holder farmers  
**Platforms:** Web (Inertia.js + React), Mobile (React Native), WhatsApp Bot, USSD  
**MVP Focus:** Web app + API foundation only  

---

## Architecture

```
Frontend: Inertia.js + React (SPA with SSR)
Backend: Laravel 11 (monolithic, serves web + REST API)
Database: PostgreSQL (Railway)
Cache/Queue: Redis (Railway)
Mobile: React Native (Phase 9)
Hosting: Railway (production)
CI/CD: GitHub Actions (automated to staging, manual to production)
Branching: Git Flow (feature branches, PR before merge)
Testing: Pest (TDD - tests before implementation)
```

---

## Authentication Flow

### Traditional Login
```
Username (phone/email) + Password
    ↓
System validates credentials
    ↓
Send OTP (SMS via Arkesel if phone, Email if email)
    ↓
User enters OTP (max 3 attempts, expires 5 min)
    ↓
Session created (Sanctum JWT)
    ↓
Redirect by role (Admin/Agent/Farmer dashboard)
```

### OAuth Login
```
Sign in with Google OR Facebook
    ↓
Phone number required (email optional)
    ↓
Complete profile
    ↓
Redirect to dashboard by role
```

---

## Integrations

| Integration | Provider | Purpose | Phase |
|---|---|---|---|
| SMS OTP | Arkesel | Send OTP codes | Phase 1 |
| OAuth | Google + Facebook | Third-party login | Phase 1 |
| WhatsApp | Meta Cloud API | Bot for income/expense logging | Phase 7 |
| Payments | MTN MoMo Collections API | User-initiated payment | Phase 8 |
| Weather | OpenWeatherMap (Phase 2+) | Weather alerts | Future |

---

## Security Requirements (Non-Negotiable)

- [x] Password hashing (bcrypt)
- [x] OTP validation (time-based, rate-limited)
- [x] RBAC (Admin, Agent, Farmer roles)
- [x] CSRF protection (Sanctum tokens)
- [x] Rate limiting (login, OTP attempts)
- [x] SQL injection prevention (Eloquent ORM)
- [x] HTTPS everywhere (TLS 1.3)
- [x] Audit logs (who did what, when)
- [x] Never log sensitive data (passwords, OTPs, phone numbers)
- [x] Soft deletes (data recovery trail)
- [x] Input validation (phone, email, amounts)

---

## Development Workflow

1. Write tests FIRST (TDD)
2. One file at a time
3. Explain what file does → State path → Ask "Ready?"
4. Only write code AFTER approval
5. No multi-line comments (/* */ or ---)
6. Clean, readable code
7. Each feature on separate Git branch
8. PR review before merge to main
9. GitHub Actions runs tests on every push
10. Manual approval for production deploy

### How Code Is Written
Never generate or create files directly. Always:
- State the full file path first
- Write the code in a code block
- Let the developer copy it into VS Code themselves

Example format:
```
**File:** `app/Services/OtpService.php`

(code block here)
```

---

## Code Design Principles

These four principles apply to every file written in this project — services, controllers, models, and tests. They are not optional.

### 1. Dependency Injection
Never create a dependency inside a class. Pass it in from outside.

**What this means in plain terms:**
If a controller needs to send an OTP, it should not create the SMS sender itself. The SMS sender should be handed to it. This way, in tests, we hand it a fake SMS sender instead — and the test runs without touching a real phone network.

```php
// Wrong — the controller creates its own dependency
class OtpController {
    public function send() {
        $sms = new ArkeselService(); // hard to test, hard to swap
    }
}

// Right — the dependency is injected
class OtpController {
    public function __construct(private OtpService $otp) {}
}
```

### 2. Pure Functions
A function should do one thing, return a predictable result, and not secretly change anything else.

**What this means in plain terms:**
If you call a function with the same input twice, you should get the same output both times. It should not write to a database, send an SMS, or change a global variable as a side effect. Pure functions are trivial to test — you just check what came out matches what you expect.

```php
// Pure — always same output for same input, no side effects
public function normalizePhone(string $phone): string {
    return '+233' . ltrim(preg_replace('/\s+/', '', $phone), '0');
}
```

### 3. Separation of Concerns
Each layer of the application has one job and does not bleed into another layer's job.

| Layer | Its only job |
|---|---|
| Controller | Receive the HTTP request, call a service, return a response |
| Service | Business logic — the rules of what the app does |
| Model | Represent data and relationships in the database |
| Request | Validate and sanitize incoming data |

**What this means in plain terms:**
A controller should never contain a database query. A model should never send an SMS. If you find yourself writing business logic inside a controller, it belongs in a service instead.

### 4. Loose Coupling
Classes should depend on contracts (interfaces), not on specific implementations.

**What this means in plain terms:**
`OtpService` should not care whether SMS is sent by Arkesel, AfricasTalking, or a log file. It should talk to an `SmsProvider` interface, and whatever is behind that interface is swappable without touching `OtpService`. This is how we mock Arkesel in tests — we bind a fake `SmsProvider` that just records what was "sent".

```php
// The service depends on a contract, not a specific provider
interface SmsProvider {
    public function send(string $phone, string $message): void;
}

class OtpService {
    public function __construct(private SmsProvider $sms) {}
}

// In tests — bind a fake
class FakeSmsProvider implements SmsProvider {
    public array $sent = [];
    public function send(string $phone, string $message): void {
        $this->sent[] = compact('phone', 'message');
    }
}
```

---

## Tech Stack

| Tool | Version | Purpose |
|---|---|---|
| PHP | 8.5.2 | Backend runtime |
| Laravel | 11 | Framework |
| Composer | 2.9.3 | PHP package manager |
| Node.js | 20.20.0 | Frontend build |
| npm | 10.8.2 | JavaScript package manager |
| React | Latest | UI library |
| Inertia.js | Latest | Server-side rendering |
| Tailwind CSS | Latest | Styling |
| PostgreSQL | Latest | Database |
| Redis | Latest | Cache/sessions |
| Pest | Latest | Testing framework |

---

# MVP PHASES & FEATURES

## Phase 1 - Authentication & Setup ⏳
**Status:** Not Started  
**Branch:** `feature/phase-1-auth`  
**Estimated Duration:** 1 week  

### Features
- [x] User registration (phone/email)
- [x] Traditional login (username + password + OTP)
- [x] OAuth login (Google + Facebook)
- [x] OTP validation (Arkesel SMS/Email)
- [x] Role-based dashboard redirect
- [x] Session management (Sanctum JWT)
- [x] Logout functionality
- [x] Password reset flow
- [x] Profile completion (phone required for OAuth)

### Todos for Phase 1

#### Database
- [ ] Create migrations: users, password_resets, otp_codes, oauth_tokens
- [ ] Add columns: phone, role (enum: admin, agent, farmer), is_verified
- [ ] Create indexes on phone, email, role
- [ ] Write migration tests

#### Models
- [ ] User model with role helpers (isAdmin, isAgent, isFarmer)
- [ ] OtpCode model (stores phone, code, expires_at)
- [ ] PasswordReset model
- [ ] OAuthToken model (Google/Facebook data)
- [ ] Write model tests

#### Services
- [ ] OtpService (generate, validate, cleanup expired)
- [ ] ArkeselService (send SMS via Arkesel API)
- [ ] AuthService (handle login logic, JWT tokens)
- [ ] OAuthService (Google + Facebook integration)
- [ ] Write service tests + integration tests

#### Controllers
- [ ] AuthController (login, register, logout)
- [ ] OtpController (request OTP, verify OTP)
- [ ] OAuthController (Google callback, Facebook callback)
- [ ] ProfileController (complete OAuth profile)
- [ ] Write controller tests

#### Middleware
- [ ] RoleMiddleware (check user role)
- [ ] OtpVerifiedMiddleware (ensure OTP was verified in session)
- [ ] Write middleware tests

#### React Components (Inertia)
- [ ] Login page (username + password + OTP form)
- [ ] OTP verification page
- [ ] OAuth buttons (Google + Facebook)
- [ ] Profile completion form (OAuth users)
- [ ] Authenticated layout shell (sidebar + topbar)
- [ ] Role-based dashboard redirects
- [ ] Write component tests

#### Routes
- [ ] Guest routes: /login, /register, /forgot-password, /reset-password
- [ ] Auth routes: /logout, /profile
- [ ] OAuth routes: /auth/google, /auth/google/callback, /auth/facebook, /auth/facebook/callback
- [ ] API routes: /api/auth/login, /api/auth/otp/request, /api/auth/otp/verify
- [ ] Write route tests

#### Security
- [ ] Rate limiting on login (5 attempts per 15 min)
- [ ] Rate limiting on OTP request (3 per 10 min)
- [ ] Rate limiting on OTP verify (3 attempts per code)
- [ ] CSRF tokens on all forms
- [ ] Password validation (min 8 char, 1 uppercase, 1 number, 1 special)
- [ ] Write security tests (SQLi, XSS, CSRF)

#### Configuration
- [ ] Arkesel API credentials (.env)
- [ ] Google OAuth credentials (.env)
- [ ] Facebook OAuth credentials (.env)
- [ ] OTP expiration time (5 minutes)
- [ ] Rate limit thresholds
- [ ] Sanctum token expiration (7 days)

#### Tests (Pest)
- [ ] Unit: OtpService, AuthService, User model
- [ ] Integration: OTP flow, login flow, OAuth flow
- [ ] Feature: Login page, OTP page, OAuth redirect
- [ ] Security: CSRF, SQLi, XSS, rate limiting
- [ ] API: /api/auth/login, /api/auth/otp/verify

#### CI/CD
- [ ] GitHub Actions workflow (.github/workflows/test.yml)
- [ ] Run tests on every push
- [ ] Run linting (ESLint, PHPStan)
- [ ] Deploy to staging if tests pass

#### Documentation
- [ ] README.md with setup instructions
- [ ] API documentation (auth endpoints)
- [ ] Environment variables guide (.env.example)

---

## Phase 2 - Farmers Module ⏳
**Status:** Not Started  
**Branch:** `feature/phase-2-farmers`  

### Features
- [ ] Create farmer profile (phone required, linked to user)
- [ ] View all farmers (admin/agent)
- [ ] Edit farmer details
- [ ] Assign farmer to agent
- [ ] View farmer ledger history
- [ ] Export farmer data (CSV)
- [ ] Delete farmer (soft delete with audit)

### Todos for Phase 2
- [ ] Create Farmer migration (user_id FK, agent_id FK, farm_type, community, region, farm_size_acres, livestock_count)
- [ ] Create Farmer model with relationships
- [ ] FarmerService (create, update, assign to agent)
- [ ] FarmerController (index, show, store, update, destroy)
- [ ] FarmerRepository (query builder)
- [ ] Farmers list page (web)
- [ ] Farmer detail page (edit form)
- [ ] Export farmer CSV
- [ ] Policy (can admin/agent view farmer?)
- [ ] Tests (unit, integration, feature)

---

## Phase 3 - Transactions (Ledger) ⏳
**Status:** Not Started  
**Branch:** `feature/phase-3-transactions`  

### Features
- [ ] Add income transaction (crop sales, livestock, eggs, off-farm)
- [ ] Add expense transaction (seeds, feed, labour, transport, vet fees)
- [ ] Auto-categorization by item name
- [ ] View transactions (searchable, filterable, paginated)
- [ ] View 30-day income/expense summary
- [ ] Edit transaction
- [ ] Delete transaction (soft delete)
- [ ] Export transactions (CSV)

### Todos for Phase 3
- [ ] Create Transaction migration (user_id, category, type, amount, description, date)
- [ ] Create Transaction model
- [ ] TransactionService (CRUD, categorization)
- [ ] TransactionController (index, store, update, destroy)
- [ ] Transactions list page (web)
- [ ] Add income form (web)
- [ ] Add expense form (web)
- [ ] Dashboard stats (30-day income/expense)
- [ ] Export transactions CSV
- [ ] Tests (unit, integration, feature)

---

## Phase 4 - Dashboard & KPIs ⏳
**Status:** Not Started  
**Branch:** `feature/phase-4-dashboard`  

### Features
- [ ] Total income (30 days)
- [ ] Total expenses (30 days)
- [ ] Net profit
- [ ] Last 5 transactions
- [ ] Number of animals (livestock count)
- [ ] Crop status summary
- [ ] Charts (income vs expense, trend)
- [ ] Alerts (low balance, upcoming payments)

### Todos for Phase 4
- [ ] DashboardService (calculate KPIs, fetch data)
- [ ] DashboardController (fetch stats by role)
- [ ] Dashboard page (React component)
- [ ] KPI cards (income, expense, profit, animals)
- [ ] Charts (Chart.js or Recharts)
- [ ] Tests

---

## Phase 5 - Livestock Health (VetAI) ⏳
**Status:** Not Started  
**Branch:** `feature/phase-5-vetai`  

### Features
- [ ] Farmer selects symptoms from dropdown
- [ ] System returns rule-based AI suggestion (JSON file, no ML)
- [ ] Display vet advice (possible disease, action items)
- [ ] Log vet consultation request
- [ ] Manual vet fee logging
- [ ] Mortality event logging

### Todos for Phase 5
- [ ] Create vet_rules.json (symptom → disease → advice)
- [ ] Create HealthLog migration (user_id, animal_type, symptoms, ai_suggestion, vet_fee)
- [ ] Create HealthLog model
- [ ] VetAIService (parse symptoms, return rule-based suggestion)
- [ ] HealthLogController (store, show)
- [ ] Health logs list page (web)
- [ ] Add symptoms form (web)
- [ ] Display AI suggestion
- [ ] Tests

---

## Phase 6 - Credit Summary & PDF Export ⏳
**Status:** Not Started  
**Branch:** `feature/phase-6-credit`  

### Features
- [ ] Auto-calculate credit score (formula-based, no ML)
- [ ] Average monthly income
- [ ] Average monthly expense
- [ ] Debt-to-income ratio
- [ ] Repayment score (0-100)
- [ ] Export credit summary as PDF
- [ ] Admin/agent can add risk notes

### Todos for Phase 6
- [ ] Create CreditScore migration (farmer_id, avg_income, avg_expense, debt_ratio, score, risk_notes)
- [ ] Create CreditScore model
- [ ] CreditScoringService (calculate score from transactions)
- [ ] CreditController (show, update notes)
- [ ] Credit page (web)
- [ ] PDF export (DomPDF)
- [ ] Tests

---

## Phase 7 - WhatsApp Bot ⏳
**Status:** Not Started  
**Branch:** `feature/phase-7-whatsapp`  

### Features
- [ ] WhatsApp webhook endpoint (receive messages)
- [ ] Parse farmer message (income/expense/balance/vet/credit)
- [ ] Send response back to WhatsApp
- [ ] Log income via WhatsApp
- [ ] Log expense via WhatsApp
- [ ] Check balance via WhatsApp
- [ ] Get livestock symptoms advice via WhatsApp
- [ ] Request credit summary via WhatsApp

### Todos for Phase 7
- [ ] Meta WhatsApp Cloud API setup (business account, phone number)
- [ ] WhatsAppController (webhook endpoint, message parsing)
- [ ] WhatsAppService (format responses, send messages)
- [ ] Message parser (NLP-like intent detection)
- [ ] Reuse TransactionService, VetAIService, CreditScoringService
- [ ] Tests (webhook, message parsing)

---

## Phase 8 - MTN MoMo Payments ⏳
**Status:** Not Started  
**Branch:** `feature/phase-8-momo`  

### Features
- [ ] Farmer enters MoMo number + amount
- [ ] Send "Request to Pay" to MTN API
- [ ] Farmer approves via USSD prompt (enters PIN)
- [ ] Receive webhook callback from MTN (success/failed)
- [ ] Auto-record confirmed transaction
- [ ] Transaction status tracking
- [ ] Payment history view

### Todos for Phase 8
- [ ] MTN MoMo API setup (sandbox credentials from momodeveloper.mtn.com)
- [ ] Create Payment migration (user_id, phone, amount, momo_ref, status, expires_at)
- [ ] Create Payment model
- [ ] MoMoService (initiate payment, handle callbacks)
- [ ] PaymentController (initiate, webhook callback)
- [ ] MoMo form (web)
- [ ] Payment status page
- [ ] Webhook security (signature verification)
- [ ] Tests

---

## Phase 9 - React Native Mobile App ⏳
**Status:** Not Started  
**Branch:** `feature/phase-9-mobile`  

### Features
- [ ] Login (username + password + OTP, OAuth)
- [ ] Offline-first data sync
- [ ] Add income/expense (queued if offline)
- [ ] View dashboard
- [ ] View transactions
- [ ] Livestock symptoms + AI advice
- [ ] Request vet consultation
- [ ] View credit summary
- [ ] Check balance

### Todos for Phase 9
- [ ] React Native project setup
- [ ] Mobile-specific UI (smaller screens, touch)
- [ ] SQLite offline database
- [ ] Sync queue (resubmit when online)
- [ ] API integration (consume Phase 1-8 endpoints)
- [ ] Auth flow (login, logout, token refresh)
- [ ] Tests (unit, integration)

---

## Additional Phases (Post-MVP)

### Phase 10 - Photo-based AI Diagnosis
- VetAI photo upload + analysis
- CropAI photo upload + analysis

### Phase 11 - Weather Intelligence
- Weather API integration
- Regional alerts
- Crop-specific recommendations

### Phase 12 - Marketplace
- Supplier management
- Input purchase history
- Supplier credit scoring

### Phase 13 - Loans Module
- Loan application
- Loan approval workflow
- Repayment tracking

### Phase 14 - Crop Tracker
- Plot management
- Planting schedule
- Harvest tracking
- Yield analysis

### Phase 15 - USSD System
- USSD menu (shortcode *384*534#)
- Income/expense logging via USSD
- Balance check
- Vet menu
- Crop tips

---

## Testing Strategy

All phases require:
- **Unit Tests** (services, models, helpers)
- **Integration Tests** (database interactions, API)
- **Feature Tests** (full user flows, UI)
- **Security Tests** (SQLi, XSS, CSRF, rate limiting)

Test coverage target: **80%+**

Tests are written before implementation (TDD). The four code design principles make this possible:
- **Dependency injection** lets us swap real services for fakes in tests
- **Pure functions** are tested by checking input → output with no setup needed
- **Separation of concerns** means each layer is tested in isolation
- **Loose coupling** via interfaces means we never hit real APIs (Arkesel, MoMo) in tests

---

## Deployment Checklist

- [ ] GitHub repo created (public/private?)
- [ ] Railway account created
- [ ] PostgreSQL database provisioned
- [ ] Redis cache provisioned
- [ ] Arkesel account + API key
- [ ] Google OAuth credentials
- [ ] Facebook OAuth credentials
- [ ] GitHub Actions workflow created
- [ ] Staging environment deployed
- [ ] Production secrets configured
- [ ] SSL/TLS certificate (auto via Railway)
- [ ] Domain name configured
- [ ] Monitoring setup (error tracking, logs)
- [ ] Backups configured (daily)

---

## Important Notes

1. **Security First** - Financial data is sensitive, no shortcuts
2. **TDD Always** - Tests before implementation
3. **One Phase Per Chat** - Each new chat starts with this Context.md
4. **Feature Branches** - Each phase gets its own branch
5. **No Compromises** - Code quality, testing, security are non-negotiable
6. **Farmers First** - UI must be simple (low literacy users)
7. **Scale Ready** - Architecture must support 100k+ farmers
8. **Design Principles** - Every file must follow: dependency injection, pure functions, separation of concerns, loose coupling

---

## Quick Reference

**Start New Chat Template:**
```
[Paste entire Context.md]

Starting Phase X — [Feature Name]
```

**Current Phase Branch:**
```bash
git checkout -b feature/phase-X-feature-name
```

**Run Tests:**
```bash
php artisan test
npm run test
```

**Deploy to Staging:**
```bash
git push origin feature/phase-X-feature-name
# GitHub Actions runs tests automatically
# If pass, manual merge to staging
```

**Deploy to Production:**
```bash
git merge staging → main
# Manual approval trigger deploy
```
