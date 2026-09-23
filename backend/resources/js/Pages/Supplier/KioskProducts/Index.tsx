import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import { Link, router, useForm } from "@inertiajs/react";
import { FormEvent, useState } from "react";

interface CategoryOption {
    id: number;
    name: string;
    requires_expiry_date: boolean;
}

interface UnitOption {
    id: number;
    name: string;
}

interface ProductImage {
    id: number;
    url: string;
}

interface ProductData {
    uuid: string;
    name: string;
    barcode: string | null;
    category: string | null;
    unit: string | null;
    price: number;
    in_stock: boolean;
    expiry_date: string | null;
    is_expired: boolean;
    price_confirmed_at: string | null;
    images: ProductImage[];
}

interface KioskInfo {
    uuid: string;
    name: string;
}

interface Props {
    kiosk: KioskInfo;
    products: ProductData[];
    categories: CategoryOption[];
    units: UnitOption[];
}

export default function Index({ kiosk, products, categories, units }: Props) {
    return (
        <AuthenticatedLayout title={`${kiosk.name} - Products`}>
            <IndexContent kiosk={kiosk} products={products} categories={categories} units={units} />
        </AuthenticatedLayout>
    );
}

type ContentProps = Pick<Props, "kiosk" | "products" | "categories" | "units">;

function pesewasToCedis(pesewas: number): string {
    return (pesewas / 100).toFixed(2);
}

