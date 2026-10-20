# Health reports and AI review

## Existing flow
A farmer reports a problem on a farm unit with a photo, a description and an optional voice note. The report is routed by the unit's farm category: Crop goes to an adviser, Livestock and Aquatic go to a vet (`OfficerRole::fromFarmCategory`). The officer responds. Services are in `app/Services/DiseaseReports/`. Offline reports wait without a photo until it arrives (status `waiting_for_photo`).

## AI review design (decided 2026-10-20)
- A Qwen vision model, reached through OpenRouter, reads the photo and description and drafts a category, a confidence figure (0 to 1), an urgent flag and a short note.
- The AI draft goes to the officer only. The farmer sees only the officer's reply.
- At 70% confidence or above the officer sees the draft labelled as an AI suggestion. Below 70% the officer sees only the photo. An urgent flag reaches the officer whatever the confidence.
- The officer confirms or edits the draft. The AI draft and the officer's final answer are both stored, so agreement can be measured and the 70% line tuned from data. A model's own confidence is not a true probability.
- The AI never names a product, medicine or dose.
- If the AI or OpenRouter fails, reports work exactly as before. `HealthImageReviewer` returns null on any failure.
- Privacy: only the photo and description are sent. No name, phone, location or EXIF data. OpenRouter is asked to use only providers that do not keep or train on the data.
- Categories live in the database (`health_categories`, per farm category), so an admin can add more later. Old reports keep the category they were given.
- Planned: a photo-quality check that asks for a better photo, a daily cap per farmer, a fallback model.
- The EPA pesticide list must be supplied by the owner as a CSV. It is not written from memory.

## Built so far (branch `feature/ai-health-review`)
1. Health categories: table, model, seeder with 9 Crop, 9 Livestock and 7 Aquatic categories.
2. `HealthImageReviewer` adapter with fake-response tests. Settings: `OPENROUTER_API_KEY`, `OPENROUTER_MODEL` (default `qwen/qwen3-vl-235b-a22b-instruct`).
3. Web report photos and voice notes are stored on `PHOTO_DISK` and `media_disk` is recorded. The S3 package is installed.

## Photos
`PhotoUpload` already resizes to 1600px on the longest side and re-encodes as WebP at 75% quality. The AI reads this stored copy.

## Next
A queued job after the photo is stored (including offline uploads), new columns on `disease_reports` for the draft, confidence, model and the officer's verdict, then the officer screen, an admin page for categories, and wording approvals. See todo.md.
