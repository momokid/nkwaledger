import AdminLayout from "@/Layouts/AdminLayout";
import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import Button from "@/Components/Button";
import { cedis, shortDate } from "@/lib/format";
import { router } from "@inertiajs/react";
import { PageProps } from "@/types";
import { ReactNode, useState } from "react";

interface Header {
    title: string;
    farmer_name: string;
    farmer_phone: string | null;
    farmer_reference: string;
    from: string;
    to: string;
    include_provisional: boolean;
    prepared_by: string;
    generated_at: string;
    verification_code: string;
    notice: string;
}

interface StatementRow {
    reference: string;
    date: string;
    description: string;
    account: string | null;
    money_in: number;
    money_out: number;
    balance: number;
    is_provisional: boolean;
    cancel_state: string;
    value_lost: number;
}

interface IncomeRow {
    account: string;
    group: string;
    amount: number;
}

interface TrialRow {
    account: string;
    code: string | null;
    class: string | null;
    debit: number;
    credit: number;
    balance: number;
}

interface Report {
    header: Header;
    rows?: StatementRow[] | TrialRow[];
    opening_balance?: number;
    closing_balance?: number;
    total_in?: number;
    total_out?: number;
    cancelled?: number;
    income_rows?: IncomeRow[];
    expense_rows?: IncomeRow[];
    loss_rows?: IncomeRow[];
    total_income?: number;
    total_expense?: number;
    total_loss?: number;
    net?: number;
    total_debit?: number;
    total_credit?: number;
    is_balanced?: boolean;
    provisional_held_back: number;
}

interface Props extends PageProps {
    farmer: { id: string; name: string };
    available: string[];
    kind: string;
    report: Report;
    filters: { from: string; to: string; provisional: boolean };
    canChooseProvisional: boolean;
    layout: "farmer" | "agent" | "admin";
    basePath: string;
}

const TITLES: Record<string, string> = {
    statement: "My records",
    income: "How am I doing",
    "trial-balance": "Trial balance",
};

function Frame({
    layout,
    title,
    children,
}: {
    layout: Props["layout"];
    title: string;
    children: ReactNode;
}) {
    if (layout === "admin") {
        return <AdminLayout title={title}>{children}</AdminLayout>;
    }

    return <AuthenticatedLayout title={title}>{children}</AuthenticatedLayout>;
}

export default function Index(props: Props) {
    return (
        <Frame layout={props.layout} title="Reports">
            <IndexContent {...props} />
        </Frame>
    );
}

type ContentProps = Pick<
    Props,
    | "farmer"
    | "available"
    | "kind"
    | "report"
    | "filters"
    | "canChooseProvisional"
    | "layout"
    | "basePath"
>;

