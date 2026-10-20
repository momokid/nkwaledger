# Handoff folder

How work passes from one chat to the next without the owner copying anything.

| File | What it is |
|---|---|
| current.md | The live handoff note: branch, where we stopped, what is next, what waits on the owner. Rewritten and committed at every stopping point |
| how-it-works.md | The exact steps for starting a session and for ending one |

## The idea
The owner opens a new chat and types something like "let's continue". Claude then reads, in this order:
1. `CLAUDE.md` (loads on its own)
2. `docs/handoff/current.md`
3. The active task file that current.md links to (in `docs/tasks/`)
4. The files that task file links to (rules, domain, ops, decisions)

Nothing else is needed. Facts are never repeated in current.md; it only points to the file that owns them.
