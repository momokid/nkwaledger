import AdminLayout from "@/Layouts/AdminLayout";
import { useTheme } from "@/Layouts/AuthenticatedLayout";
import { router } from "@inertiajs/react";

interface KioskRow {
    uuid: string;
    kiosk_number: string | null;
    name: string;
    supplier: string | null;
    status: string;
    confirmed_at: string | null;
    requires_admin_approval: boolean;
    admin_approved_at: string | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props {
    kiosks: {
        data: KioskRow[];
        links: PaginationLink[];
    };
    permissions: {
        approve: boolean;
        suspend: boolean;
    };
}

export default function Index({ kiosks, permissions }: Props) {
    return (
        <AdminLayout title="Marketplace Kiosks">
            <IndexContent kiosks={kiosks} permissions={permissions} />
        </AdminLayout>
    );
}

const statusLabel: Record<string, string> = {
    pending_confirmation: "Pending confirmation",
    active: "Active",
    suspended: "Suspended",
};

function IndexContent({ kiosks, permissions }: Props) {
    const { dark } = useTheme();

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const primary = "#1D9E75";
    const amber = "#B45309";
    const danger = "#B91C1C";

    const approve = (uuid: string) => {
        router.patch(route("admin.marketplace.kiosks.approve", uuid), {}, { preserveScroll: true });
    };

    const suspend = (uuid: string, name: string) => {
        if (!window.confirm(`Suspend "${name}"?`)) {
            return;
        }
        router.patch(route("admin.marketplace.kiosks.suspend", uuid), {}, { preserveScroll: true });
    };

    const restore = (uuid: string) => {
        router.patch(route("admin.marketplace.kiosks.restore", uuid), {}, { preserveScroll: true });
    };

    const hasActions = permissions.approve || permissions.suspend;

    return (
        <div className="overflow-x-auto" style={{ background: surface, border: `1px solid ${border}` }}>
            <table className="min-w-full" style={{ fontSize: "1.0625rem" }}>
                <thead>
                    <tr style={{ background: dark ? "rgba(29,158,117,0.15)" : "#EAF5F0" }}>
                        <th className="text-left px-4 py-3" style={{ color: primary, fontWeight: 700 }}>Number</th>
                        <th className="text-left px-4 py-3" style={{ color: primary, fontWeight: 700 }}>Name</th>
                        <th className="text-left px-4 py-3" style={{ color: primary, fontWeight: 700 }}>Supplier</th>
                        <th className="text-left px-4 py-3" style={{ color: primary, fontWeight: 700 }}>Status</th>
                        {hasActions && (
                            <th className="text-left px-4 py-3" style={{ color: primary, fontWeight: 700 }}>Action</th>
                        )}
                    </tr>
                </thead>
                <tbody>
                    {kiosks.data.length === 0 && (
                        <tr>
                            <td colSpan={hasActions ? 5 : 4} className="px-4 py-6 text-center" style={{ color: textSecondary }}>
                                No kiosks yet.
                            </td>
                        </tr>
                    )}
                    {kiosks.data.map((kiosk) => (
                        <tr key={kiosk.uuid} style={{ borderTop: `1px solid ${border}` }}>
                            <td className="px-4 py-3" style={{ color: text }}>{kiosk.kiosk_number}</td>
                            <td className="px-4 py-3" style={{ color: text }}>{kiosk.name}</td>
                            <td className="px-4 py-3" style={{ color: text }}>{kiosk.supplier}</td>
                            <td className="px-4 py-3" style={{ color: text }}>
                                {statusLabel[kiosk.status]}
                                {kiosk.requires_admin_approval && !kiosk.admin_approved_at && (
                                    <span style={{ color: amber, marginLeft: "8px", fontSize: "0.9375rem" }}>
                                        (needs approval)
                                    </span>
                                )}
                            </td>
                            {hasActions && (
                                <td className="px-4 py-3" style={{ display: "flex", gap: "12px" }}>
                                    {permissions.approve &&
                                        kiosk.requires_admin_approval &&
                                        !kiosk.admin_approved_at && (
                                            <button
                                                onClick={() => approve(kiosk.uuid)}
                                                style={{ color: primary, background: "transparent", border: "none", fontWeight: 600, cursor: "pointer" }}
                                            >
                                                Approve
                                            </button>
                                        )}
                                    {permissions.suspend && kiosk.status !== "suspended" && (
                                        <button
                                            onClick={() => suspend(kiosk.uuid, kiosk.name)}
                                            style={{ color: danger, background: "transparent", border: "none", fontWeight: 600, cursor: "pointer" }}
                                        >
                                            Suspend
                                        </button>
                                    )}
                                    {permissions.suspend && kiosk.status === "suspended" && (
                                        <button
                                            onClick={() => restore(kiosk.uuid)}
                                            style={{ color: primary, background: "transparent", border: "none", fontWeight: 600, cursor: "pointer" }}
                                        >
                                            Restore
                                        </button>
                                    )}
                                </td>
                            )}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
