import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import BackLink from "@/Components/BackLink";
import { router } from "@inertiajs/react";

interface OrderItemRow {
    product: string | null;
    quantity: number;
}

interface OrderRow {
    uuid: string;
    order_number: string;
    kiosk_name: string | null;
    farmer_name: string;
    status: string;
    confirmed_at: string | null;
    payment_method: string;
    amount_minor: number;
    requested_at: string;
    items: OrderItemRow[];
}

interface Props {
    orders: OrderRow[];
}

export default function Index({ orders }: Props) {
    return (
        <AuthenticatedLayout title="Orders">
            <BackLink fallbackHref="/supplier/dashboard" />
            <IndexContent orders={orders} />
        </AuthenticatedLayout>
    );
}

const statusLabel: Record<string, string> = {
    requested: "Requested",
    confirmed: "Confirmed",
    received: "Received",
    closed: "Closed",
};

const paymentLabel: Record<string, string> = {
    bank: "Bank payment",
    cod: "Cash on delivery",
};

function IndexContent({ orders }: Props) {
    const { dark } = useTheme();

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const primary = "#1D9E75";

    const confirm = (uuid: string) => {
        router.post(`/supplier/orders/${uuid}/confirm`, {}, { preserveScroll: true });
    };

    const cedis = (minor: number) => `GHS ${(minor / 100).toFixed(2)}`;

    if (orders.length === 0) {
        return (
            <div
                className="px-6 py-10 text-center"
                style={{ background: surface, border: `1px solid ${border}`, color: textSecondary, fontSize: "1.125rem" }}
            >
                No orders yet.
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-4">
            {orders.map((order) => (
                <div key={order.uuid} style={{ background: surface, border: `1px solid ${border}`, padding: "18px" }}>
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 style={{ color: text, fontSize: "1.1875rem", fontWeight: 700, margin: 0 }}>
                                {order.order_number}
                            </h3>
                            <p style={{ color: textSecondary, fontSize: "1rem", margin: "2px 0 0" }}>
                                {order.farmer_name} · {paymentLabel[order.payment_method]}
                            </p>
                        </div>
                        <span style={{ color: primary, fontWeight: 700, fontSize: "1rem" }}>
                            {statusLabel[order.status]}
                        </span>
                    </div>

                    <ul className="mt-3" style={{ color: text, fontSize: "1rem" }}>
                        {order.items.map((item, index) => (
                            <li key={index}>
                                {item.quantity} × {item.product ?? "Product"}
                            </li>
                        ))}
                    </ul>

                    <div className="flex flex-wrap items-center justify-between gap-3 mt-4">
                        <span style={{ color: text, fontWeight: 700, fontSize: "1.0625rem" }}>
                            {cedis(order.amount_minor)}
                        </span>

                        {order.status !== "closed" && order.confirmed_at === null && (
                            <button
                                onClick={() => confirm(order.uuid)}
                                style={{
                                    background: primary,
                                    color: "#FFFFFF",
                                    fontWeight: 600,
                                    fontSize: "1rem",
                                    padding: "9px 20px",
                                    border: "none",
                                    cursor: "pointer",
                                    fontFamily: "inherit",
                                }}
                            >
                                Confirm order
                            </button>
                        )}
                    </div>
                </div>
            ))}
        </div>
    );
}