function IndexContent({
    farmer,
    available,
    kind,
    report,
    filters,
    canChooseProvisional,
    layout,
    basePath,
}: ContentProps) {
    const { dark } = useTheme();

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const inputBorder = dark ? "#4B5563" : "#9CA3AF";
    const inputBg = dark ? "#111827" : "#FFFFFF";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const headerBg = dark ? "rgba(29,158,117,0.15)" : "#EAF5F0";
    const rowAlt = dark ? "#111827" : "#F9FAFB";
    const brand = "#1D9E75";

    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);
    const [provisional, setProvisional] = useState(filters.provisional);
    const [loading, setLoading] = useState(false);

    const url =
        layout === "farmer"
            ? basePath
            : `${basePath}/farmers/${farmer.id}/reports`;

    const visit = (params: Record<string, string>) => {
        setLoading(true);

        router.visit(url, {
            data: {
                from,
                to,
                kind,
                ...(canChooseProvisional && provisional
                    ? { provisional: "1" }
                    : {}),
                ...params,
            },
            preserveState: true,
            onFinish: () => setLoading(false),
        });
    };

    const field = {
        padding: "8px 10px",
        border: `1px solid ${inputBorder}`,
        background: inputBg,
        color: text,
        fontSize: "17px",
    } as const;

    const th = { color: brand, fontSize: "16px", fontWeight: 600 } as const;

    return (
        <div className="space-y-4">
            <style>{`@media print { .no-print { display: none !important; } }`}</style>

            <div
                className="no-print p-5"
                style={{ background: surface, border: `1px solid ${border}` }}
            >
                <div className="flex flex-wrap gap-2">
                    {available.map((option) => (
                        <Button
                            key={option}
                            size="small"
                            look={option === kind ? "primary" : "secondary"}
                            onClick={() => visit({ kind: option })}
                        >
                            {TITLES[option] ?? option}
                        </Button>
                    ))}
                </div>

                <div className="mt-4 flex flex-wrap items-end gap-3">
                    <div>
                        <label
                            style={{
                                display: "block",
                                fontSize: "16px",
                                color: textSecondary,
                                marginBottom: "4px",
                            }}
                        >
                            From
                        </label>
                        <input
                            type="date"
                            style={field}
                            value={from}
                            onChange={(event) => setFrom(event.target.value)}
                        />
                    </div>
                    <div>
                        <label
                            style={{
                                display: "block",
                                fontSize: "16px",
                                color: textSecondary,
                                marginBottom: "4px",
                            }}
                        >
                            To
                        </label>
                        <input
                            type="date"
                            style={field}
                            value={to}
                            onChange={(event) => setTo(event.target.value)}
                        />
                    </div>

                    {canChooseProvisional && (
                        <label
                            className="flex items-center gap-2"
                            style={{
                                fontSize: "17px",
                                color: text,
                                paddingBottom: "8px",
                            }}
                        >
                            <input
                                type="checkbox"
                                checked={provisional}
                                onChange={(event) =>
                                    setProvisional(event.target.checked)
                                }
                                style={{ width: "18px", height: "18px" }}
                            />
                            Include records waiting on approval
                        </label>
                    )}

                    <Button
                        onClick={() => visit({})}
                        busy={loading}
                        busyLabel="Loading..."
                    >
                        Show
                    </Button>

                    <Button
                        look="secondary"
                        onClick={() => {
                            const query = new URLSearchParams({
                                kind,
                                from,
                                to,
                                ...(canChooseProvisional && provisional
                                    ? { provisional: "1" }
                                    : {}),
                            });

                            window.open(
                                `${url}/print?${query.toString()}`,
                                "_blank",
                            );
                        }}
                    >
                        Print
                    </Button>
                </div>
            </div>

            <div
                className="p-6"
                style={{ background: surface, border: `1px solid ${border}` }}
            >
                <ReportHead
                    header={report.header}
                    text={text}
                    textSecondary={textSecondary}
                    brand={brand}
                />

                {report.provisional_held_back > 0 && (
                    <p
                        className="mt-4 p-3"
                        style={{
                            background: dark
                                ? "rgba(180,83,9,0.15)"
                                : "#FEF3C7",
                            color: "#B45309",
                            fontSize: "16px",
                        }}
                    >
                        GHS {cedis(report.provisional_held_back)} is on a part
                        of the farm nobody has checked yet
                        {report.header.include_provisional
                            ? " and is included above."
                            : " and is left out above."}
                    </p>
                )}

                {kind === "statement" && (
                    <Statement
                        report={report}
                        colours={{
                            text,
                            textSecondary,
                            border,
                            headerBg,
                            rowAlt,
                            brand,
                            th,
                        }}
                    />
                )}

                {kind === "income" && (
                    <Income
                        report={report}
                        colours={{
                            text,
                            textSecondary,
                            border,
                            headerBg,
                            rowAlt,
                            brand,
                            th,
                        }}
                    />
                )}

                {kind === "trial-balance" && (
                    <TrialBalance
                        report={report}
                        colours={{
                            text,
                            textSecondary,
                            border,
                            headerBg,
                            rowAlt,
                            brand,
                            th,
                        }}
                    />
                )}

                <div
                    className="mt-6 pt-4"
                    style={{ borderTop: `1px solid ${border}` }}
                >
                    <p
                        style={{
                            fontSize: "15px",
                            color: textSecondary,
                            margin: 0,
                        }}
                    >
                        Prepared by {report.header.prepared_by} on{" "}
                        {shortDate(report.header.generated_at)}
                    </p>
                </div>
            </div>
        </div>
    );
}

