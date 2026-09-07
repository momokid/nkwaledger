import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import useAuthGuard from "@/hooks/useAuthGuard";
import { Head, Link } from "@inertiajs/react";
import { cedis, shortDate } from "@/lib/format";
import { useState } from "react";
import GreetingHeader from "@/Components/GreetingHeader";
import {
    IconArrowDownRight,
    IconArrowUpRight,
    IconCloud,
    IconCloudRain,
    IconMinus,
    IconSun,
    IconWind,
    IconX,
} from "@tabler/icons-react";

interface Trend {
    direction: "up" | "down" | "flat";
    percent: number | null;
    good: boolean;
}

interface Summary {
    total_income: number;
    total_expense: number;
    net: number;
    trends: {
        income: Trend;
        expense: Trend;
        net: Trend;
    };
}

interface BreakdownRow {
    account: string;
    group: string;
    amount: number;
}

interface Breakdown {
    income_rows: BreakdownRow[];
    expense_rows: BreakdownRow[];
    loss_rows: BreakdownRow[];
}

interface RecentTransaction {
    name: string;
    date: string;
    amount: number;
    income: boolean;
}

interface FarmProduceItem {
    farm: string;
    type: string;
    quantity: string;
    unit: string | null;
}

interface FarmProduce {
    items: FarmProduceItem[];
    more_count: number;
}

interface WeatherAdvice {
    category: string;
    message: string;
}

interface WeatherEntry {
    community: string;
    available: boolean;
    condition?: "heavy_rain" | "strong_wind" | "very_hot" | "normal";
    headline?: string;
    advice?: WeatherAdvice[];
}

interface Filters {
    from: string;
    to: string;
}

interface Props {
    summary: Summary;
    breakdown: Breakdown;
    recent_transactions: RecentTransaction[];
    farm_produce: FarmProduce;
    weather: WeatherEntry[];
    filters: Filters;
}

const creditScore = 720;
const creditMax = 850;

