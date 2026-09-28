import AdminLayout from "@/Layouts/AdminLayout";
import { useTheme } from "@/Layouts/AuthenticatedLayout";
import { router, useForm } from "@inertiajs/react";
import { FormEvent, useState } from "react";

interface CategoryData {
    id: number;
    name: string;
    slug: string;
    display_count: number;
    sort_order: number;
    is_active: boolean;
    kiosk_products_count: number;
    produce_listings_count: number;
}

interface Props {
    categories: CategoryData[];
    permissions: { create: boolean; update: boolean; delete: boolean };
}

export default function Index({ categories, permissions }: Props) {
    return (
        <AdminLayout title="Marketplace Categories">
            <IndexContent categories={categories} permissions={permissions} />
        </AdminLayout>
    );
}

function IndexContent({ categories, permissions }: Props) {
    const { dark } = useTheme();
    const [editingId, setEditingId] = useState<number | null>(null);
    const [editName, setEditName] = useState("");
    const [editDisplayCount, setEditDisplayCount] = useState("8");
    const [editActive, setEditActive] = useState(true);

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const headerBg = dark ? "rgba(29,158,117,0.15)" : "#EAF5F0";
    const headerText = "#1D9E75";
    const primary = "#1D9E75";

    const inputStyle = {
        border: `1px solid ${dark ? "#4B5563" : "#9CA3AF"}`,
        background: dark ? "#111827" : "#FFFFFF",
        color: text,
        padding: "6px 8px",
        fontSize: "0.9375rem",
        outline: "none",
        fontFamily: "inherit",
    };

    const createForm = useForm({ name: "", display_count: "8" });

    const submitCreate = (event: FormEvent) => {
        event.preventDefault();
        createForm.post(route("admin.marketplace.categories.store"), {
            preserveScroll: true,
            onSuccess: () => createForm.reset(),
        });
    };

    const startEdit = (category: CategoryData) => {
        setEditingId(category.id);
        setEditName(category.name);
        setEditDisplayCount(String(category.display_count));
        setEditActive(category.is_active);
    };

    const saveEdit = (id: number) => {
        router.put(
            route("admin.marketplace.categories.update", id),
            { name: editName, display_count: editDisplayCount, is_active: editActive },
            { preserveScroll: true, onSuccess: () => setEditingId(null) },
        );
    };

    const destroy = (id: number, name: string) => {
        if (!window.confirm(`Delete "${name}"? This cannot be undone from here.`)) return;
        router.delete(route("admin.marketplace.categories.destroy", id), { preserveScroll: true });
    };

    const move = (index: number, direction: -1 | 1) => {
        const ids = categories.map((c) => c.id);
        const target = index + direction;
        if (target < 0 || target >= ids.length) return;
        [ids[index], ids[target]] = [ids[target], ids[index]];
        router.post(route("admin.marketplace.categories.reorder"), { ids }, { preserveScroll: true });
    };

    return (
        <>
            <h1 style={{ color: text, fontSize: "1.375rem", fontWeight: 700, margin: "0 0 6px" }}>Marketplace Categories</h1>
            <p style={{ color: textSecondary, fontSize: "0.9375rem", margin: "0 0 16px" }}>
                The rows shown on the Market Center homepage, in this order. Each spans both kiosk products and farmer produce.
            </p>

            {permissions.create && (
                <form onSubmit={submitCreate} className="flex gap-2 items-end mb-5" style={{ background: surface, border: `1px solid ${border}`, padding: "12px" }}>
                    <div>
                        <label style={{ display: "block", fontSize: "0.8125rem", color: textSecondary }}>Name</label>
                        <input
                            value={createForm.data.name}
                            onChange={(e) => createForm.setData("name", e.target.value)}
                            style={inputStyle}
                        />
                        {createForm.errors.name && <p style={{ color: "#B91C1C", fontSize: "0.8125rem" }}>{createForm.errors.name}</p>}
                    </div>
                    <div>
                        <label style={{ display: "block", fontSize: "0.8125rem", color: textSecondary }}>Items shown</label>
                        <input
                            type="number"
                            min={1}
                            max={50}
                            value={createForm.data.display_count}
                            onChange={(e) => createForm.setData("display_count", e.target.value)}
                            style={{ ...inputStyle, width: "80px" }}
                        />
                    </div>
                    <button
                        type="submit"
                        disabled={createForm.processing}
                        style={{ background: primary, color: "#FFFFFF", fontWeight: 600, padding: "7px 16px", border: "none", cursor: "pointer", fontFamily: "inherit" }}
                    >
                        Add category
                    </button>
                </form>
            )}

            <div style={{ background: surface, border: `1px solid ${border}`, overflowX: "auto" }}>
                <table className="w-full" style={{ borderCollapse: "collapse" }}>
                    <thead>
                        <tr style={{ background: headerBg }}>
                            {["Order", "Name", "Items shown", "Kiosk products", "Produce listings", "Active", "Actions"].map((label) => (
                                <th key={label} className="px-4 py-3" style={{ textAlign: "left", color: headerText, fontSize: "0.8125rem" }}>
                                    {label}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {categories.map((category, index) => (
                            <tr key={category.id} style={{ borderTop: `1px solid ${border}` }}>
                                <td className="px-4 py-3">
                                    <div className="flex gap-1">
                                        <button onClick={() => move(index, -1)} disabled={index === 0} style={{ cursor: index === 0 ? "default" : "pointer" }}>
                                            ↑
                                        </button>
                                        <button onClick={() => move(index, 1)} disabled={index === categories.length - 1} style={{ cursor: index === categories.length - 1 ? "default" : "pointer" }}>
                                            ↓
                                        </button>
                                    </div>
                                </td>
                                {editingId === category.id ? (
                                    <>
                                        <td className="px-4 py-3">
                                            <input value={editName} onChange={(e) => setEditName(e.target.value)} style={inputStyle} />
                                        </td>
                                        <td className="px-4 py-3">
                                            <input
                                                type="number"
                                                min={1}
                                                max={50}
                                                value={editDisplayCount}
                                                onChange={(e) => setEditDisplayCount(e.target.value)}
                                                style={{ ...inputStyle, width: "70px" }}
                                            />
                                        </td>
                                        <td className="px-4 py-3" style={{ color: textSecondary }}>{category.kiosk_products_count}</td>
                                        <td className="px-4 py-3" style={{ color: textSecondary }}>{category.produce_listings_count}</td>
                                        <td className="px-4 py-3">
                                            <input type="checkbox" checked={editActive} onChange={(e) => setEditActive(e.target.checked)} />
                                        </td>
                                        <td className="px-4 py-3">
                                            <button onClick={() => saveEdit(category.id)} style={{ color: primary, marginRight: "10px", cursor: "pointer" }}>
                                                Save
                                            </button>
                                            <button onClick={() => setEditingId(null)} style={{ color: textSecondary, cursor: "pointer" }}>
                                                Cancel
                                            </button>
                                        </td>
                                    </>
                                ) : (
                                    <>
                                        <td className="px-4 py-3" style={{ color: text }}>{category.name}</td>
                                        <td className="px-4 py-3" style={{ color: textSecondary }}>{category.display_count}</td>
                                        <td className="px-4 py-3" style={{ color: textSecondary }}>{category.kiosk_products_count}</td>
                                        <td className="px-4 py-3" style={{ color: textSecondary }}>{category.produce_listings_count}</td>
                                        <td className="px-4 py-3" style={{ color: category.is_active ? primary : textSecondary, fontWeight: 600 }}>
                                            {category.is_active ? "Yes" : "No"}
                                        </td>
                                        <td className="px-4 py-3">
                                            {permissions.update && (
                                                <button onClick={() => startEdit(category)} style={{ color: primary, marginRight: "10px", cursor: "pointer" }}>
                                                    Edit
                                                </button>
                                            )}
                                            {permissions.delete && (
                                                <button onClick={() => destroy(category.id, category.name)} style={{ color: "#B91C1C", cursor: "pointer" }}>
                                                    Delete
                                                </button>
                                            )}
                                        </td>
                                    </>
                                )}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </>
    );
}
