import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import Button from "@/Components/Button";
import { router, useForm, usePage } from "@inertiajs/react";
import { PageProps } from "@/types";
import { csrfToken } from "@/lib/syncHttp";
import { FormEvent, useEffect, useState } from "react";

// placeholder keys: the wording for these is not approved yet
const LENDER_TEXT = { notInList: "lender.not_in_list" };

// Bank, Microfinance and Other are the approved words; the credit union label is a placeholder key
const TYPE_LABELS: Record<string, string> = { bank: "Bank", microfinance: "Microfinance", other: "Other" };
const typeLabel = (type: string) => TYPE_LABELS[type] ?? `lender.type.${type}`;

interface Found {
    uuid: string;
    name: string;
    type: string;
}

interface PaidIntoOption {
    id: number;
    label: string;
}

interface Props extends PageProps {
    kind: "money" | "existing";
    lenderTypes: string[];
    paidInto: PaidIntoOption[];
    idempotencyKey: string;
    // an agent filling the form in for a farmer: another address to post to and to go back to
    agent: boolean;
    postUrl: string;
    backUrl: string;
}

export default function Create(props: Props) {
    return (
        <AuthenticatedLayout title="Loans">
            <CreateContent {...props} />
        </AuthenticatedLayout>
    );
}

type ContentProps = Pick<Props, "kind" | "lenderTypes" | "paidInto" | "idempotencyKey" | "agent" | "postUrl" | "backUrl">;

