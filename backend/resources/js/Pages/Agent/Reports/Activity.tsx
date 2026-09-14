import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import useAuthGuard from "@/hooks/useAuthGuard";
import { Head } from "@inertiajs/react";
import { cedis as formatMoney } from "@/lib/format";
import { IconPrinter } from "@tabler/icons-react";

interface RosterRow {
    id: string;
    name: string;
    community: string | null;
    last_activity: string | null;
    income: number;
    expense: number;
    status: "active" | "dormant";
}

interface Props {
    roster: RosterRow[];
    filters: { from: string; to: string };
}

export default function Activity({ roster, filters }: Props) {
    useAuthGuard();

    return (
        <AuthenticatedLayout title="Farmer Activity">
            <Head title="Farmer Activity" />
            <ActivityContent roster={roster} filters={filters} />
        </AuthenticatedLayout>
    );
}

function ActivityContent({ roster, filters }: Props) {
    const { dark } = useTheme();

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const primary = "#1D9E75";

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
                        Farmer Activity
                    </p>
                    <p style={{ fontSize: "1.0625rem", color: textSecondary }}>
                        {filters.from} to {filters.to}
                    </p>
                </div>

                <a
                    href="/agent/reports/activity/print"
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
                                        Last activity
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
                                        Status
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
                                                color: textSecondary,
                                            }}
                                        >
                                            {row.last_activity ??
                                                "No activity yet"}
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
                                        <td style={{ padding: "12px 8px" }}>
                                            <span
                                                style={{
                                                    color:
                                                        row.status === "active"
                                                            ? primary
                                                            : textSecondary,
                                                    fontWeight: 600,
                                                }}
                                            >
                                                {row.status === "active"
                                                    ? "Active"
                                                    : "Dormant"}
                                            </span>
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
