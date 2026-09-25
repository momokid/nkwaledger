import AdminLayout from "@/Layouts/AdminLayout";
import { useTheme } from "@/Layouts/AuthenticatedLayout";
import { type } from "@/theme/typography";
import { router } from "@inertiajs/react";
import { useState } from "react";

interface ReportRow {
    uuid: string;
    kiosk_uuid: string;
    kiosk_name: string;
    urgent: boolean;
    reporter: string;
    reason: string;
    status: string;
    supplier_due_at: string | null;
    admin_due_at: string | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Filters {
    status?: string;
}

interface Props {
    reports: { data: ReportRow[]; links: PaginationLink[] };
    filters: Filters;
    permissions: { manage: boolean };
}

export default function Index({ reports, filters, permissions }: Props) {
    return (
        <AdminLayout title="Marketplace Kiosk Reports">
            <IndexContent reports={reports} filters={filters} permissions={permissions} />
        </AdminLayout>
    );
}

const statusLabel: Record<string, string> = {
    open: "Open",
    supplier_answered: "Supplier answered",
    with_admin: "With admin",
    resolved: "Resolved",
    suspended: "Suspended",
};

type ContentProps = Pick<Props, "reports" | "filters" | "permissions">;

function IndexContent({ reports, filters, permissions }: ContentProps) {
    const { dark } = useTheme();
    const [loading, setLoading] = useState(false);
    const [draft, setDraft] = useState<Filters>(filters);

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const inputBorder = dark ? "#4B5563" : "#9CA3AF";
    const inputBg = dark ? "#111827" : "#FFFFFF";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const headerBg = dark ? "rgba(29,158,117,0.15)" : "#EAF5F0";
    const headerText = "#1D9E75";
    const rowAlt = dark ? "#111827" : "#F9FAFB";
    const skeleton = dark ? "#374151" : "#E5E7EB";
    const primary = "#1D9E75";
    const amber = "#B45309";
    const danger = "#B91C1C";

    const cell = "px-4 py-3";

    const inputStyle = {
        border: `1px solid ${inputBorder}`,
        background: inputBg,
        color: text,
        padding: "10px 12px",
        fontSize: type.secondary,
        outline: "none",
        fontFamily: "inherit",
    };

    const go = (params: Filters) => {
        setLoading(true);
        router.get(
            route("admin.marketplace.kiosk-reports.index"),
            params as Record<string, string>,
            { preserveState: true, preserveScroll: true, onFinish: () => setLoading(false) },
        );
    };

    const visit = (url: string | null) => {
        if (!url) return;

        setLoading(true);
        router.visit(url, { preserveScroll: true, onFinish: () => setLoading(false) });
    };

    const resolve = (uuid: string) => {
        router.post(route("admin.marketplace.kiosk-reports.resolve", uuid), {}, { preserveScroll: true });
    };

    const extend = (uuid: string) => {
        router.post(route("admin.marketplace.kiosk-reports.extend", uuid), {}, { preserveScroll: true });
    };

    // reuses the same kiosk-suspend action the Kiosks list already uses - resolving the
    // report itself is handled separately, by the admin controller, once this lands
    const suspend = (kioskUuid: string, kioskName: string) => {
        if (!window.confirm(`Suspend "${kioskName}"?`)) {
            return;
        }
        router.patch(route("admin.marketplace.kiosks.suspend", kioskUuid), {}, { preserveScroll: true });
    };

    const dueDate = (report: ReportRow) => {
        const value = report.status === "with_admin" ? report.admin_due_at : report.supplier_due_at;

        if (!value) return "—";

        return new Date(value).toLocaleDateString("en-GB", { day: "numeric", month: "short", year: "numeric" });
    };

    const isOpenForAction = (status: string) => status !== "resolved" && status !== "suspended";

    const hasActions = permissions.manage;
    const columnCount = hasActions ? 6 : 5;

    return (
        <>
            <div
                className="flex flex-wrap gap-3 mb-4 items-end"
                style={{ background: surface, border: `1px solid ${border}`, padding: "16px" }}
            >
                <div>
                    <label style={{ display: "block", fontSize: type.secondary, color: textSecondary, marginBottom: "4px" }}>
                        Status
                    </label>
                    <select
                        value={draft.status ?? ""}
                        onChange={(event) => {
                            const next = { ...draft, status: event.target.value || undefined };
                            setDraft(next);
                            go(next);
                        }}
                        style={inputStyle}
                    >
                        <option value="">All statuses</option>
                        {Object.entries(statusLabel).map(([value, label]) => (
                            <option key={value} value={value}>{label}</option>
                        ))}
                    </select>
                </div>
            </div>

            <div className="overflow-x-auto" style={{ background: surface, border: `1px solid ${border}` }}>
                <table className="min-w-full" style={{ fontSize: type.tableCell }}>
                    <thead>
                        <tr style={{ background: headerBg }}>
                            {["Kiosk", "Reporter", "Reason", "Status", "Due"].map((label) => (
                                <th key={label} className={`text-left ${cell}`} style={{ color: headerText, fontWeight: 700, fontSize: type.tableHeader }}>
                                    {label}
                                </th>
                            ))}
                            {hasActions && (
                                <th className={`text-left ${cell}`} style={{ color: headerText, fontWeight: 700, fontSize: type.tableHeader }}>
                                    Action
                                </th>
                            )}
                        </tr>
                    </thead>
                    <tbody>
                        {loading &&
                            Array.from({ length: 6 }).map((_, row) => (
                                <tr key={`placeholder-${row}`} style={{ borderTop: `1px solid ${border}` }}>
                                    {Array.from({ length: columnCount }).map((__, column) => (
                                        <td key={column} className={cell}>
                                            <div style={{ height: "16px", width: column === 0 ? "80%" : "55%", background: skeleton }} />
                                        </td>
                                    ))}
                                </tr>
                            ))}

                        {!loading && reports.data.length === 0 && (
                            <tr>
                                <td colSpan={columnCount} className="px-4 py-6 text-center" style={{ color: textSecondary, fontSize: type.body }}>
                                    No kiosk reports yet.
                                </td>
                            </tr>
                        )}

                        {!loading &&
                            reports.data.map((report, index) => (
                                <tr
                                    key={report.uuid}
                                    style={{ borderTop: `1px solid ${border}`, background: index % 2 === 1 ? rowAlt : "transparent" }}
                                >
                                    <td className={cell} style={{ color: text }}>
                                        {report.kiosk_name}
                                        {report.urgent && (
                                            <span style={{ color: danger, marginLeft: "8px", fontSize: type.hint, fontWeight: 600 }}>
                                                Urgent
                                            </span>
                                        )}
                                    </td>
                                    <td className={cell} style={{ color: text }}>{report.reporter}</td>
                                    <td className={cell} style={{ color: text }}>{report.reason}</td>
                                    <td className={cell}>
                                        <span
                                            style={{
                                                color: report.status === "resolved" ? primary : report.status === "suspended" ? danger : amber,
                                                fontWeight: 600,
                                            }}
                                        >
                                            {statusLabel[report.status]}
                                        </span>
                                    </td>
                                    <td className={cell} style={{ color: textSecondary, fontSize: type.secondary }}>{dueDate(report)}</td>
                                    {hasActions && (
                                        <td className={cell} style={{ display: "flex", gap: "12px" }}>
                                            {isOpenForAction(report.status) && (
                                                <button
                                                    onClick={() => resolve(report.uuid)}
                                                    style={{ color: primary, background: "transparent", border: "none", fontWeight: 600, cursor: "pointer", fontFamily: "inherit" }}
                                                >
                                                    Resolve
                                                </button>
                                            )}
                                            {report.status === "with_admin" && (
                                                <button
                                                    onClick={() => extend(report.uuid)}
                                                    style={{ color: text, background: "transparent", border: "none", fontWeight: 600, cursor: "pointer", fontFamily: "inherit" }}
                                                >
                                                    Extend window
                                                </button>
                                            )}
                                            {report.status === "with_admin" && (
                                                <button
                                                    onClick={() => suspend(report.kiosk_uuid, report.kiosk_name)}
                                                    style={{ color: danger, background: "transparent", border: "none", fontWeight: 600, cursor: "pointer", fontFamily: "inherit" }}
                                                >
                                                    Suspend
                                                </button>
                                            )}
                                        </td>
                                    )}
                                </tr>
                            ))}
                    </tbody>
                </table>
            </div>

            <div className="flex flex-wrap gap-2 mt-4">
                {reports.links.map((link) => (
                    <button
                        key={link.label}
                        onClick={() => visit(link.url)}
                        disabled={!link.url || loading}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                        style={{
                            padding: "8px 14px",
                            fontSize: type.secondary,
                            border: `1px solid ${link.active ? primary : border}`,
                            background: link.active ? primary : surface,
                            color: link.active ? "#FFFFFF" : link.url ? text : textSecondary,
                            cursor: link.url && !loading ? "pointer" : "not-allowed",
                            fontFamily: "inherit",
                        }}
                    />
                ))}
            </div>
        </>
    );
}
