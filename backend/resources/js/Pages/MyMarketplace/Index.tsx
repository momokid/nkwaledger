import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import { PageProps } from "@/types";
import { Link, router } from "@inertiajs/react";
import { useState } from "react";

interface KioskProductOption {
    kiosk_product_id: number;
    name: string | null;
    price_minor: number;
}

interface KioskCard {
    uuid: string;
    name: string;
    supplier: string | null;
    contact_phone: string;
    distance_label: string;
    categories: string[];
    products: KioskProductOption[];
    rating_average: number | null;
    review_count: number;
    sales_count: number;
    matches_farm_type: boolean;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface CategoryOption {
    id: number;
    name: string;
}

interface FarmUnitOption {
    id: number;
    name: string;
}

interface Filters {
    category?: string;
}

interface Props extends PageProps {
    kiosks: { data: KioskCard[]; links: PaginationLink[] };
    categories: CategoryOption[];
    filters: Filters;
    farmUnits: FarmUnitOption[];
}

export default function Index(props: Props) {
    return (
        <AuthenticatedLayout title="Market Center">
            <IndexContent
                kiosks={props.kiosks}
                categories={props.categories}
                filters={props.filters}
                farmUnits={props.farmUnits}
            />
        </AuthenticatedLayout>
    );
}

type ContentProps = Pick<Props, "kiosks" | "categories" | "filters" | "farmUnits">;

function IndexContent({ kiosks, categories, filters, farmUnits }: ContentProps) {
    const { dark } = useTheme();
    const [orderingUuid, setOrderingUuid] = useState<string | null>(null);
    const [productId, setProductId] = useState("");
    const [quantity, setQuantity] = useState("1");
    const [paymentMethod, setPaymentMethod] = useState("bank");
    const [farmUnitId, setFarmUnitId] = useState(farmUnits[0] ? String(farmUnits[0].id) : "");
    const [submitting, setSubmitting] = useState(false);

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const primary = "#1D9E75";

    const goToCategory = (categoryId: string | undefined) => {
        router.get("/my-marketplace", categoryId ? { category: categoryId } : {}, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const visit = (url: string | null) => {
        if (!url) return;
        router.visit(url, { preserveScroll: true });
    };

    const chipStyle = (active: boolean) => ({
        padding: "8px 16px",
        border: `1px solid ${active ? primary : border}`,
        background: active ? primary : "transparent",
        color: active ? "#FFFFFF" : text,
        fontWeight: 600,
        fontSize: "1rem",
        cursor: "pointer",
        fontFamily: "inherit",
    });

    const stars = (average: number) => "★".repeat(Math.round(average)) + "☆".repeat(5 - Math.round(average));

    const inputStyle = {
        border: `1px solid ${dark ? "#4B5563" : "#9CA3AF"}`,
        background: dark ? "#111827" : "#FFFFFF",
        color: text,
        padding: "8px 10px",
        fontSize: "1rem",
        outline: "none",
        fontFamily: "inherit",
    };

    const openOrderForm = (kiosk: KioskCard) => {
        setOrderingUuid(kiosk.uuid);
        setProductId(kiosk.products[0] ? String(kiosk.products[0].kiosk_product_id) : "");
        setQuantity("1");
        setPaymentMethod("bank");
    };

    const submitOrder = (kioskUuid: string) => {
        if (!productId || !farmUnitId) return;

        setSubmitting(true);
        router.post(
            `/my-marketplace/kiosks/${kioskUuid}/orders`,
            {
                items: [{ kiosk_product_id: Number(productId), quantity: Number(quantity) }],
                payment_method: paymentMethod,
                farm_unit_id: Number(farmUnitId),
            },
            {
                preserveScroll: true,
                onFinish: () => setSubmitting(false),
                onSuccess: () => setOrderingUuid(null),
            },
        );
    };

    return (
        <>
            <Link href="/my-marketplace/orders" style={{ display: "inline-block", color: primary, fontWeight: 600, marginBottom: "14px" }}>
                My Orders →
            </Link>

            <div className="flex flex-wrap gap-2 mb-5">
                <button onClick={() => goToCategory(undefined)} style={chipStyle(!filters.category)}>
                    All
                </button>
                {categories.map((category) => (
                    <button
                        key={category.id}
                        onClick={() => goToCategory(String(category.id))}
                        style={chipStyle(filters.category === String(category.id))}
                    >
                        {category.name}
                    </button>
                ))}
            </div>

            {kiosks.data.length === 0 && (
                <div
                    className="px-6 py-10 text-center"
                    style={{ background: surface, border: `1px solid ${border}`, color: textSecondary, fontSize: "1.125rem" }}
                >
                    No kiosks found nearby. Try a different category.
                </div>
            )}

            <div className="flex flex-col gap-4">
                {kiosks.data.map((kiosk) => (
                    <div key={kiosk.uuid} style={{ background: surface, border: `1px solid ${border}`, padding: "18px" }}>
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 style={{ color: text, fontSize: "1.1875rem", fontWeight: 700, margin: 0 }}>
                                    {kiosk.name}
                                </h3>
                                {kiosk.supplier && (
                                    <p style={{ color: textSecondary, fontSize: "1rem", margin: "2px 0 0" }}>
                                        {kiosk.supplier}
                                    </p>
                                )}
                            </div>
                            <span style={{ color: primary, fontWeight: 600, fontSize: "1rem" }}>
                                {kiosk.distance_label}
                            </span>
                        </div>

                        {kiosk.categories.length > 0 && (
                            <div className="flex flex-wrap gap-2 mt-3">
                                {kiosk.categories.map((category) => (
                                    <span
                                        key={category}
                                        style={{
                                            border: `1px solid ${border}`,
                                            color: textSecondary,
                                            fontSize: "0.9375rem",
                                            padding: "3px 10px",
                                        }}
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

                        <div className="flex flex-wrap gap-3 mt-4">
                            <a
                                href={`tel:${kiosk.contact_phone}`}
                                style={{
                                    display: "inline-block",
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

                            {kiosk.products.length > 0 && farmUnits.length > 0 && (
                                <button
                                    onClick={() => openOrderForm(kiosk)}
                                    style={{
                                        background: "transparent",
                                        color: primary,
                                        border: `1px solid ${primary}`,
                                        fontWeight: 600,
                                        fontSize: "1.0625rem",
                                        padding: "9px 22px",
                                        cursor: "pointer",
                                        fontFamily: "inherit",
                                    }}
                                >
                                    Order
                                </button>
                            )}
                        </div>

                        {orderingUuid === kiosk.uuid && (
                            <div
                                className="flex flex-wrap items-end gap-3 mt-4"
                                style={{ borderTop: `1px solid ${border}`, paddingTop: "14px" }}
                            >
                                <div>
                                    <label style={{ display: "block", fontSize: "0.9375rem", color: textSecondary, marginBottom: "4px" }}>
                                        Product
                                    </label>
                                    <select value={productId} onChange={(event) => setProductId(event.target.value)} style={inputStyle}>
                                        {kiosk.products.map((product) => (
                                            <option key={product.kiosk_product_id} value={product.kiosk_product_id}>
                                                {product.name} (GHS {(product.price_minor / 100).toFixed(2)})
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label style={{ display: "block", fontSize: "0.9375rem", color: textSecondary, marginBottom: "4px" }}>
                                        Quantity
                                    </label>
                                    <input
                                        type="number"
                                        min={1}
                                        value={quantity}
                                        onChange={(event) => setQuantity(event.target.value)}
                                        style={{ ...inputStyle, width: "80px" }}
                                    />
                                </div>

                                <div>
                                    <label style={{ display: "block", fontSize: "0.9375rem", color: textSecondary, marginBottom: "4px" }}>
                                        Payment
                                    </label>
                                    <select value={paymentMethod} onChange={(event) => setPaymentMethod(event.target.value)} style={inputStyle}>
                                        <option value="bank">Bank payment</option>
                                        <option value="cod">Cash on delivery</option>
                                    </select>
                                </div>

                                <div>
                                    <label style={{ display: "block", fontSize: "0.9375rem", color: textSecondary, marginBottom: "4px" }}>
                                        Farm unit
                                    </label>
                                    <select value={farmUnitId} onChange={(event) => setFarmUnitId(event.target.value)} style={inputStyle}>
                                        {farmUnits.map((unit) => (
                                            <option key={unit.id} value={unit.id}>
                                                {unit.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <button
                                    onClick={() => submitOrder(kiosk.uuid)}
                                    disabled={submitting}
                                    style={{
                                        background: primary,
                                        color: "#FFFFFF",
                                        fontWeight: 600,
                                        fontSize: "1rem",
                                        padding: "9px 20px",
                                        border: "none",
                                        cursor: submitting ? "not-allowed" : "pointer",
                                        fontFamily: "inherit",
                                    }}
                                >
                                    Send order
                                </button>

                                <button
                                    onClick={() => setOrderingUuid(null)}
                                    style={{
                                        background: "transparent",
                                        color: textSecondary,
                                        border: "none",
                                        fontSize: "1rem",
                                        cursor: "pointer",
                                        fontFamily: "inherit",
                                    }}
                                >
                                    Cancel
                                </button>
                            </div>
                        )}
                    </div>
                ))}
            </div>

            {kiosks.links.length > 3 && (
                <div className="flex flex-wrap gap-2 mt-5">
                    {kiosks.links.map((link) => (
                        <button
                            key={link.label}
                            onClick={() => visit(link.url)}
                            disabled={!link.url}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                            style={{
                                padding: "8px 14px",
                                fontSize: "1rem",
                                border: `1px solid ${link.active ? primary : border}`,
                                background: link.active ? primary : surface,
                                color: link.active ? "#FFFFFF" : link.url ? text : textSecondary,
                                cursor: link.url ? "pointer" : "not-allowed",
                                fontFamily: "inherit",
                            }}
                        />
                    ))}
                </div>
            )}
        </>
    );
}
