import AdminLayout from "@/Layouts/AdminLayout";
import { useTheme } from "@/Layouts/AuthenticatedLayout";
import { router, useForm, usePage } from "@inertiajs/react";
import { PageProps } from "@/types";
import { FormEvent, useState } from "react";

interface FarmTypeCategoryData {
    id: number;
    name: string;
    is_active: boolean;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props extends PageProps {
    categories: {
        data: FarmTypeCategoryData[];
        links: PaginationLink[];
    };
    permissions: {
        create: boolean;
        update: boolean;
        delete: boolean;
    };
}

export default function Index({ categories, permissions }: Props) {
    return (
        <AdminLayout title="Farm Type Categories">
            <IndexContent categories={categories} permissions={permissions} />
        </AdminLayout>
    );
}

function IndexContent({ categories, permissions }: Props) {
    const { errors } = usePage<Props>().props;
    const { dark } = useTheme();
    const [editingId, setEditingId] = useState<number | null>(null);
    const [editName, setEditName] = useState("");
    const [editActive, setEditActive] = useState(true);

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const headerBg = dark ? "rgba(29,158,117,0.15)" : "#EAF5F0";
    const headerText = "#1D9E75";
    const rowAlt = dark ? "#111827" : "#F9FAFB";

    const createForm = useForm({ name: "" });

    const submitCreate = (event: FormEvent) => {
        event.preventDefault();
        createForm.post(route("admin.farm-type-categories.store"), {
            preserveScroll: true,
            onSuccess: () => createForm.reset("name"),
        });
    };

    const startEdit = (category: FarmTypeCategoryData) => {
        setEditingId(category.id);
        setEditName(category.name);
        setEditActive(category.is_active);
    };

    const cancelEdit = () => {
        setEditingId(null);
    };

    const saveEdit = (id: number) => {
        router.put(
            route("admin.farm-type-categories.update", id),
            { name: editName, is_active: editActive },
            { preserveScroll: true, onSuccess: () => setEditingId(null) },
        );
    };

    const destroy = (id: number, name: string) => {
        if (
            !window.confirm(
                `Delete "${name}"? This cannot be undone from here.`,
            )
        ) {
            return;
        }
        router.delete(route("admin.farm-type-categories.destroy", id), {
            preserveScroll: true,
        });
    };

    const hasActions = permissions.update || permissions.delete;

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
                        <label
                            style={{
                                display: "block",
                                fontSize: "15px",
                                fontWeight: 600,
                                color: text,
                                marginBottom: "6px",
                            }}
                        >
                            New category name
                        </label>
                        <input
                            type="text"
                            value={createForm.data.name}
                            onChange={(event) =>
                                createForm.setData("name", event.target.value)
                            }
                            placeholder="e.g. Aquatic"
                            style={{
                                width: "260px",
                                border: `1px solid ${dark ? "#4B5563" : "#9CA3AF"}`,
                                background: dark ? "#111827" : "#FFFFFF",
                                color: text,
                                padding: "10px 12px",
                                fontSize: "17px",
                                outline: "none",
                                fontFamily: "inherit",
                            }}
                        />
                        {(errors?.name || createForm.errors.name) && (
                            <p
                                style={{
                                    color: "#DC2626",
                                    fontSize: "14px",
                                    marginTop: "4px",
                                }}
                            >
                                {errors?.name ?? createForm.errors.name}
                            </p>
                        )}
                    </div>
                    <button
                        type="submit"
                        disabled={createForm.processing}
                        style={{
                            background: "#1D9E75",
                            color: "#FFFFFF",
                            border: "none",
                            padding: "10px 20px",
                            fontSize: "17px",
                            fontWeight: 600,
                            cursor: createForm.processing
                                ? "not-allowed"
                                : "pointer",
                            opacity: createForm.processing ? 0.7 : 1,
                        }}
                    >
                        Add category
                    </button>
                </form>
            )}

            <div
                className="overflow-x-auto"
                style={{ background: surface, border: `1px solid ${border}` }}
            >
                <table className="min-w-full" style={{ fontSize: "17px" }}>
                    <thead>
                        <tr style={{ background: headerBg }}>
                            <th
                                className="text-left px-4 py-3"
                                style={{ color: headerText, fontWeight: 700 }}
                            >
                                Name
                            </th>
                            <th
                                className="text-left px-4 py-3"
                                style={{ color: headerText, fontWeight: 700 }}
                            >
                                Status
                            </th>
                            {hasActions && (
                                <th
                                    className="text-left px-4 py-3"
                                    style={{
                                        color: headerText,
                                        fontWeight: 700,
                                    }}
                                >
                                    Actions
                                </th>
                            )}
                        </tr>
                    </thead>
                    <tbody>
                        {categories.data.length === 0 && (
                            <tr>
                                <td
                                    colSpan={hasActions ? 3 : 2}
                                    className="px-4 py-6 text-center"
                                    style={{ color: textSecondary }}
                                >
                                    No categories yet.
                                </td>
                            </tr>
                        )}

                        {categories.data.map((category, index) => {
                            const isEditing = editingId === category.id;

                            return (
                                <tr
                                    key={category.id}
                                    style={{
                                        borderTop: `1px solid ${border}`,
                                        background:
                                            index % 2 === 1
                                                ? rowAlt
                                                : "transparent",
                                    }}
                                >
                                    <td
                                        className="px-4 py-3"
                                        style={{ color: text }}
                                    >
                                        {isEditing ? (
                                            <input
                                                type="text"
                                                value={editName}
                                                onChange={(event) =>
                                                    setEditName(
                                                        event.target.value,
                                                    )
                                                }
                                                style={{
                                                    width: "100%",
                                                    border: `1px solid ${dark ? "#4B5563" : "#9CA3AF"}`,
                                                    background: dark
                                                        ? "#111827"
                                                        : "#FFFFFF",
                                                    color: text,
                                                    padding: "6px 8px",
                                                    fontSize: "17px",
                                                    outline: "none",
                                                    fontFamily: "inherit",
                                                }}
                                            />
                                        ) : (
                                            category.name
                                        )}
                                    </td>
                                    <td
                                        className="px-4 py-3"
                                        style={{ color: text }}
                                    >
                                        {isEditing ? (
                                            <label
                                                style={{
                                                    display: "flex",
                                                    alignItems: "center",
                                                    gap: "8px",
                                                }}
                                            >
                                                <input
                                                    type="checkbox"
                                                    checked={editActive}
                                                    onChange={(event) =>
                                                        setEditActive(
                                                            event.target
                                                                .checked,
                                                        )
                                                    }
                                                />
                                                Active
                                            </label>
                                        ) : (
                                            <span
                                                style={{
                                                    color: category.is_active
                                                        ? "#1D9E75"
                                                        : textSecondary,
                                                    fontWeight: 600,
                                                }}
                                            >
                                                {category.is_active
                                                    ? "Active"
                                                    : "Inactive"}
                                            </span>
                                        )}
                                    </td>
                                    {hasActions && (
                                        <td className="px-4 py-3">
                                            <div
                                                style={{
                                                    display: "flex",
                                                    gap: "12px",
                                                }}
                                            >
                                                {isEditing ? (
                                                    <>
                                                        <button
                                                            onClick={() =>
                                                                saveEdit(
                                                                    category.id,
                                                                )
                                                            }
                                                            style={{
                                                                color: "#1D9E75",
                                                                background:
                                                                    "transparent",
                                                                border: "none",
                                                                fontWeight: 600,
                                                                cursor: "pointer",
                                                                fontSize:
                                                                    "15px",
                                                            }}
                                                        >
                                                            Save
                                                        </button>
                                                        <button
                                                            onClick={cancelEdit}
                                                            style={{
                                                                color: textSecondary,
                                                                background:
                                                                    "transparent",
                                                                border: "none",
                                                                cursor: "pointer",
                                                                fontSize:
                                                                    "15px",
                                                            }}
                                                        >
                                                            Cancel
                                                        </button>
                                                    </>
                                                ) : (
                                                    <>
                                                        {permissions.update && (
                                                            <button
                                                                onClick={() =>
                                                                    startEdit(
                                                                        category,
                                                                    )
                                                                }
                                                                style={{
                                                                    color: "#1D9E75",
                                                                    background:
                                                                        "transparent",
                                                                    border: "none",
                                                                    fontWeight: 600,
                                                                    cursor: "pointer",
                                                                    fontSize:
                                                                        "15px",
                                                                }}
                                                            >
                                                                Edit
                                                            </button>
                                                        )}
                                                        {permissions.delete && (
                                                            <button
                                                                onClick={() =>
                                                                    destroy(
                                                                        category.id,
                                                                        category.name,
                                                                    )
                                                                }
                                                                style={{
                                                                    color: "#DC2626",
                                                                    background:
                                                                        "transparent",
                                                                    border: "none",
                                                                    cursor: "pointer",
                                                                    fontSize:
                                                                        "15px",
                                                                }}
                                                            >
                                                                Delete
                                                            </button>
                                                        )}
                                                    </>
                                                )}
                                            </div>
                                        </td>
                                    )}
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            {categories.links.length > 3 && (
                <div className="flex gap-2 mt-4 flex-wrap">
                    {categories.links.map((link, index) => (
                        <button
                            key={index}
                            disabled={!link.url}
                            onClick={() =>
                                link.url &&
                                router.get(
                                    link.url,
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                            dangerouslySetInnerHTML={{ __html: link.label }}
                            style={{
                                padding: "6px 12px",
                                fontSize: "14px",
                                border: `1px solid ${border}`,
                                background: link.active ? "#1D9E75" : surface,
                                color: link.active ? "#FFFFFF" : text,
                                cursor: link.url ? "pointer" : "default",
                                opacity: link.url ? 1 : 0.5,
                            }}
                        />
                    ))}
                </div>
            )}
        </>
    );
}
