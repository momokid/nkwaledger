import { useTheme } from "@/Layouts/AuthenticatedLayout";
import TableSkeletonRows from "@/Components/Admin/TableSkeletonRows";
import Button from "@/Components/Button";
import { wording } from "@/lib/wording";
import { cedis } from "@/lib/format";
import { Link } from "@inertiajs/react";
import { PageProps } from "@/types";
import { useEffect, useRef, useState } from "react";

type Status = "active" | "repaid" | "cancelled" | "pending";

interface Row {
    row: "loan" | "entry";
    uuid: string;
    kind?: "loan" | "repayment";
    farmer: string;
    lender: string;
    amount: number;
    balance: number | null;
    status: Status;
}

interface Pagination {
    page: number;
    last: number;
}

export interface LoansListProps extends PageProps {
    rows: Row[];
    pagination: Pagination;
    total_owed: number;
    filters: { search: string; status: string };
}

const STATUS_LABELS: Record<Status, string> = {
    active: "Active",
    repaid: "Repaid",
    cancelled: "Cancelled",
    pending: "Waiting for the farmer to accept",
};

const FILTERS: { value: string; label: string }[] = [
    { value: "all", label: "All" },
    { value: "active", label: "Active" },
    { value: "repaid", label: "Repaid" },
    { value: "cancelled", label: "Cancelled" },
    { value: "waiting", label: "Waiting for the farmer to accept" },
];

type ContentProps = Pick<LoansListProps, "rows" | "pagination" | "total_owed" | "filters"> & { base: string };

// the same list for the admin and for the agent: only the address differs, and the server keeps each to their own loans
export default function LoansList({ base, rows: firstRows, pagination: firstPagination, total_owed, filters }: ContentProps) {
    const { dark } = useTheme();
    const [rows, setRows] = useState(firstRows);
    const [pagination, setPagination] = useState(firstPagination);
    const [totalOwed, setTotalOwed] = useState(total_owed);
    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState(filters.status);
    const [page, setPage] = useState(firstPagination.page);
    const [loading, setLoading] = useState(false);
    const first = useRef(true);

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const inputBorder = dark ? "#4B5563" : "#9CA3AF";
    const inputBg = dark ? "#111827" : "#FFFFFF";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";

    const field = { padding: "8px 10px", border: `1px solid ${inputBorder}`, background: inputBg, color: text, fontSize: "1.0625rem" } as const;
    const head = { textAlign: "left", padding: "10px 16px", fontSize: "0.9375rem", fontWeight: 600, color: textSecondary, borderBottom: `1px solid ${border}` } as const;

    // every search, filter and page is fetched in place: the page itself is never reloaded
    useEffect(() => {
        if (first.current) {
            first.current = false;

            return;
        }

        const controller = new AbortController();
        const timer = setTimeout(async () => {
            setLoading(true);

            try {
                const query = new URLSearchParams({ search, status, page: String(page) });
                const response = await fetch(`${base}?${query}`, { signal: controller.signal, headers: { Accept: "application/json" } });
                const json = await response.json();

                setRows(json.rows);
                setPagination(json.pagination);
                setTotalOwed(json.total_owed);
            } catch {
                // an aborted or failed fetch leaves the list as it was
            } finally {
                setLoading(false);
            }
        }, 250);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [base, search, status, page]);

    return (
        <div>
            <div className="p-4" style={{ background: surface, border: `1px solid ${border}`, maxWidth: "360px" }}>
                <p style={{ fontSize: "1.0625rem", color: textSecondary }}>Total owed on loans</p>
                <p style={{ fontSize: "1.625rem", fontWeight: 700, color: text }}>GHS {cedis(totalOwed)}</p>
            </div>

            <div className="mt-4 flex flex-wrap gap-3">
                <input
                    aria-label="Search by farmer or lender"
                    placeholder="Search by farmer or lender"
                    style={{ ...field, minWidth: "260px" }}
                    value={search}
                    onChange={(event) => {
                        setSearch(event.target.value);
                        setPage(1);
                    }}
                />
                <select
                    aria-label="Status"
                    style={field}
                    value={status}
                    onChange={(event) => {
                        setStatus(event.target.value);
                        setPage(1);
                    }}
                >
                    {FILTERS.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>
            </div>

            <div className="mt-4 overflow-x-auto" style={{ background: surface, border: `1px solid ${border}` }}>
                <table className="w-full" style={{ fontSize: "1.0625rem", color: text }}>
                    <thead>
                        <tr>
                            <th style={head}>Farmer</th>
                            <th style={head}>Lender</th>
                            <th style={head}>Amount</th>
                            <th style={head}>Loan Balance</th>
                            <th style={head}>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {loading && <TableSkeletonRows rows={5} columns={5} />}

                        {!loading && rows.length === 0 && (
                            <tr>
                                <td colSpan={5} className="px-4 py-6 text-center" style={{ color: textSecondary }}>
                                    No loans yet
                                </td>
                            </tr>
                        )}

                        {!loading &&
                            rows.map((row) => (
                                <tr key={`${row.row}-${row.uuid}`} style={{ borderTop: `1px solid ${border}` }}>
                                    <td className="px-4 py-3">{row.farmer}</td>
                                    <td className="px-4 py-3">
                                        {row.row === "loan" ? (
                                            <Link href={`${base}/${row.uuid}`} style={{ color: "#1D9E75", fontWeight: 600 }}>
                                                {row.lender}
                                            </Link>
                                        ) : (
                                            row.lender
                                        )}
                                        {row.kind === "repayment" && <span style={{ color: textSecondary }}> - {wording("repayment.entry")}</span>}
                                    </td>
                                    <td className="px-4 py-3">GHS {cedis(row.amount)}</td>
                                    <td className="px-4 py-3">{row.balance === null ? "" : `GHS ${cedis(row.balance)}`}</td>
                                    <td className="px-4 py-3">{STATUS_LABELS[row.status]}</td>
                                </tr>
                            ))}
                    </tbody>
                </table>
            </div>

            {pagination.last > 1 && (
                <div className="mt-4 flex gap-3">
                    {pagination.page > 1 && (
                        <Button type="button" look="secondary" size="small" onClick={() => setPage(pagination.page - 1)}>
                            &larr;
                        </Button>
                    )}
                    {pagination.page < pagination.last && (
                        <Button type="button" look="secondary" size="small" onClick={() => setPage(pagination.page + 1)}>
                            &rarr;
                        </Button>
                    )}
                </div>
            )}
        </div>
    );
}