function DashboardContent({
    summary,
    breakdown,
    recent_transactions,
    farm_produce,
    weather,
    filters,
}: Props) {
    const [openDetail, setOpenDetail] = useState<
        "income" | "expense" | "net" | null
    >(null);
    const { dark } = useTheme();

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const track = dark ? "#374151" : "#E5E7EB";
    const primary = "#1D9E75";
    const danger = "#DC2626";

    const scorePercent = Math.round((creditScore / creditMax) * 100);

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

    const kpis = [
        {
            key: "income" as const,
            label: "Income (30 days)",
            value: `GHS ${cedis(summary.total_income)}`,
            trend: trendMeta(summary.trends.income),
            soon: false,
            clickable: true,
        },
        {
            key: "expense" as const,
            label: "Expenses (30 days)",
            value: `GHS ${cedis(summary.total_expense)}`,
            trend: trendMeta(summary.trends.expense),
            soon: false,
            clickable: true,
        },
        {
            key: "net" as const,
            label: "Net profit",
            value: `${summary.net < 0 ? "-" : ""}GHS ${cedis(Math.abs(summary.net))}`,
            trend: trendMeta(summary.trends.net),
            soon: false,
            clickable: true,
        },
        {
            key: "loans" as const,
            label: "Active loans",
            value: "GH 2,000",
            trend: {
                TrendIcon: IconMinus,
                color: textSecondary,
                label: "Due in 14 days",
            },
            soon: true,
            clickable: false,
        },
    ];

    return (
        <>
            <GreetingHeader subtitle="Here is your farm's financial snapshot for today." />

            <div
                style={{
                    display: "grid",
                    gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))",
                    gap: "16px",
                    marginBottom: "24px",
                }}
            >
                {kpis.map((kpi) => (
                    <div
                        key={kpi.label}
                        onClick={() =>
                            kpi.clickable &&
                            setOpenDetail(
                                kpi.key as "income" | "expense" | "net",
                            )
                        }
                        style={{
                            background: surface,
                            border: `1px solid ${border}`,
                            padding: "18px",
                            opacity: kpi.soon ? 0.5 : 1,
                            cursor: kpi.clickable ? "pointer" : "default",
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
                            {kpi.soon ? " (Soon)" : ""}
                        </p>
                        <p
                            style={{
                                fontSize: "26px",
                                fontWeight: 700,
                                color: text,
                                marginBottom: "8px",
                                letterSpacing: "-0.5px",
                            }}
                        >
                            {kpi.value}
                        </p>
                        <div
                            style={{
                                display: "flex",
                                alignItems: "center",
                                gap: "4px",
                                fontSize: "15px",
                                color: kpi.trend.color,
                            }}
                        >
                            <kpi.trend.TrendIcon size={18} stroke={1.8} />
                            {kpi.trend.label}
                        </div>
                    </div>
                ))}
            </div>

            <div
                style={{
                    display: "grid",
                    gridTemplateColumns: "repeat(auto-fit, minmax(300px, 1fr))",
                    gap: "16px",
                }}
            >
                <div
                    style={{
                        background: surface,
                        border: `1px solid ${border}`,
                        padding: "18px",
                    }}
                >
                    <p
                        style={{
                            fontSize: "20px",
                            fontWeight: 600,
                            color: text,
                            marginBottom: "16px",
                        }}
                    >
                        Recent transactions
                    </p>

                    {recent_transactions.length === 0 ? (
                        <p style={{ fontSize: "16px", color: textSecondary }}>
                            No transactions recorded yet.
                        </p>
                    ) : (
                        recent_transactions.map((t, i) => (
                            <div
                                key={`${t.name}-${t.date}-${i}`}
                                style={{
                                    display: "flex",
                                    alignItems: "center",
                                    justifyContent: "space-between",
                                    padding: "12px 0",
                                    borderBottom:
                                        i === recent_transactions.length - 1
                                            ? "none"
                                            : `1px solid ${border}`,
                                }}
                            >
                                <div>
                                    <p
                                        style={{
                                            fontSize: "18px",
                                            fontWeight: 500,
                                            color: text,
                                            margin: 0,
                                        }}
                                    >
                                        {t.name}
                                    </p>
                                    <p
                                        style={{
                                            fontSize: "15px",
                                            color: textSecondary,
                                            margin: "2px 0 0",
                                        }}
                                    >
                                        {shortDate(t.date)}
                                    </p>
                                </div>
                                <span
                                    style={{
                                        fontSize: "18px",
                                        fontWeight: 600,
                                        color: t.income ? primary : danger,
                                    }}
                                >
                                    {t.income ? "+ " : "- "}GHS{" "}
                                    {cedis(t.amount)}
                                </span>
                            </div>
                        ))
                    )}
                </div>

                <div
                    style={{
                        display: "flex",
                        flexDirection: "column",
                        gap: "16px",
                    }}
                >
                    <div
                        style={{
                            background: surface,
                            border: `1px solid ${border}`,
                            padding: "18px",
                            opacity: 0.5,
                        }}
                    >
                        <p
                            style={{
                                fontSize: "20px",
                                fontWeight: 600,
                                color: text,
                                marginBottom: "12px",
                            }}
                        >
                            Credit score (Soon)
                        </p>
                        <p
                            style={{
                                fontSize: "30px",
                                fontWeight: 700,
                                color: primary,
                                marginBottom: "4px",
                                letterSpacing: "-0.5px",
                            }}
                        >
                            {creditScore}
                        </p>
                        <p
                            style={{
                                fontSize: "15px",
                                color: textSecondary,
                                marginBottom: "12px",
                            }}
                        >
                            out of {creditMax} — Good standing
                        </p>
                        <div
                            style={{
                                height: "8px",
                                background: track,
                                width: "100%",
                            }}
                        >
                            <div
                                style={{
                                    height: "8px",
                                    width: `${scorePercent}%`,
                                    background: primary,
                                }}
                            />
                        </div>
                    </div>

                    <div
                        style={{
                            background: surface,
                            border: `1px solid ${border}`,
                            padding: "18px",
                        }}
                    >
                        <div className="flex justify-between items-center mb-3">
                            <p
                                style={{
                                    fontSize: "20px",
                                    fontWeight: 600,
                                    color: text,
                                    margin: 0,
                                }}
                            >
                                Farm produce
                            </p>
                            {farm_produce.more_count > 0 && (
                                <Link
                                    href="/my-farm"
                                    style={{
                                        fontSize: "15px",
                                        fontWeight: 600,
                                        color: primary,
                                    }}
                                >
                                    +{farm_produce.more_count} more
                                </Link>
                            )}
                        </div>

                        {farm_produce.items.length === 0 ? (
                            <p
                                style={{
                                    fontSize: "16px",
                                    color: textSecondary,
                                }}
                            >
                                Nothing set up yet.
                            </p>
                        ) : (
                            <div
                                style={{
                                    display: "grid",
                                    gridTemplateColumns: "1fr 1fr",
                                    gap: "12px",
                                }}
                            >
                                {farm_produce.items.map((item) => (
                                    <div key={`${item.farm}-${item.type}`}>
                                        <p
                                            style={{
                                                fontSize: "23px",
                                                fontWeight: 700,
                                                color: text,
                                                margin: 0,
                                            }}
                                        >
                                            {item.quantity}
                                            {item.unit ? (
                                                <span
                                                    style={{
                                                        fontSize: "15px",
                                                        fontWeight: 500,
                                                        color: textSecondary,
                                                    }}
                                                >
                                                    {" "}
                                                    {item.unit}
                                                </span>
                                            ) : null}
                                        </p>
                                        <p
                                            style={{
                                                fontSize: "18px",
                                                color: text,
                                                margin: "2px 0 0",
                                            }}
                                        >
                                            {item.type}
                                        </p>
                                        <p
                                            style={{
                                                fontSize: "14px",
                                                color: textSecondary,
                                                margin: "1px 0 0",
                                            }}
                                        >
                                            {item.farm}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    {weather.length === 0 ? null : (
                        <>
                            <style>{`
                                @keyframes weatherRain { 0%,100% { transform: translateY(0); } 50% { transform: translateY(3px); } }
                                @keyframes weatherSun { 0%,100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.7; transform: scale(1.1); } }
                                @keyframes weatherWind { 0%,100% { transform: translateX(0); } 50% { transform: translateX(4px); } }
                            `}</style>
                            {weather.map((entry) => (
                                <WeatherCard
                                    key={entry.community}
                                    entry={entry}
                                    dark={dark}
                                />
                            ))}
                        </>
                    )}
                </div>
            </div>

            {openDetail && (
                <DetailModal
                    kind={openDetail}
                    breakdown={breakdown}
                    filters={filters}
                    surface={surface}
                    border={border}
                    text={text}
                    textSecondary={textSecondary}
                    primary={primary}
                    danger={danger}
                    onClose={() => setOpenDetail(null)}
                />
            )}
        </>
    );
}

function WeatherCard({ entry, dark }: { entry: WeatherEntry; dark: boolean }) {
    const goodBg = dark ? "rgba(29,158,117,0.15)" : "#EAF5F0";
    const goodBorder = dark ? "rgba(29,158,117,0.3)" : "#A8D9C8";
    const goodText = dark ? "#4ADE80" : "#0F6E56";
    const goodTextSoft = dark ? "rgba(74,222,128,0.85)" : "#0F6E56";

    const warnBg = dark ? "rgba(180,83,9,0.15)" : "#FEF3C7";
    const warnBorder = dark ? "rgba(180,83,9,0.3)" : "#FDE68A";
    const warnText = dark ? "#FBBF24" : "#92400E";
    const warnTextSoft = dark ? "rgba(251,191,36,0.85)" : "#92400E";

    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";

    if (!entry.available) {
        return (
            <div
                style={{
                    background: surface,
                    border: `1px solid ${border}`,
                    padding: "18px",
                }}
            >
                <p
                    style={{
                        fontSize: "20px",
                        fontWeight: 600,
                        color: dark ? "#F9FAFB" : "#111827",
                        marginBottom: "6px",
                    }}
                >
                    {entry.community}
                </p>
                <p style={{ fontSize: "16px", color: textSecondary }}>
                    Weather isn't available for this location right now.
                </p>
            </div>
        );
    }

    const isNormal = entry.condition === "normal";
    const bg = isNormal ? goodBg : warnBg;
    const borderColor = isNormal ? goodBorder : warnBorder;
    const textColor = isNormal ? goodText : warnText;
    const textSoft = isNormal ? goodTextSoft : warnTextSoft;

    const iconFor = {
        heavy_rain: {
            Icon: IconCloudRain,
            animation: "weatherRain 1.6s ease-in-out infinite",
        },
        strong_wind: {
            Icon: IconWind,
            animation: "weatherWind 1.6s ease-in-out infinite",
        },
        very_hot: {
            Icon: IconSun,
            animation: "weatherSun 2s ease-in-out infinite",
        },
        normal: { Icon: IconCloud, animation: "none" },
    }[entry.condition ?? "normal"];

    const { Icon, animation } = iconFor;

    return (
        <div
            style={{
                background: bg,
                border: `1px solid ${borderColor}`,
                padding: "18px",
            }}
        >
            <div
                style={{
                    display: "flex",
                    alignItems: "center",
                    gap: "8px",
                    marginBottom: "4px",
                }}
            >
                <span style={{ display: "inline-flex", animation }}>
                    <Icon size={22} stroke={1.8} color={textColor} />
                </span>
                <p
                    style={{
                        fontSize: "20px",
                        fontWeight: 600,
                        color: textColor,
                        margin: 0,
                    }}
                >
                    {entry.headline}
                </p>
            </div>
            <p
                style={{
                    fontSize: "15px",
                    color: textSecondary,
                    marginBottom: "10px",
                }}
            >
                {entry.community}
            </p>

            {entry.advice?.map((item) => (
                <p
                    key={item.category}
                    style={{
                        fontSize: "17px",
                        color: textSoft,
                        lineHeight: 1.6,
                        margin: "0 0 8px",
                    }}
                >
                    <strong>{item.category}:</strong> {item.message}
                </p>
            ))}
        </div>
    );
}

function DetailModal({
    kind,
    breakdown,
    filters,
    surface,
    border,
    text,
    textSecondary,
    primary,
    danger,
    onClose,
}: {
    kind: "income" | "expense" | "net";
    breakdown: Breakdown;
    filters: Filters;
    surface: string;
    border: string;
    text: string;
    textSecondary: string;
    primary: string;
    danger: string;
    onClose: () => void;
}) {
    const titles = {
        income: "Where your income came from",
        expense: "Where your expenses went",
        net: "How net profit was worked out",
    };

    const section = (label: string, rows: BreakdownRow[], color: string) => (
        <div className="mb-4">
            <p
                style={{
                    fontSize: "16px",
                    fontWeight: 600,
                    color: textSecondary,
                    marginBottom: "6px",
                }}
            >
                {label}
            </p>
            {rows.length === 0 ? (
                <p style={{ fontSize: "16px", color: textSecondary }}>
                    Nothing recorded for this period.
                </p>
            ) : (
                rows.map((row) => (
                    <div
                        key={row.account}
                        className="flex justify-between"
                        style={{
                            padding: "6px 0",
                            borderBottom: `1px solid ${border}`,
                        }}
                    >
                        <span style={{ fontSize: "17px", color: text }}>
                            {row.account}
                        </span>
                        <span
                            style={{
                                fontSize: "17px",
                                fontWeight: 600,
                                color,
                            }}
                        >
                            GHS {cedis(row.amount)}
                        </span>
                    </div>
                ))
            )}
        </div>
    );

    return (
        <div
            onClick={onClose}
            style={{
                position: "fixed",
                inset: 0,
                background: "rgba(0,0,0,0.4)",
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
                zIndex: 50,
            }}
        >
            <div
                onClick={(event) => event.stopPropagation()}
                style={{
                    background: surface,
                    border: `1px solid ${border}`,
                    padding: "24px",
                    width: "90%",
                    maxWidth: "480px",
                    maxHeight: "80vh",
                    overflowY: "auto",
                }}
            >
                <div className="flex justify-between items-center mb-4">
                    <p
                        style={{
                            fontSize: "20px",
                            fontWeight: 700,
                            color: text,
                        }}
                    >
                        {titles[kind]}
                    </p>
                    <button
                        onClick={onClose}
                        style={{
                            background: "none",
                            border: "none",
                            cursor: "pointer",
                            color: textSecondary,
                        }}
                    >
                        <IconX size={22} />
                    </button>
                </div>

                {(kind === "income" || kind === "net") &&
                    section("Income", breakdown.income_rows, primary)}
                {(kind === "expense" || kind === "net") &&
                    section("Expenses", breakdown.expense_rows, danger)}
                {kind === "net" &&
                    section(
                        "Lost (no cash)",
                        breakdown.loss_rows,
                        textSecondary,
                    )}

                <Link
                    href={`/my-reports?kind=income&from=${filters.from}&to=${filters.to}`}
                    style={{
                        display: "inline-block",
                        marginTop: "8px",
                        fontSize: "16px",
                        fontWeight: 600,
                        color: primary,
                    }}
                >
                    View full report →
                </Link>
            </div>
        </div>
    );
}

export default function Dashboard(props: Props) {
    useAuthGuard();

    return (
        <AuthenticatedLayout title="Dashboard">
            <Head title="Dashboard" />
            <DashboardContent
                summary={props.summary}
                breakdown={props.breakdown}
                recent_transactions={props.recent_transactions}
                farm_produce={props.farm_produce}
                weather={props.weather}
                filters={props.filters}
            />
        </AuthenticatedLayout>
    );
}
