# Adviser and vet menu items

Status: plan only. Nothing built. Waiting on the owner's answers below.

## Goal
Decide what each greyed-out adviser menu item does, and whether to add a link to the report queue.

## Read first
- `backend/resources/js/Layouts/AuthenticatedLayout.tsx`: the `adviser` and `vet` menus (`ready: false` items are placeholders with `href: "#"`).
- [rules/ui-and-frontend.md](../rules/ui-and-frontend.md)
- [domain/identity-and-permissions.md](../domain/identity-and-permissions.md)

## Today
- Adviser menu: Dashboard (live, it is the officer report queue), Crops, Consultations, Weather (greyed), Market Center (live).
- Vet menu: Dashboard (live), Livestock, Consultations (greyed), Market Center (live).
- The report screen opens from the Dashboard at `/adviser/reports/{uuid}` or `/vet/reports/{uuid}`.

## Items and the questions for the owner
| Item | Question |
|---|---|
| Health Reports link | The Dashboard already is the queue. Add a second link to it, or leave as is? Wording? |
| Weather | Farmers have `/my-weather`. What should an adviser see: a farmer's weather, or their own area? New route, so owner approval. |
| Crops | What does the adviser see on this page? |
| Consultations | New feature, no model or flow exists. Needs its own plan; shared with the vet menu. |

## Rules for this task
- No new route, permission or wording without the owner's approval.
- One item per task, one commit each.

## Done when
The owner has answered each question. Each approved item then gets its own task file.

## Log
- 2026-10-22: plan written. Owner chose all four items, plan first.
- 2026-10-22: owner decisions. Order: (1) adviser dashboard rebuild, (2) Weather = a linked farmer's weather at a new adviser route, (3) Crops = trends from received crop and livestock reports over the last 90 days (news feed later, no source chosen), (4) Consultations = list of reports the officer answered, using existing `respond`. The vet gets the same dashboard and trends for livestock. Each slice needs wording approval first.
- 2026-10-22: slice 1 built for the adviser: 4 cards (New, Reviewed, Resolved, Crop reports last 90 days) that filter the list, AI tag, DD/MM/YYYY dates. Vet page not changed yet (props already sent). Wording approved by owner.
