import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import useAuthGuard from "@/hooks/useAuthGuard";
import { Head, Link } from "@inertiajs/react";
import GreetingHeader from "@/Components/GreetingHeader";
import { slashDate } from "@/lib/format";
import { useState } from "react";

interface ReportRow {
    uuid: string;
    farm_unit_name: string | null;
    farmer_name: string;
    category: string;
    status: string;
    created_at: string;
    ai_ready: boolean;
}

interface Props {
    reports: ReportRow[];
    counts: { new: number; reviewed: number; resolved: number; recent: number };
    field: "crops" | "livestock";
}

type Filter = "new" | "reviewed" | "resolved" | "recent" | null;

const RECENT_DAYS = 90;

function isRecent(createdAt: string): boolean {
    const cutoff = new Date();
    cutoff.setDate(cutoff.getDate() - RECENT_DAYS);
    // YYYY-MM-DD strings compare in date order
    return createdAt >= cutoff.toISOString().slice(0, 10);
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

export default function Dashboard(props: Props) {
    useAuthGuard();

    return (
        <AuthenticatedLayout title="Dashboard">
            <Head title="Dashboard" />
            <DashboardContent {...props} />
        </AuthenticatedLayout>
    );
}

function DashboardContent({ reports, counts, field }: Props) {
    const { dark } = useTheme();
    const [filter, setFilter] = useState<Filter>(null);
    const recentLabel = field === "crops" ? "Crop reports, last 90 days" : "Livestock reports, last 90 days";
    const cards: { key: Exclude<Filter, null>; label: string; count: number }[] = [
        { key: "new", label: "New", count: counts.new },
        { key: "reviewed", label: "Reviewed", count: counts.reviewed },
        { key: "resolved", label: "Resolved", count: counts.resolved },
        { key: "recent", label: recentLabel, count: counts.recent },
    ];
    const shown = reports.filter((r) =>
        filter === null ? true : filter === "recent" ? isRecent(r.created_at) : r.status === filter,
    );

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";

    return (
        <div className="p-6">
            <GreetingHeader subtitle="Here are the reports sent to you." />

            <div className="mt-4 grid grid-cols-2 lg:grid-cols-4 gap-3">
                {cards.map((card) => (
                    <button
                        key={card.key}
                        type="button"
                        onClick={() => setFilter(card.key)}
                        className="text-left p-4"
                        style={{
                            background: surface,
                            border: `1px solid ${filter === card.key ? "#1D9E75" : border}`,
                        }}
                    >
                        <div style={{ color: textSecondary, fontSize: "1rem" }}>{card.label}</div>
                        <div style={{ color: text, fontWeight: 700, fontSize: "1.75rem" }}>{card.count}</div>
                    </button>
                ))}
            </div>

            {filter !== null && (
                <div className="mt-4" style={{ color: textSecondary, fontSize: "1rem" }}>
                    Showing: {cards.find((c) => c.key === filter)?.label}{" "}
                    <button type="button" onClick={() => setFilter(null)} style={{ color: "#1D9E75", fontWeight: 600 }}>
                        Show all
                    </button>
                </div>
            )}

            {reports.length === 0 && (
                <div
                    className="mt-4"
                    style={{ color: textSecondary, fontSize: "1.0625rem" }}
                >
                    No reports have been sent your way yet.
                </div>
            )}

            {reports.length > 0 && shown.length === 0 && (
                <div className="mt-4" style={{ color: textSecondary, fontSize: "1.0625rem" }}>
                    No reports in this group.
                </div>
            )}

            <div className="mt-4">
                {shown.map((report) => (
                    <Link
                        key={report.uuid}
                        href={`/adviser/reports/${report.uuid}`}
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
                            {report.category} · {slashDate(report.created_at)}
                            {report.ai_ready && (
                                <span style={{ color: "#1D9E75", fontWeight: 600 }}> · AI suggestion ready</span>
                            )}
                        </div>
                    </Link>
                ))}
            </div>
        </div>
    );
}
