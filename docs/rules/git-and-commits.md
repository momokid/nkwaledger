# Git and commits

- At the start run `git branch --show-current`. It must match the branch named in the prompt, otherwise stop.
- Run `git status --short`. Ignore untracked `.agents/`, `.claude/` and `skills-lock.json`. If anything else is uncommitted, stop.
- Author and committer must be the owner. `git config user.email` must be `anwar.rhsl@gmail.com`. If it differs or is empty, stop. Never edit git config or override the identity on the command line.
- Commit only after the full suite passes. The exception: a commit that changes only files under `docs/` or `CLAUDE.md` needs no test run. Stage only the files of the task, by explicit path. Never `git add .` or `-A`.
- Commit message: one short conventional line. No body. No Co-Authored-By line. No "Generated with" line. No mention of Claude, AI or Anthropic.
- Never amend, tag or skip hooks. Do not switch branch unless the owner asks for a new branch.
- Push rule (owner decision 2026-10-20): each finished feature is committed and then pushed to GitHub on its own feature branch. Push only when the feature is complete, the full suite is green, the PostgreSQL checks in [tdd-and-testing.md](tdd-and-testing.md) are done, and `docs/todo.md` and the task file are updated. Push only the current feature branch, with a plain `git push` (set the upstream the first time). Never push `develop` or `master`, never force push, never push a branch with failing tests. Say plainly when a push is blocked and why.
- After committing, run and report: `git log -1 --format="%h %an <%ae> | %cn <%ce>"`, `git log -1 --format=%B`, `git show --stat --format=%s HEAD`.
- Never give a destructive command (for example `migrate:fresh`) without saying what it destroys. Prefer `migrate:rollback`.
- Seeder commands for staging or production must be idempotent and use `--force`. After permission seeders also run `permission:cache-reset`.
