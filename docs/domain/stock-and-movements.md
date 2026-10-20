# Stock and movements

## Batches
- `FarmUnitStock` is a batch. It has `opening_quantity`, `current_quantity`, `started_on`, `expected_ready_on` and `source` (OpeningBalance or Purchase).
- Every batch auto-creates one `Opening` movement, confirmed automatically.
- `FarmUnitStockMovement.is_increase` sets the direction. It is recalculated on save through `MovementReason::addsToCount()`, except `Correction`, which carries its own direction.
- A rejected movement or batch stops counting toward `current_quantity` and the running total, but stays visible and marked Rejected.

## Losses and sales
- `quantity_lost` on `transactions` with `MovementReason::Loss`; `quantity_sold` on `transactions`; `is_produce_sale` on `transaction_templates`.
- A loss or sale is split proportionally across all active batches on the unit, by each batch's share of the current count. It is never assumed to come from one batch (oldest, largest), because nobody can know which animal or produce was affected.
- The last batch absorbs any rounding remainder, so the split always adds up exactly.
- The shared split is `PostingService::splitProportionally()`.
- The seeder marks `produce_sale`, `animal_sale`, `produce_of_animal_sale` and `fish_sale` as produce sales. `produce_sale` requires a farm unit.

## My Farm page (`/my-farm`)
- Farmer-scoped. Expandable unit cards with per-unit financial analysis and a date range filter.
- Cumulative stock timeline: all batches merged into one flat chronological list per unit with a running total.
- Labels: Starts with, Added, Bought, Lost, Sold, Birth, Stolen, Died, Culled, Corrected. Rejected entries show but do not count.
- The old batch-grouped view stays as a collapsible "Show batch details" section, visible to the farmer too.
- Corrections show a purple "Corrected" tag. Only on the farmer's records page so far, not on the agent and admin `Reports/Index.tsx`.

## Open
Capacity warning (`currentStockTotal()` and `isOverCapacity()` on `FarmUnit`) was never built. Fish and aquatic losses do not fit the day-by-day movement model, because deaths are silent until harvest.
