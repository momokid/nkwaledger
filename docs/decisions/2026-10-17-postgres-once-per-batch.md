# Full PostgreSQL suite once per batch

**Decided:** 2026-10-17, by the owner.

Run the full local PostgreSQL suite (Docker postgres:17 on a clean copy, plus `--group=sequential-pg`) once per batch of related tasks, not after every task. Per task, run SQLite and JS in full, plus the PostgreSQL tests for the area touched. CI runs the full PostgreSQL suite on the pull request.

**Why:** the full PostgreSQL run takes 40 to 65 minutes and blocks every commit.

**How to apply:** report each task with the SQLite and JS results and the targeted PostgreSQL result, and say plainly that the full PostgreSQL run is pending. Run it fully before pushing a batch, or sooner when a task touches ledger posting, locks, migrations with raw SQL, or collation.
