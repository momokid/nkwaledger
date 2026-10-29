# Account menu: Profile, Settings, Help, tour and AI assistant

Branch: `feature/account-menu` (cut from `feature/loan-paid-from-loan`, not pushed). Wording: [help-and-tour-wording.md](help-and-tour-wording.md) (approved by the owner).

## Done
- Profile `/profile`: read-only name, phone, email (identity, never changed here). No password change.
- Settings `/settings`: text size (Small, Medium, Large).
- Help `/help`: topics (add a farm, add a farm unit, record money or stock, loans, PIN and password, reports, need a person). Support number is read from `app_settings.support_phone` (0577915555, 24 hours).
- Quick tour on Help: farmer 6 steps, agent 5, no Marketplace. Each farmer step shows a picture of its page (`public/tour/farmer-*.jpg`, taken 27/10 from a local test farmer).
- AI help assistant: "Ask for help" button on every page for logged-in users. Cheap model (`OPENROUTER_ASSISTANT_MODEL`, default `openai/gpt-4o-mini`), English only, 50 questions per user per day, chats stored in `assistant_messages`. Knowledge is the markdown files in `resources/assistant/` (`pages.md`, `topics.md`). Page paths in a reply become links. The window tells the user that questions go to an AI provider. `provider.data_collection` is `deny`.
- New tables: `app_settings`, `assistant_messages` (run `php artisan migrate`). Set the OpenRouter key on the server.
- Full suites: PHP passed 3819 (15 skipped) before the tour pictures; front-end 586 passed.

## Not done
- Tour pictures use test data (name, lenders, amounts). Retake with demo data before real farmers use the app.
- Agent tour has no pictures. Vet, adviser and supplier tours are not written (wording must be approved first).
- USSD topic in Help (no USSD code yet) and a practice USSD screen in the app (simulator code is on `feature/ussd-base`).
- Settings: "Sign out of other devices". Help and Settings in the admin layout. An admin screen to edit `app_settings`.
- Tune the assistant knowledge files as the app changes (they list every page path).
- Push: the remote already has an old `feature/account-menu`, so push with `git push -u origin feature/account-menu:feature/account-menu-loans`.
