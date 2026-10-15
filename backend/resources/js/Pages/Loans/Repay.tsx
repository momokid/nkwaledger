import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import BackLink from "@/Components/BackLink";
import RepaymentForm, { PaidFromOption } from "@/Components/RepaymentForm";
import { PageProps } from "@/types";
import { cedis } from "@/lib/format";
import { router } from "@inertiajs/react";

interface Props extends PageProps {
    loan: { uuid: string; lender: string; amount: number; balance: number };
    paidFrom: PaidFromOption[];
    previewUrl: string;
    postUrl: string;
    backUrl: string;
    today: string;
}

export default function Repay(props: Props) {
    return (
        <AuthenticatedLayout title="Loans">
            <RepayContent {...props} />
        </AuthenticatedLayout>
    );
}

type ContentProps = Pick<Props, "loan" | "paidFrom" | "previewUrl" | "postUrl" | "backUrl" | "today">;

// an agent entering a repayment for a farmer: it waits for the farmer to accept
function RepayContent({ loan, paidFrom, previewUrl, postUrl, backUrl, today }: ContentProps) {
    const { dark } = useTheme();

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";

    return (
        <div className="p-6" style={{ background: surface, border: `1px solid ${border}` }}>
            <BackLink fallbackHref={backUrl} />
            <h2 style={{ fontSize: "1.375rem", fontWeight: 700, color: text }}>{loan.lender}</h2>
            <p style={{ color: textSecondary, fontSize: "1.0625rem" }}>
                GHS {cedis(loan.amount)} · Loan Balance GHS {cedis(loan.balance)}
            </p>

            <RepaymentForm
                paidFrom={paidFrom}
                previewUrl={previewUrl}
                postUrl={postUrl}
                today={today}
                agent
                onCancel={() => router.visit(backUrl)}
            />
        </div>
    );
}
