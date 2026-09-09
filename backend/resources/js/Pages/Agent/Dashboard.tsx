import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import useAuthGuard from "@/hooks/useAuthGuard";
import { Head } from "@inertiajs/react";
import { useEffect, useState } from "react";
import { cedis as formatMoney } from "@/lib/format";
import GreetingHeader from "@/Components/GreetingHeader";
import {
    IconArrowDownRight,
    IconArrowUpRight,
    IconMinus,
    IconAlertTriangle,
    IconClock,
    IconUsers,
    IconShieldExclamation,
    IconFileAlert,
} from "@tabler/icons-react";

// ---- types (this shape is what a real API response should match later) ----

interface Trend {
    direction: "up" | "down" | "flat";
    percent: number | null;
    good: boolean;
}

interface Kpi {
    label: string;
    value: string;
    trend: Trend;
}

interface AttentionItem {
    farmer: string;
    reason: string;
    kind: "dormant" | "flagged" | "identity" | "profile";
}

interface ActivityEntry {
    farmer: string;
    action: string;
    detail: string;
    amount: string;
    time: string;
    income: boolean;
}

type FarmerStatus = "Active" | "Flagged" | "Dormant";

interface RosterRow {
    name: string;
    community: string;
    lastActivity: string;
    income: string;
    expense: string;
    status: FarmerStatus;
}

interface DashboardData {
    kpis: Kpi[];
    weeks: string[];
    incomeSeries: number[];
    expenseSeries: number[];
    attentionItems: AttentionItem[];
    activityFeed: ActivityEntry[];
    roster: RosterRow[];
}

// ---- what the backend actually sends for the roster ----

interface BackendRosterRow {
    id: string;
    name: string;
    community: string | null;
    last_activity: string | null;
    income: number;
    expense: number;
    status: "active" | "dormant";
}

interface Props {
    roster: BackendRosterRow[];
}

// ---- fake data generator — the rest of this page still runs on this until it's wired ----

const PRODUCE_DETAILS = [
    "Maize sale",
    "Egg sales",
    "Manure sale",
    "Cassava sale",
    "Poultry sale",
];
const EXPENSE_DETAILS = [
    "Feed purchase",
    "Labour cost",
    "Fertilizer",
    "Transport",
    "Vet medicine",
];

function randomInt(min: number, max: number): number {
    return Math.floor(Math.random() * (max - min + 1)) + min;
}

function pick<T>(items: T[]): T {
    return items[randomInt(0, items.length - 1)];
}

function cedis(amount: number): string {
    return `GHS ${amount.toLocaleString("en-GH")}`;
}

function trendFrom(
    current: number,
    previous: number,
    higherIsGood: boolean,
): Trend {
    const direction =
        current === previous ? "flat" : current > previous ? "up" : "down";
    const percent =
        previous === 0
            ? null
            : Math.round(
                  (Math.abs(current - previous) / Math.abs(previous)) * 100,
              );
    const good = higherIsGood ? current >= previous : current <= previous;
    return { direction, percent, good };
}

function generateSeries(
    base: number,
    weeks: number,
    driftMin: number,
    driftMax: number,
): number[] {
    const series: number[] = [];
    let value = base;
    for (let i = 0; i < weeks; i++) {
        value = Math.max(400, value + randomInt(driftMin, driftMax));
        series.push(value);
    }
    return series;
}

// ---- real roster formatting ----

function formatLastActivity(date: string | null): string {
    if (!date) return "No activity yet";

    const days = Math.floor(
        (Date.now() - new Date(date).getTime()) / (1000 * 60 * 60 * 24),
    );

    if (days <= 0) return "Today";
    if (days === 1) return "Yesterday";
    return `${days} days ago`;
}

function formatRoster(rows: BackendRosterRow[]): RosterRow[] {
    return rows.map((row) => ({
        name: row.name,
        community: row.community ?? "—",
        lastActivity: formatLastActivity(row.last_activity),
        income: `GHS ${formatMoney(row.income)}`,
        expense: `GHS ${formatMoney(row.expense)}`,
        status: row.status === "active" ? "Active" : "Dormant",
    }));
}

