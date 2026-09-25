import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import { Link, router } from "@inertiajs/react";
import { useState } from "react";

interface OrderItemRow {
    product: string | null;
    quantity: number;
}

interface OrderRow {
    uuid: string;
    order_number: string;
    kiosk_name: string | null;
    status: string;
    received_at: string | null;
    is_fully_confirmed: boolean;
    has_review: boolean;
    amount_minor: number;
    items: OrderItemRow[];
}

interface Props {
    orders: OrderRow[];
}

export default function Orders({ orders }: Props) {
    return (
        <AuthenticatedLayout title="My Orders">
            <OrdersContent orders={orders} />
        </AuthenticatedLayout>
    );
}

const statusLabel: Record<string, string> = {
    requested: "Waiting on the supplier",
    confirmed: "Confirmed by the supplier",
    received: "Received",
    closed: "Closed (no response in time)",
};

function OrdersContent({ orders }: Props) {
    const { dark } = useTheme();
    const [reviewingUuid, setReviewingUuid] = useState<string | null>(null);
    const [rating, setRating] = useState("5");
    const [comment, setComment] = useState("");

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const primary = "#1D9E75";

    const cedis = (minor: number) => `GHS ${(minor / 100).toFixed(2)}`;

    const receive = (uuid: string) => {
        router.post(`/my-marketplace/orders/${uuid}/receive`, {}, { preserveScroll: true });
    };

    const submitReview = (uuid: string) => {
        router.post(
            `/my-marketplace/orders/${uuid}/review`,
            { rating: Number(rating), comment: comment || undefined },
            { preserveScroll: true, onSuccess: () => setReviewingUuid(null) },
        );
    };

    if (orders.length === 0) {
        return (
            <>
                <Link href="/my-marketplace" style={{ color: primary, fontWeight: 600 }}>
                    ← Back to Market Center
                </Link>
                <div
                    className="px-6 py-10 text-center mt-4"
                    style={{ background: surface, border: `1px solid ${border}`, color: textSecondary, fontSize: "1.125rem" }}
                >
                    You have not ordered anything yet.
                </div>
            </>
        );
    }

    return (
        <>
            <Link href="/my-marketplace" style={{ color: primary, fontWeight: 600 }}>
                ← Back to Market Center
            </Link>

            <div className="flex flex-col gap-4 mt-4">
                {orders.map((order) => (
                    <div key={order.uuid} style={{ background: surface, border: `1px solid ${border}`, padding: "18px" }}>
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 style={{ color: text, fontSize: "1.1875rem", fontWeight: 700, margin: 0 }}>
                                    {order.order_number}
                                </h3>
                                <p style={{ color: textSecondary, fontSize: "1rem", margin: "2px 0 0" }}>
                                    {order.kiosk_name}
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

                            <div className="flex gap-3">
                                {order.received_at === null && order.status !== "closed" && (
                                    <button
                                        onClick={() => receive(order.uuid)}
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
                                        Mark as received
                                    </button>
                                )}

                                {order.is_fully_confirmed && !order.has_review && reviewingUuid !== order.uuid && (
                                    <button
                                        onClick={() => setReviewingUuid(order.uuid)}
                                        style={{
                                            background: "transparent",
                                            color: primary,
                                            border: `1px solid ${primary}`,
                                            fontWeight: 600,
                                            fontSize: "1rem",
                                            padding: "9px 20px",
                                            cursor: "pointer",
                                            fontFamily: "inherit",
                                        }}
                                    >
                                        Leave a review
                                    </button>
                                )}
                            </div>
                        </div>

                        {reviewingUuid === order.uuid && (
                            <div className="flex flex-wrap items-end gap-3 mt-4" style={{ borderTop: `1px solid ${border}`, paddingTop: "14px" }}>
                                <div>
                                    <label style={{ display: "block", fontSize: "0.9375rem", color: textSecondary, marginBottom: "4px" }}>
                                        Rating
                                    </label>
                                    <select
                                        value={rating}
                                        onChange={(event) => setRating(event.target.value)}
                                        style={{ border: `1px solid ${border}`, background: dark ? "#111827" : "#FFFFFF", color: text, padding: "8px 10px", fontFamily: "inherit" }}
                                    >
                                        {[5, 4, 3, 2, 1].map((value) => (
                                            <option key={value} value={value}>
                                                {value} star{value === 1 ? "" : "s"}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div style={{ flex: 1, minWidth: "200px" }}>
                                    <label style={{ display: "block", fontSize: "0.9375rem", color: textSecondary, marginBottom: "4px" }}>
                                        Comment (optional)
                                    </label>
                                    <input
                                        type="text"
                                        value={comment}
                                        onChange={(event) => setComment(event.target.value)}
                                        style={{ border: `1px solid ${border}`, background: dark ? "#111827" : "#FFFFFF", color: text, padding: "8px 10px", width: "100%", fontFamily: "inherit" }}
                                    />
                                </div>
                                <button
                                    onClick={() => submitReview(order.uuid)}
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
                                    Submit review
                                </button>
                            </div>
                        )}
                    </div>
                ))}
            </div>
        </>
    );
}
