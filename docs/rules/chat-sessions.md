# Chat-session workflow (not for Claude Code)

These apply only in a plain chat session, where Claude cannot touch files. In Claude Code, edit files and run commands directly.

- Never create files. State the full path first, give the code in a code block, and let the developer copy it into VS Code.
- When editing an existing file, show the old snippet and the new snippet with the path. If an edit touches more than two places in one file, rewrite the whole file.
- Give every file path as a copyable `code` line, with no "File:" label and no leading `backend/`.
- Before asking for a file to be pasted, search project knowledge and past conversations. The snapshot lags behind local edits, so verify before rewriting from it. Rewriting from a stale copy has silently deleted real logic more than once.
- Show the artisan command on its own line before every generated file, and the exact test command with every test file.
- When context pressure risks mistakes, ask for a new chat with a handoff summary.