function CreateContent({ kind, lenderTypes, paidInto, idempotencyKey, agent, postUrl, backUrl }: ContentProps) {
    const { errors, old } = usePage<Props>().props as unknown as {
        errors: Record<string, string>;
        old: Record<string, string>;
    };
    const { dark } = useTheme();

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const inputBorder = dark ? "#4B5563" : "#9CA3AF";
    const inputBg = dark ? "#111827" : "#FFFFFF";
    const text = dark ? "#F9FAFB" : "#111827";

    const isMoney = kind === "money";
    const today = new Date().toISOString().slice(0, 10);

    const form = useForm({
        kind,
        lender_name: old?.lender_name ?? "",
        lender_type: old?.lender_type ?? "",
        lender: old?.lender ?? "",
        amount: old?.amount ?? "",
        date: old?.date ?? today,
        paid_into: old?.paid_into ?? "",
        interest: old?.interest ?? "",
        due_date: old?.due_date ?? "",
        note: old?.note ?? "",
        idempotency_key: idempotencyKey,
    });

    const institution = form.data.lender_type !== "";
    const [found, setFound] = useState<Found[] | null>(null);

    // an institution is picked from the list: what is typed here only searches, it is never saved as a name
    useEffect(() => {
        setFound(null);

        if (!institution || form.data.lender !== "" || form.data.lender_name.trim().length < 2) {
            return;
        }

        const controller = new AbortController();
        const timer = setTimeout(async () => {
            try {
                const response = await fetch("/lenders/search", {
                    method: "POST",
                    signal: controller.signal,
                    headers: { Accept: "application/json", "Content-Type": "application/json", "X-CSRF-TOKEN": csrfToken() },
                    body: JSON.stringify({ q: form.data.lender_name, type: form.data.lender_type }),
                });

                setFound(response.ok ? await response.json() : null);
            } catch {
                setFound(null);
            }
        }, 250);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [institution, form.data.lender_name, form.data.lender, form.data.lender_type]);

    const field = {
        width: "100%",
        padding: "10px 12px",
        border: `1px solid ${inputBorder}`,
        background: inputBg,
        color: text,
        fontSize: "1.125rem",
    } as const;

    const label = {
        display: "block",
        fontSize: "1.0625rem",
        fontWeight: 600,
        color: text,
        marginBottom: "6px",
    } as const;

    const errorText = {
        fontSize: "0.9375rem",
        color: "#B91C1C",
        marginTop: "4px",
    } as const;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(postUrl);
    };

    const text_ = (name: keyof typeof form.data, id: string, title: string, type = "text", inputMode?: "decimal") => (
        <div className="mb-4">
            <label htmlFor={id} style={label}>
                {title}
            </label>
            <input
                id={id}
                type={type}
                inputMode={inputMode}
                style={field}
                value={form.data[name] as string}
                onChange={(event) => form.setData(name, event.target.value as never)}
            />
            {errors[name] && <p style={errorText}>{errors[name]}</p>}
        </div>
    );

    return (
        <div
            className="p-6"
            style={{ background: surface, border: `1px solid ${border}`, maxWidth: "560px" }}
        >
            <form onSubmit={submit}>
                <div className="mb-4">
                    <label htmlFor="lender_name" style={label}>
                        Lender name
                    </label>
                    <input
                        id="lender_name"
                        type="text"
                        autoComplete="off"
                        style={field}
                        value={form.data.lender_name}
                        onChange={(event) => {
                            form.setData("lender_name", event.target.value);
                            form.setData("lender", "");
                        }}
                    />
                    {institution && found !== null && found.length > 0 && (
                        <ul role="listbox" style={{ border: `1px solid ${inputBorder}`, background: inputBg }}>
                            {found.map((item) => (
                                <li key={item.uuid}>
                                    <button
                                        type="button"
                                        role="option"
                                        aria-selected={false}
                                        style={{ display: "block", width: "100%", textAlign: "left", padding: "8px 12px", color: text }}
                                        onClick={() => {
                                            form.setData("lender_name", item.name);
                                            form.setData("lender", item.uuid);
                                            setFound(null);
                                        }}
                                    >
                                        {item.name}
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                    {institution && found !== null && found.length === 0 && <p style={errorText}>{LENDER_TEXT.notInList}</p>}
                    {errors.lender_name && <p style={errorText}>{errors.lender_name}</p>}
                    {errors.lender && <p style={errorText}>{errors.lender}</p>}
                </div>

                <div className="mb-4">
                    <label htmlFor="lender_type" style={label}>
                        Lender type
                    </label>
                    <select
                        id="lender_type"
                        style={field}
                        value={form.data.lender_type}
                        onChange={(event) => {
                            form.setData("lender_type", event.target.value);
                            form.setData("lender", "");
                        }}
                    >
                        <option value=""></option>
                        {lenderTypes.map((type) => (
                            <option key={type} value={type}>
                                {typeLabel(type)}
                            </option>
                        ))}
                    </select>
                    {errors.lender_type && <p style={errorText}>{errors.lender_type}</p>}
                </div>

                {text_("amount", "amount", isMoney ? "Amount received" : agent ? "Loan Amount" : "Amount you still owe", "text", "decimal")}

                {isMoney && text_("date", "date", "Date received", "date")}

                {isMoney && (
                    <div className="mb-4">
                        <label htmlFor="paid_into" style={label}>
                            Paid into
                        </label>
                        <select
                            id="paid_into"
                            style={field}
                            value={form.data.paid_into}
                            onChange={(event) => form.setData("paid_into", event.target.value)}
                        >
                            <option value=""></option>
                            {paidInto.map((option) => (
                                <option key={option.id} value={option.id}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        {errors.paid_into && <p style={errorText}>{errors.paid_into}</p>}
                    </div>
                )}

                {text_(
                    "interest",
                    "interest",
                    isMoney ? "Total interest agreed (optional)" : "Interest still to pay (optional)",
                    "text",
                    "decimal",
                )}

                {text_("due_date", "due_date", "Due date (optional)", "date")}

                {text_("note", "note", "Note (optional)")}

                <div className="flex flex-wrap gap-3">
                    <Button type="submit" busy={form.processing} busyLabel="Saving...">
                        Save this record
                    </Button>
                    <Button type="button" look="secondary" onClick={() => router.visit(backUrl)}>
                        Cancel
                    </Button>
                </div>
            </form>
        </div>
    );
}
