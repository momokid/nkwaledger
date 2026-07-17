import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import useAuthGuard from "@/hooks/useAuthGuard";
import { Head, usePage } from "@inertiajs/react";
import {
    IconArrowDownRight,
    IconArrowUpRight,
    IconCloudRain,
    IconCreditCard,
    IconMinus,
} from "@tabler/icons-react";

interface PageProps {
    auth: {
        user: {
            first_name: string;
            surname: string;
        } | null;
    };
}

const kpis = [
    {
        label: "Income (30 days)",
        value: "GH₵ 4,820",
        trend: "+12% vs last month",
        up: true,
    },
    {
        label: "Expenses (30 days)",
        value: "GH₵ 1,340",
        trend: "+4% vs last month",
        up: false,
    },
    {
        label: "Net profit",
        value: "GH₵ 3,480",
        trend: "+18% vs last month",
        up: true,
    },
    {
        label: "Active loans",
        value: "GH₵ 2,000",
        trend: "Due in 14 days",
        up: null,
    },
];

const transactions = [
    {
        name: "Pig sale",
        date: "Today, 9:30am",
        amount: "+ GH₵ 850",
        income: true,
    },
    {
        name: "Fertilizer",
        date: "Yesterday",
        amount: "− GH₵ 220",
        income: false,
    },
    {
        name: "Maize sale",
        date: "2 days ago",
        amount: "+ GH₵ 1,200",
        income: true,
    },
    {
        name: "Transport",
        date: "3 days ago",
        amount: "− GH₵ 80",
        income: false,
    },
];

