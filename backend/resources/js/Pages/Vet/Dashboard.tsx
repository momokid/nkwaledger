import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import useAuthGuard from "@/hooks/useAuthGuard";
import { Head, Link } from "@inertiajs/react";
import GreetingHeader from "@/Components/GreetingHeader";

interface ReportRow {
    uuid: string;
    farm_unit_name: string | null;
    farmer_name: string;
    category: string;
    status: string;
    created_at: string;
}

interface Props {
    reports: ReportRow[];
}

function statusColor(status: string): string {
    switch (status) {
        case "resolved":
            return "#1D9E75";
        case "reviewed":
            return "#BA7517";
        default:
            return "#B91C1C";
    }
}

function statusLabel(status: string): string {
    switch (status) {
        case "resolved":
            return "Resolved";
        case "reviewed":
            return "Reviewed";
        default:
            return "New";
    }
}

export default function Dashboard({ reports }: Props) {
    useAuthGuard();

    return (
        <AuthenticatedLayout title="Dashboard">
            <Head title="Dashboard" />
            <DashboardContent reports={reports} />
        </AuthenticatedLayout>
    );
}

function DashboardContent({ reports }: Props) {
    const { dark } = useTheme();

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";

    return (
        <div className="p-6">
            <GreetingHeader subtitle="Here are the reports sent to you." />

            {reports.length === 0 && (
                <div
                    className="mt-4"
                    style={{ color: textSecondary, fontSize: "1.0625rem" }}
                >
                    No reports have been sent your way yet.
                </div>
            )}

            <div className="mt-4">
                {reports.map((report) => (
                    <Link
                        key={report.uuid}
                        href={`/vet/reports/${report.uuid}`}
                        className="block mb-3 p-4"
                        style={{
                            background: surface,
                            border: `1px solid ${border}`,
                        }}
                    >
                        <div className="flex justify-between items-baseline">
                            <span
                                style={{
                                    fontWeight: 700,
                                    color: text,
                                    fontSize: "1.125rem",
                                }}
                            >
                                {report.farm_unit_name} — {report.farmer_name}
                            </span>
                            <span
                                style={{
                                    color: statusColor(report.status),
                                    fontWeight: 600,
                                    fontSize: "1rem",
                                }}
                            >
                                {statusLabel(report.status)}
                            </span>
                        </div>
                        <div
                            style={{
                                color: textSecondary,
                                marginTop: "4px",
                                fontSize: "1rem",
                            }}
                        >
                            {report.category} · {report.created_at}
                        </div>
                    </Link>
                ))}
            </div>
        </div>
    );
}
