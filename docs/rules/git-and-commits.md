# Git and commits

- At the start run `git branch --show-current`. It must match the branch named in the prompt, otherwise stop.
- Run `git status --short`. Ignore untracked `.agents/`, `.claude/` and `skills-lock.json`. If anything else is uncommitted, stop.
- Author and committer must be the owner. `git config user.email` must be `anwar.rhsl@gmail.com`. If it differs or is empty, stop. Never edit git config or override the identity on the command line.
- Commit only after the full suite passes. Stage only the files of the task, by explicit path. Never `git add .` or `-A`.
- Commit message: one short conventional line. No body. No Co-Authored-By line. No "Generated with" line. No mention of Claude, AI or Anthropic.
- Never push, amend, tag or skip hooks. Do not switch branch unless the owner asks for a new branch.
- After committing, run and report: `git log -1 --format="%h %an <%ae> | %cn <%ce>"`, `git log -1 --format=%B`, `git show --stat --format=%s HEAD`.
- Never give a destructive command (for example `migrate:fresh`) without saying what it destroys. Prefer `migrate:rollback`.
- Seeder commands for staging or production must be idempotent and use `--force`. After permission seeders also run `permission:cache-reset`.
