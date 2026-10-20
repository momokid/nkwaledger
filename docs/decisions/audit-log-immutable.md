# The audit log is immutable

The audit log has no update, no delete and no `updated_at`. Farmer usage analytics go in a separate table (`activity_logs`) later. The table will outgrow all others; the retention window is not chosen and needs the Bank of Ghana figure.

Source: the archived phase-2 handoff.
