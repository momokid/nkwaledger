import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import BackLink from "@/Components/BackLink";
import { router } from "@inertiajs/react";
import { useState } from "react";

interface KioskInfo {
    uuid: string;
    name: string;
    supplier: string | null;
    contact_phone: string;
    distance_label: string;
    categories: string[];
    rating_average: number | null;
    review_count: number;
    sales_count: number;
}

interface ProductRow {
    kiosk_product_id: number;
    name: string | null;
    price_minor: number;
    unit: string | null;
    image_url: string | null;
}

interface FarmUnitOption {
    id: number;
    name: string;
}

interface Props {
    kiosk: KioskInfo;
    products: ProductRow[];
    farmUnits: FarmUnitOption[];
}

export default function Kiosk({ kiosk, products, farmUnits }: Props) {
    return (
        <AuthenticatedLayout title={kiosk.name}>
            <BackLink fallbackHref="/my-marketplace" />
            <KioskContent kiosk={kiosk} products={products} farmUnits={farmUnits} />
        </AuthenticatedLayout>
    );
}

function KioskContent({ kiosk, products, farmUnits }: Props) {
    const { dark } = useTheme();
    const [orderingId, setOrderingId] = useState<number | null>(null);
    const [quantity, setQuantity] = useState("1");
    const [paymentMethod, setPaymentMethod] = useState("bank");
    const [farmUnitId, setFarmUnitId] = useState(farmUnits[0] ? String(farmUnits[0].id) : "");
    const [submitting, setSubmitting] = useState(false);

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const placeholderBg = dark ? "#111827" : "#F3F4F6";
    const primary = "#1D9E75";

    const stars = (average: number) => "★".repeat(Math.round(average)) + "☆".repeat(5 - Math.round(average));
    const cedis = (minor: number) => `GHS ${(minor / 100).toFixed(2)}`;

    const inputStyle = {
        border: `1px solid ${dark ? "#4B5563" : "#9CA3AF"}`,
        background: dark ? "#111827" : "#FFFFFF",
        color: text,
        padding: "8px 10px",
        fontSize: "0.9375rem",
        outline: "none",
        fontFamily: "inherit",
    };

    const submitOrder = (product: ProductRow) => {
        if (!farmUnitId) return;

        setSubmitting(true);
        router.post(
            `/my-marketplace/kiosks/${kiosk.uuid}/orders`,
            {
                items: [{ kiosk_product_id: product.kiosk_product_id, quantity: Number(quantity) }],
                payment_method: paymentMethod,
                farm_unit_id: Number(farmUnitId),
            },
            {
                preserveScroll: true,
                onFinish: () => setSubmitting(false),
                onSuccess: () => setOrderingId(null),
            },
        );
    };

    return (
        <>
            <div style={{ background: surface, border: `1px solid ${border}`, padding: "20px", marginBottom: "20px" }}>
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 style={{ color: text, fontSize: "1.375rem", fontWeight: 700, margin: 0 }}>{kiosk.name}</h1>
                        {kiosk.supplier && (
                            <p style={{ color: textSecondary, fontSize: "1rem", margin: "2px 0 0" }}>{kiosk.supplier}</p>
                        )}
                    </div>
                    <span style={{ color: primary, fontWeight: 600, fontSize: "1rem" }}>{kiosk.distance_label}</span>
                </div>

                {kiosk.categories.length > 0 && (
                    <div className="flex flex-wrap gap-2 mt-3">
                        {kiosk.categories.map((category) => (
                            <span
                                key={category}
                                style={{ border: `1px solid ${border}`, color: textSecondary, fontSize: "0.9375rem", padding: "3px 10px" }}
                            >
                                {category}
                            </span>
                        ))}
                    </div>
                )}

                <div className="flex flex-wrap items-center gap-4 mt-4" style={{ fontSize: "1rem" }}>
                    {kiosk.rating_average !== null ? (
                        <span style={{ color: text }}>
                            <span style={{ color: "#B45309" }}>{stars(kiosk.rating_average)}</span>{" "}
                            {kiosk.rating_average.toFixed(1)} ({kiosk.review_count})
                        </span>
                    ) : (
                        <span style={{ color: textSecondary }}>No reviews yet</span>
                    )}
                    <span style={{ color: textSecondary }}>
                        {kiosk.sales_count} sale{kiosk.sales_count === 1 ? "" : "s"}
                    </span>
                </div>

                <a
                    href={`tel:${kiosk.contact_phone}`}
                    style={{
                        display: "inline-block",
                        marginTop: "14px",
                        background: primary,
                        color: "#FFFFFF",
                        fontWeight: 600,
                        fontSize: "1.0625rem",
                        padding: "10px 22px",
                        textDecoration: "none",
                    }}
                >
                    Call {kiosk.contact_phone}
                </a>
            </div>

            {products.length === 0 && (
                <div
                    className="px-6 py-10 text-center"
                    style={{ background: surface, border: `1px solid ${border}`, color: textSecondary, fontSize: "1.125rem" }}
                >
                    This kiosk has no products in stock right now.
                </div>
            )}

            <div className="grid gap-4" style={{ gridTemplateColumns: "repeat(auto-fill, minmax(220px, 1fr))" }}>
                {products.map((product) => (
                    <div key={product.kiosk_product_id} style={{ background: surface, border: `1px solid ${border}`, overflow: "hidden" }}>
                        {product.image_url ? (
                            <img
                                src={product.image_url}
                                alt={product.name ?? ""}
                                style={{ width: "100%", height: "140px", objectFit: "cover", display: "block" }}
                            />
                        ) : (
                            <div
                                style={{
                                    width: "100%",
                                    height: "140px",
                                    background: placeholderBg,
                                    display: "flex",
                                    alignItems: "center",
                                    justifyContent: "center",
                                    color: textSecondary,
                                    fontSize: "0.875rem",
                                }}
                            >
                                No photo
                            </div>
                        )}

                        <div className="p-3">
                            <h3 style={{ color: text, fontSize: "1.0625rem", fontWeight: 700, margin: 0 }}>{product.name}</h3>
                            <p style={{ color: primary, fontWeight: 700, fontSize: "1.0625rem", margin: "4px 0" }}>
                                {cedis(product.price_minor)}
                                {product.unit && <span style={{ color: textSecondary, fontWeight: 400 }}> / {product.unit}</span>}
                            </p>

                            {farmUnits.length > 0 && orderingId !== product.kiosk_product_id && (
                                <button
                                    onClick={() => {
                                        setOrderingId(product.kiosk_product_id);
                                        setQuantity("1");
                                        setPaymentMethod("bank");
                                    }}
                                    style={{
                                        width: "100%",
                                        background: primary,
                                        color: "#FFFFFF",
                                        fontWeight: 600,
                                        fontSize: "0.9375rem",
                                        padding: "8px 0",
                                        border: "none",
                                        cursor: "pointer",
                                        fontFamily: "inherit",
                                    }}
                                >
                                    Order
                                </button>
                            )}

                            {orderingId === product.kiosk_product_id && (
                                <div className="flex flex-col gap-2 mt-1">
                                    <div>
                                        <label style={{ display: "block", fontSize: "0.8125rem", color: textSecondary, marginBottom: "2px" }}>
                                            Quantity
                                        </label>
                                        <input
                                            type="number"
                                            min={1}
                                            value={quantity}
                                            onChange={(event) => setQuantity(event.target.value)}
                                            style={{ ...inputStyle, width: "100%" }}
                                        />
                                    </div>
                                    <div>
                                        <label style={{ display: "block", fontSize: "0.8125rem", color: textSecondary, marginBottom: "2px" }}>
                                            Payment
                                        </label>
                                        <select
                                            value={paymentMethod}
                                            onChange={(event) => setPaymentMethod(event.target.value)}
                                            style={{ ...inputStyle, width: "100%" }}
                                        >
                                            <option value="bank">Bank payment</option>
                                            <option value="cod">Cash on delivery</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label style={{ display: "block", fontSize: "0.8125rem", color: textSecondary, marginBottom: "2px" }}>
                                            Farm unit
                                        </label>
                                        <select
                                            value={farmUnitId}
                                            onChange={(event) => setFarmUnitId(event.target.value)}
                                            style={{ ...inputStyle, width: "100%" }}
                                        >
                                            {farmUnits.map((unit) => (
                                                <option key={unit.id} value={unit.id}>
                                                    {unit.name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div className="flex gap-2">
                                        <button
                                            onClick={() => submitOrder(product)}
                                            disabled={submitting}
                                            style={{
                                                flex: 1,
                                                background: primary,
                                                color: "#FFFFFF",
                                                fontWeight: 600,
                                                fontSize: "0.9375rem",
                                                padding: "8px 0",
                                                border: "none",
                                                cursor: submitting ? "not-allowed" : "pointer",
                                                fontFamily: "inherit",
                                            }}
                                        >
                                            Send
                                        </button>
                                        <button
                                            onClick={() => setOrderingId(null)}
                                            style={{
                                                background: "transparent",
                                                color: textSecondary,
                                                border: "none",
                                                fontSize: "0.9375rem",
                                                cursor: "pointer",
                                                fontFamily: "inherit",
                                            }}
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                ))}
            </div>
        </>
    );
}
