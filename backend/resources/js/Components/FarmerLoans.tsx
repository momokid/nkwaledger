import { useTheme } from "@/Layouts/AuthenticatedLayout";
import { cedis } from "@/lib/format";

type Status = "active" | "repaid" | "cancelled";

export interface FarmerLoanRow {
    uuid: string;
    lender: string;
    amount: number;
    balance: number;
    status: Status;
}

const STATUS_LABELS: Record<Status, string> = {
    active: "Active",
    repaid: "Repaid",
    cancelled: "Cancelled",
};

// read-only: an admin sees a farmer's loans here and cannot add or change one
export default function FarmerLoans({ loans }: { loans: FarmerLoanRow[] }) {
    const { dark } = useTheme();

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";

    if (loans.length === 0) {
        return null;
    }

    const head = {
        textAlign: "left",
        padding: "10px 16px",
        fontSize: "0.9375rem",
        fontWeight: 600,
        color: textSecondary,
        borderBottom: `1px solid ${border}`,
    } as const;

    return (
        <div
            className="p-6 mt-5"
            style={{ background: surface, border: `1px solid ${border}` }}
        >
            <h2 style={{ fontSize: "1.375rem", fontWeight: 700, color: text }}>
                Loans
            </h2>

            <div className="mt-4" style={{ overflowX: "auto" }}>
                <table style={{ width: "100%", borderCollapse: "collapse" }}>
                    <thead>
                        <tr>
                            <th style={head}>Lender</th>
                            <th style={head}>Amount</th>
                            <th style={head}>Loan Balance</th>
                            <th style={head}>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {loans.map((loan) => (
                            <tr key={loan.uuid} style={{ borderBottom: `1px solid ${border}` }}>
                                <td className="px-4 py-3" style={{ color: text }}>
                                    {loan.lender}
                                </td>
                                <td className="px-4 py-3" style={{ color: text }}>
                                    GHS {cedis(loan.amount)}
                                </td>
                                <td className="px-4 py-3" style={{ color: text, fontWeight: 600 }}>
                                    GHS {cedis(loan.balance)}
                                </td>
                                <td
                                    className="px-4 py-3"
                                    style={{ color: loan.status === "cancelled" ? textSecondary : text }}
                                >
                                    {STATUS_LABELS[loan.status]}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
