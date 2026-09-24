import AdminLayout from "@/Layouts/AdminLayout";
import { useTheme } from "@/Layouts/AuthenticatedLayout";
import { router } from "@inertiajs/react";

interface StockImage {
    id: number;
    url: string;
}

interface StockRow {
    uuid: string;
    kiosk: string;
    name: string;
    barcode: string | null;
    price: number;
    in_stock: boolean;
    expiry_date: string | null;
    status: string;
    images: StockImage[];
}

interface SupplierInfo {
    uuid: string;
    business_name: string | null;
}

interface Props {
    supplier: SupplierInfo;
    products: StockRow[];
}

export default function Stock({ supplier, products }: Props) {
    return (
        <AdminLayout title={`${supplier.business_name} - Stock`}>
            <StockContent supplier={supplier} products={products} />
        </AdminLayout>
    );
}

type ContentProps = Pick<Props, "supplier" | "products">;

function StockContent({ supplier, products }: ContentProps) {
    const { dark } = useTheme();

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const primary = "#1D9E75";
    const amber = "#B45309";
    const danger = "#B91C1C";

    const removeImage = (productUuid: string, imageId: number) => {
        if (!window.confirm("Remove this image?")) {
            return;
        }
        router.delete(route("admin.marketplace.kiosk-products.images.destroy", [productUuid, imageId]), {
            preserveScroll: true,
        });
    };

    return (
        <>
            <p style={{ fontSize: "1.25rem", fontWeight: 700, color: text, marginBottom: "16px" }}>
                {supplier.business_name}
            </p>

            <div className="overflow-x-auto" style={{ background: surface, border: `1px solid ${border}` }}>
                <table className="min-w-full" style={{ fontSize: "1.0625rem" }}>
                    <thead>
                        <tr style={{ background: dark ? "rgba(29,158,117,0.15)" : "#EAF5F0" }}>
                            <th className="text-left px-4 py-3" style={{ color: primary, fontWeight: 700 }}>Kiosk</th>
                            <th className="text-left px-4 py-3" style={{ color: primary, fontWeight: 700 }}>Product</th>
                            <th className="text-left px-4 py-3" style={{ color: primary, fontWeight: 700 }}>Barcode</th>
                            <th className="text-left px-4 py-3" style={{ color: primary, fontWeight: 700 }}>Price</th>
                            <th className="text-left px-4 py-3" style={{ color: primary, fontWeight: 700 }}>Stock</th>
                            <th className="text-left px-4 py-3" style={{ color: primary, fontWeight: 700 }}>Expiry</th>
                            <th className="text-left px-4 py-3" style={{ color: primary, fontWeight: 700 }}>Status</th>
                            <th className="text-left px-4 py-3" style={{ color: primary, fontWeight: 700 }}>Photos</th>
                        </tr>
                    </thead>
                    <tbody>
                        {products.length === 0 && (
                            <tr>
                                <td colSpan={8} className="px-4 py-6 text-center" style={{ color: textSecondary }}>
                                    No stock listed yet.
                                </td>
                            </tr>
                        )}
                        {products.map((product) => (
                            <tr key={product.uuid} style={{ borderTop: `1px solid ${border}` }}>
                                <td className="px-4 py-3" style={{ color: text }}>{product.kiosk}</td>
                                <td className="px-4 py-3" style={{ color: text }}>{product.name}</td>
                                <td className="px-4 py-3" style={{ color: textSecondary }}>{product.barcode ?? "No barcode"}</td>
                                <td className="px-4 py-3" style={{ color: text }}>GHS {(product.price / 100).toFixed(2)}</td>
                                <td className="px-4 py-3" style={{ color: product.in_stock ? primary : amber, fontWeight: 600 }}>
                                    {product.in_stock ? "In stock" : "Out of stock"}
                                </td>
                                <td className="px-4 py-3" style={{ color: textSecondary }}>{product.expiry_date ?? "—"}</td>
                                <td className="px-4 py-3" style={{ color: text }}>{product.status}</td>
                                <td className="px-4 py-3" style={{ display: "flex", gap: "8px", flexWrap: "wrap" }}>
                                    {product.images.length === 0 && (
                                        <span style={{ color: textSecondary, fontSize: "0.9375rem" }}>None</span>
                                    )}
                                    {product.images.map((image) => (
                                        <span key={image.id} style={{ display: "flex", flexDirection: "column", alignItems: "center", gap: "2px" }}>
                                            <img src={image.url} alt="" style={{ width: "40px", height: "40px", objectFit: "cover" }} />
                                            <button
                                                onClick={() => removeImage(product.uuid, image.id)}
                                                style={{ color: danger, background: "transparent", border: "none", fontSize: "0.8125rem", cursor: "pointer" }}
                                            >
                                                Remove
                                            </button>
                                        </span>
                                    ))}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </>
    );
}