const livestock = [
    { type: "Goats", count: 12 },
    { type: "Pigs", count: 4 },
    { type: "Chickens", count: 24 },
    { type: "Cattle", count: 2 },
];

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
    const surfaceDeep = dark ? "#111827" : "#F9FAFB";
    const border = dark ? "rgba(255,255,255,0.08)" : "#F3F4F6";
    const textPrimary = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "rgba(255,255,255,0.55)" : "#6B7280";
    const textMuted = dark ? "rgba(255,255,255,0.35)" : "#9CA3AF";

    const card = {
        background: surface,
        border: `1px solid ${dark ? "rgba(255,255,255,0.08)" : "#E5E7EB"}`,
        padding: "20px",
    };

    return (
        <>
            <p
                style={{
                    fontSize: "24px",
                    fontWeight: 700,
                    color: textPrimary,
                    marginBottom: "4px",
                    letterSpacing: "-0.3px",
                }}
            >
                {greeting}, {firstName}
            </p>
            <p
                style={{
                    fontSize: "17px",
                    color: textSecondary,
                    marginBottom: "28px",
                }}
            >
                Here's your farm's financial snapshot for today.
            </p>

            <div
                style={{
                    display: "grid",
                    gridTemplateColumns: "repeat(auto-fit, minmax(180px, 1fr))",
                    gap: "14px",
                    marginBottom: "28px",
                }}
            >
                {kpis.map((kpi) => (
                    <div key={kpi.label} style={card}>
                        <p
                            style={{
                                fontSize: "15px",
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
                                color: textPrimary,
                                marginBottom: "6px",
                                letterSpacing: "-0.3px",
                            }}
                        >
                            {kpi.value}
                        </p>
                        <p
                            style={{
                                fontSize: "14px",
                                display: "flex",
                                alignItems: "center",
                                gap: "4px",
                                color:
                                    kpi.up === null
                                        ? textMuted
                                        : kpi.up
                                          ? "#1D9E75"
                                          : "#E24B4A",
                            }}
                        >
                            {kpi.up === true && <IconArrowUpRight size={16} />}
                            {kpi.up === false && (
                                <IconArrowDownRight size={16} />
                            )}
                            {kpi.up === null && <IconMinus size={16} />}
                            {kpi.trend}
                        </p>
                    </div>
                ))}
            </div>

            <div
                style={{
                    display: "grid",
                    gridTemplateColumns: "1fr 1fr",
                    gap: "18px",
                    marginBottom: "18px",
                }}
            >
                <div style={card}>
                    <div
                        style={{
                            display: "flex",
                            justifyContent: "space-between",
                            alignItems: "center",
                            marginBottom: "16px",
                        }}
                    >
                        <p
                            style={{
                                fontSize: "17px",
                                fontWeight: 600,
                                color: textPrimary,
                            }}
                        >
                            Recent transactions
                        </p>
                        <a
                            href="/ledger"
                            style={{
                                fontSize: "15px",
                                color: "#1D9E75",
                                textDecoration: "none",
                                fontWeight: 500,
                            }}
                        >
                            View all
                        </a>
                    </div>
                    {transactions.map((tx) => (
                        <div
                            key={tx.name + tx.date}
                            style={{
                                display: "flex",
                                justifyContent: "space-between",
                                alignItems: "center",
                                padding: "10px 0",
                                borderBottom: `1px solid ${border}`,
                            }}
                        >
                            <div>
                                <p
                                    style={{
                                        fontSize: "15px",
                                        fontWeight: 500,
                                        color: textPrimary,
                                    }}
                                >
                                    {tx.name}
                                </p>
                                <p
                                    style={{
                                        fontSize: "13px",
                                        color: textMuted,
                                        marginTop: "2px",
                                    }}
                                >
                                    {tx.date}
                                </p>
                            </div>
                            <p
                                style={{
                                    fontSize: "15px",
                                    fontWeight: 600,
                                    color: tx.income ? "#1D9E75" : "#E24B4A",
                                }}
                            >
                                {tx.amount}
                            </p>
                        </div>
                    ))}
                </div>

                <div
                    style={{
                        display: "flex",
                        flexDirection: "column",
                        gap: "18px",
                    }}
                >
                    <div style={card}>
                        <div
                            style={{
                                display: "flex",
                                alignItems: "center",
                                gap: "10px",
                                marginBottom: "12px",
                            }}
                        >
                            <IconCreditCard size={20} color="#1D9E75" />
                            <p
                                style={{
                                    fontSize: "17px",
                                    fontWeight: 600,
                                    color: textPrimary,
                                }}
                            >
                                Credit score
                            </p>
                        </div>
                        <p
                            style={{
                                fontSize: "38px",
                                fontWeight: 700,
                                color: "#1D9E75",
                                letterSpacing: "-0.5px",
                            }}
                        >
                            682
                        </p>
                        <p
                            style={{
                                fontSize: "15px",
                                color: textSecondary,
                                marginBottom: "12px",
                            }}
                        >
                            Good standing
                        </p>
                        <div
                            style={{
                                background: surfaceDeep,
                                height: "10px",
                                marginBottom: "8px",
                            }}
                        >
                            <div
                                style={{
                                    background: "#1D9E75",
                                    height: "10px",
                                    width: "68%",
                                }}
                            />
                        </div>
                        <div
                            style={{
                                display: "flex",
                                justifyContent: "space-between",
                                fontSize: "13px",
                                color: textMuted,
                            }}
                        >
                            <span>0</span>
                            <span>1000</span>
                        </div>
                    </div>

                    <div style={card}>
                        <div
                            style={{
                                display: "flex",
                                justifyContent: "space-between",
                                alignItems: "center",
                                marginBottom: "14px",
                            }}
                        >
                            <p
                                style={{
                                    fontSize: "17px",
                                    fontWeight: 600,
                                    color: textPrimary,
                                }}
                            >
                                Livestock
                            </p>
                            <a
                                href="/livestock"
                                style={{
                                    fontSize: "15px",
                                    color: "#1D9E75",
                                    textDecoration: "none",
                                    fontWeight: 500,
                                }}
                            >
                                View all
                            </a>
                        </div>
                        <div
                            style={{
                                display: "grid",
                                gridTemplateColumns: "1fr 1fr",
                                gap: "10px",
                            }}
                        >
                            {livestock.map((l) => (
                                <div
                                    key={l.type}
                                    style={{
                                        background: surfaceDeep,
                                        padding: "12px",
                                        border: `1px solid ${border}`,
                                    }}
                                >
                                    <p
                                        style={{
                                            fontSize: "24px",
                                            fontWeight: 700,
                                            color: textPrimary,
                                        }}
                                    >
                                        {l.count}
                                    </p>
                                    <p
                                        style={{
                                            fontSize: "14px",
                                            color: textSecondary,
                                            marginTop: "2px",
                                        }}
                                    >
                                        {l.type}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </div>

            <div style={card}>
                <div
                    style={{
                        display: "flex",
                        alignItems: "center",
                        gap: "10px",
                        marginBottom: "14px",
                    }}
                >
                    <IconCloudRain size={20} color="#1D9E75" />
                    <p
                        style={{
                            fontSize: "17px",
                            fontWeight: 600,
                            color: textPrimary,
                        }}
                    >
                        Weather alerts
                    </p>
                </div>
                <div
                    style={{
                        background: dark ? "rgba(29,158,117,0.15)" : "#EAF5F0",
                        border: `1px solid ${dark ? "rgba(29,158,117,0.3)" : "#A8D9C8"}`,
                        padding: "14px",
                    }}
                >
                    <p
                        style={{
                            fontSize: "15px",
                            fontWeight: 600,
                            color: dark ? "#4ADE80" : "#0F6E56",
                            marginBottom: "6px",
                        }}
                    >
                        Rain expected
                    </p>
                    <p
                        style={{
                            fontSize: "15px",
                            color: dark ? "rgba(74,222,128,0.8)" : "#0F6E56",
                            lineHeight: 1.6,
                        }}
                    >
                        Heavy rainfall expected in Brong-Ahafo region in the
                        next 48 hours. Consider harvesting early.
                    </p>
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
