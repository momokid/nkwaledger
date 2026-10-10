import AdminLayout from "@/Layouts/AdminLayout";
import { useTheme } from "@/Layouts/AuthenticatedLayout";

interface Stats {
    active_kiosk_products: number;
    active_produce_listings: number;
    orders_last_7_days: number;
    produce_sales_last_7_days: number;
}

interface Props {
    stats: Stats;
}

export default function Index({ stats }: Props) {
    return (
        <AdminLayout title="Marketplace Dashboard">
            <IndexContent stats={stats} />
        </AdminLayout>
    );
}

function IndexContent({ stats }: Props) {
    const { dark } = useTheme();
    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const primary = "#1D9E75";

    const cards = [
        { label: "Active kiosk products", value: stats.active_kiosk_products },
        { label: "Active produce listings", value: stats.active_produce_listings },
        { label: "Orders, last 7 days", value: stats.orders_last_7_days },
        { label: "Produce sales, last 7 days", value: stats.produce_sales_last_7_days },
    ];

    return (
        <>
            <h1 style={{ color: text, fontSize: "1.375rem", fontWeight: 700, margin: "0 0 16px" }}>Marketplace Dashboard</h1>

            <div className="grid gap-4" style={{ gridTemplateColumns: "repeat(auto-fit, minmax(200px, 1fr))" }}>
                {cards.map((card) => (
                    <div key={card.label} style={{ background: surface, border: `1px solid ${border}`, padding: "18px" }}>
                        <p style={{ color: textSecondary, fontSize: "0.875rem", margin: "0 0 6px" }}>{card.label}</p>
                        <p style={{ color: primary, fontSize: "2rem", fontWeight: 700, margin: 0 }}>{card.value}</p>
                    </div>
                ))}
            </div>
        </>
    );
}
