# AI review design

**Decided:** 2026-10-20, by the owner.

- Qwen vision model through OpenRouter, behind one adapter class. The model id is a setting.
- The AI drafts; the officer confirms or edits. The farmer never sees the AI draft.
- A draft is shown to the officer at 70% confidence or above. Urgent signs reach the officer whatever the confidence.
- Categories are rows in the database so more can be added later.
- The officer's final answer is stored next to the AI draft so accuracy can be measured.
- Report photos live on the Railway Bucket (`PHOTO_DISK=s3`).

**Why:** a smallholder farmer should never act on an unchecked AI answer, and cost per report is tiny (well under one US cent).

Details: domain/health-ai-review/context.md.
