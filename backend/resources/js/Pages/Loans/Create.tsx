import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import Button from "@/Components/Button";
import { useForm, usePage } from "@inertiajs/react";
import { PageProps } from "@/types";
import { FormEvent } from "react";

interface PaidIntoOption {
    id: number;
    label: string;
}

interface Props extends PageProps {
    kind: "money" | "existing";
    lenderTypes: string[];
    paidInto: PaidIntoOption[];
    idempotencyKey: string;
}

const capital = (value: string) => value.charAt(0).toUpperCase() + value.slice(1);

export default function Create(props: Props) {
    return (
        <AuthenticatedLayout title="Loans">
            <CreateContent {...props} />
        </AuthenticatedLayout>
    );
}

type ContentProps = Pick<Props, "kind" | "lenderTypes" | "paidInto" | "idempotencyKey">;

function CreateContent({ kind, lenderTypes, paidInto, idempotencyKey }: ContentProps) {
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
        amount: old?.amount ?? "",
        date: old?.date ?? today,
        paid_into: old?.paid_into ?? "",
        interest: old?.interest ?? "",
        due_date: old?.due_date ?? "",
        note: old?.note ?? "",
        idempotency_key: idempotencyKey,
    });

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
        form.post("/loans");
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
                {text_("lender_name", "lender_name", "Lender name")}

                <div className="mb-4">
                    <label htmlFor="lender_type" style={label}>
                        Lender type
                    </label>
                    <select
                        id="lender_type"
                        style={field}
                        value={form.data.lender_type}
                        onChange={(event) => form.setData("lender_type", event.target.value)}
                    >
                        <option value=""></option>
                        {lenderTypes.map((type) => (
                            <option key={type} value={type}>
                                {capital(type)}
                            </option>
                        ))}
                    </select>
                    {errors.lender_type && <p style={errorText}>{errors.lender_type}</p>}
                </div>

                {text_("amount", "amount", isMoney ? "Amount received" : "Amount you still owe", "text", "decimal")}

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

                <Button type="submit" busy={form.processing} busyLabel="Saving...">
                    Save this record
                </Button>
            </form>
        </div>
    );
}
