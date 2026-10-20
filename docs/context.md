# Docs router

Start here. Find the topic, open the folder, read its `context.md`, then only the file you need. Each folder has its own `context.md` that lists its files.

| You are about to... | Go to |
|---|---|
| Start a session ("continue", "let's continue") or end one | handoff/ (read current.md first) |
| Do the steps of one piece of work | tasks/ |
| Pick the next task, or record that one is done | todo.md |
| Understand what the product is, the stack or the roadmap | project/ |
| Commit, test, write a reply, write UI or touch security | rules/ |
| Work on accounting, stock, permissions, loans, USSD or health AI review | domain/ |
| Deploy, set env variables, run seeders or hit an odd bug | ops/ |
| Find out why something was decided | decisions/ |
| Read the marketplace spec | specs/ |
| Look at unscheduled ideas | ideas/ |
| Check an old file | archive/ (out of date, read only) |

## Always
- The hard safety rules are also in the root `CLAUDE.md`, which loads every session: git identity, no pushing, no invented wording.
- Update docs/todo.md at the end of every task.
- When a fact changes, change it in the one file that owns it, not in several places.
- A new topic gets its own file and a line in the folder `context.md`.
- Split by feature or by instruction, never by line count. One file covers one feature or one instruction. A topic with several features (USSD, AI review) gets its own folder, a `context.md` that lists the files, and one file per feature. Never keep appending to one big file.
- When a new feature or instruction does not fit an existing file, give it its own file, add a line to the folder `context.md`, and link to related files instead of copying their facts.
- Read only the files a task needs. Start at this router, then the folder `context.md`, then the one file.
