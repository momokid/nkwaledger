# AI review, slice 4: the officer screen

Status: not started. Needs slice 3.

## Goal
The vet or adviser sees the AI draft next to the photo and confirms or edits it. Their final answer and whether it matched the AI are stored.

## Read first
- [domain/health-ai-review/context.md](../domain/health-ai-review/context.md)
- [decisions/2026-10-20-ai-review-design.md](../decisions/2026-10-20-ai-review-design.md)
- [rules/ui-and-frontend.md](../rules/ui-and-frontend.md) and [rules/reply-style.md](../rules/reply-style.md)
- [ai-review-slice-3-job.md](ai-review-slice-3-job.md): the columns this screen reads

## Steps
1. Show the draft (category, note, urgent flag) on the officer report page only when `ai_status` is `ready`, labelled as an AI suggestion. Urgent shows whatever the confidence.
2. Let the officer confirm the category or pick another from the active list.
3. Store the officer final category and a verdict (agreed, edited or disagreed). New columns for this belong in this slice.
4. Keep AI fields out of every farmer page and response.

## Needs the owner
All visible labels and wording (the AI suggestion label, the urgent flag text, the buttons). Do not invent them; propose and wait for approval.

## Done when
Full suite plus the TypeScript check and production build pass, committed, todo and handoff updated.

## Log
- 2026-10-20: task written.
