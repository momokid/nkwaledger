import AdminLayout from "@/Layouts/AdminLayout";
import { useTheme } from "@/Layouts/AuthenticatedLayout";
import BackLink from "@/Components/BackLink";
import LoanGrantedDetails from "@/Components/LoanGrantedDetails";
import { wording } from "@/lib/wording";
import { cedis, shortDate } from "@/lib/format";
import LoanCheckNotice, { LoanCheck, CheckStatus, checkOptionLabel } from "@/Components/LoanCheckNotice";
import LoanCheckForm, { SavedChecks } from "@/Components/Admin/LoanCheckForm";
import LoanCheckWithdraw from "@/Components/Admin/LoanCheckWithdraw";
import { PageProps } from "@/types";
import { useState } from "react";

interface LoanSummary {
    uuid: string;
    lender: string;
    amount: number;
    agreed_interest: number | null;
    granted_on: string;
    balance: number;
    status: string;
    check: LoanCheck | null;
}

interface CheckRow {
    uuid: string;
    status: CheckStatus | "withdrawn";
    note: string | null;
    date: string;
    by: string;
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
    checks: CheckRow[];
    idempotencyKey: string;
}

const STATUS_LABELS: Record<string, string> = { active: "Active", repaid: "Repaid", cancelled: "Cancelled" };

export default function Show(props: Props) {
    return (
        <AdminLayout title="Loans">
            <ShowContent {...props} />
        </AdminLayout>
    );
}

type ContentProps = Pick<Props, "loan" | "farmer" | "repayments" | "rejected" | "checks" | "idempotencyKey">;

// the admin looks at the money and records nothing on it from here; the one thing saved is the check with the lender
function ShowContent({ loan, farmer, repayments, rejected, checks: firstChecks, idempotencyKey }: ContentProps) {
    const { dark } = useTheme();
    const [check, setCheck] = useState(loan.check);
    const [checks, setChecks] = useState(firstChecks);

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

            <div className="mt-2">
                <LoanCheckNotice check={check} />
            </div>

            {check && (
                <LoanCheckWithdraw
                    loanUuid={loan.uuid}
                    onSaved={(saved: SavedChecks) => {
                        setCheck(saved.check);
                        setChecks(saved.checks as CheckRow[]);
                    }}
                />
            )}

            {loan.status !== "cancelled" && (
                <LoanCheckForm
                    loanUuid={loan.uuid}
                    idempotencyKey={idempotencyKey}
                    onSaved={(saved: SavedChecks) => {
                        setCheck(saved.check);
                        setChecks(saved.checks as CheckRow[]);
                    }}
                />
            )}

            {checks.length > 0 && (
                <div className="mt-5">
                    <h3 style={{ fontSize: "1.1875rem", fontWeight: 700, color: text }}>{wording("lender.check_history")}</h3>
                    {checks.map((row) => (
                        <div key={row.uuid} className="mt-2" style={{ borderTop: `1px solid ${border}`, paddingTop: "8px" }}>
                            <p style={{ color: text, fontSize: "1.0625rem", fontWeight: 600 }}>
                                {checkOptionLabel(row.status)} · {shortDate(row.date)} · {row.by}
                            </p>
                            {row.note && <p style={{ color: textSecondary, fontSize: "1.0625rem", whiteSpace: "pre-wrap" }}>{row.note}</p>}
                        </div>
                    ))}
                </div>
            )}

            <LoanGrantedDetails grantedOn={loan.granted_on} amount={loan.amount} interest={loan.agreed_interest} />

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
