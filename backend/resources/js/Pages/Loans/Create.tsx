import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import { wording } from "@/lib/wording";
import Button from "@/Components/Button";
import { router, useForm, usePage } from "@inertiajs/react";
import { PageProps } from "@/types";
import { csrfToken } from "@/lib/syncHttp";
import { FormEvent, useEffect, useRef, useState } from "react";

const LENDER_TEXT = { notInList: wording("lender.not_in_list") };

const typeLabel = (type: string) => wording(`lender.type.${type}`);

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

type ContentProps = Pick<Props, "kind" | "paidInto" | "idempotencyKey" | "agent" | "postUrl" | "backUrl">;

function CreateContent({ kind, paidInto, idempotencyKey, agent, postUrl, backUrl }: ContentProps) {
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
        lender: old?.lender ?? "",
        amount: old?.amount ?? "",
        date: old?.date ?? today,
        paid_into: old?.paid_into ?? "",
        interest: old?.interest ?? "",
        due_date: old?.due_date ?? "",
        note: old?.note ?? "",
        idempotency_key: idempotencyKey,
    });

    const [found, setFound] = useState<Found[] | null>(null);
    const [pickedType, setPickedType] = useState("");
    const optionRefs = useRef<(HTMLButtonElement | null)[]>([]);

    // a lender is picked from the list: what is typed here only searches, and only the picked id is sent
    useEffect(() => {
        setFound(null);

        if (form.data.lender !== "" || form.data.lender_name.trim().length < 2) {
            return;
        }

        const controller = new AbortController();
        const timer = setTimeout(async () => {
            try {
                const response = await fetch("/lenders/search", {
                    method: "POST",
                    signal: controller.signal,
                    headers: { Accept: "application/json", "Content-Type": "application/json", "X-CSRF-TOKEN": csrfToken() },
                    body: JSON.stringify({ q: form.data.lender_name }),
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
    }, [form.data.lender_name, form.data.lender]);

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

        if (form.data.lender === "") {
            return;
        }

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
                        role="combobox"
                        aria-expanded={found !== null && found.length > 0}
                        aria-controls="lender_results"
                        aria-autocomplete="list"
                        autoComplete="off"
                        style={field}
                        value={form.data.lender_name}
                        onChange={(event) => {
                            form.setData("lender_name", event.target.value);
                            form.setData("lender", "");
                            setPickedType("");
                        }}
                        onKeyDown={(event) => {
                            if (event.key === "ArrowDown" && found !== null && found.length > 0) {
                                event.preventDefault();
                                optionRefs.current[0]?.focus();
                            }

                            if (event.key === "Escape") {
                                setFound(null);
                            }
                        }}
                    />
                    {found !== null && found.length > 0 && (
                        <ul id="lender_results" role="listbox" style={{ border: `1px solid ${inputBorder}`, background: inputBg }}>
                            {found.map((item, index) => (
                                <li key={item.uuid}>
                                    <button
                                        type="button"
                                        role="option"
                                        aria-selected={false}
                                        ref={(node) => {
                                            optionRefs.current[index] = node;
                                        }}
                                        style={{
                                            display: "flex",
                                            justifyContent: "space-between",
                                            alignItems: "center",
                                            gap: "12px",
                                            width: "100%",
                                            minHeight: "48px",
                                            textAlign: "left",
                                            padding: "12px 16px",
                                            fontSize: "1.0625rem",
                                            color: text,
                                        }}
                                        onKeyDown={(event) => {
                                            if (event.key === "ArrowDown") {
                                                event.preventDefault();
                                                optionRefs.current[index + 1]?.focus();
                                            }

                                            if (event.key === "ArrowUp") {
                                                event.preventDefault();
                                                (optionRefs.current[index - 1] ?? document.getElementById("lender_name"))?.focus();
                                            }

                                            if (event.key === "Escape") {
                                                setFound(null);
                                                document.getElementById("lender_name")?.focus();
                                            }
                                        }}
                                        onClick={() => {
                                            form.setData("lender_name", item.name);
                                            form.setData("lender", item.uuid);
                                            setPickedType(item.type);
                                            setFound(null);
                                        }}
                                    >
                                        <span>{item.name}</span>
                                        <span style={{ fontSize: "0.9375rem", opacity: 0.75 }}>{typeLabel(item.type)}</span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                    {pickedType !== "" && form.data.lender !== "" && <p style={{ fontSize: "0.9375rem", marginTop: "4px", color: text }}>{typeLabel(pickedType)}</p>}
                    {found !== null && found.length === 0 && <p style={errorText}>{LENDER_TEXT.notInList}</p>}
                    {errors.lender && <p style={errorText}>{errors.lender}</p>}
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
                    <Button type="submit" busy={form.processing} busyLabel="Saving..." disabled={form.data.lender === ""}>
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
