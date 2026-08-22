import AdminLayout from "@/Layouts/AdminLayout";
import { useTheme } from "@/Layouts/AuthenticatedLayout";

export default function Dashboard() {
    return (
        <AdminLayout title="Dashboard">
            <DashboardContent />
        </AdminLayout>
    );
}

function DashboardContent() {
    const { dark } = useTheme();

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";

    return (
        <div
            className="p-6"
            style={{ background: surface, border: `1px solid ${border}` }}
        >
            <h2 style={{ fontSize: "22px", fontWeight: 700, color: text }}>
                Welcome back
            </h2>
            <p
                style={{
                    fontSize: "20px",
                    color: textSecondary,
                    marginTop: "4px",
                }}
            >
                Farm types, farmer groups, and ledger accounts will show up here
                as we build out their screens.
            </p>
        </div>
    );
}
