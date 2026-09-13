import AdminLayout from "@/Layouts/AdminLayout";
import { useTheme } from "@/Layouts/AuthenticatedLayout";
import GreetingHeader from "@/Components/GreetingHeader";

const kpis = [
    { label: "Total farmers", value: "1,204" },
    { label: "Active agents", value: "38" },
    { label: "Pending approvals", value: "12" },
    { label: "Income logged (30 days)", value: "GHS 482,900" },
];

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
        <div className="p-6">
            <div
                style={{
                    background: dark ? "rgba(180,83,9,0.15)" : "#FEF3C7",
                    border: `1px solid ${dark ? "rgba(180,83,9,0.3)" : "#FDE68A"}`,
                    padding: "12px 16px",
                    marginBottom: "20px",
                    fontSize: "1rem",
                    color: dark ? "#FBBF24" : "#92400E",
                }}
            >
                Preview — this dashboard is under construction. The numbers
                below are examples, not your real data.
            </div>

            <GreetingHeader subtitle="Here is what's happening across the platform." />

            <div
                style={{
                    display: "grid",
                    gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))",
                    gap: "16px",
                }}
            >
                {kpis.map((kpi) => (
                    <div
                        key={kpi.label}
                        style={{
                            background: surface,
                            border: `1px solid ${border}`,
                            padding: "18px",
                        }}
                    >
                        <p
                            style={{
                                fontSize: "1.125rem",
                                color: textSecondary,
                                marginBottom: "8px",
                            }}
                        >
                            {kpi.label}
                        </p>
                        <p
                            style={{
                                fontSize: "1.625rem",
                                fontWeight: 700,
                                color: text,
                                letterSpacing: "-0.5px",
                            }}
                        >
                            {kpi.value}
                        </p>
                    </div>
                ))}
            </div>
        </div>
    );
}