function generateAttentionItems(roster: RosterRow[]): AttentionItem[] {
    const items: AttentionItem[] = [];

    roster.forEach((farmer) => {
        if (farmer.status === "Dormant") {
            items.push({
                farmer: farmer.name,
                reason:
                    farmer.lastActivity === "No activity yet"
                        ? "No activity logged yet"
                        : `No activity logged in ${farmer.lastActivity.replace(" ago", "")}`,
                kind: "dormant",
            });
        }
        if (farmer.status === "Flagged") {
            items.push({
                farmer: farmer.name,
                reason: "Unusual transaction pattern flagged for review",
                kind: "flagged",
            });
        }
    });

    const active = roster.filter((f) => f.status === "Active");
    if (active.length > 0 && Math.random() > 0.4) {
        items.push({
            farmer: pick(active).name,
            reason: "Identity document not verified",
            kind: "identity",
        });
    }
    if (active.length > 0 && Math.random() > 0.5) {
        items.push({
            farmer: pick(active).name,
            reason: "Farm profile has no farm unit yet",
            kind: "profile",
        });
    }

    return items.slice(0, 5);
}

function generateActivityFeed(roster: RosterRow[]): ActivityEntry[] {
    const eligible = roster.filter((f) => f.status !== "Dormant");
    if (eligible.length === 0) return [];

    return Array.from({ length: 5 }, () => {
        const farmer = pick(eligible);
        const income = Math.random() > 0.45;
        return {
            farmer: farmer.name,
            action: income ? "Logged income" : "Logged expense",
            detail: income ? pick(PRODUCE_DETAILS) : pick(EXPENSE_DETAILS),
            amount: `${income ? "+" : "-"}${cedis(randomInt(100, 900))}`,
            time: pick([
                "1h ago",
                "3h ago",
                "5h ago",
                "Yesterday",
                "2 days ago",
            ]),
            income,
        };
    });
}

function generateMockData(roster: RosterRow[]): DashboardData {
    const weeks = [
        "Wk 1",
        "Wk 2",
        "Wk 3",
        "Wk 4",
        "Wk 5",
        "Wk 6",
        "Wk 7",
        "Wk 8",
    ];
    const incomeSeries = generateSeries(3000, 8, -300, 700);
    const expenseSeries = generateSeries(1500, 8, -200, 400);

    const currentIncome = incomeSeries.slice(4).reduce((a, b) => a + b, 0);
    const previousIncome = incomeSeries.slice(0, 4).reduce((a, b) => a + b, 0);
    const currentExpense = expenseSeries.slice(4).reduce((a, b) => a + b, 0);
    const previousExpense = expenseSeries
        .slice(0, 4)
        .reduce((a, b) => a + b, 0);

    const activeCount = roster.filter((f) => f.status !== "Dormant").length;

    const kpis: Kpi[] = [
        {
            label: "Income (30 days)",
            value: cedis(currentIncome),
            trend: trendFrom(currentIncome, previousIncome, true),
        },
        {
            label: "Expenses (30 days)",
            value: cedis(currentExpense),
            trend: trendFrom(currentExpense, previousExpense, false),
        },
        {
            label: "Net profit",
            value: cedis(currentIncome - currentExpense),
            trend: trendFrom(
                currentIncome - currentExpense,
                previousIncome - previousExpense,
                true,
            ),
        },
        {
            label: "Active farmers",
            value: String(activeCount),
            trend: { direction: "flat", percent: null, good: true },
        },
    ];

    return {
        kpis,
        weeks,
        incomeSeries,
        expenseSeries,
        attentionItems: generateAttentionItems(roster),
        activityFeed: generateActivityFeed(roster),
        roster,
    };
}

export default function Dashboard(props: Props) {
    useAuthGuard();

    return (
        <AuthenticatedLayout title="Dashboard">
            <Head title="Dashboard" />
            <DashboardContent roster={props.roster} />
        </AuthenticatedLayout>
    );
}

