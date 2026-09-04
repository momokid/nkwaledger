import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import Button from "@/Components/Button";
import { router, useForm, usePage } from "@inertiajs/react";
import { PageProps } from "@/types";
import { useState } from "react";

interface Row {
    uuid: string;
    reference: string;
    date: string;
    description: string;
    type: string;
    money_in: number;
    money_out: number;
    balance: number;
    is_provisional: boolean;
    cancel_state: "open" | "waiting" | "cancelled" | "correction";
    account: string | null;
    value_lost: number;
}

interface AccountOption {
    id: number;
    name: string;
}

interface Statement {
    rows: Row[];
    opening_balance: number;
    closing_balance: number;
    total_in: number;
    total_out: number;
    cancelled: number;
    provisional_held_back: number;
    total: number;
    page: number;
    last_page: number;
}

interface Props extends PageProps {
    farmer: { id: string; name: string };
    statement: Statement;
    filters: { from: string; to: string; account: number | null };
    accounts: AccountOption[];
    layout: "farmer" | "agent";
    basePath: string;
}

import { cedis, shortDate } from "@/lib/format";

export default function Index(props: Props) {
    return (
        <AuthenticatedLayout title="My records">
            <IndexContent {...props} />
        </AuthenticatedLayout>
    );
}

type ContentProps = Pick<
    Props,
    "farmer" | "statement" | "filters" | "accounts" | "layout" | "basePath"
>;

