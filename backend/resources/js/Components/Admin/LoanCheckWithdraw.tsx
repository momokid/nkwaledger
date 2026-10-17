import { useTheme } from "@/Layouts/AuthenticatedLayout";
import Button from "@/Components/Button";
import { SavedChecks } from "@/Components/Admin/LoanCheckForm";
import { csrfToken } from "@/lib/syncHttp";
import { FALLBACK, wording } from "@/lib/wording";
import { FormEvent, useState } from "react";

interface Props {
    loanUuid: string;
    onSaved: (saved: SavedChecks) => void;
}

const newKey = () => (typeof crypto !== "undefined" && "randomUUID" in crypto ? crypto.randomUUID() : String(Date.now()));

// takes the current check back, with the reason. It saves in place: the page gets the history back and nothing reloads
export default function LoanCheckWithdraw({ loanUuid, onSaved }: Props) {
    const { dark } = useTheme();
    const [open, setOpen] = useState(false);
    const [note, setNote] = useState("");
    const [key, setKey] = useState(newKey);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState("");

    const border = dark ? "#374151" : "#E5E7EB";
    const inputBorder = dark ? "#4B5563" : "#9CA3AF";
    const inputBg = dark ? "#111827" : "#FFFFFF";
    const text = dark ? "#F9FAFB" : "#111827";

    const close = () => {
        setOpen(false);
        setNote("");
        setError("");
    };

    const withdraw = async (event: FormEvent) => {
        event.preventDefault();
        setBusy(true);
        setError("");

        try {
            const response = await fetch(`/admin/loans/${loanUuid}/checks/withdraw`, {
                method: "POST",
                headers: { Accept: "application/json", "Content-Type": "application/json", "X-CSRF-TOKEN": csrfToken() },
                body: JSON.stringify({ note, idempotency_key: key }),
            });
            const json = await response.json().catch(() => ({}));

            if (!response.ok) {
                setError(json.errors?.note?.[0] ?? json.message ?? FALLBACK);

                return;
            }

            onSaved(json);
            setKey(newKey());
            close();
        } catch {
            setError(FALLBACK);
        } finally {
            setBusy(false);
        }
    };

    if (!open) {
        return (
            <div className="mt-3">
                <Button type="button" look="secondary" size="small" onClick={() => setOpen(true)}>
                    {wording("lender.check_withdraw")}
                </Button>
            </div>
        );
    }

    return (
        <form onSubmit={withdraw} className="mt-3 p-4" style={{ border: `1px solid ${border}`, maxWidth: "560px" }}>
            <label htmlFor="withdraw-note" style={{ display: "block", fontSize: "1.0625rem", fontWeight: 600, color: text, marginBottom: "6px" }}>
                {wording("lender.check_note")}
            </label>
            <textarea
                id="withdraw-note"
                rows={3}
                maxLength={500}
                value={note}
                onChange={(event) => setNote(event.target.value)}
                style={{ width: "100%", padding: "10px 12px", border: `1px solid ${inputBorder}`, background: inputBg, color: text, fontSize: "1.0625rem" }}
            />
            {error && <p style={{ fontSize: "0.9375rem", color: "#B91C1C", marginTop: "4px" }}>{error}</p>}
            <div className="mt-3 flex gap-3">
                <Button type="submit" busy={busy} busyLabel={wording("repayment.saving")} disabled={note.trim() === ""}>
                    {wording("lender.check_withdraw")}
                </Button>
                <Button type="button" look="secondary" onClick={close}>
                    {wording("lender.cancel")}
                </Button>
            </div>
        </form>
    );
}
