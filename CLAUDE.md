# NkwaLedger

Agricultural fintech platform for Ghanaian smallholder farmers: turns farm activity into bankable financial records. Laravel 12 backend in `backend/`, Inertia + React frontend, PostgreSQL on Railway.

## Starting a session
If the owner says "continue", "let's continue" or anything like it, read `docs/handoff/current.md` first, then `docs/handoff/how-it-works.md`, and follow them. They point to the active task file in `docs/tasks/` and the files it needs. At every stopping point (after a commit), update the task file, `docs/todo.md` and `docs/handoff/current.md`, and commit them.

## Where things are
Read `docs/context.md` first. It routes you to the right folder, and each folder has its own `context.md`. Read only the file you need.

- What is done and what is next: `docs/todo.md`. Update it at the end of every task.
- How to work (git, tests, replies, UI, security): `docs/rules/`.
- How each part works (accounting, stock, permissions, loans, USSD, AI review): `docs/domain/`.
- Hosting, env variables, seeders, known traps: `docs/ops/`.
- Why something was decided: `docs/decisions/`.

## Hard rules (always, even if you read nothing else)
- At the start run `git branch --show-current`. It must match the branch named in the prompt, otherwise stop. Run `git status --short`. Ignore untracked `.agents/`, `.claude/` and `skills-lock.json`. If anything else is uncommitted, stop.
- Failing test first, then implement. The full suite must pass before any commit, except a commit that changes only files under `docs/` or `CLAUDE.md`.
- Commit each finished feature. Stage files by explicit path, never `git add .` or `-A`. `git config user.email` must be `anwar.rhsl@gmail.com`; never change the git identity. One short conventional commit line, no body, no Co-Authored-By, no "Generated with", no mention of Claude, AI or Anthropic.
- Push a finished feature to its own feature branch only, under the push rule in `docs/rules/git-and-commits.md`. Never push `develop` or `master`, never force push. Never amend, tag, skip hooks, or switch branch unless asked. Never run a destructive command without saying what it destroys.
- One issue per task. Report other problems; do not fix them. Ask before adding packages, columns, routes, permissions, UI or wording.
- Never invent user-facing text. Use only wording the owner approved. Dates shown are DD/MM/YYYY.
- No numeric database id in any page prop, response, link or notification. Use uuids.
- Keep replies short and plain, like for an 8th grader. Go into detail only for risks to money, security or user data.
- End every task with the report in `docs/rules/report-format.md`, and say whether a new session would be safer (`docs/rules/session-hygiene.md`).