function IndexContent({ kiosk, products, categories, units }: ContentProps) {
    const { dark } = useTheme();
    const [priceEditUuid, setPriceEditUuid] = useState<string | null>(null);
    const [priceDraft, setPriceDraft] = useState("");

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const inputBorder = dark ? "#4B5563" : "#9CA3AF";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const primary = "#1D9E75";
    const amber = "#B45309";
    const danger = "#B91C1C";

    const inputStyle = {
        border: `1px solid ${inputBorder}`,
        background: dark ? "#111827" : "#FFFFFF",
        color: text,
        padding: "8px 10px",
        fontSize: "1.0625rem",
        outline: "none",
    };

    const selectedCategory = (id: string) => categories.find((c) => String(c.id) === id);

    const createForm = useForm<{
        raw_code: string;
        name: string;
        category_id: string;
        unit_id: string;
        pack_quantity: string;
        price: string;
        expiry_date: string;
        images: File[];
    }>({
        raw_code: "",
        name: "",
        category_id: "",
        unit_id: "",
        pack_quantity: "",
        price: "",
        expiry_date: "",
        images: [],
    });

    const submitCreate = (event: FormEvent) => {
        event.preventDefault();
        createForm.post(route("supplier.kiosks.products.store", kiosk.uuid), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => createForm.reset(),
        });
    };

    const requiresExpiry = selectedCategory(createForm.data.category_id)?.requires_expiry_date ?? false;

    const startPriceEdit = (product: ProductData) => {
        setPriceEditUuid(product.uuid);
        setPriceDraft(pesewasToCedis(product.price));
    };

    const savePrice = (uuid: string) => {
        const pesewas = Math.round(parseFloat(priceDraft || "0") * 100);
        router.patch(
            route("supplier.kiosks.products.price", [kiosk.uuid, uuid]),
            { price: pesewas },
            { preserveScroll: true, onSuccess: () => setPriceEditUuid(null) },
        );
    };

    const confirmPrice = (uuid: string) => {
        router.post(route("supplier.kiosks.products.confirm-price", [kiosk.uuid, uuid]), {}, { preserveScroll: true });
    };

    const toggleStock = (product: ProductData) => {
        router.patch(
            route("supplier.kiosks.products.stock", [kiosk.uuid, product.uuid]),
            { in_stock: !product.in_stock },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <p style={{ fontSize: "1.25rem", fontWeight: 700, color: text, marginBottom: "16px" }}>
                {kiosk.name}
            </p>

            <form
                onSubmit={submitCreate}
                className="mb-6"
                style={{
                    background: surface,
                    border: `1px solid ${border}`,
                    padding: "20px",
                    display: "flex",
                    gap: "12px",
                    alignItems: "flex-end",
                    flexWrap: "wrap",
                }}
            >
                <div>
                    <label style={{ display: "block", fontSize: "1rem", fontWeight: 600, color: text, marginBottom: "4px" }}>
                        Barcode or QR (optional)
                    </label>
                    <input
                        type="text"
                        value={createForm.data.raw_code}
                        onChange={(event) => createForm.setData("raw_code", event.target.value)}
                        style={{ ...inputStyle, width: "180px" }}
                    />
                </div>

                <div>
                    <label style={{ display: "block", fontSize: "1rem", fontWeight: 600, color: text, marginBottom: "4px" }}>
                        Name
                    </label>
                    <input
                        type="text"
                        value={createForm.data.name}
                        onChange={(event) => createForm.setData("name", event.target.value)}
                        style={{ ...inputStyle, width: "180px" }}
                    />
                    {createForm.errors.name && (
                        <p style={{ color: danger, fontSize: "0.9375rem" }}>{createForm.errors.name}</p>
                    )}
                </div>

                <div>
                    <label style={{ display: "block", fontSize: "1rem", fontWeight: 600, color: text, marginBottom: "4px" }}>
                        Category
                    </label>
                    <select
                        value={createForm.data.category_id}
                        onChange={(event) => createForm.setData("category_id", event.target.value)}
                        style={{ ...inputStyle, width: "160px" }}
                    >
                        <option value="">No category</option>
                        {categories.map((category) => (
                            <option key={category.id} value={category.id}>
                                {category.name}
                            </option>
                        ))}
                    </select>
                </div>

                <div>
                    <label style={{ display: "block", fontSize: "1rem", fontWeight: 600, color: text, marginBottom: "4px" }}>
                        Unit
                    </label>
                    <select
                        value={createForm.data.unit_id}
                        onChange={(event) => createForm.setData("unit_id", event.target.value)}
                        style={{ ...inputStyle, width: "140px" }}
                    >
                        <option value="">No unit</option>
                        {units.map((unit) => (
                            <option key={unit.id} value={unit.id}>
                                {unit.name}
                            </option>
                        ))}
                    </select>
                </div>

                <div>
                    <label style={{ display: "block", fontSize: "1rem", fontWeight: 600, color: text, marginBottom: "4px" }}>
                        Price (GHS)
                    </label>
                    <input
                        type="number"
                        step="0.01"
                        min="0.01"
                        value={createForm.data.price ? Number(createForm.data.price) / 100 : ""}
                        onChange={(event) =>
                            createForm.setData("price", String(Math.round(parseFloat(event.target.value || "0") * 100)))
                        }
                        style={{ ...inputStyle, width: "120px" }}
                    />
                    {createForm.errors.price && (
                        <p style={{ color: danger, fontSize: "0.9375rem" }}>{createForm.errors.price}</p>
                    )}
                </div>

                <div>
                    <label style={{ display: "block", fontSize: "1rem", fontWeight: 600, color: text, marginBottom: "4px" }}>
                        Expiry date{requiresExpiry ? " (required)" : " (optional)"}
                    </label>
                    <input
                        type="date"
                        value={createForm.data.expiry_date}
                        onChange={(event) => createForm.setData("expiry_date", event.target.value)}
                        style={{ ...inputStyle, width: "160px" }}
                    />
                    {createForm.errors.expiry_date && (
                        <p style={{ color: danger, fontSize: "0.9375rem" }}>{createForm.errors.expiry_date}</p>
                    )}
                </div>

                <div>
                    <label style={{ display: "block", fontSize: "1rem", fontWeight: 600, color: text, marginBottom: "4px" }}>
                        Photos (up to 3)
                    </label>
                    <input
                        type="file"
                        accept="image/*"
                        multiple
                        onChange={(event) => createForm.setData("images", Array.from(event.target.files ?? []))}
                    />
                    {createForm.errors.images && (
                        <p style={{ color: danger, fontSize: "0.9375rem" }}>{createForm.errors.images}</p>
                    )}
                </div>

                <button
                    type="submit"
                    disabled={createForm.processing}
                    style={{
                        background: primary,
                        color: "#FFFFFF",
                        border: "none",
                        padding: "9px 18px",
                        fontSize: "1.0625rem",
                        fontWeight: 600,
                        cursor: createForm.processing ? "not-allowed" : "pointer",
                    }}
                >
                    Add product
                </button>
            </form>

            <div className="overflow-x-auto" style={{ background: surface, border: `1px solid ${border}` }}>
                <table className="min-w-full" style={{ fontSize: "1.0625rem" }}>
                    <thead>
                        <tr style={{ background: dark ? "rgba(29,158,117,0.15)" : "#EAF5F0" }}>
                            <th className="text-left px-4 py-3" style={{ color: primary, fontWeight: 700 }}>Name</th>
                            <th className="text-left px-4 py-3" style={{ color: primary, fontWeight: 700 }}>Barcode</th>
                            <th className="text-left px-4 py-3" style={{ color: primary, fontWeight: 700 }}>Price</th>
                            <th className="text-left px-4 py-3" style={{ color: primary, fontWeight: 700 }}>Stock</th>
                            <th className="text-left px-4 py-3" style={{ color: primary, fontWeight: 700 }}>Expiry</th>
                            <th className="text-left px-4 py-3" style={{ color: primary, fontWeight: 700 }}>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        {products.length === 0 && (
                            <tr>
                                <td colSpan={6} className="px-4 py-6 text-center" style={{ color: textSecondary }}>
                                    No products yet.
                                </td>
                            </tr>
                        )}
                        {products.map((product) => (
                            <tr key={product.uuid} style={{ borderTop: `1px solid ${border}` }}>
                                <td className="px-4 py-3" style={{ color: text }}>
                                    {product.name}
                                    {product.is_expired && (
                                        <span style={{ color: danger, marginLeft: "8px", fontSize: "0.9375rem" }}>
                                            (expired)
                                        </span>
                                    )}
                                </td>
                                <td className="px-4 py-3" style={{ color: textSecondary }}>
                                    {product.barcode ?? "No barcode"}
                                </td>
                                <td className="px-4 py-3" style={{ color: text }}>
                                    {priceEditUuid === product.uuid ? (
                                        <span style={{ display: "flex", gap: "6px" }}>
                                            <input
                                                type="number"
                                                step="0.01"
                                                value={priceDraft}
                                                onChange={(event) => setPriceDraft(event.target.value)}
                                                style={{ ...inputStyle, width: "90px" }}
                                            />
                                            <button
                                                onClick={() => savePrice(product.uuid)}
                                                style={{ color: primary, background: "transparent", border: "none", fontWeight: 600, cursor: "pointer" }}
                                            >
                                                Save
                                            </button>
                                        </span>
                                    ) : (
                                        <>
                                            GHS {pesewasToCedis(product.price)}
                                            <button
                                                onClick={() => startPriceEdit(product)}
                                                style={{ color: primary, background: "transparent", border: "none", fontWeight: 600, cursor: "pointer", marginLeft: "8px" }}
                                            >
                                                Change
                                            </button>
                                            <button
                                                onClick={() => confirmPrice(product.uuid)}
                                                style={{ color: textSecondary, background: "transparent", border: "none", cursor: "pointer", marginLeft: "8px" }}
                                            >
                                                Price unchanged
                                            </button>
                                        </>
                                    )}
                                </td>
                                <td className="px-4 py-3">
                                    <button
                                        onClick={() => toggleStock(product)}
                                        style={{
                                            color: product.in_stock ? primary : amber,
                                            background: "transparent",
                                            border: "none",
                                            fontWeight: 600,
                                            cursor: "pointer",
                                        }}
                                    >
                                        {product.in_stock ? "In stock" : "Out of stock"}
                                    </button>
                                </td>
                                <td className="px-4 py-3" style={{ color: textSecondary }}>
                                    {product.expiry_date ?? "—"}
                                </td>
                                <td className="px-4 py-3">
                                    {product.images.length > 0 && (
                                        <span style={{ color: textSecondary, fontSize: "0.9375rem" }}>
                                            {product.images.length} photo{product.images.length > 1 ? "s" : ""}
                                        </span>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <Link
                href={route("supplier.kiosks.index")}
                style={{ display: "inline-block", marginTop: "16px", color: primary, fontWeight: 600 }}
            >
                ← Back to kiosks
            </Link>
        </>
    );
}
