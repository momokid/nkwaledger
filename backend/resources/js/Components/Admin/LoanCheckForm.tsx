import { useTheme } from "@/Layouts/AuthenticatedLayout";
import Button from "@/Components/Button";
import { CheckStatus, checkOptionLabel } from "@/Components/LoanCheckNotice";
import { csrfToken } from "@/lib/syncHttp";
import { FALLBACK, wording } from "@/lib/wording";
import { FormEvent, useState } from "react";

export const CHECK_STATUSES: CheckStatus[] = ["confirmed", "not_confirmed", "does_not_match", "unreachable"];

export interface SavedChecks {
    check: { status: CheckStatus; note: string | null; date: string } | null;
    checks: unknown[];
}

interface Props {
    loanUuid: string;
    idempotencyKey: string;
    onSaved: (saved: SavedChecks) => void;
}

const newKey = () => (typeof crypto !== "undefined" && "randomUUID" in crypto ? crypto.randomUUID() : String(Date.now()));

// the admin records what the lender said. It saves in place: nothing reloads, and the page gets the new check and history back
export default function LoanCheckForm({ loanUuid, idempotencyKey, onSaved }: Props) {
    const { dark } = useTheme();
    const [status, setStatus] = useState<CheckStatus | "">("");
    const [note, setNote] = useState("");
    const [key, setKey] = useState(idempotencyKey);
    const [busy, setBusy] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const border = dark ? "#374151" : "#E5E7EB";
    const inputBorder = dark ? "#4B5563" : "#9CA3AF";
    const inputBg = dark ? "#111827" : "#FFFFFF";
    const text = dark ? "#F9FAFB" : "#111827";

    const label = { display: "block", fontSize: "1.0625rem", fontWeight: 600, color: text, marginBottom: "6px" } as const;
    const errorText = { fontSize: "0.9375rem", color: "#B91C1C", marginTop: "4px" } as const;

    const save = async (event: FormEvent) => {
        event.preventDefault();
        setBusy(true);
        setErrors({});

        try {
            const response = await fetch(`/admin/loans/${loanUuid}/checks`, {
                method: "POST",
                headers: { Accept: "application/json", "Content-Type": "application/json", "X-CSRF-TOKEN": csrfToken() },
                body: JSON.stringify({ status, note, idempotency_key: key }),
            });
            const json = await response.json().catch(() => ({}));

            if (!response.ok) {
                const fields = (json.errors ?? {}) as Record<string, string[]>;

                setErrors({
                    status: fields.status?.[0] ?? "",
                    note: fields.note?.[0] ?? "",
                    form: fields.status || fields.note ? "" : (json.message ?? ""),
                });

                return;
            }

            onSaved(json);
            setStatus("");
            setNote("");
            setKey(newKey());
        } catch {
            setErrors({ form: FALLBACK });
        } finally {
            setBusy(false);
        }
    };

    return (
        <form onSubmit={save} className="mt-5 p-4" style={{ border: `1px solid ${border}`, maxWidth: "560px" }}>
            <h3 style={{ fontSize: "1.1875rem", fontWeight: 700, color: text }}>{wording("lender.check_heading")}</h3>

            <fieldset className="mt-3 mb-4">
                <legend style={label}>{wording("lender.check_status")}</legend>
                {CHECK_STATUSES.map((option) => (
                    <label key={option} className="flex items-center gap-2 py-1" style={{ color: text, fontSize: "1.0625rem" }}>
                        <input type="radio" name="check-status" value={option} checked={status === option} onChange={() => setStatus(option)} />
                        {checkOptionLabel(option)}
                    </label>
                ))}
                {errors.status && <p style={errorText}>{errors.status}</p>}
            </fieldset>

            <div className="mb-4">
                <label htmlFor="check-note" style={label}>
                    {wording("lender.check_note")}
                </label>
                <textarea
                    id="check-note"
                    rows={3}
                    maxLength={500}
                    value={note}
                    onChange={(event) => setNote(event.target.value)}
                    style={{ width: "100%", padding: "10px 12px", border: `1px solid ${inputBorder}`, background: inputBg, color: text, fontSize: "1.0625rem" }}
                />
                {errors.note && <p style={errorText}>{errors.note}</p>}
            </div>

            {errors.form && <p style={errorText}>{errors.form}</p>}

            <Button type="submit" busy={busy} busyLabel={wording("repayment.saving")} disabled={status === ""}>
                {wording("lender.save")}
            </Button>
        </form>
    );
}
