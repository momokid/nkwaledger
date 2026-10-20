# The AI draft

## Rules (decided 2026-10-20)
- A Qwen vision model, reached through OpenRouter, reads the photo and description and drafts: a category from the active list, a confidence from 0 to 1, an urgent flag, whether the photo is usable, and a short note.
- The draft goes to the officer only. The farmer sees only the officer reply.
- At 70% confidence or above the officer sees the draft labelled as an AI suggestion. Below 70% the officer sees only the photo. Urgent signs reach the officer whatever the confidence.
- The officer confirms or edits. The AI draft and the officer final answer are both stored, so agreement can be measured and the 70% line tuned from data. A model confidence figure is not a true probability.
- The AI never names a product, medicine or dose. Any product advice stays with the officer.
- The EPA pesticide list must come from the owner as a CSV; it is never written from memory.
- If the AI fails, reports work exactly as before. The reviewer returns null on any failure and never throws.

## The adapter
`HealthImageReviewer::review(bytes, mime, description, farmCategory, categories)` returns a `HealthReview` (photoUsable, category, confidence, urgent, note, model) or null. It refuses a category not on the list and a confidence outside 0 to 1. Settings: `OPENROUTER_API_KEY`, `OPENROUTER_MODEL` (default `qwen/qwen3-vl-235b-a22b-instruct`). Tests fake the HTTP call.

## Planned
Photo-quality check that asks for a better photo, a daily cap per farmer, a fallback model. Steps are in the files under [../../tasks/](../../tasks/context.md).
