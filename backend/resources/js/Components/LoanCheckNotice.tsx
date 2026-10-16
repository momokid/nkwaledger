import { useTheme } from "@/Layouts/AuthenticatedLayout";
import { wording, fill } from "@/lib/wording";
import { shortDate } from "@/lib/format";
import { LOAN_NOTICE } from "@/lib/loanNotice";

export type CheckStatus = "confirmed" | "not_confirmed" | "does_not_match" | "unreachable";

export interface LoanCheck {
    status: CheckStatus;
    note: string | null;
    date: string;
}

// the history also lists a check that was taken back
export const checkOptionLabel = (status: CheckStatus | "withdrawn") => wording(`lender.check_option_${status}`);

const COLORS: Record<CheckStatus, string> = {
    confirmed: "#0F6E56",
    not_confirmed: "#B45309",
    does_not_match: "#B91C1C",
    unreachable: "#B45309",
};

interface Props {
    check?: LoanCheck | null;
    // a loan nobody has checked shows the self-reported notice; staff lists show nothing until there is a check
    whenNone?: "notice" | "nothing";
    size?: string;
}

// what the farmer, the agent and the admin read about a loan's check with the lender, and the note that came with it
export default function LoanCheckNotice({ check, whenNone = "notice", size = "1rem" }: Props) {
    const { dark } = useTheme();
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";

    if (!check) {
        return whenNone === "notice" ? (
            <p role="note" style={{ color: "#B45309", fontSize: size }}>
                {LOAN_NOTICE}
            </p>
        ) : null;
    }

    return (
        <div role="note">
            <p style={{ color: COLORS[check.status], fontSize: size, fontWeight: 600 }}>
                {fill(`lender.check_line_${check.status}`, { date: shortDate(check.date) })}
            </p>
            {check.note && <p style={{ color: textSecondary, fontSize: size, whiteSpace: "pre-wrap" }}>{check.note}</p>}
        </div>
    );
}
