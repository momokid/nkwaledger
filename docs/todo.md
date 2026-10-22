# Todo

The one list of what is done and what is not. Update it at the end of every task.
Last reviewed: 2026-10-20. Branch notes say where the work lives; unmerged work is marked.

Legend: [x] done · [~] in progress · [ ] not started · [?] unverified, check the code first

## MVP order (see project/roadmap.md)

### 1. Offline sync (PWA first)
- [?] Branch `feature/offline-sync-api` exists. Offline health reports and chunked photo upload are in the code. Check what remains before starting.

### 2. Loans and repayments
- [x] Lock race fixes; three-dots SMS cut
- [x] Back link on every loan form and detail page
- [x] Mobile menu "More" tab for hidden menu items
- [x] Overdue after the due date; Default at 90 days past due
- [x] Loan dates as DD/MM/YYYY
- [x] Agent menu Credit group; New Loan page with search over own farmers
- [x] Repayment statement sorted by date with balance after each repayment
- [ ] Task 3.9: lender request button, admin queue, two-step loan form (branches `feature/lender-requests`, `feature/loan-two-step-form` exist; check merge state)
- [ ] Penalties
- [ ] Corrections (LN-19)
- [ ] Offline and agent recording
- [ ] 3.3c: cancelled money in and out on Reports, CSV and print (needs an owner decision)
- [ ] Wording for three provisional messages: NO_PHONE and TOO_MANY_SENDS (EntryCodeService), NOT_PENDING (PendingEntryService)
- [ ] Parked by the owner: goods loans and repayment in produce or animals (see domain/loans.md)
- [ ] Owner side: browser click-through as farmer and agent; spot-check of the 327 institution names; lawyer review of lender names, default status and penalties

### 3. USSD (branch `feature/ussd-base`, built, not pushed)
- [x] Farmer and agent menus, Back, PIN, Twi draft, simulator, Pending Approvals, Exit last
- [ ] Push and deploy
- [ ] Arkesel callback set up with `USSD_CALLBACK_SECRET`
- [ ] Handset test (a separate session, ussd-handset, is waiting on it)
- [ ] Twi native-speaker review; Twi wording for "Pending Approvals"
- [ ] Confirm the meaning of the "100" response code
- [ ] Full PostgreSQL suite for the batch (on the USSD branch)

### 4. Crop and vet AI review (branch `feature/ai-health-review`, not pushed)
Task files: [tasks/ai-review-slice-3-job.md](tasks/ai-review-slice-3-job.md) · [slice 4](tasks/ai-review-slice-4-officer-screen.md) · [slice 5](tasks/ai-review-slice-5-category-admin.md) · [bucket check](tasks/verify-bucket-on-staging.md)
- [x] Slice 1: health categories (table, model, seeder)
- [x] Slice 2: OpenRouter adapter `HealthImageReviewer`
- [x] Web report photos and voice notes on `PHOTO_DISK`; S3 package installed
- [x] Slice 3: queued job `ReviewDiseaseReport` after the photo is stored (web and offline), `ai_*` columns on `disease_reports`, 70% rule in `services.openrouter.min_confidence`. The officer verdict columns come in slice 4.
- [x] Full PostgreSQL suite for slices 1 to 5 (22/10: 3686 passed, 9 skipped; sequential-pg 6 passed)
- [ ] Confirm a queue worker runs on Railway (the job needs one)
- [x] Slice 4: officer screen shows the draft (only when `ai_status` is `ready`; urgent always), Confirm or pick another category, stores `officer_health_category_id`, `officer_verdict` (`agreed`/`edited`/`disagreed` (Reject suggestion), null when no draft was shown) and `officer_reviewed_at`
- [x] Slice 5: admin page `/admin/health-categories` (permission `health-categories.manage`): add, rename, move up/down, switch on/off. No delete route.
- [ ] Photo-quality check and its wording (needs owner approval)
- [ ] EPA pesticide list as a CSV from the owner
- [ ] Fallback model; daily cap per farmer
- [ ] Live check with the real OpenRouter key
- [ ] Test with real Twi descriptions
- [ ] Set `PHOTO_DISK=s3` on staging and submit a test report to confirm the Bucket works
- [ ] Run `HealthCategorySeeder` after deploy
- [ ] Review the instruction text in `HealthImageReviewer.php`

### 4b. Adviser and vet menu items
Task file: [tasks/adviser-menu-items.md](tasks/adviser-menu-items.md)
- [x] 1 Adviser dashboard rebuild and vet page · [ ] 2 Weather · [ ] 3 Crops trends (90 days) · [ ] 4 Consultations list · [ ] News feed (later, source not chosen)

### 4c. Loan credit score
- [ ] Not built. Only the gate `FarmerEligibilityService::canBeCreditScored` and the text "Not yet available" in `AgentReportsController` exist. Needs its own plan: inputs, formula, who sees it, wording.

### 5. Fertilizer calculator
- [ ] Not started. Idea notes in ideas/feature-backlog.md.

### 6. Weather alerts
- [?] Partly built: `app/Services/Weather`, `FarmerWeatherController`, `weather:snapshot-advisories`. Check what is missing before starting.

### 7. WhatsApp
- [ ] Parked on purpose.

### 8. MVP close (not code; real farmers cannot sign up without these)
- [ ] Terms, privacy policy, consent text (must mention photos going to an AI provider)
- [ ] Lawyer review
- [ ] DPC registration

## Dashboard and reports (Phase 5)
- [x] My Farm page, cumulative stock timeline, loss recording, produce-sold tracking, purple "Corrected" tag on the farmer's records page
- [?] Farmer Dashboard (income and expense KPIs, net profit, livestock and crop summary). CLAUDE.md used to say not started, but `FarmerDashboardController` exists. Check.
- [ ] Re-seed the `produce_sale` template change on Railway staging and production
- [ ] Add the "Corrected" tag to the agent and admin `Reports/Index.tsx` (owner to decide)
- [ ] Capacity warning on `FarmUnit` (never built)
- [ ] Unit-of-measure placeholder variety (birds, acres, kg)
- [ ] Text sweep: "Waiting on somebody else" to "Pending for approval"
- [ ] Staff email requirement and email-first OTP (needs its own planning pass)
- [ ] Fish and aquatic loss model

## Docs
- [x] Restructure the .md files into docs/ (2026-10-20)
- [x] Handoff folder and task files so a new chat can just "continue" (2026-10-20)

## Older items from the archived phase-2 handoff (unverified)
- Railway has no scheduler, so `verification:expire` may never run in production
- Unnamed routes `auth/check` and `confirm-password` POST
- Skeleton placeholders missing on FarmTypeCategories, FarmTypes, Roles, Users, UserDetail
- Admin sidebar shows every item regardless of permission
- React key warning in `UserDetail.tsx`
- Convert remaining pages to the typography tokens as they are touched
- No WAF in front of Railway; no hard SMS spend cap on Arkesel
- Audit log archiving and retention window
- Farmer usage analytics (`activity_logs`)
- Stronger staff password rule