interface Colours {
    text: string;
    textSecondary: string;
    border: string;
    headerBg: string;
    rowAlt: string;
    brand: string;
    th: { color: string; fontSize: string; fontWeight: number };
}

function ReportHead({
    header,
    text,
    textSecondary,
    brand,
}: {
    header: Header;
    text: string;
    textSecondary: string;
    brand: string;
}) {
    return (
        <div>
            <p
                style={{
                    fontSize: "16px",
                    fontWeight: 700,
                    color: brand,
                    margin: 0,
                    letterSpacing: "1px",
                }}
            >
                NKWALEDGER
            </p>
            <h2
                style={{
                    fontSize: "24px",
                    fontWeight: 700,
                    color: text,
                    margin: "4px 0 0",
                }}
            >
                {header.title}
            </h2>

            <div className="flex flex-wrap gap-6" style={{ marginTop: "10px" }}>
                <Fact
                    label="Farmer"
                    value={header.farmer_name}
                    text={text}
                    textSecondary={textSecondary}
                />
                <Fact
                    label="Phone"
                    value={header.farmer_phone ?? "—"}
                    text={text}
                    textSecondary={textSecondary}
                />
                <Fact
                    label="Period"
                    value={`${shortDate(header.from)} to ${shortDate(header.to)}`}
                    text={text}
                    textSecondary={textSecondary}
                />
                <Fact
                    label="Records waiting on approval"
                    value={header.include_provisional ? "Included" : "Left out"}
                    text={text}
                    textSecondary={textSecondary}
                />
                <Fact
                    label="Check code"
                    value={header.verification_code}
                    text={text}
                    textSecondary={textSecondary}
                />
            </div>
        </div>
    );
}

function Fact({
    label,
    value,
    text,
    textSecondary,
}: {
    label: string;
    value: string;
    text: string;
    textSecondary: string;
}) {
    return (
        <div>
            <p style={{ fontSize: "15px", color: textSecondary, margin: 0 }}>
                {label}
            </p>
            <p
                style={{
                    fontSize: "17px",
                    color: text,
                    margin: "2px 0 0",
                    fontWeight: 600,
                }}
            >
                {value}
            </p>
        </div>
    );
}

