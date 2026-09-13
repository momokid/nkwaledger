import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import { Head } from "@inertiajs/react";

interface ReportRow {
    uuid: string;
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

export default function Index({ reports }: Props) {
    return (
        <AuthenticatedLayout title="My Reports">
            <Head title="My Reports" />
            <IndexContent reports={reports} />
        </AuthenticatedLayout>
    );
}

function IndexContent({ reports }: Props) {
    const { dark } = useTheme();

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";

    return (
        <div className="p-6" style={{ maxWidth: "560px" }}>
            <h2 style={{ fontSize: "1.375rem", fontWeight: 700, color: text }}>
                My Reports
            </h2>
            <p
                style={{
                    fontSize: "1.0625rem",
                    color: textSecondary,
                    marginTop: "4px",
                }}
            >
                Every problem you have reported, and where it stands.
            </p>

            {reports.length === 0 && (
                <div
                    className="mt-4"
                    style={{ color: textSecondary, fontSize: "1.0625rem" }}
                >
                    You have not reported anything yet.
                </div>
            )}

            <div className="mt-4">
                {reports.map((report) => (
                    <div
                        key={report.uuid}
                        className="mb-3 p-4"
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
                                {report.category}
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
                            Reported {report.created_at}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
