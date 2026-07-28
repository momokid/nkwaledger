import AdminLayout from "@/Layouts/AdminLayout";
import { useTheme } from "@/Layouts/AuthenticatedLayout";
import { router, useForm, usePage } from "@inertiajs/react";
import { PageProps } from "@/types";
import { FormEvent, useState } from "react";
import axios from "axios";

interface ClassOption {
    id: number;
    name: string;
}

interface LedgerCategoryData {
    id: number;
    name: string;
    class_id: number;
    class: ClassOption | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props extends PageProps {
    ledgerCategories: {
        data: LedgerCategoryData[];
        links: PaginationLink[];
    };
    classes: ClassOption[];
    permissions: {
        create: boolean;
        update: boolean;
        delete: boolean;
    };
}

export default function Index({
    ledgerCategories,
    classes,
    permissions,
}: Props) {
    return (
        <AdminLayout title="Ledger Categories">
            <IndexContent
                ledgerCategories={ledgerCategories}
                classes={classes}
                permissions={permissions}
            />
        </AdminLayout>
    );
}

type ContentProps = Pick<Props, "ledgerCategories" | "classes" | "permissions">;

function IndexContent({
    ledgerCategories,
    classes,
    permissions,
}: ContentProps) {
    const { errors } = usePage<Props>().props;
    const { dark } = useTheme();
    const [editingId, setEditingId] = useState<number | null>(null);
    const [editName, setEditName] = useState("");
    const [editClassId, setEditClassId] = useState("");
    const [quickAddBusy, setQuickAddBusy] = useState(false);

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const inputBorder = dark ? "#4B5563" : "#9CA3AF";
    const inputBg = dark ? "#111827" : "#FFFFFF";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const headerBg = dark ? "rgba(29,158,117,0.15)" : "#EAF5F0";
    const headerText = "#1D9E75";
    const rowAlt = dark ? "#111827" : "#F9FAFB";

    const createForm = useForm({ name: "", class_id: "" });

    const submitCreate = (event: FormEvent) => {
        event.preventDefault();
        createForm.post(route("admin.ledger-categories.store"), {
            preserveScroll: true,
            onSuccess: () => createForm.reset("name", "class_id"),
        });
    };

    const startEdit = (ledgerCategory: LedgerCategoryData) => {
        setEditingId(ledgerCategory.id);
        setEditName(ledgerCategory.name);
        setEditClassId(String(ledgerCategory.class_id));
    };

    const cancelEdit = () => {
        setEditingId(null);
    };

    const saveEdit = (id: number) => {
        router.put(
            route("admin.ledger-categories.update", id),
            {
                name: editName,
                class_id: Number(editClassId),
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
        router.delete(route("admin.ledger-categories.destroy", id), {
            preserveScroll: true,
        });
    };

    const quickAddClass = async () => {
        const name = window.prompt("New class name (e.g. Dr, Cr)");
        if (!name || !name.trim()) return;

        setQuickAddBusy(true);
        try {
            await axios.post(route("admin.ledger-classes.store"), {
                name: name.trim(),
            });
            router.reload({ only: ["classes"] });
        } catch (error) {
            window.alert("Could not create the class. It may already exist.");
        } finally {
            setQuickAddBusy(false);
        }
    };

    const hasActions = permissions.update || permissions.delete;

    const inputStyle = {
        border: `1px solid ${inputBorder}`,
        background: inputBg,
        color: text,
        padding: "10px 12px",
        fontSize: "17px",
        outline: "none",
        fontFamily: "inherit",
    };

    const quickAddButtonStyle = {
        border: `1px solid ${inputBorder}`,
        background: "transparent",
        color: "#1D9E75",
        fontWeight: 700,
        fontSize: "17px",
        width: "42px",
        height: "42px",
        cursor: quickAddBusy ? "not-allowed" : "pointer",
        opacity: quickAddBusy ? 0.6 : 1,
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
                            placeholder="e.g. Assets"
                            style={{ ...inputStyle, width: "220px" }}
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
                            Class
                        </label>
                        <div style={{ display: "flex", gap: "8px" }}>
                            <select
                                value={createForm.data.class_id}
                                onChange={(event) =>
                                    createForm.setData(
                                        "class_id",
                                        event.target.value,
                                    )
                                }
                                style={{ ...inputStyle, width: "160px" }}
                            >
                                <option value="">Select one</option>
                                {classes.map((ledgerClass) => (
                                    <option
                                        key={ledgerClass.id}
                                        value={ledgerClass.id}
                                    >
                                        {ledgerClass.name}
                                    </option>
                                ))}
                            </select>
                            <button
                                type="button"
                                onClick={quickAddClass}
                                disabled={quickAddBusy}
                                title="Add a new class"
                                style={quickAddButtonStyle}
                            >
                                +
                            </button>
                        </div>
                        {(errors?.class_id || createForm.errors.class_id) && (
                            <p
                                style={{
                                    color: "#DC2626",
                                    fontSize: "14px",
                                    marginTop: "4px",
                                }}
                            >
                                {errors?.class_id ?? createForm.errors.class_id}
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
                                Class
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
                        {ledgerCategories.data.length === 0 && (
                            <tr>
                                <td
                                    colSpan={hasActions ? 3 : 2}
                                    className="px-4 py-6 text-center"
                                    style={{ color: textSecondary }}
                                >
                                    No ledger categories yet.
                                </td>
                            </tr>
                        )}

                        {ledgerCategories.data.map((ledgerCategory, index) => {
                            const isEditing = editingId === ledgerCategory.id;

                            return (
                                <tr
                                    key={ledgerCategory.id}
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
                                            ledgerCategory.name
                                        )}
                                    </td>
                                    <td
                                        className="px-4 py-3"
                                        style={{ color: text }}
                                    >
                                        {isEditing ? (
                                            <select
                                                value={editClassId}
                                                onChange={(event) =>
                                                    setEditClassId(
                                                        event.target.value,
                                                    )
                                                }
                                                style={{
                                                    ...inputStyle,
                                                    padding: "6px 8px",
                                                }}
                                            >
                                                {classes.map((ledgerClass) => (
                                                    <option
                                                        key={ledgerClass.id}
                                                        value={ledgerClass.id}
                                                    >
                                                        {ledgerClass.name}
                                                    </option>
                                                ))}
                                            </select>
                                        ) : (
                                            (ledgerCategory.class?.name ?? (
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
                                                                    ledgerCategory.id,
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
                                                                        ledgerCategory,
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
                                                                        ledgerCategory.id,
                                                                        ledgerCategory.name,
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

            {ledgerCategories.links.length > 3 && (
                <div className="flex gap-2 mt-4 flex-wrap">
                    {ledgerCategories.links.map((link, index) => (
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
