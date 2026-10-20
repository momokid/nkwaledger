# Current handoff

Written: 2026-10-20. Rewrite this at every stopping point.

## Where we are
- Branch: `feature/ai-health-review` (cut from `origin/develop`). Run `git log --oneline -8` to see the latest commits.
- Nothing is pushed. Push rule: [rules/git-and-commits.md](../rules/git-and-commits.md). Blocked for now: the full PostgreSQL suite is still pending for this batch. The owner said not now (2026-10-20), so do not run it or push until asked. Finished so far on this branch: health categories, the AI adapter, and the photo disk and S3 fix.
- Last code work: web report photos and voice notes now use `PHOTO_DISK`, and the S3 package is installed. Docs restructure and the handoff folder are committed after it.

## Active task
[tasks/ai-review-slice-3-job.md](../tasks/ai-review-slice-3-job.md) — the queued job that asks the AI for a draft review after a photo is stored. Not started. Tests come first.

## Next after that
- [tasks/ai-review-slice-4-officer-screen.md](../tasks/ai-review-slice-4-officer-screen.md)
- [tasks/ai-review-slice-5-category-admin.md](../tasks/ai-review-slice-5-category-admin.md)

## Waiting on the owner
- [tasks/verify-bucket-on-staging.md](../tasks/verify-bucket-on-staging.md): set `PHOTO_DISK=s3` on staging, submit one test report, check the photo loads.
- The EPA pesticide list as a CSV.
- Approval of any new wording (photo-quality message, officer screen labels).
- Whether to add a fallback model to the AI adapter.
- Whether the docs commits should stay on this branch or move to their own.

## Other branches (not this session)
- `feature/ussd-base`: USSD built, not pushed. See [domain/ussd/context.md](../domain/ussd/context.md) and the USSD section of [todo.md](../todo.md).

## Read first in a new session
[how-it-works.md](how-it-works.md), then the active task file above.
