import AdminLayout from "@/Layouts/AdminLayout";
import { useTheme } from "@/Layouts/AuthenticatedLayout";
import BackLink from "@/Components/BackLink";
import { wording } from "@/lib/wording";
import { cedis, shortDate } from "@/lib/format";
import { LOAN_NOTICE } from "@/lib/loanNotice";
import { PageProps } from "@/types";

interface LoanSummary {
    uuid: string;
    lender: string;
    amount: number;
    balance: number;
    status: string;
}

interface RepaymentRow {
    uuid: string;
    date: string;
    amount: number;
    principal: number;
    interest: number;
    cancelled: boolean;
}

interface RejectedRow {
    uuid: string;
    date: string;
    amount: number;
}

interface Props extends PageProps {
    loan: LoanSummary;
    farmer: string;
    repayments: RepaymentRow[];
    rejected: RejectedRow[];
}

const STATUS_LABELS: Record<string, string> = { active: "Active", repaid: "Repaid", cancelled: "Cancelled" };

export default function Show(props: Props) {
    return (
        <AdminLayout title="Loans">
            <ShowContent {...props} />
        </AdminLayout>
    );
}

type ContentProps = Pick<Props, "loan" | "farmer" | "repayments" | "rejected">;

// read-only: the admin looks, and records nothing from here
function ShowContent({ loan, farmer, repayments, rejected }: ContentProps) {
    const { dark } = useTheme();

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";

    const head = { textAlign: "left", padding: "10px 16px", fontSize: "0.9375rem", fontWeight: 600, color: textSecondary, borderBottom: `1px solid ${border}` } as const;

    return (
        <div className="p-6" style={{ background: surface, border: `1px solid ${border}` }}>
            <BackLink fallbackHref="/admin/loans" />
            <h2 style={{ fontSize: "1.375rem", fontWeight: 700, color: text }}>{loan.lender}</h2>
            <p style={{ color: textSecondary, fontSize: "1.0625rem" }}>
                {farmer} · GHS {cedis(loan.amount)} · Loan Balance GHS {cedis(loan.balance)} · {STATUS_LABELS[loan.status] ?? loan.status}
            </p>

            <p role="note" className="mt-2" style={{ color: "#B45309", fontSize: "1rem" }}>
                {LOAN_NOTICE}
            </p>

            <h3 className="mt-5" style={{ fontSize: "1.1875rem", fontWeight: 700, color: text }}>
                Repayments
            </h3>

            {rejected.map((row) => (
                <p key={row.uuid} className="mt-2" style={{ color: textSecondary, fontSize: "1.0625rem" }}>
                    {shortDate(row.date)} · GHS {cedis(row.amount)} · {wording("repayment.rejected")}
                </p>
            ))}

            {repayments.length > 0 && (
                <div className="mt-3 overflow-x-auto" style={{ border: `1px solid ${border}` }}>
                    <table className="w-full" style={{ fontSize: "1.0625rem", color: text }}>
                        <thead>
                            <tr>
                                <th style={head}>{wording("repayment.date")}</th>
                                <th style={head}>{wording("repayment.amount")}</th>
                                <th style={head}>{wording("repayment.principal_part")}</th>
                                <th style={head}>{wording("repayment.interest_part")}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {repayments.map((row) => (
                                <tr key={row.uuid} style={{ borderTop: `1px solid ${border}`, opacity: row.cancelled ? 0.6 : 1 }}>
                                    <td className="px-4 py-3">{shortDate(row.date)}</td>
                                    <td className="px-4 py-3">GHS {cedis(row.amount)}</td>
                                    <td className="px-4 py-3">GHS {cedis(row.principal)}</td>
                                    <td className="px-4 py-3">GHS {cedis(row.interest)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}
