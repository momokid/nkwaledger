import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import { router } from "@inertiajs/react";
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
}

interface Statement {
    rows: Row[];
    opening_balance: number;
    closing_balance: number;
    total_in: number;
    total_out: number;
    provisional_held_back: number;
    total: number;
    page: number;
    last_page: number;
}

interface Props extends PageProps {
    farmer: { id: string; name: string };
    statement: Statement;
    filters: { from: string; to: string };
    layout: "farmer" | "agent";
    basePath: string;
}

const cedis = (minor: number) =>
    (minor / 100).toLocaleString("en-GH", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

export default function Index(props: Props) {
    return (
        <AuthenticatedLayout title="My records">
            <IndexContent {...props} />
        </AuthenticatedLayout>
    );
}

type ContentProps = Pick<
    Props,
    "farmer" | "statement" | "filters" | "layout" | "basePath"
>;

function IndexContent({
    farmer,
    statement,
    filters,
    layout,
    basePath,
}: ContentProps) {
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
    const brand = "#1D9E75";

    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);
    const [loading, setLoading] = useState(false);

    const visit = (params: Record<string, string | number>) => {
        setLoading(true);
        router.visit(basePath, {
            data: { from, to, ...params },
            preserveState: true,
            onFinish: () => setLoading(false),
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
                <button
                    onClick={() => visit({ page: 1 })}
                    style={{
                        padding: "9px 20px",
                        background: brand,
                        color: "#FFFFFF",
                        fontSize: "17px",
                        fontWeight: 600,
                    }}
                >
                    Show
                </button>
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
                            ].map((heading) => (
                                <th
                                    key={heading}
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
                                    colSpan={6}
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
                                    colSpan={6}
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
                                    }}
                                >
                                    <td
                                        className="px-4 py-3"
                                        style={{ color: text }}
                                    >
                                        {row.date}
                                    </td>
                                    <td
                                        className="px-4 py-3"
                                        style={{ color: text }}
                                    >
                                        {row.description}
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
                                                row.money_in > 0
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
                                                row.money_out > 0
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
                                </tr>
                            ))}
                    </tbody>
                </table>
            </div>

            {statement.last_page > 1 && (
                <div className="mt-4 flex items-center gap-3">
                    <button
                        disabled={statement.page <= 1}
                        onClick={() => visit({ page: statement.page - 1 })}
                        style={{
                            padding: "8px 16px",
                            border: `1px solid ${inputBorder}`,
                            color: text,
                            opacity: statement.page <= 1 ? 0.4 : 1,
                        }}
                    >
                        Back
                    </button>
                    <span style={{ color: textSecondary, fontSize: "16px" }}>
                        Page {statement.page} of {statement.last_page}
                    </span>
                    <button
                        disabled={statement.page >= statement.last_page}
                        onClick={() => visit({ page: statement.page + 1 })}
                        style={{
                            padding: "8px 16px",
                            border: `1px solid ${inputBorder}`,
                            color: text,
                            opacity:
                                statement.page >= statement.last_page ? 0.4 : 1,
                        }}
                    >
                        Next
                    </button>
                </div>
            )}
        </div>
    );
}
