import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import useAuthGuard from "@/hooks/useAuthGuard";
import { Head, Link, router } from "@inertiajs/react";
import { useEffect, useState } from "react";
import { IconSearch, IconFileAnalytics } from "@tabler/icons-react";

interface FarmerResult {
    id: string;
    name: string;
    phone: string | null;
    community: string | null;
}

interface Props {
    query: string;
    farmers: FarmerResult[];
}

interface AggregateReport {
    label: string;
    description: string;
}

interface AggregateReportReady extends AggregateReport {
    href: string;
}

const AGGREGATE_REPORTS: (AggregateReport | AggregateReportReady)[] = [
    {
        label: "Farmer activity list",
        description:
            "A record of each assigned farmer's recent activity and standing.",
        href: "/agent/reports/activity",
    },
    {
        label: "Dormant farmers",
        description:
            "Farmers with no recorded activity, and the date each was last active.",
        href: "/agent/reports/dormant",
    },
    {
        label: "Income & expense summary",
        description: "Totals across all your farmers, with account breakdown.",
        href: "/agent/reports/income-summary",
    },
    {
        label: "Farmer ranking",
        description: "Your farmers ranked by income or net, over a period.",
    },
];

export default function Reports({ query, farmers }: Props) {
    useAuthGuard();

    return (
        <AuthenticatedLayout title="Reports">
            <Head title="Reports" />
            <ReportsContent query={query} farmers={farmers} />
        </AuthenticatedLayout>
    );
}

function ReportsContent({ query, farmers }: Props) {
    const { dark } = useTheme();
    const [search, setSearch] = useState(query);
    const [searching, setSearching] = useState(false);

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const inputBorder = dark ? "#4B5563" : "#9CA3AF";
    const inputBg = dark ? "#111827" : "#FFFFFF";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";

    useEffect(() => {
        if (search === query) return;

        setSearching(true);
        const timer = setTimeout(() => {
            router.get(
                route("agent.reports.menu"),
                { q: search },
                {
                    only: ["farmers", "query"],
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    onFinish: () => setSearching(false),
                },
            );
        }, 350);

        return () => clearTimeout(timer);
    }, [search]);

    return (
        <div className="p-6 space-y-6">
            <div>
                <p
                    style={{
                        fontSize: "24px",
                        fontWeight: 700,
                        color: text,
                        marginBottom: "4px",
                    }}
                >
                    Reports
                </p>
                <p style={{ fontSize: "17px", color: textSecondary }}>
                    Find a farmer's statement, or view a report across all your
                    farmers.
                </p>
            </div>

            <div
                style={{
                    background: surface,
                    border: `1px solid ${border}`,
                    padding: "20px",
                }}
            >
                <label
                    style={{
                        display: "block",
                        fontSize: "18px",
                        fontWeight: 600,
                        color: text,
                        marginBottom: "8px",
                    }}
                >
                    Find a farmer
                </label>
                <div style={{ position: "relative", maxWidth: "460px" }}>
                    <IconSearch
                        size={20}
                        style={{
                            position: "absolute",
                            left: "10px",
                            top: "50%",
                            transform: "translateY(-50%)",
                            color: textSecondary,
                        }}
                    />
                    <input
                        type="text"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Name, phone, or community"
                        style={{
                            width: "100%",
                            border: `1px solid ${inputBorder}`,
                            background: inputBg,
                            padding: "12px 12px 12px 40px",
                            fontSize: "17px",
                            color: text,
                            outline: "none",
                            fontFamily: "inherit",
                        }}
                    />
                </div>

                {search.trim() !== "" && (
                    <div style={{ marginTop: "16px" }}>
                        {searching ? (
                            <p
                                style={{
                                    fontSize: "16px",
                                    color: textSecondary,
                                }}
                            >
                                Searching…
                            </p>
                        ) : farmers.length === 0 ? (
                            <p
                                style={{
                                    fontSize: "16px",
                                    color: textSecondary,
                                }}
                            >
                                No farmers match that.
                            </p>
                        ) : (
                            <div
                                style={{
                                    display: "flex",
                                    flexDirection: "column",
                                    gap: "1px",
                                    border: `1px solid ${border}`,
                                }}
                            >
                                {farmers.map((farmer) => (
                                    <Link
                                        key={farmer.id}
                                        href={`/agent/farmers/${farmer.id}/reports`}
                                        style={{
                                            display: "flex",
                                            justifyContent: "space-between",
                                            alignItems: "center",
                                            padding: "14px",
                                            background: surface,
                                            textDecoration: "none",
                                        }}
                                    >
                                        <div>
                                            <p
                                                style={{
                                                    fontSize: "17px",
                                                    fontWeight: 600,
                                                    color: text,
                                                }}
                                            >
                                                {farmer.name}
                                            </p>
                                            <p
                                                style={{
                                                    fontSize: "15px",
                                                    color: textSecondary,
                                                }}
                                            >
                                                {[
                                                    farmer.phone,
                                                    farmer.community,
                                                ]
                                                    .filter(Boolean)
                                                    .join(" · ")}
                                            </p>
                                        </div>
                                        <IconFileAnalytics
                                            size={20}
                                            style={{ color: textSecondary }}
                                        />
                                    </Link>
                                ))}
                            </div>
                        )}
                    </div>
                )}
            </div>

            <div
                style={{
                    background: surface,
                    border: `1px solid ${border}`,
                    padding: "20px",
                }}
            >
                <p
                    style={{
                        fontSize: "20px",
                        fontWeight: 600,
                        color: text,
                        marginBottom: "14px",
                    }}
                >
                    Reports across all your farmers
                </p>
                <div
                    style={{
                        display: "grid",
                        gridTemplateColumns:
                            "repeat(auto-fit, minmax(220px, 1fr))",
                        gap: "12px",
                    }}
                >
                    {AGGREGATE_REPORTS.map((report) => {
                        const ready = "href" in report;
                        const card = (
                            <div
                                style={{
                                    border: `1px solid ${border}`,
                                    padding: "14px",
                                    opacity: ready ? 1 : 0.6,
                                }}
                            >
                                <p
                                    style={{
                                        fontSize: "17px",
                                        fontWeight: 600,
                                        color: text,
                                        marginBottom: "4px",
                                    }}
                                >
                                    {report.label}
                                </p>
                                <p
                                    style={{
                                        fontSize: "15px",
                                        color: textSecondary,
                                        marginBottom: "8px",
                                    }}
                                >
                                    {report.description}
                                </p>
                                <p
                                    style={{
                                        fontSize: "14px",
                                        color: textSecondary,
                                    }}
                                >
                                    {ready ? "" : "Coming soon"}
                                </p>
                            </div>
                        );

                        return ready ? (
                            <Link
                                key={report.label}
                                href={(report as AggregateReportReady).href}
                                style={{ textDecoration: "none" }}
                            >
                                {card}
                            </Link>
                        ) : (
                            <div key={report.label}>{card}</div>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}
