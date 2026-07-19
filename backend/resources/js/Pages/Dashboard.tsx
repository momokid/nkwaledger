import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import useAuthGuard from "@/hooks/useAuthGuard";
import { Head, usePage } from "@inertiajs/react";
import {
    IconArrowDownRight,
    IconArrowUpRight,
    IconCloudRain,
    IconMinus,
} from "@tabler/icons-react";

interface PageProps {
    auth: {
        user: {
            first_name?: string;
            surname?: string;
        } | null;
    };
}

const kpis = [
    {
        label: "Income (30 days)",
        value: "GH 4,820",
        trend: "+12% vs last month",
        up: true,
    },
    {
        label: "Expenses (30 days)",
        value: "GH 1,340",
        trend: "+4% vs last month",
        up: false,
    },
    {
        label: "Net profit",
        value: "GH 3,480",
        trend: "+18% vs last month",
        up: true,
    },
    {
        label: "Active loans",
        value: "GH 2,000",
        trend: "Due in 14 days",
        up: null,
    },
];

const transactions = [
    {
        name: "Pig sale",
        date: "Today, 9:30am",
        amount: "+ GH 850",
        income: true,
    },
    {
        name: "Fertilizer",
        date: "Yesterday",
        amount: "- GH 220",
        income: false,
    },
    {
        name: "Maize sale",
        date: "2 days ago",
        amount: "+ GH 1,200",
        income: true,
    },
    { name: "Transport", date: "3 days ago", amount: "- GH 80", income: false },
];

const livestock = [
    { type: "Goats", count: 12 },
    { type: "Pigs", count: 4 },
    { type: "Chickens", count: 24 },
    { type: "Cattle", count: 2 },
];

const creditScore = 720;
const creditMax = 850;

function DashboardContent() {
    const { dark } = useTheme();
    const { auth } = usePage().props as unknown as PageProps;
    const firstName = auth?.user?.first_name ?? "Farmer";

    const hour = new Date().getHours();
    const greeting =
        hour < 12
            ? "Good morning"
            : hour < 17
              ? "Good afternoon"
              : "Good evening";

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const track = dark ? "#374151" : "#E5E7EB";
    const primary = "#1D9E75";
    const danger = "#DC2626";

    const scorePercent = Math.round((creditScore / creditMax) * 100);

    return (
        <>
            <p
                style={{
                    fontSize: "22px",
                    fontWeight: 600,
                    color: text,
                    marginBottom: "4px",
                }}
            >
                {greeting}, {firstName}
            </p>
            <p
                style={{
                    fontSize: "15px",
                    color: textSecondary,
                    marginBottom: "24px",
                }}
            >
                Here is your farm's financial snapshot for today.
            </p>

            <div
                style={{
                    display: "grid",
                    gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))",
                    gap: "16px",
                    marginBottom: "24px",
                }}
            >
                {kpis.map((kpi) => {
                    const TrendIcon =
                        kpi.up === null
                            ? IconMinus
                            : kpi.up
                              ? IconArrowUpRight
                              : IconArrowDownRight;
                    const trendColor =
                        kpi.up === null
                            ? textSecondary
                            : kpi.up
                              ? primary
                              : danger;

                    return (
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
                                    fontSize: "14px",
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
                                    fontSize: "13px",
                                    color: trendColor,
                                }}
                            >
                                <TrendIcon size={18} stroke={1.8} />
                                {kpi.trend}
                            </div>
                        </div>
                    );
                })}
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
                            fontSize: "17px",
                            fontWeight: 600,
                            color: text,
                            marginBottom: "16px",
                        }}
                    >
                        Recent transactions
                    </p>

                    {transactions.map((t, i) => (
                        <div
                            key={t.name}
                            style={{
                                display: "flex",
                                alignItems: "center",
                                justifyContent: "space-between",
                                padding: "12px 0",
                                borderBottom:
                                    i === transactions.length - 1
                                        ? "none"
                                        : `1px solid ${border}`,
                            }}
                        >
                            <div>
                                <p
                                    style={{
                                        fontSize: "15px",
                                        fontWeight: 500,
                                        color: text,
                                        margin: 0,
                                    }}
                                >
                                    {t.name}
                                </p>
                                <p
                                    style={{
                                        fontSize: "13px",
                                        color: textSecondary,
                                        margin: "2px 0 0",
                                    }}
                                >
                                    {t.date}
                                </p>
                            </div>
                            <span
                                style={{
                                    fontSize: "15px",
                                    fontWeight: 600,
                                    color: t.income ? primary : danger,
                                }}
                            >
                                {t.amount}
                            </span>
                        </div>
                    ))}
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
                        }}
                    >
                        <p
                            style={{
                                fontSize: "17px",
                                fontWeight: 600,
                                color: text,
                                marginBottom: "12px",
                            }}
                        >
                            Credit score
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
                                fontSize: "13px",
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
                        <p
                            style={{
                                fontSize: "17px",
                                fontWeight: 600,
                                color: text,
                                marginBottom: "12px",
                            }}
                        >
                            Livestock
                        </p>
                        <div
                            style={{
                                display: "grid",
                                gridTemplateColumns: "1fr 1fr",
                                gap: "12px",
                            }}
                        >
                            {livestock.map((l) => (
                                <div key={l.type}>
                                    <p
                                        style={{
                                            fontSize: "22px",
                                            fontWeight: 700,
                                            color: text,
                                            margin: 0,
                                        }}
                                    >
                                        {l.count}
                                    </p>
                                    <p
                                        style={{
                                            fontSize: "14px",
                                            color: textSecondary,
                                            margin: "2px 0 0",
                                        }}
                                    >
                                        {l.type}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div
                        style={{
                            background: dark
                                ? "rgba(29,158,117,0.15)"
                                : "#EAF5F0",
                            border: `1px solid ${dark ? "rgba(29,158,117,0.3)" : "#A8D9C8"}`,
                            padding: "18px",
                        }}
                    >
                        <div
                            style={{
                                display: "flex",
                                alignItems: "center",
                                gap: "8px",
                                marginBottom: "6px",
                            }}
                        >
                            <IconCloudRain
                                size={22}
                                stroke={1.8}
                                color={dark ? "#4ADE80" : "#0F6E56"}
                            />
                            <p
                                style={{
                                    fontSize: "16px",
                                    fontWeight: 600,
                                    color: dark ? "#4ADE80" : "#0F6E56",
                                    margin: 0,
                                }}
                            >
                                Rain expected
                            </p>
                        </div>
                        <p
                            style={{
                                fontSize: "15px",
                                color: dark
                                    ? "rgba(74,222,128,0.85)"
                                    : "#0F6E56",
                                lineHeight: 1.6,
                                margin: 0,
                            }}
                        >
                            Heavy rainfall expected in your region in the next
                            48 hours. Consider harvesting early.
                        </p>
                    </div>
                </div>
            </div>
        </>
    );
}

export default function Dashboard() {
    useAuthGuard();

    return (
        <AuthenticatedLayout title="Dashboard">
            <Head title="Dashboard" />
            <DashboardContent />
        </AuthenticatedLayout>
    );
}
