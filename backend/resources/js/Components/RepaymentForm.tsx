import { useTheme } from "@/Layouts/AuthenticatedLayout";
import { wording } from "@/lib/wording";
import Button from "@/Components/Button";
import { csrfToken } from "@/lib/syncHttp";
import { cedis } from "@/lib/format";
import { useForm, usePage } from "@inertiajs/react";
import { FormEvent, useEffect, useState } from "react";

export interface PaidFromOption {
    value: string;
    label: string;
}

interface Split {
    principal: number;
    interest: number;
}

interface Props {
    paidFrom: PaidFromOption[];
    previewUrl: string;
    postUrl: string;
    today: string;
    idempotencyKey?: string;
    onCancel: () => void;
}

export const REPAYMENT_TEXT = {
    amount: wording("repayment.amount"),
    date: wording("repayment.date"),
    paidFrom: wording("repayment.paid_from"),
    principalPart: wording("repayment.principal_part"),
    interestPart: wording("repayment.interest_part"),
    save: wording("repayment.save"),
    saving: wording("repayment.saving"),
    cancel: wording("repayment.cancel"),
};

const AMOUNT = /^\d+(\.\d{1,2})?$/;

export default function RepaymentForm({ paidFrom, previewUrl, postUrl, today, idempotencyKey, onCancel }: Props) {
    const { dark } = useTheme();
    const { errors } = usePage().props as unknown as { errors: Record<string, string> };

    const [split, setSplit] = useState<Split | null>(null);
    // the server's own words when the amount is more than the loan owes, or the loan is fully repaid
    const [refusal, setRefusal] = useState<string | null>(null);

    const form = useForm({
        amount: "",
        date: today,
        paid_from: paidFrom[0]?.value ?? "",
        idempotency_key: idempotencyKey ?? "",
    });

    // the split is only ever shown, never typed: the server works it out again when it saves
    useEffect(() => {
        setRefusal(null);

        if (!AMOUNT.test(form.data.amount)) {
            setSplit(null);

            return;
        }

        const controller = new AbortController();
        const timer = setTimeout(async () => {
            try {
                const response = await fetch(previewUrl, {
                    method: "POST",
                    signal: controller.signal,
                    headers: { Accept: "application/json", "Content-Type": "application/json", "X-CSRF-TOKEN": csrfToken() },
                    body: JSON.stringify({ amount: form.data.amount }),
                });

                const body = await response.json();

                setSplit(response.ok ? body : null);
                setRefusal(response.ok ? null : (body.message ?? null));
            } catch {
                setSplit(null);
            }
        }, 250);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [form.data.amount, previewUrl]);

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const inputBorder = dark ? "#4B5563" : "#9CA3AF";
    const inputBg = dark ? "#111827" : "#FFFFFF";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";

    const field = { width: "100%", padding: "10px 12px", border: `1px solid ${inputBorder}`, background: inputBg, color: text, fontSize: "1.125rem" } as const;
    const label = { display: "block", fontSize: "1.0625rem", fontWeight: 600, color: text, marginBottom: "6px" } as const;
    const errorText = { fontSize: "0.9375rem", color: "#B91C1C", marginTop: "4px" } as const;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(postUrl);
    };

    return (
        <form onSubmit={submit} className="mt-4 p-4" style={{ background: surface, border: `1px solid ${border}`, maxWidth: "560px" }}>
            <div className="mb-4">
                <label htmlFor="repay-amount" style={label}>
                    {REPAYMENT_TEXT.amount}
                </label>
                <input
                    id="repay-amount"
                    type="text"
                    inputMode="decimal"
                    style={field}
                    value={form.data.amount}
                    onChange={(event) => form.setData("amount", event.target.value)}
                />
                {(errors.amount || refusal) && <p style={errorText}>{errors.amount || refusal}</p>}
            </div>

            <div className="mb-4">
                <label htmlFor="repay-date" style={label}>
                    {REPAYMENT_TEXT.date}
                </label>
                <input id="repay-date" type="date" style={field} value={form.data.date} onChange={(event) => form.setData("date", event.target.value)} />
                {errors.date && <p style={errorText}>{errors.date}</p>}
            </div>

            <div className="mb-4">
                <label htmlFor="repay-paid-from" style={label}>
                    {REPAYMENT_TEXT.paidFrom}
                </label>
                <select id="repay-paid-from" style={field} value={form.data.paid_from} onChange={(event) => form.setData("paid_from", event.target.value)}>
                    {paidFrom.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>
                {errors.paid_from && <p style={errorText}>{errors.paid_from}</p>}
            </div>

            {split !== null && (
                <dl className="mb-4" aria-label="split" style={{ color: text, fontSize: "1.0625rem" }}>
                    <div className="flex justify-between">
                        <dt style={{ color: textSecondary }}>{REPAYMENT_TEXT.principalPart}</dt>
                        <dd>GHS {cedis(split.principal)}</dd>
                    </div>
                    <div className="flex justify-between">
                        <dt style={{ color: textSecondary }}>{REPAYMENT_TEXT.interestPart}</dt>
                        <dd>GHS {cedis(split.interest)}</dd>
                    </div>
                </dl>
            )}

            <div className="flex flex-wrap gap-3">
                <Button type="submit" busy={form.processing} busyLabel={REPAYMENT_TEXT.saving}>
                    {REPAYMENT_TEXT.save}
                </Button>
                <Button type="button" look="secondary" onClick={onCancel}>
                    {REPAYMENT_TEXT.cancel}
                </Button>
            </div>
        </form>
    );
}
