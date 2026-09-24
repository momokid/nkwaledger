import AdminLayout from "@/Layouts/AdminLayout";
import { useTheme } from "@/Layouts/AuthenticatedLayout";
import { Link, router } from "@inertiajs/react";

interface SupplierRow {
    uuid: string;
    business_name: string | null;
    email: string;
    phone: string | null;
    verification_status: string;
    account_status: string;
    kiosks_count: number;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props {
    suppliers: {
        data: SupplierRow[];
        links: PaginationLink[];
    };
    permissions: {
        suspend: boolean;
    };
}

export default function Index({ suppliers, permissions }: Props) {
    return (
        <AdminLayout title="Marketplace Suppliers">
            <IndexContent suppliers={suppliers} permissions={permissions} />
        </AdminLayout>
    );
}

const verificationLabel: Record<string, string> = {
    unverified: "Unverified",
    email_and_phone_verified: "Email & phone verified",
    id_verified: "ID verified",
};

function IndexContent({ suppliers, permissions }: Props) {
    const { dark } = useTheme();

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const primary = "#1D9E75";
    const danger = "#B91C1C";

    const suspend = (uuid: string, name: string | null) => {
        if (!window.confirm(`Suspend ${name ?? "this supplier"} and all of its kiosks?`)) {
            return;
        }
        router.patch(route("admin.marketplace.suppliers.suspend", uuid), {}, { preserveScroll: true });
    };

    const restore = (uuid: string) => {
        router.patch(route("admin.marketplace.suppliers.restore", uuid), {}, { preserveScroll: true });
    };

    return (
        <div className="overflow-x-auto" style={{ background: surface, border: `1px solid ${border}` }}>
            <table className="min-w-full" style={{ fontSize: "1.0625rem" }}>
                <thead>
                    <tr style={{ background: dark ? "rgba(29,158,117,0.15)" : "#EAF5F0" }}>
                        <th className="text-left px-4 py-3" style={{ color: primary, fontWeight: 700 }}>Business</th>
                        <th className="text-left px-4 py-3" style={{ color: primary, fontWeight: 700 }}>Contact</th>
                        <th className="text-left px-4 py-3" style={{ color: primary, fontWeight: 700 }}>Verification</th>
                        <th className="text-left px-4 py-3" style={{ color: primary, fontWeight: 700 }}>Status</th>
                        <th className="text-left px-4 py-3" style={{ color: primary, fontWeight: 700 }}>Kiosks</th>
                        <th className="text-left px-4 py-3" style={{ color: primary, fontWeight: 700 }}>Stock</th>
                        {permissions.suspend && (
                            <th className="text-left px-4 py-3" style={{ color: primary, fontWeight: 700 }}>Action</th>
                        )}
                    </tr>
                </thead>
                <tbody>
                    {suppliers.data.length === 0 && (
                        <tr>
                            <td colSpan={permissions.suspend ? 7 : 6} className="px-4 py-6 text-center" style={{ color: textSecondary }}>
                                No suppliers yet.
                            </td>
                        </tr>
                    )}
                    {suppliers.data.map((supplier) => (
                        <tr key={supplier.uuid} style={{ borderTop: `1px solid ${border}` }}>
                            <td className="px-4 py-3" style={{ color: text }}>{supplier.business_name}</td>
                            <td className="px-4 py-3" style={{ color: text }}>
                                {supplier.email}
                                <br />
                                <span style={{ color: textSecondary, fontSize: "0.9375rem" }}>{supplier.phone}</span>
                            </td>
                            <td className="px-4 py-3" style={{ color: text }}>{verificationLabel[supplier.verification_status]}</td>
                            <td className="px-4 py-3">
                                <span style={{ color: supplier.account_status === "active" ? primary : danger, fontWeight: 600 }}>
                                    {supplier.account_status === "active" ? "Active" : "Suspended"}
                                </span>
                            </td>
                            <td className="px-4 py-3" style={{ color: text }}>{supplier.kiosks_count}</td>
                            <td className="px-4 py-3">
                                <Link
                                    href={route("admin.marketplace.suppliers.stock", supplier.uuid)}
                                    style={{ color: primary, fontWeight: 600 }}
                                >
                                    View stock
                                </Link>
                            </td>
                            {permissions.suspend && (
                                <td className="px-4 py-3">
                                    {supplier.account_status === "active" ? (
                                        <button
                                            onClick={() => suspend(supplier.uuid, supplier.business_name)}
                                            style={{ color: danger, background: "transparent", border: "none", fontWeight: 600, cursor: "pointer" }}
                                        >
                                            Suspend
                                        </button>
                                    ) : (
                                        <button
                                            onClick={() => restore(supplier.uuid)}
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
