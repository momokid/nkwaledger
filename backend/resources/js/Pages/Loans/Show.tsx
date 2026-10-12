import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import Button from "@/Components/Button";
import RepaymentForm, { PaidFromOption } from "@/Components/RepaymentForm";
import { PageProps } from "@/types";
import { cedis, shortDate } from "@/lib/format";
import { useState } from "react";

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

interface Props extends PageProps {
    loan: LoanSummary;
    repayments: RepaymentRow[];
    paidFrom: PaidFromOption[];
    idempotencyKey: string;
    previewUrl: string;
    postUrl: string;
    today: string;
}

export default function Show(props: Props) {
    return (
        <AuthenticatedLayout title="Loans">
            <ShowContent {...props} />
        </AuthenticatedLayout>
    );
}

type ContentProps = Pick<Props, "loan" | "repayments" | "paidFrom" | "idempotencyKey" | "previewUrl" | "postUrl" | "today">;

function ShowContent({ loan, repayments, paidFrom, idempotencyKey, previewUrl, postUrl, today }: ContentProps) {
    const { dark } = useTheme();
    const [open, setOpen] = useState(false);

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";

    const head = { textAlign: "left", padding: "10px 16px", fontSize: "0.9375rem", fontWeight: 600, color: textSecondary, borderBottom: `1px solid ${border}` } as const;

    return (
        <div className="p-6" style={{ background: surface, border: `1px solid ${border}` }}>
            <h2 style={{ fontSize: "1.375rem", fontWeight: 700, color: text }}>{loan.lender}</h2>
            <p style={{ color: textSecondary, fontSize: "1.0625rem" }}>
                GHS {cedis(loan.amount)} · Loan Balance GHS {cedis(loan.balance)}
            </p>

            <h3 className="mt-5" style={{ fontSize: "1.1875rem", fontWeight: 700, color: text }}>
                Repayments
            </h3>

            {loan.status !== "cancelled" && !open && (
                <div className="mt-3">
                    <Button type="button" onClick={() => setOpen(true)}>
                        Record a repayment
                    </Button>
                </div>
            )}

            {open && (
                <RepaymentForm
                    paidFrom={paidFrom}
                    previewUrl={previewUrl}
                    postUrl={postUrl}
                    today={today}
                    idempotencyKey={idempotencyKey}
                    agent={false}
                    onCancel={() => setOpen(false)}
                />
            )}

            {repayments.length > 0 && (
                <div className="mt-4" style={{ overflowX: "auto" }}>
                    <table style={{ width: "100%", borderCollapse: "collapse" }}>
                        <thead>
                            <tr>
                                <th style={head}>repayment.date</th>
                                <th style={head}>repayment.amount</th>
                                <th style={head}>repayment.principal_part</th>
                                <th style={head}>repayment.interest_part</th>
                            </tr>
                        </thead>
                        <tbody>
                            {repayments.map((row) => (
                                <tr key={row.uuid} style={{ borderBottom: `1px solid ${border}`, color: row.cancelled ? textSecondary : text }}>
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
