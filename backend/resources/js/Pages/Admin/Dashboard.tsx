import AdminLayout from "@/Layouts/AdminLayout";
import { useTheme } from "@/Layouts/AuthenticatedLayout";
import GreetingHeader from "@/Components/GreetingHeader";
import Button from "@/Components/Button";
import { cedis as formatMoney } from "@/lib/format";
import { router } from "@inertiajs/react";
import { useState } from "react";

type Sort = "net" | "activity";

interface Snapshot {
    active_farmers: number;
    active_agents: number;
    total_farmers: number;
    total_agents: number;
    total_income: number;
    total_expense: number;
    net: number;
}

interface LeaderboardRow {
    agent_id: number;
    name: string;
    farmer_count: number;
    new_farmers: number;
    records_logged: number;
    income: number;
    expense: number;
    net: number;
    rank: number;
}

interface Filters {
    from: string;
    to: string;
}

interface Props {
    snapshot: Snapshot;
    leaderboard: LeaderboardRow[];
    sort: Sort;
    filters: Filters;
}

export default function Dashboard(props: Props) {
    return (
        <AdminLayout title="Dashboard">
            <DashboardContent {...props} />
        </AdminLayout>
    );
}

function DashboardContent({ snapshot, leaderboard, sort, filters }: Props) {
    const { dark } = useTheme();
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);
    const [loading, setLoading] = useState(false);

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const inputBorder = dark ? "#4B5563" : "#9CA3AF";
    const inputBg = dark ? "#111827" : "#FFFFFF";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const brand = "#1D9E75";

    const field = {
        padding: "8px 10px",
        border: `1px solid ${inputBorder}`,
        background: inputBg,
        color: text,
        fontSize: "1.0625rem",
    } as const;

    const visit = (params: Record<string, string>) => {
        setLoading(true);

        router.get(
            route("admin.dashboard"),
            { from, to, sort, ...params },
            { preserveState: true, preserveScroll: true, onFinish: () => setLoading(false) },
        );
    };

    const setSort = (value: Sort) => visit({ sort: value });

    const snapshotCards = [
        {
            label: "Active farmers (30 days)",
            value: snapshot.active_farmers.toLocaleString(),
            secondary: `of ${snapshot.total_farmers.toLocaleString()} total`,
        },
        {
            label: "Active agents (30 days)",
            value: snapshot.active_agents.toLocaleString(),
            secondary: `of ${snapshot.total_agents.toLocaleString()} total`,
        },
        {
            label: "Income",
            value: `GHS ${formatMoney(snapshot.total_income)}`,
        },
        {
            label: "Expense",
            value: `GHS ${formatMoney(snapshot.total_expense)}`,
        },
        {
            label: "Net",
            value: `GHS ${formatMoney(snapshot.net)}`,
        },
    ];

    return (
        <div className="p-6 space-y-6">
            <GreetingHeader subtitle="Here is what's happening across the platform." />

            <div
                className="flex flex-wrap items-end gap-3"
                style={{
                    background: surface,
                    border: `1px solid ${border}`,
                    padding: "16px",
                }}
            >
                <div>
                    <label
                        style={{
                            display: "block",
                            fontSize: "1rem",
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
                            fontSize: "1rem",
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

                <Button onClick={() => visit({})} busy={loading} busyLabel="Loading...">
                    Show
                </Button>
            </div>

            <div
                style={{
                    display: "grid",
                    gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))",
                    gap: "16px",
                }}
            >
                {snapshotCards.map((card) => (
                    <div
                        key={card.label}
                        style={{
                            background: surface,
                            border: `1px solid ${border}`,
                            padding: "18px",
                        }}
                    >
                        <p
                            style={{
                                fontSize: "1rem",
                                color: textSecondary,
                                marginBottom: "8px",
                            }}
                        >
                            {card.label}
                        </p>
                        <p
                            style={{
                                fontSize: "1.625rem",
                                fontWeight: 700,
                                color: text,
                                letterSpacing: "-0.5px",
                                marginBottom: card.secondary ? "2px" : 0,
                            }}
                        >
                            {card.value}
                        </p>
                        {card.secondary && (
                            <p style={{ fontSize: "0.8125rem", color: textSecondary }}>
                                {card.secondary}
                            </p>
                        )}
                    </div>
                ))}
            </div>

            <div
                style={{
                    background: surface,
                    border: `1px solid ${border}`,
                    padding: "20px",
                }}
            >
                <div className="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <p
                        style={{
                            fontSize: "1.25rem",
                            fontWeight: 700,
                            color: text,
                        }}
                    >
                        Agent leaderboard
                    </p>

                    <div style={{ display: "flex", gap: "8px" }}>
                        {(
                            [
                                ["net", "Net"],
                                ["activity", "Activity"],
                            ] as const
                        ).map(([value, label]) => (
                            <button
                                key={value}
                                onClick={() => setSort(value)}
                                style={{
                                    border: `1px solid ${sort === value ? brand : border}`,
                                    background: sort === value ? brand : surface,
                                    color: sort === value ? "#FFFFFF" : text,
                                    padding: "8px 16px",
                                    fontSize: "1rem",
                                    fontWeight: 600,
                                    cursor: "pointer",
                                }}
                            >
                                By {label}
                            </button>
                        ))}
                    </div>
                </div>

                {leaderboard.length === 0 ? (
                    <p style={{ fontSize: "1.0625rem", color: textSecondary }}>
                        No agents yet.
                    </p>
                ) : (
                    <div style={{ overflowX: "auto" }}>
                        <table
                            style={{
                                width: "100%",
                                borderCollapse: "collapse",
                                fontSize: "1rem",
                            }}
                        >
                            <thead>
                                <tr
                                    style={{
                                        borderBottom: `1px solid ${border}`,
                                        textAlign: "left",
                                    }}
                                >
                                    {[
                                        "Rank",
                                        "Agent",
                                        "Farmers",
                                        "New farmers",
                                        "Records logged",
                                        "Income",
                                        "Expense",
                                        "Net",
                                    ].map((label) => (
                                        <th
                                            key={label}
                                            style={{
                                                padding: "10px 8px",
                                                color: textSecondary,
                                            }}
                                        >
                                            {label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {leaderboard.map((row) => (
                                    <tr
                                        key={row.agent_id}
                                        style={{ borderBottom: `1px solid ${border}` }}
                                    >
                                        <td style={{ padding: "12px 8px", color: text, fontWeight: 700 }}>
                                            {row.rank}
                                        </td>
                                        <td style={{ padding: "12px 8px", color: text, fontWeight: 600 }}>
                                            {row.name}
                                        </td>
                                        <td style={{ padding: "12px 8px", color: text }}>
                                            {row.farmer_count}
                                        </td>
                                        <td style={{ padding: "12px 8px", color: text }}>
                                            {row.new_farmers}
                                        </td>
                                        <td style={{ padding: "12px 8px", color: text }}>
                                            {row.records_logged}
                                        </td>
                                        <td style={{ padding: "12px 8px", color: text }}>
                                            GHS {formatMoney(row.income)}
                                        </td>
                                        <td style={{ padding: "12px 8px", color: text }}>
                                            GHS {formatMoney(row.expense)}
                                        </td>
                                        <td style={{ padding: "12px 8px", color: brand, fontWeight: 600 }}>
                                            GHS {formatMoney(row.net)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </div>
    );
}
