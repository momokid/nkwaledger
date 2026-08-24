import AdminLayout from "@/Layouts/AdminLayout";
import { useTheme } from "@/Layouts/AuthenticatedLayout";
import { router, useForm, usePage } from "@inertiajs/react";
import { PageProps } from "@/types";
import { FormEvent, useState } from "react";

interface CategoryOption {
    id: number;
    name: string;
}

interface FarmTypeData {
    id: number;
    name: string;
    category_id: number | null;
    category: CategoryOption | null;
    is_active: boolean;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props extends PageProps {
    farmTypes: {
        data: FarmTypeData[];
        links: PaginationLink[];
    };
    categories: CategoryOption[];
    permissions: {
        create: boolean;
        update: boolean;
        delete: boolean;
    };
}

export default function Index({ farmTypes, categories, permissions }: Props) {
    return (
        <AdminLayout title="Farm Types">
            <IndexContent
                farmTypes={farmTypes}
                categories={categories}
                permissions={permissions}
            />
        </AdminLayout>
    );
}

type ContentProps = Pick<Props, "farmTypes" | "categories" | "permissions">;

function IndexContent({ farmTypes, categories, permissions }: ContentProps) {
    const { errors } = usePage<Props>().props;
    const { dark } = useTheme();
    const [editingId, setEditingId] = useState<number | null>(null);
    const [editName, setEditName] = useState("");
    const [editCategoryId, setEditCategoryId] = useState<string>("");
    const [editActive, setEditActive] = useState(true);

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const inputBorder = dark ? "#4B5563" : "#9CA3AF";
    const inputBg = dark ? "#111827" : "#FFFFFF";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const headerBg = dark ? "rgba(29,158,117,0.15)" : "#EAF5F0";
    const headerText = "#1D9E75";
    const rowAlt = dark ? "#111827" : "#F9FAFB";

    const createForm = useForm({ name: "", category_id: "" });

    const submitCreate = (event: FormEvent) => {
        event.preventDefault();
        createForm.post(route("admin.farm-types.store"), {
            preserveScroll: true,
            onSuccess: () => createForm.reset("name", "category_id"),
        });
    };

    const startEdit = (farmType: FarmTypeData) => {
        setEditingId(farmType.id);
        setEditName(farmType.name);
        setEditCategoryId(
            farmType.category_id ? String(farmType.category_id) : "",
        );
        setEditActive(farmType.is_active);
    };

    const cancelEdit = () => {
        setEditingId(null);
    };

    const saveEdit = (id: number) => {
        router.put(
            route("admin.farm-types.update", id),
            {
                name: editName,
                category_id:
                    editCategoryId === "" ? null : Number(editCategoryId),
                is_active: editActive,
            },
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
        router.delete(route("admin.farm-types.destroy", id), {
            preserveScroll: true,
        });
    };

    const hasActions = permissions.update || permissions.delete;

    const selectStyle = {
        border: `1px solid ${inputBorder}`,
        background: inputBg,
        color: text,
        padding: "10px 12px",
        fontSize: "20px",
        outline: "none",
        fontFamily: "inherit",
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
                        <label
                            style={{
                                display: "block",
                                fontSize: "18px",
                                fontWeight: 600,
                                color: text,
                                marginBottom: "6px",
                            }}
                        >
                            New farm type name
                        </label>
                        <input
                            type="text"
                            value={createForm.data.name}
                            onChange={(event) =>
                                createForm.setData("name", event.target.value)
                            }
                            placeholder="e.g. Maize"
                            style={{ ...selectStyle, width: "220px" }}
                        />
                        {(errors?.name || createForm.errors.name) && (
                            <p
                                style={{
                                    color: "#DC2626",
                                    fontSize: "18px",
                                    marginTop: "4px",
                                }}
                            >
                                {errors?.name ?? createForm.errors.name}
                            </p>
                        )}
                    </div>
                    <div>
                        <label
                            style={{
                                display: "block",
                                fontSize: "18px",
                                fontWeight: 600,
                                color: text,
                                marginBottom: "6px",
                            }}
                        >
                            Category
                        </label>
                        <select
                            value={createForm.data.category_id}
                            onChange={(event) =>
                                createForm.setData(
                                    "category_id",
                                    event.target.value,
                                )
                            }
                            style={{ ...selectStyle, width: "200px" }}
                        >
                            <option value="">No category</option>
                            {categories.map((category) => (
                                <option key={category.id} value={category.id}>
                                    {category.name}
                                </option>
                            ))}
                        </select>
                        {(errors?.category_id ||
                            createForm.errors.category_id) && (
                            <p
                                style={{
                                    color: "#DC2626",
                                    fontSize: "18px",
                                    marginTop: "4px",
                                }}
                            >
                                {errors?.category_id ??
                                    createForm.errors.category_id}
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
                            fontSize: "20px",
                            fontWeight: 600,
                            cursor: createForm.processing
                                ? "not-allowed"
                                : "pointer",
                            opacity: createForm.processing ? 0.7 : 1,
                        }}
                    >
                        Add farm type
                    </button>
                </form>
            )}

            <div
                className="overflow-x-auto"
                style={{ background: surface, border: `1px solid ${border}` }}
            >
                <table className="min-w-full" style={{ fontSize: "20px" }}>
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
                                Category
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
                        {farmTypes.data.length === 0 && (
                            <tr>
                                <td
                                    colSpan={hasActions ? 4 : 3}
                                    className="px-4 py-6 text-center"
                                    style={{ color: textSecondary }}
                                >
                                    No farm types yet.
                                </td>
                            </tr>
                        )}

                        {farmTypes.data.map((farmType, index) => {
                            const isEditing = editingId === farmType.id;

                            return (
                                <tr
                                    key={farmType.id}
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
                                                    ...selectStyle,
                                                    width: "100%",
                                                    padding: "6px 8px",
                                                }}
                                            />
                                        ) : (
                                            farmType.name
                                        )}
                                    </td>
                                    <td
                                        className="px-4 py-3"
                                        style={{ color: text }}
                                    >
                                        {isEditing ? (
                                            <select
                                                value={editCategoryId}
                                                onChange={(event) =>
                                                    setEditCategoryId(
                                                        event.target.value,
                                                    )
                                                }
                                                style={{
                                                    ...selectStyle,
                                                    padding: "6px 8px",
                                                }}
                                            >
                                                <option value="">
                                                    No category
                                                </option>
                                                {categories.map((category) => (
                                                    <option
                                                        key={category.id}
                                                        value={category.id}
                                                    >
                                                        {category.name}
                                                    </option>
                                                ))}
                                            </select>
                                        ) : (
                                            (farmType.category?.name ?? (
                                                <span
                                                    style={{
                                                        color: textSecondary,
                                                    }}
                                                >
                                                    None
                                                </span>
                                            ))
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
                                                    color: farmType.is_active
                                                        ? "#1D9E75"
                                                        : textSecondary,
                                                    fontWeight: 600,
                                                }}
                                            >
                                                {farmType.is_active
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
                                                                    farmType.id,
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
                                                                        farmType,
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
                                                                        farmType.id,
                                                                        farmType.name,
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

            {farmTypes.links.length > 3 && (
                <div className="flex gap-2 mt-4 flex-wrap">
                    {farmTypes.links.map((link, index) => (
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
                                fontSize: "18px",
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
