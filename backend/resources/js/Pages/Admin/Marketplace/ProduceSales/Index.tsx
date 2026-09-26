import AdminLayout from "@/Layouts/AdminLayout";
import { useTheme } from "@/Layouts/AuthenticatedLayout";
import { router } from "@inertiajs/react";

interface SaleRow {
    uuid: string;
    farmer: string;
    buyer: string;
    product_name: string | null;
    quantity: number;
    amount_minor: number;
    status: string;
    agent: string | null;
    counts_toward_credit: boolean;
    requested_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props {
    sales: { data: SaleRow[]; links: PaginationLink[] };
}

export default function Index({ sales }: Props) {
    return (
        <AdminLayout title="Produce Sales">
            <IndexContent sales={sales} />
        </AdminLayout>
    );
}

function IndexContent({ sales }: Props) {
    const { dark } = useTheme();
    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const headerBg = dark ? "rgba(29,158,117,0.15)" : "#EAF5F0";
    const headerText = "#1D9E75";
    const primary = "#1D9E75";
    const amber = "#B45309";

    const cedis = (minor: number) => `GHS ${(minor / 100).toFixed(2)}`;
    const cell = "px-4 py-3";

    const visit = (url: string | null) => {
        if (!url) return;
        router.visit(url, { preserveScroll: true });
    };

    return (
        <>
            <h1 style={{ color: text, fontSize: "1.375rem", fontWeight: 700, margin: "0 0 6px" }}>Produce Sales</h1>
            <p style={{ color: textSecondary, fontSize: "0.9375rem", margin: "0 0 16px" }}>
                Visibility only - a sale counts toward a farmer's credit score once an agent has co-confirmed it.
            </p>

            <div style={{ background: surface, border: `1px solid ${border}`, overflowX: "auto" }}>
                <table className="w-full" style={{ borderCollapse: "collapse" }}>
                    <thead>
                        <tr style={{ background: headerBg }}>
                            {["Farmer", "Buyer", "Product", "Qty", "Amount", "Status", "Agent", "Counts toward credit"].map((label) => (
                                <th key={label} className={cell} style={{ textAlign: "left", color: headerText, fontSize: "0.8125rem" }}>
                                    {label}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {sales.data.length === 0 && (
                            <tr>
                                <td className={cell} colSpan={8} style={{ color: textSecondary, textAlign: "center" }}>
                                    No produce sales yet.
                                </td>
                            </tr>
                        )}
                        {sales.data.map((sale) => (
                            <tr key={sale.uuid} style={{ borderTop: `1px solid ${border}` }}>
                                <td className={cell} style={{ color: text }}>{sale.farmer}</td>
                                <td className={cell} style={{ color: text }}>{sale.buyer}</td>
                                <td className={cell} style={{ color: text }}>{sale.product_name}</td>
                                <td className={cell} style={{ color: text }}>{sale.quantity}</td>
                                <td className={cell} style={{ color: text }}>{cedis(sale.amount_minor)}</td>
                                <td className={cell} style={{ color: textSecondary }}>{sale.status}</td>
                                <td className={cell} style={{ color: textSecondary }}>{sale.agent ?? "-"}</td>
                                <td className={cell} style={{ color: sale.counts_toward_credit ? primary : amber, fontWeight: 600 }}>
                                    {sale.counts_toward_credit ? "Yes" : "Not yet"}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {sales.links.length > 3 && (
                <div className="flex flex-wrap gap-2 mt-4">
                    {sales.links.map((link) => (
                        <button
                            key={link.label}
                            onClick={() => visit(link.url)}
                            disabled={!link.url}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                            style={{
                                padding: "8px 14px",
                                fontSize: "1rem",
                                border: `1px solid ${link.active ? primary : border}`,
                                background: link.active ? primary : surface,
                                color: link.active ? "#FFFFFF" : link.url ? text : textSecondary,
                                cursor: link.url ? "pointer" : "not-allowed",
                                fontFamily: "inherit",
                            }}
                        />
                    ))}
                </div>
            )}
        </>
    );
}
