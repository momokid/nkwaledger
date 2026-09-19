import AdminLayout from "@/Layouts/AdminLayout";
import { useTheme } from "@/Layouts/AuthenticatedLayout";
import GreetingHeader from "@/Components/GreetingHeader";
import Button from "@/Components/Button";
import Drawer from "@/Components/Drawer";
import { cedis as formatMoney } from "@/lib/format";
import { router } from "@inertiajs/react";
import { useState } from "react";

interface AgentDetail {
    summary: {
        total_income: number;
        total_expense: number;
        net: number;
        cash_collected: number;
        cash_paid_out: number;
    };
    farmer_count: number;
    roster: Array<{
        id: string;
        name: string;
        community: string | null;
        income: number;
        expense: number;
        status: string;
    }>;
}

interface RegionDetail {
    region_name: string;
    farmers: Array<{
        id: string;
        name: string;
        community: string | null;
        income: number;
        expense: number;
    }>;
}

interface HealthDetail {
    region_name: string;
    reports: Array<{
        uuid: string;
        farmer_name: string;
        category: string;
        status: string;
        description: string;
        created_at: string;
    }>;
}

type Sort = "net" | "activity";

interface Snapshot {
    active_farmers: number;
    active_agents: number;
    total_farmers: number;
    total_agents: number;
    total_income: number;
    total_expense: number;
    net: number;
}

interface LeaderboardRow {
    agent_id: number;
    name: string;
    farmer_count: number;
    new_farmers: number;
    records_logged: number;
    income: number;
    expense: number;
    net: number;
    rank: number;
}

interface RegionalRow {
    region_id: number;
    region_name: string;
    farmer_count: number;
    farm_unit_count: number;
    income: number;
    expense: number;
    net: number;
}

interface HealthRow {
    region_id: number;
    region_name: string;
    total: number;
    by_category: Record<string, number>;
    by_status: Record<string, number>;
}

interface Filters {
    from: string;
    to: string;
}

interface Props {
    snapshot: Snapshot;
    leaderboard: LeaderboardRow[];
    regionalTrends: RegionalRow[];
    healthTrends: HealthRow[];
    sort: Sort;
    filters: Filters;
}

export default function Dashboard(props: Props) {
    return (
        <AdminLayout title="Dashboard">
            <DashboardContent {...props} />
        </AdminLayout>
    );
}

