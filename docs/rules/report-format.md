# Task report format

## Written summary (max 15 lines, plain English, no code)
What changed · files changed (count and list, from the commit output) · tests passed, failed, skipped and time · decisions made on your own · what is not done · what to check by hand. Then the full production diff in the reply. No patch files.

## Raw output below the summary
- `git status`, `git log origin/develop..HEAD --oneline`, `git show --stat` for each commit.
- The three commit-check outputs from rules/git-and-commits.md.
- Full output of any failing run, and repeat runs of anything flaky.
- Full-suite results for SQLite and JS, plus the PostgreSQL tests for the area touched. The full PostgreSQL suite runs once per batch and before pushing; say when it is still pending.
- Every placeholder wording key used.
- Every decision the owner did not settle.
