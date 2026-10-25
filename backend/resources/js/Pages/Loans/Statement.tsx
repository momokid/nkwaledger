import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import { wording } from "@/lib/wording";
import BackLink from "@/Components/BackLink";
import { cedis, slashDate } from "@/lib/format";
import { PageProps } from "@/types";
import { IconPrinter } from "@tabler/icons-react";

interface Repayment {
    uuid: string;
    date: string;
    amount: number;
    principal: number;
    interest: number;
    balance: number;
    cancelled: boolean;
}

interface Farm {
    uuid: string;
    name: string;
    purpose: string | null;
    planned: number;
    accounted: number;
    not_yet_accounted: number;
    over: number;
}

interface Purchase {
    date: string;
    what: string;
    farm: string | null;
    amount: number;
}

interface Statement {
    loan: {
        uuid: string;
        lender: string;
        amount: number;
        granted_on: string | null;
        paid_into: string | null;
        interest: number | null;
        due_date: string | null;
        status: string;
        balance: number;
    };
    repayments: Repayment[];
    farms: Farm[];
    purchases: Purchase[];
    kinds: { name: string; amount: number }[];
    other_uses: { uuid: string; note: string; amount: number }[];
    totals: { on_farms: number; other: number; not_yet: number };
    prepared_on: string;
    notice: string;
}

interface Props extends PageProps {
    statement: Statement;
    backUrl: string;
    printUrl: string;
    pdfUrl: string;
}

export default function LoanStatement(props: Props) {
    return (
        <AuthenticatedLayout title="Loans">
            <StatementContent {...props} />
        </AuthenticatedLayout>
    );
}

