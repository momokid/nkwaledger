# How handoff works

## Starting a session
When the owner says "continue", "let's continue" or anything like it:
1. Read `docs/handoff/current.md`.
2. Run the checks in `docs/rules/git-and-commits.md` (branch, status, identity). The branch must match the one in current.md; if not, stop and say so.
3. Open the active task file named in current.md, then the files it links to. Read only those.
4. Tell the owner in two or three lines where we are and what you will do next, then do it.
5. If current.md and the real git state disagree (different branch, extra commits, uncommitted changes), the real state wins. Say what differs.

## Ending a session (at a stopping point: after a commit)
1. Update the task file: tick finished steps, note decisions made and anything left.
2. Update `docs/todo.md` for what is now done.
3. Rewrite `docs/handoff/current.md` so it is true now. Keep it short.
4. Commit those docs on their own with a one-line `docs:` message. A commit that changes only files under `docs/` or `CLAUDE.md` does not need the test suite.
5. Tell the owner it is safe to open a new chat.

## What goes where
- Rules and agreed ways of working: `docs/rules/`
- How a feature works: `docs/domain/`
- A choice and why: `docs/decisions/`
- Steps of one piece of work: `docs/tasks/`
- Where we are right now: `docs/handoff/current.md`
- Never copy a fact into current.md. Link to the file that owns it.
