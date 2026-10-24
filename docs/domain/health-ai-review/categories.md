# Health categories

- Table `health_categories`: a public uuid, the farm category it belongs to (Crop, Livestock or Aquatic), a name, a sort order and an on/off flag. A name is unique within its farm category.
- Model `HealthCategory`; `HealthCategory::activeFor($farmTypeCategory)` returns the active ones in order.
- They live in the database so an admin can add or rename categories later without a deploy. Old reports keep the category they were given, so a category in use is switched off, not deleted.
- The AI is given the current active list each time.
- `HealthCategorySeeder` fills the first list: 9 Crop, 9 Livestock and 7 Aquatic, each ending with "Not sure". It skips a farm category that already has a list, so admin edits survive a re-seed. Run it once after deploy (see [../../ops/env-and-seeders.md](../../ops/env-and-seeders.md)).
- Seeder note: DatabaseSeeder runs with model events off, so the seeder sets the uuid itself.
- Admin page: `/admin/health-categories`, permission `health-categories.manage` (admin default). Add (goes last), rename, move up/down (renumbers the list), switch on/off. No delete route. The farm category is sent by name because `farm_type_categories` has no uuid. Task: [../../tasks/ai-review-slice-5-category-admin.md](../../tasks/ai-review-slice-5-category-admin.md).
