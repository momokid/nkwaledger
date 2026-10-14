import { useTheme } from "@/Layouts/AuthenticatedLayout";
import { wording } from "@/lib/wording";
import Button from "@/Components/Button";
import { cedis } from "@/lib/format";
import { router, usePage } from "@inertiajs/react";
import { useState } from "react";

type Status = "active" | "repaid" | "cancelled" | "pending" | "declined" | "withdrawn" | "rejected";

export interface FarmerLoanRow {
    row: "loan" | "entry";
    kind?: "loan" | "repayment";
    uuid: string;
    lender: string;
    amount: number;
    balance: number | null;
    status: Status;
}

const STATUS_LABELS: Record<Status, string> = {
    active: "Active",
    repaid: "Repaid",
    cancelled: "Cancelled",
    pending: "Waiting for the farmer to accept",
    declined: "Declined by the farmer",
    withdrawn: "Withdrawn",
    rejected: wording("repayment.rejected"),
};

interface Props {
    loans: FarmerLoanRow[];
    // present only for the farmer's current agent, who adds loans and sees entries through to acceptance
    farmerUuid?: string;
}

// an admin only reads this; the farmer's current agent can also act on it
export default function FarmerLoans({ loans, farmerUuid }: Props) {
    const { dark } = useTheme();
    const errors = (usePage().props as unknown as { errors?: Record<string, string> }).errors ?? {};

    const [code, setCode] = useState<Record<string, string>>({});
    const [tried, setTried] = useState<string | null>(null);
    const [withdrawing, setWithdrawing] = useState<string | null>(null);

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const inputBorder = dark ? "#4B5563" : "#9CA3AF";
    const inputBg = dark ? "#111827" : "#FFFFFF";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";

    const acting = farmerUuid !== undefined;

    if (loans.length === 0 && !acting) {
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

    const confirm = (uuid: string) => {
        setTried(uuid);
        router.post(`/agent/loan-entries/${uuid}/code`, { code: code[uuid] ?? "" });
    };

    return (
        <div className="p-6 mt-5" style={{ background: surface, border: `1px solid ${border}` }}>
            <h2 style={{ fontSize: "1.375rem", fontWeight: 700, color: text }}>Loans</h2>

            {acting && (
                <div className="mt-4 flex flex-wrap gap-3">
                    <Button type="button" onClick={() => router.visit(`/agent/farmers/${farmerUuid}/loans/create`)}>
                        Add a loan
                    </Button>
                    <Button
                        type="button"
                        look="secondary"
                        onClick={() => router.visit(`/agent/farmers/${farmerUuid}/loans/existing/create`)}
                    >
                        Add an existing loan for the farmer
                    </Button>
                </div>
            )}

            {loans.length === 0 ? (
                <p className="mt-4" style={{ color: textSecondary, fontSize: "1.0625rem" }}>
                    No loans recorded for this farmer.
                </p>
            ) : (
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
                                <RowWithActions key={loan.uuid} loan={loan} />
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );

    function RowWithActions({ loan }: { loan: FarmerLoanRow }) {
        const waiting = acting && loan.row === "entry" && loan.status === "pending";

        return (
            <>
                <tr style={{ borderBottom: waiting ? "none" : `1px solid ${border}` }}>
                    <td className="px-4 py-3" style={{ color: text }}>
                        {loan.lender}
                        {loan.kind === "repayment" && <span style={{ color: textSecondary }}> - {wording("repayment.entry")}</span>}
                        {acting && loan.row === "loan" && loan.status === "active" && (
                            <Button
                                type="button"
                                look="secondary"
                                size="small"
                                style={{ marginLeft: "12px" }}
                                onClick={() => router.visit(`/agent/loans/${loan.uuid}/repayments/create`)}
                            >
                                Record a repayment
                            </Button>
                        )}
                    </td>
                    <td className="px-4 py-3" style={{ color: text }}>
                        GHS {cedis(loan.amount)}
                    </td>
                    <td className="px-4 py-3" style={{ color: text, fontWeight: 600 }}>
                        {loan.balance === null ? "—" : `GHS ${cedis(loan.balance)}`}
                    </td>
                    <td
                        className="px-4 py-3"
                        style={{ color: ["cancelled", "declined", "withdrawn"].includes(loan.status) ? textSecondary : text }}
                    >
                        {STATUS_LABELS[loan.status]}
                    </td>
                </tr>

                {waiting && (
                    <tr style={{ borderBottom: `1px solid ${border}` }}>
                        <td colSpan={4} className="px-4 pb-4">
                            <div className="flex flex-wrap items-end gap-3">
                                <Button
                                    type="button"
                                    look="secondary"
                                    size="small"
                                    onClick={() => router.post(`/agent/loan-entries/${loan.uuid}/send-code`)}
                                >
                                    Send code to the farmer
                                </Button>

                                <div>
                                    <label
                                        htmlFor={`code-${loan.uuid}`}
                                        style={{ display: "block", fontSize: "0.9375rem", color: textSecondary, marginBottom: "4px" }}
                                    >
                                        Enter the 6-digit code the farmer received.
                                    </label>
                                    <input
                                        id={`code-${loan.uuid}`}
                                        type="text"
                                        inputMode="numeric"
                                        maxLength={6}
                                        autoComplete="one-time-code"
                                        value={code[loan.uuid] ?? ""}
                                        onChange={(event) => setCode({ ...code, [loan.uuid]: event.target.value })}
                                        style={{
                                            padding: "8px 10px",
                                            border: `1px solid ${inputBorder}`,
                                            background: inputBg,
                                            color: text,
                                            fontSize: "1.125rem",
                                            width: "9rem",
                                        }}
                                    />
                                </div>

                                <Button type="button" size="small" onClick={() => confirm(loan.uuid)}>
                                    Confirm
                                </Button>

                                {withdrawing !== loan.uuid && (
                                    <Button type="button" look="secondary" size="small" onClick={() => setWithdrawing(loan.uuid)}>
                                        Withdraw
                                    </Button>
                                )}
                            </div>

                            {(tried === null || tried === loan.uuid) && errors.code && (
                                <p style={{ fontSize: "0.9375rem", color: "#B91C1C", marginTop: "4px" }}>{errors.code}</p>
                            )}

                            {withdrawing === loan.uuid && (
                                <div className="mt-3" role="alertdialog" aria-label="Withdraw this loan?">
                                    <p style={{ color: text, fontSize: "1.0625rem" }}>
                                        Withdraw this loan? The farmer will no longer be able to accept it.
                                    </p>
                                    <div className="mt-2 flex gap-3">
                                        <Button
                                            type="button"
                                            look="danger"
                                            size="small"
                                            onClick={() => router.post(`/agent/loan-entries/${loan.uuid}/withdraw`)}
                                        >
                                            Withdraw
                                        </Button>
                                        <Button type="button" look="secondary" size="small" onClick={() => setWithdrawing(null)}>
                                            Keep
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </td>
                    </tr>
                )}
            </>
        );
    }
}