function DashboardContent({
    snapshot,
    leaderboard,
    regionalTrends,
    healthTrends,
    sort,
    filters,
}: Props) {
    const { dark } = useTheme();
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);
    const [loading, setLoading] = useState(false);
    const [drawerAgent, setDrawerAgent] = useState<LeaderboardRow | null>(null);
    const [agentDetail, setAgentDetail] = useState<AgentDetail | null>(null);
    const [drawerRegion, setDrawerRegion] = useState<RegionalRow | null>(null);
    const [regionDetail, setRegionDetail] = useState<RegionDetail | null>(null);
    const [drawerHealth, setDrawerHealth] = useState<HealthRow | null>(null);
    const [healthDetail, setHealthDetail] = useState<HealthDetail | null>(null);
    const [detailLoading, setDetailLoading] = useState(false);

    const openAgentDetail = (row: LeaderboardRow) => {
        setDrawerAgent(row);
        setDetailLoading(true);
        fetch(
            `/admin/agents/${row.agent_id}/detail?from=${filters.from}&to=${filters.to}`,
        )
            .then((response) => response.json())
            .then((data: AgentDetail) => setAgentDetail(data))
            .finally(() => setDetailLoading(false));
    };

    const openRegionDetail = (row: RegionalRow) => {
        setDrawerRegion(row);
        setDetailLoading(true);
        fetch(
            `/admin/regions/${row.region_id}/detail?from=${filters.from}&to=${filters.to}`,
        )
            .then((response) => response.json())
            .then((data: RegionDetail) => setRegionDetail(data))
            .finally(() => setDetailLoading(false));
    };

    const openHealthDetail = (row: HealthRow) => {
        setDrawerHealth(row);
        setDetailLoading(true);
        fetch(`/admin/regions/${row.region_id}/health-detail`)
            .then((response) => response.json())
            .then((data: HealthDetail) => setHealthDetail(data))
            .finally(() => setDetailLoading(false));
    };

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const inputBorder = dark ? "#4B5563" : "#9CA3AF";
    const inputBg = dark ? "#111827" : "#FFFFFF";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const brand = "#1D9E75";

    const field = {
        padding: "8px 10px",
        border: `1px solid ${inputBorder}`,
        background: inputBg,
        color: text,
        fontSize: "1.0625rem",
    } as const;

    const visit = (params: Record<string, string>) => {
        setLoading(true);

        router.get(
            route("admin.dashboard"),
            { from, to, sort, ...params },
            {
                preserveState: true,
                preserveScroll: true,
                onFinish: () => setLoading(false),
            },
        );
    };

    const setSort = (value: Sort) => visit({ sort: value });

    const snapshotCards = [
        {
            label: "Active (30 days)",
            value: `${snapshot.active_farmers.toLocaleString()} farmers · ${snapshot.active_agents.toLocaleString()} agents`,
            secondary: `of ${snapshot.total_farmers.toLocaleString()} farmers / ${snapshot.total_agents.toLocaleString()} agents total`,
        },
        {
            label: "Income",
            value: `GHS ${formatMoney(snapshot.total_income)}`,
        },
        {
            label: "Expense",
            value: `GHS ${formatMoney(snapshot.total_expense)}`,
        },
        {
            label: "Net",
            value: `GHS ${formatMoney(snapshot.net)}`,
        },
    ];

    return (
        <div className="p-6 space-y-6">
            <GreetingHeader subtitle="" />

            <div
                className="flex flex-wrap items-end gap-3"
                style={{
                    background: surface,
                    border: `1px solid ${border}`,
                    padding: "16px",
                }}
            >
                <div>
                    <label
                        style={{
                            display: "block",
                            fontSize: "1rem",
                            color: textSecondary,
                            marginBottom: "4px",
                        }}
                    >
                        From
                    </label>
                    <input
                        type="date"
                        style={field}
                        value={from}
                        onChange={(event) => setFrom(event.target.value)}
                    />
                </div>
                <div>
                    <label
                        style={{
                            display: "block",
                            fontSize: "1rem",
                            color: textSecondary,
                            marginBottom: "4px",
                        }}
                    >
                        To
                    </label>
                    <input
                        type="date"
                        style={field}
                        value={to}
                        onChange={(event) => setTo(event.target.value)}
                    />
                </div>

                <Button
                    onClick={() => visit({})}
                    busy={loading}
                    busyLabel="Loading..."
                >
                    Show
                </Button>
            </div>

            <div
                style={{
                    display: "grid",
                    gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))",
                    gap: "16px",
                }}
            >
                {snapshotCards.map((card) => (
                    <div
                        key={card.label}
                        style={{
                            background: surface,
                            border: `1px solid ${border}`,
                            padding: "18px",
                        }}
                    >
                        <p
                            style={{
                                fontSize: "1rem",
                                color: textSecondary,
                                marginBottom: "8px",
                            }}
                        >
                            {card.label}
                        </p>
                        <p
                            style={{
                                fontSize: "1.625rem",
                                fontWeight: 700,
                                color: text,
                                letterSpacing: "-0.5px",
                                marginBottom: card.secondary ? "2px" : 0,
                            }}
                        >
                            {card.value}
                        </p>
                        {card.secondary && (
                            <p
                                style={{
                                    fontSize: "0.8125rem",
                                    color: textSecondary,
                                }}
                            >
                                {card.secondary}
                            </p>
                        )}
                    </div>
                ))}
            </div>

            <div
                style={{
                    background: surface,
                    border: `1px solid ${border}`,
                    padding: "20px",
                }}
            >
                <div className="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <p
                        style={{
                            fontSize: "1.25rem",
                            fontWeight: 700,
                            color: text,
                        }}
                    >
                        Agent leaderboard
                    </p>

                    <div style={{ display: "flex", gap: "8px" }}>
                        {(
                            [
                                ["net", "Net"],
                                ["activity", "Activity"],
                            ] as const
                        ).map(([value, label]) => (
                            <button
                                key={value}
                                onClick={() => setSort(value)}
                                style={{
                                    border: `1px solid ${sort === value ? brand : border}`,
                                    background:
                                        sort === value ? brand : surface,
                                    color: sort === value ? "#FFFFFF" : text,
                                    padding: "8px 16px",
                                    fontSize: "1rem",
                                    fontWeight: 600,
                                    cursor: "pointer",
                                }}
                            >
                                By {label}
                            </button>
                        ))}
                    </div>
                </div>

                {leaderboard.length === 0 ? (
                    <p style={{ fontSize: "1.0625rem", color: textSecondary }}>
                        No agents yet.
                    </p>
                ) : (
                    <div style={{ overflowX: "auto" }}>
                        <table
                            style={{
                                width: "100%",
                                borderCollapse: "collapse",
                                fontSize: "1rem",
                            }}
                        >
                            <thead>
                                <tr
                                    style={{
                                        borderBottom: `1px solid ${border}`,
                                        textAlign: "left",
                                    }}
                                >
                                    {[
                                        "Rank",
                                        "Agent",
                                        "Farmers",
                                        "New farmers",
                                        "Records logged",
                                        "Income",
                                        "Expense",
                                        "Net",
                                    ].map((label) => (
                                        <th
                                            key={label}
                                            style={{
                                                padding: "10px 8px",
                                                color: textSecondary,
                                            }}
                                        >
                                            {label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {leaderboard.map((row) => (
                                    <tr
                                        key={row.agent_id}
                                        onClick={() => openAgentDetail(row)}
                                        style={{
                                            borderBottom: `1px solid ${border}`,
                                            cursor: "pointer",
                                        }}
                                    >
                                        <td
                                            style={{
                                                padding: "12px 8px",
                                                color: text,
                                                fontWeight: 700,
                                            }}
                                        >
                                            {row.rank}
                                        </td>
                                        <td
                                            style={{
                                                padding: "12px 8px",
                                                color: text,
                                                fontWeight: 600,
                                            }}
                                        >
                                            {row.name}
                                        </td>
                                        <td
                                            style={{
                                                padding: "12px 8px",
                                                color: text,
                                            }}
                                        >
                                            {row.farmer_count}
                                        </td>
                                        <td
                                            style={{
                                                padding: "12px 8px",
                                                color: text,
                                            }}
                                        >
                                            {row.new_farmers}
                                        </td>
                                        <td
                                            style={{
                                                padding: "12px 8px",
                                                color: text,
                                            }}
                                        >
                                            {row.records_logged}
                                        </td>
                                        <td
                                            style={{
                                                padding: "12px 8px",
                                                color: text,
                                            }}
                                        >
                                            GHS {formatMoney(row.income)}
                                        </td>
                                        <td
                                            style={{
                                                padding: "12px 8px",
                                                color: text,
                                            }}
                                        >
                                            GHS {formatMoney(row.expense)}
                                        </td>
                                        <td
                                            style={{
                                                padding: "12px 8px",
                                                color: brand,
                                                fontWeight: 600,
                                            }}
                                        >
                                            GHS {formatMoney(row.net)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
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
                        fontSize: "1.25rem",
                        fontWeight: 700,
                        color: text,
                        marginBottom: "14px",
                    }}
                >
                    Regional trends
                </p>

                {regionalTrends.length === 0 ? (
                    <p style={{ fontSize: "1.0625rem", color: textSecondary }}>
                        No regional data yet.
                    </p>
                ) : (
                    <div style={{ overflowX: "auto" }}>
                        <table
                            style={{
                                width: "100%",
                                borderCollapse: "collapse",
                                fontSize: "1rem",
                            }}
                        >
                            <thead>
                                <tr
                                    style={{
                                        borderBottom: `1px solid ${border}`,
                                        textAlign: "left",
                                    }}
                                >
                                    {[
                                        "Region",
                                        "Farmers",
                                        "Farm units",
                                        "Income",
                                        "Expense",
                                        "Net",
                                    ].map((label) => (
                                        <th
                                            key={label}
                                            style={{
                                                padding: "10px 8px",
                                                color: textSecondary,
                                            }}
                                        >
                                            {label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {[...regionalTrends]
                                    .sort((a, b) => b.net - a.net)
                                    .map((row) => (
                                        <tr
                                            key={row.region_id}
                                            onClick={() =>
                                                openRegionDetail(row)
                                            }
                                            style={{
                                                borderBottom: `1px solid ${border}`,
                                                cursor: "pointer",
                                            }}
                                        >
                                            <td
                                                style={{
                                                    padding: "12px 8px",
                                                    color: text,
                                                    fontWeight: 600,
                                                }}
                                            >
                                                {row.region_name}
                                            </td>
                                            <td
                                                style={{
                                                    padding: "12px 8px",
                                                    color: text,
                                                }}
                                            >
                                                {row.farmer_count}
                                            </td>
                                            <td
                                                style={{
                                                    padding: "12px 8px",
                                                    color: text,
                                                }}
                                            >
                                                {row.farm_unit_count}
                                            </td>
                                            <td
                                                style={{
                                                    padding: "12px 8px",
                                                    color: text,
                                                }}
                                            >
                                                GHS {formatMoney(row.income)}
                                            </td>
                                            <td
                                                style={{
                                                    padding: "12px 8px",
                                                    color: text,
                                                }}
                                            >
                                                GHS {formatMoney(row.expense)}
                                            </td>
                                            <td
                                                style={{
                                                    padding: "12px 8px",
                                                    color: brand,
                                                    fontWeight: 600,
                                                }}
                                            >
                                                GHS {formatMoney(row.net)}
                                            </td>
                                        </tr>
                                    ))}
                            </tbody>
                        </table>
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
                        fontSize: "1.25rem",
                        fontWeight: 700,
                        color: text,
                        marginBottom: "14px",
                    }}
                >
                    Health trends
                </p>

                {healthTrends.length === 0 ? (
                    <p style={{ fontSize: "1.0625rem", color: textSecondary }}>
                        No disease reports yet.
                    </p>
                ) : (
                    <div style={{ overflowX: "auto" }}>
                        <table
                            style={{
                                width: "100%",
                                borderCollapse: "collapse",
                                fontSize: "1rem",
                            }}
                        >
                            <thead>
                                <tr
                                    style={{
                                        borderBottom: `1px solid ${border}`,
                                        textAlign: "left",
                                    }}
                                >
                                    {[
                                        "Region",
                                        "Total",
                                        "By category",
                                        "By status",
                                    ].map((label) => (
                                        <th
                                            key={label}
                                            style={{
                                                padding: "10px 8px",
                                                color: textSecondary,
                                            }}
                                        >
                                            {label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {[...healthTrends]
                                    .sort((a, b) => b.total - a.total)
                                    .map((row) => (
                                        <tr
                                            key={row.region_id}
                                            onClick={() =>
                                                openHealthDetail(row)
                                            }
                                            style={{
                                                borderBottom: `1px solid ${border}`,
                                                cursor: "pointer",
                                            }}
                                        >
                                            <td
                                                style={{
                                                    padding: "12px 8px",
                                                    color: text,
                                                    fontWeight: 600,
                                                }}
                                            >
                                                {row.region_name}
                                            </td>
                                            <td
                                                style={{
                                                    padding: "12px 8px",
                                                    color: text,
                                                    fontWeight: 700,
                                                }}
                                            >
                                                {row.total}
                                            </td>
                                            <td
                                                style={{
                                                    padding: "12px 8px",
                                                    color: textSecondary,
                                                }}
                                            >
                                                {Object.entries(row.by_category)
                                                    .map(
                                                        ([k, v]) =>
                                                            `${k}: ${v}`,
                                                    )
                                                    .join(" · ")}
                                            </td>
                                            <td
                                                style={{
                                                    padding: "12px 8px",
                                                    color: textSecondary,
                                                }}
                                            >
                                                {Object.entries(row.by_status)
                                                    .map(
                                                        ([k, v]) =>
                                                            `${k}: ${v}`,
                                                    )
                                                    .join(" · ")}
                                            </td>
                                        </tr>
                                    ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            <Drawer
                open={drawerAgent !== null}
                title={drawerAgent ? `${drawerAgent.name}'s farmers` : ""}
                onClose={() => {
                    setDrawerAgent(null);
                    setAgentDetail(null);
                }}
            >
                {detailLoading || !agentDetail ? (
                    <p style={{ color: text }}>Loading…</p>
                ) : (
                    <div>
                        <div
                            style={{
                                display: "flex",
                                gap: "20px",
                                marginBottom: "20px",
                            }}
                        >
                            <div>
                                <p
                                    style={{
                                        fontSize: "0.875rem",
                                        color: text,
                                    }}
                                >
                                    Income
                                </p>
                                <p
                                    style={{
                                        fontSize: "1.25rem",
                                        fontWeight: 700,
                                        color: text,
                                    }}
                                >
                                    GHS{" "}
                                    {formatMoney(
                                        agentDetail.summary.total_income,
                                    )}
                                </p>
                            </div>
                            <div>
                                <p
                                    style={{
                                        fontSize: "0.875rem",
                                        color: text,
                                    }}
                                >
                                    Expense
                                </p>
                                <p
                                    style={{
                                        fontSize: "1.25rem",
                                        fontWeight: 700,
                                        color: text,
                                    }}
                                >
                                    GHS{" "}
                                    {formatMoney(
                                        agentDetail.summary.total_expense,
                                    )}
                                </p>
                            </div>
                            <div>
                                <p
                                    style={{
                                        fontSize: "0.875rem",
                                        color: text,
                                    }}
                                >
                                    Net
                                </p>
                                <p
                                    style={{
                                        fontSize: "1.25rem",
                                        fontWeight: 700,
                                        color: text,
                                    }}
                                >
                                    GHS {formatMoney(agentDetail.summary.net)}
                                </p>
                            </div>
                        </div>

                        <p
                            style={{
                                fontSize: "1.0625rem",
                                fontWeight: 700,
                                color: text,
                                marginBottom: "10px",
                            }}
                        >
                            Farmers ({agentDetail.farmer_count} active)
                        </p>

                        {agentDetail.roster.length === 0 ? (
                            <p style={{ color: text }}>No farmers assigned.</p>
                        ) : (
                            <table
                                style={{
                                    width: "100%",
                                    borderCollapse: "collapse",
                                }}
                            >
                                <tbody>
                                    {agentDetail.roster.map((farmer) => (
                                        <tr
                                            key={farmer.id}
                                            style={{
                                                borderBottom: `1px solid ${border}`,
                                            }}
                                        >
                                            <td
                                                style={{
                                                    padding: "8px 0",
                                                    color: text,
                                                }}
                                            >
                                                {farmer.name}
                                                <div
                                                    style={{
                                                        fontSize: "0.8125rem",
                                                        color: text,
                                                    }}
                                                >
                                                    {farmer.community}
                                                </div>
                                            </td>
                                            <td
                                                style={{
                                                    padding: "8px 0",
                                                    color: text,
                                                    textAlign: "right",
                                                }}
                                            >
                                                GHS {formatMoney(farmer.income)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>
                )}
            </Drawer>

            <Drawer
                open={drawerRegion !== null}
                title={
                    drawerRegion ? `${drawerRegion.region_name} farmers` : ""
                }
                onClose={() => {
                    setDrawerRegion(null);
                    setRegionDetail(null);
                }}
            >
                {detailLoading || !regionDetail ? (
                    <p style={{ color: text }}>Loading…</p>
                ) : regionDetail.farmers.length === 0 ? (
                    <p style={{ color: text }}>No farmers in this region.</p>
                ) : (
                    <table
                        style={{ width: "100%", borderCollapse: "collapse" }}
                    >
                        <tbody>
                            {regionDetail.farmers.map((farmer) => (
                                <tr
                                    key={farmer.id}
                                    style={{
                                        borderBottom: `1px solid ${border}`,
                                    }}
                                >
                                    <td
                                        style={{
                                            padding: "8px 0",
                                            color: text,
                                        }}
                                    >
                                        {farmer.name}
                                        <div
                                            style={{
                                                fontSize: "0.8125rem",
                                                color: text,
                                            }}
                                        >
                                            {farmer.community}
                                        </div>
                                    </td>
                                    <td
                                        style={{
                                            padding: "8px 0",
                                            color: text,
                                            textAlign: "right",
                                        }}
                                    >
                                        GHS {formatMoney(farmer.income)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </Drawer>

            <Drawer
                open={drawerHealth !== null}
                title={
                    drawerHealth
                        ? `${drawerHealth.region_name} disease reports`
                        : ""
                }
                onClose={() => {
                    setDrawerHealth(null);
                    setHealthDetail(null);
                }}
            >
                {detailLoading || !healthDetail ? (
                    <p style={{ color: text }}>Loading…</p>
                ) : healthDetail.reports.length === 0 ? (
                    <p style={{ color: text }}>No reports in this region.</p>
                ) : (
                    <table
                        style={{ width: "100%", borderCollapse: "collapse" }}
                    >
                        <tbody>
                            {healthDetail.reports.map((report) => (
                                <tr
                                    key={report.uuid}
                                    style={{
                                        borderBottom: `1px solid ${border}`,
                                    }}
                                >
                                    <td
                                        style={{
                                            padding: "8px 0",
                                            color: text,
                                        }}
                                    >
                                        {report.farmer_name}
                                        <div
                                            style={{
                                                fontSize: "0.8125rem",
                                                color: text,
                                            }}
                                        >
                                            {report.category} · {report.status}{" "}
                                            · {report.created_at}
                                        </div>
                                        <div
                                            style={{
                                                fontSize: "0.875rem",
                                                color: text,
                                            }}
                                        >
                                            {report.description}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </Drawer>
        </div>
    );
}