function Statement({ report, colours }: { report: Report; colours: Colours }) {
    const rows = (report.rows ?? []) as StatementRow[];

    return (
        <div className="mt-5 overflow-x-auto">
            <table className="w-full" style={{ borderCollapse: "collapse" }}>
                <thead>
                    <tr style={{ background: colours.headerBg }}>
                        {[
                            "Date",
                            "What happened",
                            "Reference",
                            "In",
                            "Out",
                            "Left",
                        ].map((heading) => (
                            <th
                                key={heading}
                                className="px-4 py-3 text-left"
                                style={colours.th}
                            >
                                {heading}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    <tr style={{ borderTop: `1px solid ${colours.border}` }}>
                        <td
                            className="px-4 py-3"
                            colSpan={5}
                            style={{ color: colours.textSecondary }}
                        >
                            Brought forward
                        </td>
                        <td
                            className="px-4 py-3"
                            style={{ color: colours.text, fontWeight: 600 }}
                        >
                            {cedis(report.opening_balance ?? 0)}
                        </td>
                    </tr>

                    {rows.length === 0 && (
                        <tr>
                            <td
                                colSpan={6}
                                className="px-4 py-6 text-center"
                                style={{ color: colours.textSecondary }}
                            >
                                Nothing was recorded in this period.
                            </td>
                        </tr>
                    )}

                    {rows.map((row, index) => (
                        <tr
                            key={row.reference}
                            style={{
                                borderTop: `1px solid ${colours.border}`,
                                background:
                                    index % 2 === 1
                                        ? colours.rowAlt
                                        : "transparent",
                            }}
                        >
                            <td
                                className="px-4 py-3"
                                style={{ color: colours.text }}
                            >
                                {shortDate(row.date)}
                            </td>
                            <td
                                className="px-4 py-3"
                                style={{ color: colours.text }}
                            >
                                {row.description}
                                {row.account && (
                                    <span
                                        style={{
                                            display: "block",
                                            fontSize: "15px",
                                            color: colours.textSecondary,
                                        }}
                                    >
                                        {row.account}
                                    </span>
                                )}
                                {row.value_lost > 0 && (
                                    <span
                                        style={{
                                            display: "block",
                                            fontSize: "15px",
                                            color: colours.textSecondary,
                                        }}
                                    >
                                        Worth GHS {cedis(row.value_lost)}, no
                                        money moved
                                    </span>
                                )}
                                {row.cancel_state === "cancelled" && (
                                    <span
                                        style={{
                                            display: "block",
                                            fontSize: "15px",
                                            color: colours.textSecondary,
                                        }}
                                    >
                                        Cancelled
                                    </span>
                                )}
                            </td>
                            <td
                                className="px-4 py-3"
                                style={{
                                    color: colours.textSecondary,
                                    fontSize: "16px",
                                }}
                            >
                                {row.reference}
                            </td>
                            <td
                                className="px-4 py-3"
                                style={{ color: colours.text }}
                            >
                                {row.money_in > 0 ? cedis(row.money_in) : "—"}
                            </td>
                            <td
                                className="px-4 py-3"
                                style={{ color: colours.text }}
                            >
                                {row.money_out > 0 ? cedis(row.money_out) : "—"}
                            </td>
                            <td
                                className="px-4 py-3"
                                style={{ color: colours.text, fontWeight: 600 }}
                            >
                                {cedis(row.balance)}
                            </td>
                        </tr>
                    ))}
                </tbody>
                <tfoot>
                    <tr
                        style={{
                            borderTop: `2px solid ${colours.border}`,
                            background: colours.headerBg,
                        }}
                    >
                        <td
                            className="px-4 py-3"
                            colSpan={3}
                            style={{ color: colours.text, fontWeight: 700 }}
                        >
                            Totals
                        </td>
                        <td
                            className="px-4 py-3"
                            style={{ color: colours.text, fontWeight: 700 }}
                        >
                            {cedis(report.total_in ?? 0)}
                        </td>
                        <td
                            className="px-4 py-3"
                            style={{ color: colours.text, fontWeight: 700 }}
                        >
                            {cedis(report.total_out ?? 0)}
                        </td>
                        <td
                            className="px-4 py-3"
                            style={{ color: colours.text, fontWeight: 700 }}
                        >
                            {cedis(report.closing_balance ?? 0)}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    );
}

function Income({ report, colours }: { report: Report; colours: Colours }) {
    const sections = [
        {
            label: "What came in",
            rows: report.income_rows ?? [],
            total: report.total_income ?? 0,
        },
        {
            label: "What went out",
            rows: report.expense_rows ?? [],
            total: report.total_expense ?? 0,
        },
        {
            label: "What was lost",
            rows: report.loss_rows ?? [],
            total: report.total_loss ?? 0,
        },
    ];

    const net = report.net ?? 0;

    return (
        <div className="mt-5">
            {sections.map((section) => (
                <div key={section.label} style={{ marginBottom: "22px" }}>
                    <p
                        style={{
                            fontSize: "18px",
                            fontWeight: 700,
                            color: colours.brand,
                            marginBottom: "6px",
                        }}
                    >
                        {section.label}
                    </p>

                    {section.rows.length === 0 && (
                        <p
                            style={{
                                fontSize: "17px",
                                color: colours.textSecondary,
                                margin: 0,
                            }}
                        >
                            Nothing here.
                        </p>
                    )}

                    {section.rows.map((row) => (
                        <div
                            key={row.account}
                            className="flex justify-between"
                            style={{
                                padding: "7px 0",
                                borderBottom: `1px solid ${colours.border}`,
                            }}
                        >
                            <span
                                style={{
                                    fontSize: "17px",
                                    color: colours.text,
                                }}
                            >
                                {row.account}
                            </span>
                            <span
                                style={{
                                    fontSize: "17px",
                                    color: colours.text,
                                }}
                            >
                                {cedis(row.amount)}
                            </span>
                        </div>
                    ))}

                    {section.rows.length > 0 && (
                        <div
                            className="flex justify-between"
                            style={{ padding: "8px 0" }}
                        >
                            <span
                                style={{
                                    fontSize: "17px",
                                    fontWeight: 700,
                                    color: colours.text,
                                }}
                            >
                                Total
                            </span>
                            <span
                                style={{
                                    fontSize: "17px",
                                    fontWeight: 700,
                                    color: colours.text,
                                }}
                            >
                                {cedis(section.total)}
                            </span>
                        </div>
                    )}
                </div>
            ))}

            <div
                className="flex justify-between p-4"
                style={{ background: colours.headerBg }}
            >
                <span
                    style={{
                        fontSize: "20px",
                        fontWeight: 700,
                        color: colours.text,
                    }}
                >
                    {net < 0 ? "Short by" : "What is left"}
                </span>
                <span
                    style={{
                        fontSize: "20px",
                        fontWeight: 700,
                        color: net < 0 ? "#B91C1C" : colours.brand,
                    }}
                >
                    GHS {cedis(Math.abs(net))}
                </span>
            </div>
        </div>
    );
}

function TrialBalance({
    report,
    colours,
}: {
    report: Report;
    colours: Colours;
}) {
    const rows = (report.rows ?? []) as TrialRow[];

    return (
        <div className="mt-5 overflow-x-auto">
            <table className="w-full" style={{ borderCollapse: "collapse" }}>
                <thead>
                    <tr style={{ background: colours.headerBg }}>
                        {["Code", "Account", "Side", "Debit", "Credit"].map(
                            (heading) => (
                                <th
                                    key={heading}
                                    className="px-4 py-3 text-left"
                                    style={colours.th}
                                >
                                    {heading}
                                </th>
                            ),
                        )}
                    </tr>
                </thead>
                <tbody>
                    {rows.length === 0 && (
                        <tr>
                            <td
                                colSpan={5}
                                className="px-4 py-6 text-center"
                                style={{ color: colours.textSecondary }}
                            >
                                Nothing was recorded in this period.
                            </td>
                        </tr>
                    )}

                    {rows.map((row, index) => (
                        <tr
                            key={row.account}
                            style={{
                                borderTop: `1px solid ${colours.border}`,
                                background:
                                    index % 2 === 1
                                        ? colours.rowAlt
                                        : "transparent",
                            }}
                        >
                            <td
                                className="px-4 py-3"
                                style={{ color: colours.textSecondary }}
                            >
                                {row.code ?? "—"}
                            </td>
                            <td
                                className="px-4 py-3"
                                style={{ color: colours.text }}
                            >
                                {row.account}
                            </td>
                            <td
                                className="px-4 py-3"
                                style={{ color: colours.textSecondary }}
                            >
                                {row.class ?? "—"}
                            </td>
                            <td
                                className="px-4 py-3"
                                style={{ color: colours.text }}
                            >
                                {row.debit > 0 ? cedis(row.debit) : "—"}
                            </td>
                            <td
                                className="px-4 py-3"
                                style={{ color: colours.text }}
                            >
                                {row.credit > 0 ? cedis(row.credit) : "—"}
                            </td>
                        </tr>
                    ))}
                </tbody>
                <tfoot>
                    <tr
                        style={{
                            borderTop: `2px solid ${colours.border}`,
                            background: colours.headerBg,
                        }}
                    >
                        <td
                            className="px-4 py-3"
                            colSpan={3}
                            style={{ color: colours.text, fontWeight: 700 }}
                        >
                            Totals
                        </td>
                        <td
                            className="px-4 py-3"
                            style={{ color: colours.text, fontWeight: 700 }}
                        >
                            {cedis(report.total_debit ?? 0)}
                        </td>
                        <td
                            className="px-4 py-3"
                            style={{ color: colours.text, fontWeight: 700 }}
                        >
                            {cedis(report.total_credit ?? 0)}
                        </td>
                    </tr>
                </tfoot>
            </table>

            <p
                style={{
                    marginTop: "12px",
                    fontSize: "17px",
                    fontWeight: 600,
                    color: report.is_balanced ? colours.brand : "#B91C1C",
                }}
            >
                {report.is_balanced
                    ? "The books balance."
                    : "The books do not balance. Tell somebody."}
            </p>
        </div>
    );
}