function StatementContent({ statement: s, backUrl, printUrl, pdfUrl }: Props) {
    const { dark } = useTheme();

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";

    const money = (minor: number | null) => (minor === null ? "—" : `GHS ${cedis(minor)}`);
    const head = { textAlign: "left", padding: "10px 16px", fontSize: "0.9375rem", fontWeight: 600, color: textSecondary, borderBottom: `1px solid ${border}` } as const;
    const section = { fontSize: "1.1875rem", fontWeight: 700, color: text } as const;
    const line = { color: text, fontSize: "1.0625rem", marginTop: "4px" } as const;
    const button = { display: "inline-flex", alignItems: "center", gap: "6px", padding: "8px 14px", border: `1px solid ${border}`, color: text, fontWeight: 600, textDecoration: "none" } as const;

    const details: [string, string][] = [
        ["Lender", s.loan.lender],
        ["Amount received", money(s.loan.amount)],
        ["Date received", slashDate(s.loan.granted_on)],
        ["Paid into", s.loan.paid_into ?? "—"],
        ["Total interest agreed", money(s.loan.interest)],
        ["Due date", slashDate(s.loan.due_date)],
        ["Status", s.loan.status.charAt(0).toUpperCase() + s.loan.status.slice(1)],
        ["Loan balance", money(s.loan.balance)],
    ];

    const usedAnything = s.farms.length > 0 || s.purchases.length > 0 || s.other_uses.length > 0;

    return (
        <div className="p-6" style={{ background: surface, border: `1px solid ${border}` }}>
            <BackLink fallbackHref={backUrl} />
            <h2 style={{ fontSize: "1.375rem", fontWeight: 700, color: text }}>Loan statement</h2>
            <p style={{ color: textSecondary, fontSize: "1.0625rem" }}>{s.loan.lender}</p>

            <div className="mt-3 flex gap-3">
                <a href={printUrl} target="_blank" rel="noreferrer" style={button}>
                    <IconPrinter size={18} />
                    Print
                </a>
                <a href={pdfUrl} style={button}>
                    Download PDF
                </a>
            </div>

            <h3 className="mt-5" style={section}>
                The loan
            </h3>
            {details.map(([label, value]) => (
                <p key={label} style={line}>
                    <span style={{ color: textSecondary }}>{label}: </span>
                    {value}
                </p>
            ))}

            {s.repayments.length > 0 && (
                <>
                    <h3 className="mt-5" style={section}>
                        Repayments
                    </h3>
                    <div className="mt-2" style={{ overflowX: "auto" }}>
                        <table style={{ width: "100%", borderCollapse: "collapse" }}>
                            <thead>
                                <tr>
                                    <th style={head}>{wording("repayment.date")}</th>
                                    <th style={head}>{wording("repayment.amount")}</th>
                                    <th style={head}>{wording("repayment.principal_part")}</th>
                                    <th style={head}>{wording("repayment.interest_part")}</th>
                                    <th style={head}>{wording("repayment.loan_balance")}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {s.repayments.map((row) => (
                                    <tr key={row.uuid} style={{ borderBottom: `1px solid ${border}`, color: row.cancelled ? textSecondary : text, textDecoration: row.cancelled ? "line-through" : "none" }}>
                                        <td className="px-4 py-3">{slashDate(row.date)}</td>
                                        <td className="px-4 py-3">{money(row.amount)}</td>
                                        <td className="px-4 py-3">{money(row.principal)}</td>
                                        <td className="px-4 py-3">{money(row.interest)}</td>
                                        <td className="px-4 py-3">{money(row.balance)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </>
            )}

            {usedAnything && (
                <>
                    <h3 className="mt-5" style={section}>
                        What the money was used for
                    </h3>

                    {s.farms.map((farm) => (
                        <div key={farm.uuid} className="mt-2">
                            {farm.purpose && <p style={line}>{farm.name}: {farm.purpose}</p>}
                            <p style={line}>
                                {farm.name}: planned {money(farm.planned)}, accounted {money(farm.accounted)}, not yet accounted {money(farm.not_yet_accounted)}
                            </p>
                            {farm.over > 0 && <p style={{ ...line, color: "#B45309" }}>{farm.name}: {money(farm.over)} more than planned</p>}
                        </div>
                    ))}

                    {s.purchases.length > 0 && (
                        <>
                            <div className="mt-3" style={{ overflowX: "auto" }}>
                                <table style={{ width: "100%", borderCollapse: "collapse" }}>
                                    <thead>
                                        <tr>
                                            <th style={head}>Date</th>
                                            <th style={head}>What it was</th>
                                            <th style={head}>Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {s.purchases.map((row, index) => (
                                            <tr key={index} style={{ borderBottom: `1px solid ${border}`, color: text }}>
                                                <td className="px-4 py-3">{slashDate(row.date)}</td>
                                                <td className="px-4 py-3">
                                                    {row.what}
                                                    {row.farm ? ` – ${row.farm}` : ""}
                                                </td>
                                                <td className="px-4 py-3">{money(row.amount)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <div className="mt-2">
                                {s.kinds.map((kind) => (
                                    <p key={kind.name} style={line}>
                                        {kind.name}: {money(kind.amount)}
                                    </p>
                                ))}
                            </div>
                        </>
                    )}

                    {s.other_uses.length > 0 && (
                        <div className="mt-2">
                            {s.other_uses.map((use) => (
                                <p key={use.uuid} style={line}>
                                    {use.note}: {money(use.amount)}
                                </p>
                            ))}
                        </div>
                    )}

                    <div className="mt-3">
                        <p style={{ ...line, fontWeight: 600 }}>Used on the farms: {money(s.totals.on_farms)}</p>
                        <p style={{ ...line, fontWeight: 600 }}>Used for something else: {money(s.totals.other)}</p>
                        <p style={{ ...line, fontWeight: 600 }}>Not yet accounted for: {money(s.totals.not_yet)}</p>
                    </div>
                </>
            )}

            <p className="mt-5" style={{ color: textSecondary, fontSize: "1rem" }}>
                Prepared on {slashDate(s.prepared_on)}
            </p>
            <p style={{ color: textSecondary, fontSize: "1rem" }}>{s.notice}</p>
        </div>
    );
}
