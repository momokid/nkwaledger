import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import { useForm, usePage } from "@inertiajs/react";
import { PageProps } from "@/types";
import { FormEvent, useMemo } from "react";
import Button from "@/Components/Button";

interface Template {
    id: number;
    name: string;
    transaction_type: string;
    settlement_side: string;
    requires_farm_unit: boolean;
    is_produce_sale: boolean;
}

interface AccountOption {
    id: number;
    name: string;
}

interface UnitOption {
    id: number;
    name: string;
    is_approved: boolean;
}

interface Props extends PageProps {
    farmer: { id: string; name: string };
    templates: Template[];
    settlementAccounts: AccountOption[];
    farmUnits: UnitOption[];
    layout: "farmer" | "agent";
    basePath: string;
}

export default function Create(props: Props) {
    return (
        <AuthenticatedLayout title="Record something">
            <CreateContent {...props} />
        </AuthenticatedLayout>
    );
}

type ContentProps = Pick
    Props,
    | "farmer"
    | "templates"
    | "settlementAccounts"
    | "farmUnits"
    | "layout"
    | "basePath"
>;

function CreateContent({
    farmer,
    templates,
    settlementAccounts,
    farmUnits,
    layout,
    basePath,
}: ContentProps) {
    const { errors, flash, old } = usePage<Props>().props as ContentProps & {
        errors: Record<string, string>;
        flash: { success?: string; reference?: string };
        old: Record<string, string>;
    };
    const { dark } = useTheme();

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const inputBorder = dark ? "#4B5563" : "#9CA3AF";
    const inputBg = dark ? "#111827" : "#FFFFFF";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const noticeBg = dark ? "rgba(29,158,117,0.15)" : "#EAF5F0";
    const warnBg = dark ? "rgba(180,83,9,0.15)" : "#FEF3C7";
    const brand = "#1D9E75";

    const today = new Date().toISOString().slice(0, 10);

    const form = useForm({
        transaction_template_id: old.transaction_template_id ?? "",
        amount: old.amount ?? "",
        settlement_account_id: old.settlement_account_id ?? "",
        transaction_date: old.transaction_date ?? today,
        farm_unit_id: old.farm_unit_id ?? "",
        quantity_lost: old.quantity_lost ?? "",
        quantity_sold: old.quantity_sold ?? "",
        narration: old.narration ?? "",
    });

    const chosen = useMemo(
        () =>
            templates.find(
                (item) => String(item.id) === form.data.transaction_template_id,
            ) ?? null,
        [templates, form.data.transaction_template_id],
    );

    // a loss moves no money, so nobody is asked where it went
    const needsAccount = chosen !== null && chosen.settlement_side !== "none";
    const needsUnit = chosen?.requires_farm_unit ?? false;
    const needsQuantityLost = chosen?.transaction_type === "LOSS";
    const needsQuantitySold = chosen?.is_produce_sale ?? false;

    const chosenUnit =
        farmUnits.find((unit) => String(unit.id) === form.data.farm_unit_id) ??
        null;

    const postUrl =
        layout === "agent"
            ? `/agent/farmers/${farmer.id}/records`
            : "/my-records";

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.post(postUrl, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () =>
                form.reset(
                    "amount",
                    "quantity_lost",
                    "quantity_sold",
                    "narration",
                ),
        });
    };

    const field = {
        width: "100%",
        padding: "10px 12px",
        border: `1px solid ${inputBorder}`,
        background: inputBg,
        color: text,
        fontSize: "18px",
    } as const;

    const label = {
        display: "block",
        fontSize: "17px",
        fontWeight: 600,
        color: text,
        marginBottom: "6px",
    } as const;

    const errorText = {
        fontSize: "15px",
        color: "#B91C1C",
        marginTop: "4px",
    } as const;

    return (
        <div
            className="p-6"
            style={{
                background: surface,
                border: `1px solid ${border}`,
                maxWidth: "560px",
            }}
        >
            <h2 style={{ fontSize: "22px", fontWeight: 700, color: text }}>
                {layout === "agent"
                    ? `Record for ${farmer.name}`
                    : "Record something"}
            </h2>
            <p
                style={{
                    fontSize: "17px",
                    color: textSecondary,
                    marginTop: "4px",
                }}
            >
                Answer the questions below. We will keep the book for you.
            </p>

            {flash?.success && (
                <div
                    className="mt-4 p-3"
                    style={{
                        background: noticeBg,
                        color: brand,
                        fontSize: "17px",
                    }}
                >
                    {flash.success}
                    {flash.reference && (
                        <span
                            style={{
                                display: "block",
                                color: textSecondary,
                                marginTop: "2px",
                            }}
                        >
                            Reference {flash.reference}
                        </span>
                    )}
                </div>
            )}

            <form onSubmit={submit} className="mt-5">
                <div className="mb-4">
                    <label style={label}>What happened?</label>
                    <select
                        style={field}
                        value={form.data.transaction_template_id}
                        onChange={(event) => {
                            form.setData(
                                "transaction_template_id",
                                event.target.value,
                            );
                            form.setData("settlement_account_id", "");
                            form.setData("farm_unit_id", "");
                            form.setData("quantity_lost", "");
                            form.setData("quantity_sold", "");
                        }}
                    >
                        <option value="">Choose one</option>
                        {templates.map((template) => (
                            <option key={template.id} value={template.id}>
                                {template.name}
                            </option>
                        ))}
                    </select>
                    {errors.transaction_template_id && (
                        <p style={errorText}>
                            {errors.transaction_template_id}
                        </p>
                    )}
                </div>

                <div className="mb-4">
                    <label style={label}>How much, in cedis?</label>
                    <input
                        type="text"
                        inputMode="decimal"
                        placeholder="250.75"
                        style={field}
                        value={form.data.amount}
                        onChange={(event) =>
                            form.setData("amount", event.target.value)
                        }
                    />
                    {errors.amount && <p style={errorText}>{errors.amount}</p>}
                </div>

                {needsQuantityLost && (
                    <div className="mb-4">
                        <label style={label}>How many were lost?</label>
                        <input
                            type="text"
                            inputMode="decimal"
                            placeholder="e.g. 6"
                            style={field}
                            value={form.data.quantity_lost}
                            onChange={(event) =>
                                form.setData(
                                    "quantity_lost",
                                    event.target.value,
                                )
                            }
                        />
                        {errors.quantity_lost && (
                            <p style={errorText}>{errors.quantity_lost}</p>
                        )}
                    </div>
                )}

                {needsQuantitySold && (
                    <div className="mb-4">
                        <label style={label}>How many did you sell?</label>
                        <input
                            type="text"
                            inputMode="decimal"
                            placeholder="e.g. 6"
                            style={field}
                            value={form.data.quantity_sold}
                            onChange={(event) =>
                                form.setData(
                                    "quantity_sold",
                                    event.target.value,
                                )
                            }
                        />
                        {errors.quantity_sold && (
                            <p style={errorText}>{errors.quantity_sold}</p>
                        )}
                    </div>
                )}

                {needsAccount && (
                    <div className="mb-4">
                        <label style={label}>
                            {chosen?.settlement_side === "debit"
                                ? "Where did the money go?"
                                : "Where did the money come from?"}
                        </label>
                        <select
                            style={field}
                            value={form.data.settlement_account_id}
                            onChange={(event) =>
                                form.setData(
                                    "settlement_account_id",
                                    event.target.value,
                                )
                            }
                        >
                            <option value="">Choose one</option>
                            {settlementAccounts.map((account) => (
                                <option key={account.id} value={account.id}>
                                    {account.name}
                                </option>
                            ))}
                        </select>
                        {errors.settlement_account_id && (
                            <p style={errorText}>
                                {errors.settlement_account_id}
                            </p>
                        )}
                    </div>
                )}

                <div className="mb-4">
                    <label style={label}>When did it happen?</label>
                    <input
                        type="date"
                        max={today}
                        style={field}
                        value={form.data.transaction_date}
                        onChange={(event) =>
                            form.setData("transaction_date", event.target.value)
                        }
                    />
                    {errors.transaction_date && (
                        <p style={errorText}>{errors.transaction_date}</p>
                    )}
                </div>

                {needsUnit && (
                    <div className="mb-4">
                        <label style={label}>
                            Which farm did this happen on
                        </label>
                        <select
                            style={field}
                            value={form.data.farm_unit_id}
                            onChange={(event) =>
                                form.setData("farm_unit_id", event.target.value)
                            }
                        >
                            <option value="">Choose one</option>
                            {farmUnits.map((unit) => (
                                <option key={unit.id} value={unit.id}>
                                    {unit.name}
                                    {!unit.is_approved
                                        ? " (not checked yet)"
                                        : ""}
                                </option>
                            ))}
                        </select>
                        {errors.farm_unit_id && (
                            <p style={errorText}>{errors.farm_unit_id}</p>
                        )}

                        {chosenUnit && !chosenUnit.is_approved && (
                            <div
                                className="mt-2 p-3"
                                style={{
                                    background: warnBg,
                                    color: "#B45309",
                                    fontSize: "16px",
                                }}
                            >
                                This part of the farm has not been checked yet.
                                We will keep your record, but it will not count
                                toward your loan report until someone visits.
                            </div>
                        )}
                    </div>
                )}

                <div className="mb-5">
                    <label style={label}>A note, if you want one</label>
                    <input
                        type="text"
                        placeholder="Sold maize at Kejetia"
                        style={field}
                        value={form.data.narration}
                        onChange={(event) =>
                            form.setData("narration", event.target.value)
                        }
                    />
                    {errors.narration && (
                        <p style={errorText}>{errors.narration}</p>
                    )}
                </div>

                <Button
                    type="submit"
                    busy={form.processing}
                    busyLabel="Saving..."
                >
                    Save this record
                </Button>
            </form>
        </div>
    );
}