import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import { PageProps } from "@/types";
import { Link, router } from "@inertiajs/react";
import { useState } from "react";

interface ProductCard {
    kiosk_uuid: string;
    kiosk_name: string;
    distance_label: string;
    contact_phone: string;
    kiosk_product_id: number;
    product_name: string | null;
    price_minor: number;
    unit: string | null;
    image_url: string | null;
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
    products: { data: ProductCard[]; links: PaginationLink[] };
    categories: CategoryOption[];
    filters: Filters;
    farmUnits: FarmUnitOption[];
}

export default function Index(props: Props) {
    return (
        <AuthenticatedLayout title="Market Center">
            <IndexContent
                products={props.products}
                categories={props.categories}
                filters={props.filters}
                farmUnits={props.farmUnits}
            />
        </AuthenticatedLayout>
    );
}

type ContentProps = Pick<Props, "products" | "categories" | "filters" | "farmUnits">;

function IndexContent({ products, categories, filters, farmUnits }: ContentProps) {
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

    const inputStyle = {
        border: `1px solid ${dark ? "#4B5563" : "#9CA3AF"}`,
        background: dark ? "#111827" : "#FFFFFF",
        color: text,
        padding: "8px 10px",
        fontSize: "0.9375rem",
        outline: "none",
        fontFamily: "inherit",
    };

    const cedis = (minor: number) => `GHS ${(minor / 100).toFixed(2)}`;

    const openOrderForm = (product: ProductCard) => {
        setOrderingId(product.kiosk_product_id);
        setQuantity("1");
        setPaymentMethod("bank");
    };

    const submitOrder = (product: ProductCard) => {
        if (!farmUnitId) return;

        setSubmitting(true);
        router.post(
            `/my-marketplace/kiosks/${product.kiosk_uuid}/orders`,
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

            {products.data.length === 0 && (
                <div
                    className="px-6 py-10 text-center"
                    style={{ background: surface, border: `1px solid ${border}`, color: textSecondary, fontSize: "1.125rem" }}
                >
                    No products found nearby. Try a different category.
                </div>
            )}

            <div className="grid gap-4" style={{ gridTemplateColumns: "repeat(auto-fill, minmax(220px, 1fr))" }}>
                {products.data.map((product) => (
                    <div key={product.kiosk_product_id} style={{ background: surface, border: `1px solid ${border}`, overflow: "hidden" }}>
                        <Link href={`/my-marketplace/kiosks/${product.kiosk_uuid}`}>
                            {product.image_url ? (
                                <img
                                    src={product.image_url}
                                    alt={product.product_name ?? ""}
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
                        </Link>

                        <div className="p-3">
                            <h3 style={{ color: text, fontSize: "1.0625rem", fontWeight: 700, margin: 0 }}>
                                {product.product_name}
                            </h3>
                            <p style={{ color: primary, fontWeight: 700, fontSize: "1.0625rem", margin: "4px 0" }}>
                                {cedis(product.price_minor)}
                                {product.unit && <span style={{ color: textSecondary, fontWeight: 400 }}> / {product.unit}</span>}
                            </p>
                            <Link
                                href={`/my-marketplace/kiosks/${product.kiosk_uuid}`}
                                style={{ display: "block", color: textSecondary, fontSize: "0.9375rem" }}
                            >
                                {product.kiosk_name} · {product.distance_label}
                            </Link>

                            {farmUnits.length > 0 && (
                                <button
                                    onClick={() => openOrderForm(product)}
                                    style={{
                                        marginTop: "10px",
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
                                <div className="flex flex-col gap-2 mt-3" style={{ borderTop: `1px solid ${border}`, paddingTop: "10px" }}>
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

            {products.links.length > 3 && (
                <div className="flex flex-wrap gap-2 mt-5">
                    {products.links.map((link) => (
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