function DashboardContent({ roster }: Props) {
    const { dark } = useTheme();
    const [data, setData] = useState<DashboardData | null>(null);

    useEffect(() => {
        // the roster is already real; the rest of this page still simulates a load
        const formattedRoster = formatRoster(roster);
        const timer = setTimeout(
            () => setData(generateMockData(formattedRoster)),
            450,
        );
        return () => clearTimeout(timer);
    }, [roster]);

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const track = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const primary = "#1D9E75";
    const amber = "#BA7517";
    const danger = "#DC2626";

    const trendMeta = (trend: Trend) => {
        const TrendIcon =
            trend.direction === "flat"
                ? IconMinus
                : trend.direction === "up"
                  ? IconArrowUpRight
                  : IconArrowDownRight;
        const color =
            trend.direction === "flat"
                ? textSecondary
                : trend.good
                  ? primary
                  : danger;
        const label =
            trend.percent === null
                ? "No prior period to compare"
                : `${trend.percent}% vs previous period`;
        return { TrendIcon, color, label };
    };

    const statusColor = (status: FarmerStatus) => {
        if (status === "Active") return primary;
        if (status === "Flagged") return amber;
        return textSecondary;
    };

    const attentionIcon = (kind: AttentionItem["kind"]) => {
        if (kind === "dormant") return IconClock;
        if (kind === "identity") return IconFileAlert;
        return IconAlertTriangle;
    };

    const Skeleton = ({
        height = "16px",
        width = "100%",
    }: {
        height?: string;
        width?: string;
    }) => (
        <div
            className="animate-pulse"
            style={{ height, width, background: track }}
        />
    );

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
                below are randomly generated, not your real data.
            </div>

            <GreetingHeader subtitle="Here is how your farmers are doing." />

            {/* KPI strip */}
            <div
                style={{
                    display: "grid",
                    gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))",
                    gap: "16px",
                    marginBottom: "24px",
                }}
            >
                {(data?.kpis ?? Array.from({ length: 4 })).map((kpi, i) => (
                    <div
                        key={kpi ? (kpi as Kpi).label : i}
                        style={{
                            background: surface,
                            border: `1px solid ${border}`,
                            padding: "18px",
                        }}
                    >
                        {!kpi ? (
                            <>
                                <Skeleton height="14px" width="60%" />
                                <div style={{ marginTop: "10px" }}>
                                    <Skeleton height="26px" width="80%" />
                                </div>
                                <div style={{ marginTop: "10px" }}>
                                    <Skeleton height="12px" width="50%" />
                                </div>
                            </>
                        ) : (
                            (() => {
                                const { TrendIcon, color, label } = trendMeta(
                                    (kpi as Kpi).trend,
                                );
                                return (
                                    <>
                                        <p
                                            style={{
                                                fontSize: "16px",
                                                color: textSecondary,
                                                marginBottom: "8px",
                                            }}
                                        >
                                            {(kpi as Kpi).label}
                                        </p>
                                        <p
                                            style={{
                                                fontSize: "26px",
                                                fontWeight: 700,
                                                color: text,
                                                letterSpacing: "-0.5px",
                                                marginBottom: "6px",
                                            }}
                                        >
                                            {(kpi as Kpi).value}
                                        </p>
                                        <div
                                            style={{
                                                display: "flex",
                                                alignItems: "center",
                                                gap: "4px",
                                                fontSize: "13px",
                                                color,
                                            }}
                                        >
                                            <TrendIcon size={16} />
                                            <span>{label}</span>
                                        </div>
                                    </>
                                );
                            })()
                        )}
                    </div>
                ))}
            </div>

            {/* Trend + attention */}
            <div
                style={{
                    display: "grid",
                    gridTemplateColumns: "2fr 1fr",
                    gap: "16px",
                    marginBottom: "16px",
                }}
            >
                <div
                    style={{
                        background: surface,
                        border: `1px solid ${border}`,
                        padding: "20px",
                    }}
                >
                    <p
                        style={{
                            fontSize: "18px",
                            fontWeight: 600,
                            color: text,
                            marginBottom: "16px",
                        }}
                    >
                        Income vs expenses across your farmers
                    </p>
                    {!data ? (
                        <Skeleton height="200px" />
                    ) : (
                        <TrendChart
                            weeks={data.weeks}
                            income={data.incomeSeries}
                            expense={data.expenseSeries}
                            primary={primary}
                            danger={danger}
                            textSecondary={textSecondary}
                            border={border}
                        />
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
                            fontSize: "18px",
                            fontWeight: 600,
                            color: text,
                            marginBottom: "16px",
                        }}
                    >
                        Needs your attention
                    </p>
                    <div
                        style={{
                            display: "flex",
                            flexDirection: "column",
                            gap: "14px",
                        }}
                    >
                        {!data ? (
                            Array.from({ length: 4 }).map((_, i) => (
                                <Skeleton key={i} height="34px" />
                            ))
                        ) : data.attentionItems.length === 0 ? (
                            <p
                                style={{
                                    fontSize: "14px",
                                    color: textSecondary,
                                }}
                            >
                                Nothing needs your attention right now.
                            </p>
                        ) : (
                            data.attentionItems.map((item, i) => {
                                const Icon = attentionIcon(item.kind);
                                return (
                                    <div
                                        key={i}
                                        style={{
                                            display: "flex",
                                            gap: "10px",
                                            alignItems: "flex-start",
                                        }}
                                    >
                                        <Icon
                                            size={18}
                                            style={{
                                                color: amber,
                                                marginTop: "2px",
                                                flexShrink: 0,
                                            }}
                                        />
                                        <div>
                                            <p
                                                style={{
                                                    fontSize: "15px",
                                                    color: text,
                                                    fontWeight: 600,
                                                }}
                                            >
                                                {item.farmer}
                                            </p>
                                            <p
                                                style={{
                                                    fontSize: "13px",
                                                    color: textSecondary,
                                                }}
                                            >
                                                {item.reason}
                                            </p>
                                        </div>
                                    </div>
                                );
                            })
                        )}
                    </div>
                </div>
            </div>

            {/* Activity feed + risk teaser */}
            <div
                style={{
                    display: "grid",
                    gridTemplateColumns: "2fr 1fr",
                    gap: "16px",
                    marginBottom: "16px",
                }}
            >
                <div
                    style={{
                        background: surface,
                        border: `1px solid ${border}`,
                        padding: "20px",
                    }}
                >
                    <p
                        style={{
                            fontSize: "18px",
                            fontWeight: 600,
                            color: text,
                            marginBottom: "16px",
                        }}
                    >
                        Recent activity
                    </p>
                    <div
                        style={{
                            display: "flex",
                            flexDirection: "column",
                            gap: "12px",
                        }}
                    >
                        {!data
                            ? Array.from({ length: 5 }).map((_, i) => (
                                  <Skeleton key={i} height="20px" />
                              ))
                            : data.activityFeed.map((entry, i) => (
                                  <div
                                      key={i}
                                      style={{
                                          display: "flex",
                                          justifyContent: "space-between",
                                          alignItems: "center",
                                          paddingBottom: "12px",
                                          borderBottom:
                                              i === data.activityFeed.length - 1
                                                  ? "none"
                                                  : `1px solid ${border}`,
                                      }}
                                  >
                                      <div>
                                          <p
                                              style={{
                                                  fontSize: "15px",
                                                  color: text,
                                              }}
                                          >
                                              <span style={{ fontWeight: 600 }}>
                                                  {entry.farmer}
                                              </span>{" "}
                                              — {entry.action.toLowerCase()}
                                          </p>
                                          <p
                                              style={{
                                                  fontSize: "13px",
                                                  color: textSecondary,
                                              }}
                                          >
                                              {entry.detail} · {entry.time}
                                          </p>
                                      </div>
                                      <p
                                          style={{
                                              fontSize: "15px",
                                              fontWeight: 700,
                                              color: entry.income
                                                  ? primary
                                                  : danger,
                                          }}
                                      >
                                          {entry.amount}
                                      </p>
                                  </div>
                              ))}
                    </div>
                </div>

                <div
                    style={{
                        background: surface,
                        border: `1px solid ${amber}`,
                        padding: "20px",
                    }}
                >
                    <div
                        style={{
                            display: "flex",
                            alignItems: "center",
                            gap: "8px",
                            marginBottom: "10px",
                        }}
                    >
                        <IconShieldExclamation
                            size={20}
                            style={{ color: amber }}
                        />
                        <p
                            style={{
                                fontSize: "18px",
                                fontWeight: 600,
                                color: text,
                            }}
                        >
                            Risk flags
                        </p>
                    </div>
                    {!data ? (
                        <Skeleton height="60px" />
                    ) : (
                        <>
                            <p
                                style={{
                                    fontSize: "28px",
                                    fontWeight: 700,
                                    color: text,
                                    marginBottom: "6px",
                                }}
                            >
                                {
                                    data.roster.filter(
                                        (f) => f.status === "Flagged",
                                    ).length
                                }
                            </p>
                            <p
                                style={{
                                    fontSize: "14px",
                                    color: textSecondary,
                                    marginBottom: "10px",
                                }}
                            >
                                Farmers with unusual amounts or pricing this
                                week.
                            </p>
                            <p
                                style={{
                                    fontSize: "13px",
                                    color: textSecondary,
                                }}
                            >
                                Automatic review is coming soon.
                            </p>
                        </>
                    )}
                </div>
            </div>

            {/* Farmer roster */}
            <div
                style={{
                    background: surface,
                    border: `1px solid ${border}`,
                    padding: "20px",
                }}
            >
                <div
                    style={{
                        display: "flex",
                        alignItems: "center",
                        gap: "8px",
                        marginBottom: "16px",
                    }}
                >
                    <IconUsers size={20} style={{ color: textSecondary }} />
                    <p
                        style={{
                            fontSize: "18px",
                            fontWeight: 600,
                            color: text,
                        }}
                    >
                        Your farmers
                    </p>
                </div>
                <div style={{ overflowX: "auto" }}>
                    <table
                        style={{
                            width: "100%",
                            borderCollapse: "collapse",
                            fontSize: "14px",
                        }}
                    >
                        <thead>
                            <tr
                                style={{
                                    borderBottom: `1px solid ${border}`,
                                    textAlign: "left",
                                }}
                            >
                                <th
                                    style={{
                                        padding: "8px 4px",
                                        color: textSecondary,
                                        fontWeight: 500,
                                    }}
                                >
                                    Farmer
                                </th>
                                <th
                                    style={{
                                        padding: "8px 4px",
                                        color: textSecondary,
                                        fontWeight: 500,
                                    }}
                                >
                                    Community
                                </th>
                                <th
                                    style={{
                                        padding: "8px 4px",
                                        color: textSecondary,
                                        fontWeight: 500,
                                    }}
                                >
                                    Last activity
                                </th>
                                <th
                                    style={{
                                        padding: "8px 4px",
                                        color: textSecondary,
                                        fontWeight: 500,
                                    }}
                                >
                                    Income (30d)
                                </th>
                                <th
                                    style={{
                                        padding: "8px 4px",
                                        color: textSecondary,
                                        fontWeight: 500,
                                    }}
                                >
                                    Expense (30d)
                                </th>
                                <th
                                    style={{
                                        padding: "8px 4px",
                                        color: textSecondary,
                                        fontWeight: 500,
                                    }}
                                >
                                    Status
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {!data ? (
                                Array.from({ length: 5 }).map((_, i) => (
                                    <tr
                                        key={i}
                                        style={{
                                            borderBottom: `1px solid ${border}`,
                                        }}
                                    >
                                        <td
                                            colSpan={6}
                                            style={{ padding: "10px 4px" }}
                                        >
                                            <Skeleton height="16px" />
                                        </td>
                                    </tr>
                                ))
                            ) : data.roster.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={6}
                                        style={{
                                            padding: "16px 4px",
                                            color: textSecondary,
                                            textAlign: "center",
                                        }}
                                    >
                                        No farmers assigned to you yet.
                                    </td>
                                </tr>
                            ) : (
                                data.roster.map((farmer) => (
                                    <tr
                                        key={farmer.name}
                                        style={{
                                            borderBottom: `1px solid ${border}`,
                                        }}
                                    >
                                        <td
                                            style={{
                                                padding: "10px 4px",
                                                color: text,
                                                fontWeight: 600,
                                            }}
                                        >
                                            {farmer.name}
                                        </td>
                                        <td
                                            style={{
                                                padding: "10px 4px",
                                                color: textSecondary,
                                            }}
                                        >
                                            {farmer.community}
                                        </td>
                                        <td
                                            style={{
                                                padding: "10px 4px",
                                                color: textSecondary,
                                            }}
                                        >
                                            {farmer.lastActivity}
                                        </td>
                                        <td
                                            style={{
                                                padding: "10px 4px",
                                                color: text,
                                            }}
                                        >
                                            {farmer.income}
                                        </td>
                                        <td
                                            style={{
                                                padding: "10px 4px",
                                                color: text,
                                            }}
                                        >
                                            {farmer.expense}
                                        </td>
                                        <td style={{ padding: "10px 4px" }}>
                                            <span
                                                style={{
                                                    color: statusColor(
                                                        farmer.status,
                                                    ),
                                                    fontWeight: 600,
                                                }}
                                            >
                                                {farmer.status}
                                            </span>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
}

function TrendChart({
    weeks,
    income,
    expense,
    primary,
    danger,
    textSecondary,
    border,
}: {
    weeks: string[];
    income: number[];
    expense: number[];
    primary: string;
    danger: string;
    textSecondary: string;
    border: string;
}) {
    const width = 560;
    const height = 200;
    const paddingLeft = 40;
    const paddingBottom = 24;
    const max = Math.max(...income, ...expense);
    const chartHeight = height - paddingBottom - 10;

    const groupWidth = (width - paddingLeft - 10) / weeks.length;
    const barWidth = groupWidth * 0.3;
    const gap = groupWidth * 0.08;

    const barHeight = (value: number) => (value / max) * chartHeight;

    return (
        <div>
            <div style={{ display: "flex", gap: "16px", marginBottom: "12px" }}>
                <LegendDot
                    color={primary}
                    label="Income"
                    textColor={textSecondary}
                />
                <LegendDot
                    color={danger}
                    label="Expense"
                    textColor={textSecondary}
                />
            </div>
            <svg
                viewBox={`0 0 ${width} ${height}`}
                style={{ width: "100%", height: "auto" }}
            >
                {[0, 0.5, 1].map((fraction) => (
                    <line
                        key={fraction}
                        x1={paddingLeft}
                        x2={width - 10}
                        y1={height - paddingBottom - fraction * chartHeight}
                        y2={height - paddingBottom - fraction * chartHeight}
                        stroke={border}
                        strokeWidth={1}
                    />
                ))}
                {weeks.map((label, i) => {
                    const groupX = paddingLeft + i * groupWidth;
                    const incomeHeight = barHeight(income[i]);
                    const expenseHeight = barHeight(expense[i]);

                    return (
                        <g key={label}>
                            <rect
                                x={groupX + gap}
                                y={height - paddingBottom - incomeHeight}
                                width={barWidth}
                                height={incomeHeight}
                                fill={primary}
                            />
                            <rect
                                x={groupX + gap * 2 + barWidth}
                                y={height - paddingBottom - expenseHeight}
                                width={barWidth}
                                height={expenseHeight}
                                fill={danger}
                            />
                            <text
                                x={groupX + groupWidth / 2}
                                y={height - 6}
                                fontSize={11}
                                fill={textSecondary}
                                textAnchor="middle"
                            >
                                {label}
                            </text>
                        </g>
                    );
                })}
            </svg>
        </div>
    );
}

function LegendDot({
    color,
    label,
    textColor,
}: {
    color: string;
    label: string;
    textColor: string;
}) {
    return (
        <div
            style={{
                display: "flex",
                alignItems: "center",
                gap: "6px",
                fontSize: "13px",
                color: textColor,
            }}
        >
            <div style={{ width: "10px", height: "10px", background: color }} />
            {label}
        </div>
    );
}
