import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import { wording } from "@/lib/wording";
import Button from "@/Components/Button";
import { Link, router } from "@inertiajs/react";
import { PageProps } from "@/types";
import { cedis } from "@/lib/format";
import { LOAN_NOTICE } from "@/lib/loanNotice";
import { useState } from "react";

type Status = "active" | "repaid" | "cancelled";

interface LoanRow {
    uuid: string;
    lender: string;
    amount: number;
    balance: number;
    status: Status;
}

interface PendingCard {
    kind: "loan" | "repayment";
    uuid: string;
    lender: string;
    amount: number;
    date: string;
    agent: string;
}

interface Props extends PageProps {
    loans: LoanRow[];
    pending: PendingCard[];
}

const STATUS_LABELS: Record<Status, string> = {
    active: "Active",
    repaid: "Repaid",
    cancelled: "Cancelled",
};

export default function Index(props: Props) {
    return (
        <AuthenticatedLayout title="Loans">
            <IndexContent {...props} />
        </AuthenticatedLayout>
    );
}

type ContentProps = Pick<Props, "loans" | "pending">;

function IndexContent({ loans, pending }: ContentProps) {
    const { dark } = useTheme();
    const [declining, setDeclining] = useState<string | null>(null);

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const noticeBorder = "#B45309";

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
            className="p-6"
            style={{ background: surface, border: `1px solid ${border}` }}
        >
            <h2 style={{ fontSize: "1.375rem", fontWeight: 700, color: text }}>
                Loans
            </h2>

            {pending.map((card) => (
                <div
                    key={card.uuid}
                    className="mt-4 p-4"
                    style={{ background: surface, border: `2px solid ${noticeBorder}` }}
                >
                    <p style={{ color: text, fontSize: "1.125rem", fontWeight: 700 }}>
                        {card.lender} · GHS {cedis(card.amount)}
                    </p>
                    <p style={{ color: textSecondary, fontSize: "1rem" }}>{card.date}</p>
                    <p style={{ color: textSecondary, fontSize: "1rem" }}>Recorded by {card.agent}</p>
                    <p className="mt-2" style={{ color: "#B45309", fontSize: "1rem" }}>
                        {card.kind === "repayment"
                            ? wording("repayment.pending_notice")
                            : "Your agent recorded a loan for you. It is not counted until you accept it."}
                    </p>

                    {declining === card.uuid ? (
                        <div className="mt-3" role="alertdialog" aria-label="Decline this loan?">
                            <p style={{ color: text, fontSize: "1.0625rem" }}>Decline this loan?</p>
                            <div className="mt-2 flex gap-3">
                                <Button
                                    type="button"
                                    look="danger"
                                    onClick={() => router.post(`/loans/entries/${card.uuid}/decline`)}
                                >
                                    Decline
                                </Button>
                                <Button type="button" look="secondary" onClick={() => setDeclining(null)}>
                                    Keep
                                </Button>
                            </div>
                        </div>
                    ) : (
                        <div className="mt-3 flex gap-3">
                            <Button type="button" onClick={() => router.post(`/loans/entries/${card.uuid}/accept`)}>
                                Accept
                            </Button>
                            <Button type="button" look="secondary" onClick={() => setDeclining(card.uuid)}>
                                Decline
                            </Button>
                        </div>
                    )}
                </div>
            ))}

            <div className="mt-4 flex flex-wrap gap-3">
                <Button type="button" onClick={() => router.visit("/loans/create")}>
                    Add a loan
                </Button>
                <Button
                    type="button"
                    look="secondary"
                    onClick={() => router.visit("/loans/existing/create")}
                >
                    Add a loan I already had
                </Button>
            </div>

            {loans.length === 0 ? (
                <p className="mt-5" style={{ color: textSecondary, fontSize: "1.0625rem" }}>
                    You have no loans recorded.
                </p>
            ) : (
                <div className="mt-5" style={{ overflowX: "auto" }}>
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
                                        <Link href={`/loans/${loan.uuid}`} style={{ color: text, textDecoration: "underline" }}>
                                            {loan.lender}
                                        </Link>
                                        <p role="note" style={{ color: "#B45309", fontSize: "0.9375rem" }}>
                                            {LOAN_NOTICE}
                                        </p>
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
            )}
        </div>
    );
}
