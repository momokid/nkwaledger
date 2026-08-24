import AdminLayout from "@/Layouts/AdminLayout";
import { useTheme } from "@/Layouts/AuthenticatedLayout";
import { router, useForm, usePage } from "@inertiajs/react";
import { PageProps } from "@/types";
import { FormEvent, useState } from "react";

interface CategoryOption {
    id: number;
    name: string;
}

interface LedgerSubcategoryData {
    id: number;
    name: string;
    category_id: number;
    category: CategoryOption | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props extends PageProps {
    ledgerSubcategories: {
        data: LedgerSubcategoryData[];
        links: PaginationLink[];
    };
    categories: CategoryOption[];
    permissions: {
        create: boolean;
        update: boolean;
        delete: boolean;
    };
}

export default function Index({
    ledgerSubcategories,
    categories,
    permissions,
}: Props) {
    return (
        <AdminLayout title="Ledger Subcategories">
            <IndexContent
                ledgerSubcategories={ledgerSubcategories}
                categories={categories}
                permissions={permissions}
            />
        </AdminLayout>
    );
}

type ContentProps = Pick<
    Props,
    "ledgerSubcategories" | "categories" | "permissions"
>;

function IndexContent({
    ledgerSubcategories,
    categories,
    permissions,
}: ContentProps) {
    const { errors } = usePage<Props>().props;
    const { dark } = useTheme();
    const [editingId, setEditingId] = useState<number | null>(null);
    const [editName, setEditName] = useState("");
    const [editCategoryId, setEditCategoryId] = useState("");

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const inputBorder = dark ? "#4B5563" : "#9CA3AF";
    const inputBg = dark ? "#111827" : "#FFFFFF";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const headerBg = dark ? "rgba(29,158,117,0.15)" : "#EAF5F0";
    const headerText = "#1D9E75";
    const rowAlt = dark ? "#111827" : "#F9FAFB";

    const createForm = useForm({
        category_id: "",
        name: "",
    });

    const submitCreate = (event: FormEvent) => {
        event.preventDefault();
        createForm.post(route("admin.ledger-subcategories.store"), {
            preserveScroll: true,
            onSuccess: () => createForm.reset("name"),
        });
    };

    const startEdit = (ledgerSubcategory: LedgerSubcategoryData) => {
        setEditingId(ledgerSubcategory.id);
        setEditName(ledgerSubcategory.name);
        setEditCategoryId(String(ledgerSubcategory.category_id));
    };

    const cancelEdit = () => {
        setEditingId(null);
    };

    const saveEdit = (id: number) => {
        router.put(
            route("admin.ledger-subcategories.update", id),
            {
                category_id: Number(editCategoryId),
                name: editName,
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
        router.delete(route("admin.ledger-subcategories.destroy", id), {
            preserveScroll: true,
        });
    };

    const hasActions = permissions.update || permissions.delete;

    const inputStyle = {
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
                            style={{ ...inputStyle, width: "200px" }}
                        >
                            <option value="">Select one</option>
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
                            New subcategory name
                        </label>
                        <input
                            type="text"
                            value={createForm.data.name}
                            onChange={(event) =>
                                createForm.setData("name", event.target.value)
                            }
                            placeholder="e.g. Short Term Asset"
                            style={{ ...inputStyle, width: "240px" }}
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
                        Add subcategory
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
                        {ledgerSubcategories.data.length === 0 && (
                            <tr>
                                <td
                                    colSpan={hasActions ? 3 : 2}
                                    className="px-4 py-6 text-center"
                                    style={{ color: textSecondary }}
                                >
                                    No ledger subcategories yet.
                                </td>
                            </tr>
                        )}

                        {ledgerSubcategories.data.map(
                            (ledgerSubcategory, index) => {
                                const isEditing =
                                    editingId === ledgerSubcategory.id;

                                return (
                                    <tr
                                        key={ledgerSubcategory.id}
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
                                                        ...inputStyle,
                                                        width: "100%",
                                                        padding: "6px 8px",
                                                    }}
                                                />
                                            ) : (
                                                ledgerSubcategory.name
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
                                                        ...inputStyle,
                                                        padding: "6px 8px",
                                                    }}
                                                >
                                                    {categories.map(
                                                        (category) => (
                                                            <option
                                                                key={
                                                                    category.id
                                                                }
                                                                value={
                                                                    category.id
                                                                }
                                                            >
                                                                {category.name}
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                            ) : (
                                                (ledgerSubcategory.category
                                                    ?.name ?? (
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
                                                                        ledgerSubcategory.id,
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
                                                                onClick={
                                                                    cancelEdit
                                                                }
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
                                                                            ledgerSubcategory,
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
                                                                            ledgerSubcategory.id,
                                                                            ledgerSubcategory.name,
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
                            },
                        )}
                    </tbody>
                </table>
            </div>

            {ledgerSubcategories.links.length > 3 && (
                <div className="flex gap-2 mt-4 flex-wrap">
                    {ledgerSubcategories.links.map((link, index) => (
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