function IndexContent({
    farmer,
    statement,
    filters,
    accounts,
    layout,
    basePath,
}: ContentProps) {
    const { errors, flash } = usePage<Props>().props as ContentProps & {
        errors: Record<string, string>;
        flash: { success?: string };
    };
    const { dark } = useTheme();

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const inputBorder = dark ? "#4B5563" : "#9CA3AF";
    const inputBg = dark ? "#111827" : "#FFFFFF";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const headerBg = dark ? "rgba(29,158,117,0.15)" : "#EAF5F0";
    const rowAlt = dark ? "#111827" : "#F9FAFB";
    const warnBg = dark ? "rgba(180,83,9,0.15)" : "#FEF3C7";
    const noticeBg = dark ? "rgba(29,158,117,0.15)" : "#EAF5F0";
    const brand = "#1D9E75";

    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);
    const [account, setAccount] = useState(
        filters.account ? String(filters.account) : "",
    );
    const [loading, setLoading] = useState(false);
    const [cancelling, setCancelling] = useState<Row | null>(null);

    const form = useForm({ reason: "" });

    const visit = (params: Record<string, string | number>) => {
        setLoading(true);
        router.visit(basePath, {
            data: { from, to, ...(account ? { account } : {}), ...params },
            preserveState: true,
            onFinish: () => setLoading(false),
        });
    };

    const cancelUrl = (row: Row) =>
        layout === "agent"
            ? `/agent/farmers/${farmer.id}/records/${row.uuid}/cancel`
            : `/my-records/${row.uuid}/cancel`;

    const askToCancel = () => {
        if (cancelling === null) return;

        form.post(cancelUrl(cancelling), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setCancelling(null);
            },
        });
    };

    const field = {
        padding: "8px 10px",
        border: `1px solid ${inputBorder}`,
        background: inputBg,
        color: text,
        fontSize: "17px",
    } as const;

    const summary = [
        { label: "Money in", value: statement.total_in, colour: brand },
        { label: "Money out", value: statement.total_out, colour: "#B45309" },
        ...(statement.cancelled > 0
            ? [
                  {
                      label: "Cancelled",
                      value: statement.cancelled,
                      colour: textSecondary,
                  },
              ]
            : []),
        {
            label: "What is left",
            value: statement.closing_balance,
            colour: text,
        },
    ];

    return (
        <div
            className="p-6"
            style={{ background: surface, border: `1px solid ${border}` }}
        >
            <h2 style={{ fontSize: "22px", fontWeight: 700, color: text }}>
                {layout === "agent" ? `${farmer.name} — records` : "My records"}
            </h2>

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
                </div>
            )}

            <div className="mt-4 flex flex-wrap gap-4">
                {summary.map((item) => (
                    <div
                        key={item.label}
                        className="p-4"
                        style={{ background: headerBg, minWidth: "160px" }}
                    >
                        <p style={{ fontSize: "16px", color: textSecondary }}>
                            {item.label}
                        </p>
                        <p
                            style={{
                                fontSize: "22px",
                                fontWeight: 700,
                                color: item.colour,
                                marginTop: "2px",
                            }}
                        >
                            GHS {cedis(item.value)}
                        </p>
                    </div>
                ))}
            </div>

            {statement.provisional_held_back > 0 && (
                <div
                    className="mt-4 p-3"
                    style={{
                        background: warnBg,
                        color: "#B45309",
                        fontSize: "16px",
                    }}
                >
                    GHS {cedis(statement.provisional_held_back)} of this is on a
                    part of the farm nobody has checked yet. It stays in your
                    book, but it will not count toward a loan report until
                    someone visits.
                </div>
            )}

            <div className="mt-5 flex flex-wrap items-end gap-3">
                <div>
                    <label
                        style={{
                            display: "block",
                            fontSize: "16px",
                            color: textSecondary,
                            marginBottom: "4px",
                        }}
                    >
                        From
                    </label>
                    <input
                        type="date"
                        style={field}
                        value={from}
                        onChange={(event) => setFrom(event.target.value)}
                    />
                </div>
                <div>
                    <label
                        style={{
                            display: "block",
                            fontSize: "16px",
                            color: textSecondary,
                            marginBottom: "4px",
                        }}
                    >
                        To
                    </label>
                    <input
                        type="date"
                        style={field}
                        value={to}
                        onChange={(event) => setTo(event.target.value)}
                    />
                </div>
                <div>
                    <label
                        style={{
                            display: "block",
                            fontSize: "16px",
                            color: textSecondary,
                            marginBottom: "4px",
                        }}
                    >
                        Money kept in
                    </label>
                    <select
                        style={field}
                        value={account}
                        onChange={(event) => setAccount(event.target.value)}
                    >
                        <option value="">All</option>
                        {accounts.map((option) => (
                            <option key={option.id} value={option.id}>
                                {option.name}
                            </option>
                        ))}
                    </select>
                </div>
                <div>
                    <label
                        style={{
                            display: "block",
                            fontSize: "16px",
                            color: textSecondary,
                            marginBottom: "4px",
                        }}
                    >
                        Money kept in
                    </label>
                    <select
                        style={field}
                        value={account}
                        onChange={(event) => setAccount(event.target.value)}
                    >
                        <option value="">All</option>
                        {accounts.map((option) => (
                            <option key={option.id} value={option.id}>
                                {option.name}
                            </option>
                        ))}
                    </select>
                </div>
                <Button
                    onClick={() => visit({ page: 1 })}
                    busy={loading}
                    busyLabel="Loading..."
                >
                    Show
                </Button>
            </div>

            <div className="mt-5 overflow-x-auto">
                <table
                    className="w-full"
                    style={{ borderCollapse: "collapse" }}
                >
                    <thead>
                        <tr style={{ background: headerBg }}>
                            {[
                                "Date",
                                "What happened",
                                "Reference",
                                "In",
                                "Out",
                                "Left",
                                "",
                            ].map((heading, index) => (
                                <th
                                    key={index}
                                    className="px-4 py-3 text-left"
                                    style={{
                                        color: brand,
                                        fontSize: "16px",
                                        fontWeight: 600,
                                    }}
                                >
                                    {heading}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {loading && (
                            <tr>
                                <td
                                    colSpan={7}
                                    className="px-4 py-6 text-center"
                                    style={{ color: textSecondary }}
                                >
                                    Loading...
                                </td>
                            </tr>
                        )}

                        {!loading && statement.rows.length === 0 && (
                            <tr>
                                <td
                                    colSpan={7}
                                    className="px-4 py-6 text-center"
                                    style={{
                                        color: textSecondary,
                                        fontSize: "17px",
                                    }}
                                >
                                    Nothing here yet. Record something and it
                                    will show up.
                                </td>
                            </tr>
                        )}

                        {!loading &&
                            statement.rows.map((row, index) => (
                                <tr
                                    key={row.uuid}
                                    style={{
                                        borderTop: `1px solid ${border}`,
                                        background:
                                            index % 2 === 1
                                                ? rowAlt
                                                : "transparent",
                                        opacity:
                                            row.cancel_state === "cancelled"
                                                ? 0.6
                                                : 1,
                                    }}
                                >
                                    <td
                                        className="px-4 py-3"
                                        style={{ color: text }}
                                    >
                                        {shortDate(row.date)}
                                    </td>
                                    <td
                                        className="px-4 py-3"
                                        style={{ color: text }}
                                    >
                                        <span
                                            style={{
                                                textDecoration:
                                                    row.cancel_state ===
                                                    "cancelled"
                                                        ? "line-through"
                                                        : "none",
                                            }}
                                        >
                                            {row.description}
                                        </span>
                                        {row.account && (
                                            <span
                                                style={{
                                                    display: "block",
                                                    fontSize: "15px",
                                                    color: textSecondary,
                                                }}
                                            >
                                                {row.account}
                                            </span>
                                        )}
                                        {row.value_lost > 0 && (
                                            <span
                                                style={{
                                                    display: "block",
                                                    fontSize: "15px",
                                                    color: textSecondary,
                                                }}
                                            >
                                                Worth GHS{" "}
                                                {cedis(row.value_lost)}, no
                                                money moved
                                            </span>
                                        )}
                                        {row.is_provisional && (
                                            <span
                                                style={{
                                                    display: "block",
                                                    fontSize: "15px",
                                                    color: "#B45309",
                                                }}
                                            >
                                                Not counted yet
                                            </span>
                                        )}
                                        {row.cancel_state === "waiting" && (
                                            <span
                                                style={{
                                                    display: "block",
                                                    fontSize: "15px",
                                                    color: "#B45309",
                                                }}
                                            >
                                                Waiting for someone to agree
                                            </span>
                                        )}
                                        {row.cancel_state === "cancelled" && (
                                            <span
                                                style={{
                                                    display: "block",
                                                    fontSize: "15px",
                                                    color: textSecondary,
                                                }}
                                            >
                                                Cancelled
                                            </span>
                                        )}
                                        {row.cancel_state === "correction" && (
                                            <span
                                                style={{
                                                    display: "inline-block",
                                                    marginTop: "4px",
                                                    padding: "1px 8px",
                                                    fontSize: "13px",
                                                    fontWeight: 600,
                                                    color: "#7C3AED",
                                                    background:
                                                        "rgba(124, 58, 237, 0.12)",
                                                    border: "1px solid #7C3AED",
                                                }}
                                            >
                                                Corrected
                                            </span>
                                        )}
                                    </td>
                                    <td
                                        className="px-4 py-3"
                                        style={{
                                            color: textSecondary,
                                            fontSize: "16px",
                                        }}
                                    >
                                        {row.reference}
                                    </td>
                                    <td
                                        className="px-4 py-3"
                                        style={{
                                            color:
                                                row.cancel_state ===
                                                    "correction" ||
                                                row.cancel_state === "cancelled"
                                                    ? textSecondary
                                                    : row.money_in > 0
                                                      ? brand
                                                      : textSecondary,
                                        }}
                                    >
                                        {row.money_in > 0
                                            ? cedis(row.money_in)
                                            : "—"}
                                    </td>
                                    <td
                                        className="px-4 py-3"
                                        style={{
                                            color:
                                                row.cancel_state ===
                                                    "correction" ||
                                                row.cancel_state === "cancelled"
                                                    ? textSecondary
                                                    : row.money_out > 0
                                                      ? "#B45309"
                                                      : textSecondary,
                                        }}
                                    >
                                        {row.money_out > 0
                                            ? cedis(row.money_out)
                                            : "—"}
                                    </td>
                                    <td
                                        className="px-4 py-3"
                                        style={{ color: text, fontWeight: 600 }}
                                    >
                                        {cedis(row.balance)}
                                    </td>
                                    <td className="px-4 py-3">
                                        {row.cancel_state === "open" && (
                                            <Button
                                                look="secondary"
                                                size="small"
                                                onClick={() =>
                                                    setCancelling(row)
                                                }
                                            >
                                                Cancel this
                                            </Button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                    </tbody>
                </table>
            </div>

            {statement.last_page > 1 && (
                <div className="mt-4 flex items-center gap-3">
                    <Button
                        look="secondary"
                        size="small"
                        disabled={statement.page <= 1}
                        onClick={() => visit({ page: statement.page - 1 })}
                    >
                        Back
                    </Button>
                    <span style={{ color: textSecondary, fontSize: "16px" }}>
                        Page {statement.page} of {statement.last_page}
                    </span>
                    <Button
                        look="secondary"
                        size="small"
                        disabled={statement.page >= statement.last_page}
                        onClick={() => visit({ page: statement.page + 1 })}
                    >
                        Next
                    </Button>
                </div>
            )}

            {cancelling !== null && (
                <div
                    className="fixed inset-0 flex items-center justify-center p-4"
                    style={{ background: "rgba(0,0,0,0.5)", zIndex: 50 }}
                >
                    <div
                        className="p-6"
                        style={{
                            background: surface,
                            border: `1px solid ${border}`,
                            maxWidth: "460px",
                            width: "100%",
                        }}
                    >
                        <h3
                            style={{
                                fontSize: "20px",
                                fontWeight: 700,
                                color: text,
                            }}
                        >
                            Cancel this record?
                        </h3>

                        <p
                            style={{
                                fontSize: "17px",
                                color: textSecondary,
                                marginTop: "6px",
                            }}
                        >
                            {cancelling.description}, reference{" "}
                            {cancelling.reference}.
                        </p>

                        <p
                            style={{
                                fontSize: "16px",
                                color: textSecondary,
                                marginTop: "10px",
                            }}
                        >
                            Nothing is rubbed out. Somebody else has to agree,
                            then a correction goes into your book next to the
                            original.
                        </p>

                        <label
                            style={{
                                display: "block",
                                fontSize: "17px",
                                fontWeight: 600,
                                color: text,
                                marginTop: "16px",
                                marginBottom: "6px",
                            }}
                        >
                            What went wrong?
                        </label>
                        <textarea
                            rows={3}
                            style={{ ...field, width: "100%" }}
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData("reason", event.target.value)
                            }
                        />
                        {errors.reason && (
                            <p
                                style={{
                                    fontSize: "15px",
                                    color: "#B91C1C",
                                    marginTop: "4px",
                                }}
                            >
                                {errors.reason}
                            </p>
                        )}

                        <div
                            className="flex gap-3"
                            style={{ marginTop: "18px" }}
                        >
                            <Button
                                onClick={askToCancel}
                                busy={form.processing}
                                busyLabel="Sending..."
                            >
                                Ask to cancel
                            </Button>
                            <Button
                                look="secondary"
                                onClick={() => {
                                    form.reset();
                                    setCancelling(null);
                                }}
                            >
                                Keep it
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
