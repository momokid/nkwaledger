import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import useAuthGuard from "@/hooks/useAuthGuard";
import { Head } from "@inertiajs/react";
import { cedis as formatMoney } from "@/lib/format";
import { IconPrinter } from "@tabler/icons-react";

interface AccountRow {
    account: string;
    amount: number;
}

interface Summary {
    total_income: number;
    total_expense: number;
    net: number;
    income_by_account: AccountRow[];
    expense_by_account: AccountRow[];
}

interface Props {
    summary: Summary;
    filters: { from: string; to: string };
}

export default function IncomeSummary({ summary, filters }: Props) {
    useAuthGuard();

    return (
        <AuthenticatedLayout title="Income &amp; Expense Summary">
            <Head title="Income & Expense Summary" />
            <IncomeSummaryContent summary={summary} filters={filters} />
        </AuthenticatedLayout>
    );
}

function IncomeSummaryContent({ summary, filters }: Props) {
    const { dark } = useTheme();

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const primary = "#1D9E75";
    const danger = "#DC2626";

    const AccountTable = ({
        rows,
        color,
    }: {
        rows: AccountRow[];
        color: string;
    }) =>
        rows.length === 0 ? (
            <p style={{ fontSize: "1rem", color: textSecondary }}>
                Nothing recorded in this period.
            </p>
        ) : (
            <table
                style={{
                    width: "100%",
                    borderCollapse: "collapse",
                    fontSize: "1rem",
                }}
            >
                <tbody>
                    {rows.map((row) => (
                        <tr
                            key={row.account}
                            style={{ borderBottom: `1px solid ${border}` }}
                        >
                            <td style={{ padding: "10px 8px", color: text }}>
                                {row.account}
                            </td>
                            <td
                                style={{
                                    padding: "10px 8px",
                                    textAlign: "right",
                                    color,
                                    fontWeight: 600,
                                }}
                            >
                                GHS {formatMoney(row.amount)}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        );

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
                        Income &amp; Expense Summary
                    </p>
                    <p style={{ fontSize: "1.0625rem", color: textSecondary }}>
                        {filters.from} to {filters.to}
                    </p>
                </div>

                <a
                    href="/agent/reports/income-summary/print"
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
                    display: "grid",
                    gridTemplateColumns: "repeat(auto-fit, minmax(200px, 1fr))",
                    gap: "16px",
                }}
            >
                {[
                    {
                        label: "Total income",
                        value: summary.total_income,
                        color: primary,
                    },
                    {
                        label: "Total expense",
                        value: summary.total_expense,
                        color: danger,
                    },
                    { label: "Net", value: summary.net, color: text },
                ].map((item) => (
                    <div
                        key={item.label}
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
                            {item.label}
                        </p>
                        <p
                            style={{
                                fontSize: "1.625rem",
                                fontWeight: 700,
                                color: item.color,
                            }}
                        >
                            GHS {formatMoney(item.value)}
                        </p>
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
                <p
                    style={{
                        fontSize: "1.125rem",
                        fontWeight: 600,
                        color: text,
                        marginBottom: "12px",
                    }}
                >
                    Income by account
                </p>
                <AccountTable
                    rows={summary.income_by_account}
                    color={primary}
                />
            </div>

            <div
                style={{
                    background: surface,
                    border: `1px solid ${border}`,
                    padding: "20px",
                }}
            >
                <p
                    style={{
                        fontSize: "1.125rem",
                        fontWeight: 600,
                        color: text,
                        marginBottom: "12px",
                    }}
                >
                    Expense by account
                </p>
                <AccountTable
                    rows={summary.expense_by_account}
                    color={danger}
                />
            </div>
        </div>
    );
}
