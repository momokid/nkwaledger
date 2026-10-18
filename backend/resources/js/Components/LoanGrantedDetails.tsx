import { useTheme } from "@/Layouts/AuthenticatedLayout";
import { cedis, slashDate } from "@/lib/format";

interface Props {
    grantedOn: string;
    amount: number;
    // null when no interest was agreed
    interest: number | null;
}

// the loan itself, shown ahead of the repayments made on it
export default function LoanGrantedDetails({ grantedOn, amount, interest }: Props) {
    const { dark } = useTheme();

    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";

    const head = { textAlign: "left", padding: "10px 16px", fontSize: "0.9375rem", fontWeight: 600, color: textSecondary, borderBottom: `1px solid ${border}` } as const;

    return (
        <div className="mt-5">
            <h3 style={{ fontSize: "1.1875rem", fontWeight: 700, color: text }}>Loan Granted Details</h3>
            <div className="mt-3 overflow-x-auto" style={{ border: `1px solid ${border}` }}>
                <table className="w-full" style={{ fontSize: "1.0625rem", color: text }}>
                    <thead>
                        <tr>
                            <th style={head}>Date Granted</th>
                            <th style={head}>Amount Granted</th>
                            <th style={head}>Interest Agreed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td className="px-4 py-3">{slashDate(grantedOn)}</td>
                            <td className="px-4 py-3">GHS {cedis(amount)}</td>
                            <td className="px-4 py-3">{interest === null ? "" : `GHS ${cedis(interest)}`}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    );
}
