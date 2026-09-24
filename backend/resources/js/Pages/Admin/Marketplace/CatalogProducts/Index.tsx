import AdminLayout from "@/Layouts/AdminLayout";
import { useTheme } from "@/Layouts/AuthenticatedLayout";
import { router, useForm } from "@inertiajs/react";
import { FormEvent, useState } from "react";

interface CategoryOption {
    id: number;
    name: string;
}

interface UnitOption {
    id: number;
    name: string;
}

interface ProductRow {
    uuid: string;
    barcode: string | null;
    name: string;
    category: string | null;
    unit: string | null;
    seeded: boolean;
    kiosks_count: number;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props {
    products: {
        data: ProductRow[];
        links: PaginationLink[];
    };
    categories: CategoryOption[];
    units: UnitOption[];
    permissions: {
        create: boolean;
        merge: boolean;
    };
}

export default function Index({ products, categories, units, permissions }: Props) {
    return (
        <AdminLayout title="Marketplace Catalog">
            <IndexContent products={products} categories={categories} units={units} permissions={permissions} />
        </AdminLayout>
    );
}

type ContentProps = Pick<Props, "products" | "categories" | "units" | "permissions">;

function IndexContent({ products, categories, units, permissions }: ContentProps) {
    const { dark } = useTheme();
    const [mergingUuid, setMergingUuid] = useState<string | null>(null);
    const [mergeInto, setMergeInto] = useState("");

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const inputBorder = dark ? "#4B5563" : "#9CA3AF";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const primary = "#1D9E75";

    const inputStyle = {
        border: `1px solid ${inputBorder}`,
        background: dark ? "#111827" : "#FFFFFF",
        color: text,
        padding: "8px 10px",
        fontSize: "1.0625rem",
        outline: "none",
    };

    const createForm = useForm({
        barcode: "",
        name: "",
        category_id: "",
        unit_id: "",
        pack_quantity: "",
    });

    const submitCreate = (event: FormEvent) => {
        event.preventDefault();
        createForm.post(route("admin.marketplace.catalog.store"), {
            preserveScroll: true,
            onSuccess: () => createForm.reset(),
        });
    };

    const submitMerge = (duplicateUuid: string) => {
        router.patch(
            route("admin.marketplace.catalog.merge", duplicateUuid),
            { into: mergeInto },
            { preserveScroll: true, onSuccess: () => setMergingUuid(null) },
        );
    };

    return (
        <>
            {permissions.create && (
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
                            Barcode (optional)
                        </label>
                        <input
                            type="text"
                            value={createForm.data.barcode}
                            onChange={(event) => createForm.setData("barcode", event.target.value)}
                            style={{ ...inputStyle, width: "160px" }}
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
                            style={{ ...inputStyle, width: "200px" }}
                        />
                        {createForm.errors.name && (
                            <p style={{ color: "#DC2626", fontSize: "0.9375rem" }}>{createForm.errors.name}</p>
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
                        Seed catalog product
                    </button>
                </form>
            )}

            <div className="overflow-x-auto" style={{ background: surface, border: `1px solid ${border}` }}>
                <table className="min-w-full" style={{ fontSize: "1.0625rem" }}>
                    <thead>
                        <tr style={{ background: dark ? "rgba(29,158,117,0.15)" : "#EAF5F0" }}>
                            <th className="text-left px-4 py-3" style={{ color: primary, fontWeight: 700 }}>Name</th>
                            <th className="text-left px-4 py-3" style={{ color: primary, fontWeight: 700 }}>Barcode</th>
                            <th className="text-left px-4 py-3" style={{ color: primary, fontWeight: 700 }}>Category</th>
                            <th className="text-left px-4 py-3" style={{ color: primary, fontWeight: 700 }}>Unit</th>
                            <th className="text-left px-4 py-3" style={{ color: primary, fontWeight: 700 }}>Kiosks</th>
                            <th className="text-left px-4 py-3" style={{ color: primary, fontWeight: 700 }}>Source</th>
                            {permissions.merge && (
                                <th className="text-left px-4 py-3" style={{ color: primary, fontWeight: 700 }}>Merge</th>
                            )}
                        </tr>
                    </thead>
                    <tbody>
                        {products.data.length === 0 && (
                            <tr>
                                <td colSpan={permissions.merge ? 7 : 6} className="px-4 py-6 text-center" style={{ color: textSecondary }}>
                                    No catalog products yet.
                                </td>
                            </tr>
                        )}
                        {products.data.map((product) => (
                            <tr key={product.uuid} style={{ borderTop: `1px solid ${border}` }}>
                                <td className="px-4 py-3" style={{ color: text }}>{product.name}</td>
                                <td className="px-4 py-3" style={{ color: textSecondary }}>{product.barcode ?? "No barcode"}</td>
                                <td className="px-4 py-3" style={{ color: text }}>{product.category ?? "—"}</td>
                                <td className="px-4 py-3" style={{ color: text }}>{product.unit ?? "—"}</td>
                                <td className="px-4 py-3" style={{ color: text }}>{product.kiosks_count}</td>
                                <td className="px-4 py-3" style={{ color: textSecondary }}>{product.seeded ? "Seeded" : "Supplier"}</td>
                                {permissions.merge && (
                                    <td className="px-4 py-3">
                                        {mergingUuid === product.uuid ? (
                                            <span style={{ display: "flex", gap: "6px" }}>
                                                <input
                                                    type="text"
                                                    placeholder="Keeper's uuid"
                                                    value={mergeInto}
                                                    onChange={(event) => setMergeInto(event.target.value)}
                                                    style={{ ...inputStyle, width: "160px" }}
                                                />
                                                <button
                                                    onClick={() => submitMerge(product.uuid)}
                                                    style={{ color: primary, background: "transparent", border: "none", fontWeight: 600, cursor: "pointer" }}
                                                >
                                                    Confirm
                                                </button>
                                            </span>
                                        ) : (
                                            <button
                                                onClick={() => {
                                                    setMergingUuid(product.uuid);
                                                    setMergeInto("");
                                                }}
                                                style={{ color: primary, background: "transparent", border: "none", fontWeight: 600, cursor: "pointer" }}
                                            >
                                                Merge into...
                                            </button>
                                        )}
                                    </td>
                                )}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </>
    );
}
