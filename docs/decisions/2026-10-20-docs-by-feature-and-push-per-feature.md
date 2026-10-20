# Docs split by feature, and each feature is pushed

**Decided:** 2026-10-20, by the owner.

- Docs are split by feature or by instruction, not by line count. Big topics get a folder with a router `context.md` and one file per feature. No single file keeps growing, because a long file makes Claude forget and wastes tokens.
- Each finished feature is committed and then pushed to GitHub.

**Why:** small, focused files let Claude load only what a task needs. Pushing per feature keeps work safe on GitHub and reviewable in small pieces.

**How to apply:** follow the push rule in [../rules/git-and-commits.md](../rules/git-and-commits.md): feature branch only, suite green, PostgreSQL checks done, never `develop` or `master`, never force push. To add a feature to a doc folder, add a file and a line in that folder `context.md`.
