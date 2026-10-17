import AdminLayout from "@/Layouts/AdminLayout";
import { useTheme } from "@/Layouts/AuthenticatedLayout";
import TableSkeletonRows from "@/Components/Admin/TableSkeletonRows";
import { cedis } from "@/lib/format";
import { PageProps } from "@/types";
import { useEffect, useRef, useState } from "react";

interface Figures {
    loans: number;
    amount: number;
    interest: number;
    repaid: number;
    balance: number;
    repaid_percent: number;
}

interface Row extends Figures {
    label: string;
}

interface Props extends PageProps {
    rows: Row[];
    totals: Figures;
    group: string;
    from: string;
    to: string;
}

const GROUPS: { value: string; label: string }[] = [
    { value: "region", label: "Region" },
    { value: "district", label: "District" },
    { value: "farm_type", label: "Farm Type" },
    { value: "lender_type", label: "Lender Type" },
    { value: "lender", label: "Lender" },
    { value: "farmer_group", label: "Farmer Group" },
    { value: "agent", label: "Agent" },
    { value: "month", label: "Month" },
    { value: "status", label: "Status" },
    { value: "checked", label: "Checked with the lender" },
];

export default function Index(props: Props) {
    return (
        <AdminLayout title="Loan Analysis">
            <IndexContent {...props} />
        </AdminLayout>
    );
}

type ContentProps = Pick<Props, "rows" | "totals" | "group" | "from" | "to">;

function IndexContent({ rows: firstRows, totals: firstTotals, group: firstGroup, from: firstFrom, to: firstTo }: ContentProps) {
    const { dark } = useTheme();
    const [rows, setRows] = useState(firstRows);
    const [totals, setTotals] = useState(firstTotals);
    const [group, setGroup] = useState(firstGroup);
    const [from, setFrom] = useState(firstFrom);
    const [to, setTo] = useState(firstTo);
    const [loading, setLoading] = useState(false);
    const first = useRef(true);

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const inputBorder = dark ? "#4B5563" : "#9CA3AF";
    const inputBg = dark ? "#111827" : "#FFFFFF";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const track = dark ? "#374151" : "#E5E7EB";

    const field = { padding: "8px 10px", border: `1px solid ${inputBorder}`, background: inputBg, color: text, fontSize: "1.0625rem" } as const;
    const head = { textAlign: "left", padding: "10px 16px", fontSize: "0.9375rem", fontWeight: 600, color: textSecondary, borderBottom: `1px solid ${border}` } as const;
    const label = { display: "block", fontSize: "0.9375rem", fontWeight: 600, color: textSecondary, marginBottom: "4px" } as const;

    const groupLabel = GROUPS.find((option) => option.value === group)?.label ?? "";

    // changing the grouping or the dates is fetched in place: the page itself is never reloaded
    useEffect(() => {
        if (first.current) {
            first.current = false;

            return;
        }

        const controller = new AbortController();

        (async () => {
            setLoading(true);

            try {
                const query = new URLSearchParams({ group, ...(from ? { from } : {}), ...(to ? { to } : {}) });
                const response = await fetch(`/admin/loan-analysis?${query}`, { signal: controller.signal, headers: { Accept: "application/json" } });

                if (response.ok) {
                    const json = await response.json();

                    setRows(json.rows);
                    setTotals(json.totals);
                }
            } catch {
                // an aborted or failed fetch leaves the table as it was
            } finally {
                setLoading(false);
            }
        })();

        return () => controller.abort();
    }, [group, from, to]);

    const money = (minor: number) => `GHS ${cedis(minor)}`;

    const bar = (percent: number) => (
        <div className="flex items-center gap-2">
            <div role="progressbar" aria-valuemin={0} aria-valuemax={100} aria-valuenow={percent} style={{ width: "72px", height: "8px", background: track }}>
                <div style={{ width: `${Math.min(100, percent)}%`, height: "100%", background: "#1D9E75" }} />
            </div>
            <span>{percent}%</span>
        </div>
    );

    return (
        <div>
            <div className="flex flex-wrap gap-3">
                <div>
                    <label htmlFor="analysis-group" style={label}>
                        Group by
                    </label>
                    <select id="analysis-group" style={field} value={group} onChange={(event) => setGroup(event.target.value)}>
                        {GROUPS.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                </div>
                <div>
                    <label htmlFor="analysis-from" style={label}>
                        From
                    </label>
                    <input id="analysis-from" type="date" style={field} value={from} onChange={(event) => setFrom(event.target.value)} />
                </div>
                <div>
                    <label htmlFor="analysis-to" style={label}>
                        To
                    </label>
                    <input id="analysis-to" type="date" style={field} value={to} onChange={(event) => setTo(event.target.value)} />
                </div>
            </div>

            {group === "farm_type" && (
                <p className="mt-3" style={{ color: textSecondary, fontSize: "1rem" }}>
                    A farmer with more than one farm type is counted under each of them
                </p>
            )}

            <div className="mt-4 overflow-x-auto" style={{ background: surface, border: `1px solid ${border}` }}>
                <table className="w-full" style={{ fontSize: "1.0625rem", color: text }}>
                    <thead>
                        <tr>
                            <th style={head}>{groupLabel}</th>
                            <th style={head}>Loans</th>
                            <th style={head}>Amount</th>
                            <th style={head}>Interest</th>
                            <th style={head}>Repaid</th>
                            <th style={head}>Loan Balance</th>
                            <th style={head}>Repaid %</th>
                        </tr>
                    </thead>
                    <tbody>
                        {loading && <TableSkeletonRows rows={5} columns={7} />}

                        {!loading && rows.length === 0 && (
                            <tr>
                                <td colSpan={7} className="px-4 py-6 text-center" style={{ color: textSecondary }}>
                                    No loans yet
                                </td>
                            </tr>
                        )}

                        {!loading &&
                            rows.map((row) => (
                                <tr key={row.label} style={{ borderTop: `1px solid ${border}` }}>
                                    <td className="px-4 py-3">{row.label}</td>
                                    <td className="px-4 py-3">{row.loans}</td>
                                    <td className="px-4 py-3">{money(row.amount)}</td>
                                    <td className="px-4 py-3">{money(row.interest)}</td>
                                    <td className="px-4 py-3">{money(row.repaid)}</td>
                                    <td className="px-4 py-3">{money(row.balance)}</td>
                                    <td className="px-4 py-3">{bar(row.repaid_percent)}</td>
                                </tr>
                            ))}

                        {!loading && rows.length > 0 && (
                            <tr style={{ borderTop: `2px solid ${border}`, fontWeight: 700 }}>
                                <td className="px-4 py-3">Total</td>
                                <td className="px-4 py-3">{totals.loans}</td>
                                <td className="px-4 py-3">{money(totals.amount)}</td>
                                <td className="px-4 py-3">{money(totals.interest)}</td>
                                <td className="px-4 py-3">{money(totals.repaid)}</td>
                                <td className="px-4 py-3">{money(totals.balance)}</td>
                                <td className="px-4 py-3">{bar(totals.repaid_percent)}</td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
