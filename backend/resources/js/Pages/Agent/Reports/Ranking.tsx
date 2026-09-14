import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import useAuthGuard from "@/hooks/useAuthGuard";
import { Head, router } from "@inertiajs/react";
import { cedis as formatMoney } from "@/lib/format";
import { IconPrinter } from "@tabler/icons-react";

interface RosterRow {
    id: string;
    rank: number;
    name: string;
    community: string | null;
    income: number;
    expense: number;
    net: number;
}

interface Props {
    roster: RosterRow[];
    sort: "income" | "net";
    filters: { from: string; to: string };
}

export default function Ranking({ roster, sort, filters }: Props) {
    useAuthGuard();

    return (
        <AuthenticatedLayout title="Farmer Ranking">
            <Head title="Farmer Ranking" />
            <RankingContent roster={roster} sort={sort} filters={filters} />
        </AuthenticatedLayout>
    );
}

function RankingContent({ roster, sort, filters }: Props) {
    const { dark } = useTheme();

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const primary = "#1D9E75";

    const setSort = (value: "income" | "net") => {
        router.get(
            route("agent.reports.ranking"),
            { sort: value },
            { preserveScroll: true },
        );
    };

    return (
        <div className="p-6 space-y-6">
            <div
                style={{
                    display: "flex",
                    justifyContent: "space-between",
                    alignItems: "flex-start",
                }}
            >
                <div>
                    <p
                        style={{
                            fontSize: "1.5rem",
                            fontWeight: 700,
                            color: text,
                            marginBottom: "4px",
                        }}
                    >
                        Farmer Ranking
                    </p>
                    <p style={{ fontSize: "1.0625rem", color: textSecondary }}>
                        {filters.from} to {filters.to}
                    </p>
                </div>

                <a
                    href={`/agent/reports/ranking/print?sort=${sort}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    style={{
                        display: "flex",
                        alignItems: "center",
                        gap: "8px",
                        border: `1px solid ${border}`,
                        background: surface,
                        color: text,
                        padding: "10px 16px",
                        fontSize: "1.0625rem",
                        textDecoration: "none",
                    }}
                >
                    <IconPrinter size={18} />
                    Print
                </a>
            </div>

            <div style={{ display: "flex", gap: "8px" }}>
                {(["income", "net"] as const).map((option) => (
                    <button
                        key={option}
                        onClick={() => setSort(option)}
                        style={{
                            border: `1px solid ${sort === option ? primary : border}`,
                            background: sort === option ? primary : surface,
                            color: sort === option ? "#FFFFFF" : text,
                            padding: "8px 16px",
                            fontSize: "1rem",
                            fontWeight: 600,
                            cursor: "pointer",
                        }}
                    >
                        By {option === "income" ? "Income" : "Net"}
                    </button>
                ))}
            </div>

            <div
                style={{
                    background: surface,
                    border: `1px solid ${border}`,
                    padding: "20px",
                }}
            >
                {roster.length === 0 ? (
                    <p style={{ fontSize: "1.0625rem", color: textSecondary }}>
                        No farmers are assigned to you.
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
                                    <th
                                        style={{
                                            padding: "10px 8px",
                                            color: textSecondary,
                                        }}
                                    >
                                        Rank
                                    </th>
                                    <th
                                        style={{
                                            padding: "10px 8px",
                                            color: textSecondary,
                                        }}
                                    >
                                        Farmer
                                    </th>
                                    <th
                                        style={{
                                            padding: "10px 8px",
                                            color: textSecondary,
                                        }}
                                    >
                                        Community
                                    </th>
                                    <th
                                        style={{
                                            padding: "10px 8px",
                                            color: textSecondary,
                                        }}
                                    >
                                        Income
                                    </th>
                                    <th
                                        style={{
                                            padding: "10px 8px",
                                            color: textSecondary,
                                        }}
                                    >
                                        Expense
                                    </th>
                                    <th
                                        style={{
                                            padding: "10px 8px",
                                            color: textSecondary,
                                        }}
                                    >
                                        Net
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {roster.map((row) => (
                                    <tr
                                        key={row.id}
                                        style={{
                                            borderBottom: `1px solid ${border}`,
                                        }}
                                    >
                                        <td
                                            style={{
                                                padding: "12px 8px",
                                                color: text,
                                                fontWeight: 700,
                                            }}
                                        >
                                            {row.rank}
                                        </td>
                                        <td
                                            style={{
                                                padding: "12px 8px",
                                                color: text,
                                                fontWeight: 600,
                                            }}
                                        >
                                            {row.name}
                                        </td>
                                        <td
                                            style={{
                                                padding: "12px 8px",
                                                color: textSecondary,
                                            }}
                                        >
                                            {row.community ?? "—"}
                                        </td>
                                        <td
                                            style={{
                                                padding: "12px 8px",
                                                color: text,
                                            }}
                                        >
                                            GHS {formatMoney(row.income)}
                                        </td>
                                        <td
                                            style={{
                                                padding: "12px 8px",
                                                color: text,
                                            }}
                                        >
                                            GHS {formatMoney(row.expense)}
                                        </td>
                                        <td
                                            style={{
                                                padding: "12px 8px",
                                                color: primary,
                                                fontWeight: 600,
                                            }}
                                        >
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
