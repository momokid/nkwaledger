import { useTheme } from "@/Layouts/AuthenticatedLayout";
import Button from "@/Components/Button";
import { wording } from "@/lib/wording";
import { csrfToken } from "@/lib/syncHttp";
import { useState } from "react";

const TYPES = ["bank", "rural_bank", "credit_financial_institution", "microfinance", "other"];

interface Props {
    // what was typed in the lender search
    name: string;
    // the farmer an agent is asking for; a farmer asks for themselves
    farmer: string | null;
    onClose: () => void;
}

// asks the admin to add a lender that is not in the list; sent in place, the loan form is left as it is
export default function LenderRequestForm({ name: typed, farmer, onClose }: Props) {
    const { dark } = useTheme();
    const [name, setName] = useState(typed);
    const [type, setType] = useState("");
    const [note, setNote] = useState("");
    const [busy, setBusy] = useState(false);
    const [sent, setSent] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const border = dark ? "#374151" : "#E5E7EB";
    const inputBorder = dark ? "#4B5563" : "#9CA3AF";
    const inputBg = dark ? "#111827" : "#FFFFFF";
    const text = dark ? "#F9FAFB" : "#111827";

    const field = { width: "100%", padding: "10px 12px", border: `1px solid ${inputBorder}`, background: inputBg, color: text, fontSize: "1.125rem" } as const;
    const label = { display: "block", fontSize: "1.0625rem", fontWeight: 600, color: text, marginBottom: "6px" } as const;
    const errorText = { fontSize: "0.9375rem", color: "#B91C1C", marginTop: "4px" } as const;

    if (sent) {
        return (
            <p role="status" className="mt-3" style={{ color: text, fontSize: "1.0625rem" }}>
                {wording("lender.request_sent")}
            </p>
        );
    }

    const send = async () => {
        setBusy(true);
        setErrors({});

        try {
            const response = await fetch("/lender-requests", {
                method: "POST",
                headers: { Accept: "application/json", "Content-Type": "application/json", "X-CSRF-TOKEN": csrfToken() },
                body: JSON.stringify({ name, type, ...(note.trim() !== "" ? { note } : {}), ...(farmer ? { farmer } : {}) }),
            });

            if (response.ok) {
                setSent(true);
            } else {
                const json = await response.json().catch(() => ({}));

                setErrors(Object.fromEntries(Object.entries((json.errors ?? {}) as Record<string, string[]>).map(([key, messages]) => [key, messages[0]])));
            }
        } catch {
            setErrors({});
        } finally {
            setBusy(false);
        }
    };

    return (
        <div className="mt-3 p-4" style={{ border: `1px solid ${border}` }}>
            <h3 style={{ fontSize: "1.1875rem", fontWeight: 700, color: text }}>{wording("lender.request_heading")}</h3>

            <div className="mt-3">
                <label htmlFor="request_name" style={label}>
                    {wording("lender.name")}
                </label>
                <input id="request_name" style={field} value={name} onChange={(event) => setName(event.target.value)} />
                {errors.name && <p style={errorText}>{errors.name}</p>}
            </div>

            <div className="mt-3">
                <label htmlFor="request_type" style={label}>
                    {wording("lender.type")}
                </label>
                <select id="request_type" style={field} value={type} onChange={(event) => setType(event.target.value)}>
                    <option value=""></option>
                    {TYPES.map((item) => (
                        <option key={item} value={item}>
                            {wording(`lender.type.${item}`)}
                        </option>
                    ))}
                </select>
                {errors.type && <p style={errorText}>{errors.type}</p>}
            </div>

            <div className="mt-3">
                <label htmlFor="request_note" style={label}>
                    {wording("lender.request_note")}
                </label>
                <textarea id="request_note" rows={3} style={field} value={note} onChange={(event) => setNote(event.target.value)} />
                {errors.note && <p style={errorText}>{errors.note}</p>}
            </div>

            {(errors.farmer || errors.request) && <p style={errorText}>{errors.farmer ?? errors.request}</p>}

            <div className="mt-4 flex flex-wrap gap-3">
                <Button type="button" busy={busy} disabled={name.trim() === "" || type === ""} onClick={send}>
                    {wording("lender.request_send")}
                </Button>
                <Button type="button" look="secondary" onClick={onClose}>
                    {wording("lender.cancel")}
                </Button>
            </div>
        </div>
    );
}
