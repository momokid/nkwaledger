import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import useAuthGuard from "@/hooks/useAuthGuard";
import { Head } from "@inertiajs/react";
import GreetingHeader from "@/Components/GreetingHeader";

const kpis = [
    { label: "Farmers managed", value: "24" },
    { label: "Approvals pending", value: "5" },
    { label: "Income logged (30 days)", value: "GHS 12,400" },
    { label: "Farms visited this week", value: "9" },
];

export default function Dashboard() {
    useAuthGuard();

    return (
        <AuthenticatedLayout title="Dashboard">
            <Head title="Dashboard" />
            <DashboardContent />
        </AuthenticatedLayout>
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
                    fontSize: "16px",
                    color: dark ? "#FBBF24" : "#92400E",
                }}
            >
                Preview — this dashboard is under construction. The numbers
                below are examples, not your real data.
            </div>

            <GreetingHeader subtitle="Here is your agent overview." />

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
                                fontSize: "18px",
                                color: textSecondary,
                                marginBottom: "8px",
                            }}
                        >
                            {kpi.label}
                        </p>
                        <p
                            style={{
                                fontSize: "26px",
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
