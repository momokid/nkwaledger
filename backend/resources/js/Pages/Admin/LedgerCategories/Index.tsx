import AdminLayout from "@/Layouts/AdminLayout";
import { useTheme } from "@/Layouts/AuthenticatedLayout";
import { router, useForm, usePage } from "@inertiajs/react";
import { PageProps } from "@/types";
import { FormEvent, useState } from "react";

interface FundamentalTypeOption {
    id: number;
    name: string;
}

interface LedgerCategoryData {
    id: number;
    name: string;
    type: string;
    class: string;
    fundamental_type_id: number;
    fundamental_type: FundamentalTypeOption | null;
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
    fundamentalTypes: FundamentalTypeOption[];
    permissions: {
        create: boolean;
        update: boolean;
        delete: boolean;
    };
}

export default function Index({
    ledgerCategories,
    fundamentalTypes,
    permissions,
}: Props) {
    return (
        <AdminLayout title="Ledger Categories">
            <IndexContent
                ledgerCategories={ledgerCategories}
                fundamentalTypes={fundamentalTypes}
                permissions={permissions}
            />
        </AdminLayout>
    );
}

type ContentProps = Pick
    Props,
    "ledgerCategories" | "fundamentalTypes" | "permissions"
>;

function IndexContent({
    ledgerCategories,
    fundamentalTypes,
    permissions,
}: ContentProps) {
    const { errors } = usePage<Props>().props;
    const { dark } = useTheme();
    const [editingId, setEditingId] = useState<number | null>(null);
    const [editName, setEditName] = useState("");
    const [editType, setEditType] = useState("");
    const [editFundamentalTypeId, setEditFundamentalTypeId] = useState("");

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
        fundamental_type_id: "",
        name: "",
        type: "",
    });

    const submitCreate = (event: FormEvent) => {
        event.preventDefault();
        createForm.post(route("admin.ledger-categories.store"), {
            preserveScroll: true,
            onSuccess: () =>
                createForm.reset("fundamental_type_id", "name", "type"),
        });
    };

    const startEdit = (ledgerCategory: LedgerCategoryData) => {
        setEditingId(ledgerCategory.id);
        setEditName(ledgerCategory.name);
        setEditType(ledgerCategory.type);
        setEditFundamentalTypeId(String(ledgerCategory.fundamental_type_id));
    };

    const cancelEdit = () => {
        setEditingId(null);
    };

    const saveEdit = (id: number) => {
        router.put(
            route("admin.ledger-categories.update", id),
            {
                fundamental_type_id: Number(editFundamentalTypeId),
                name: editName,
                type: editType,
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
                            Fundamental type
                        </label>
                        <select
                            value={createForm.data.fundamental_type_id}
                            onChange={(event) =>
                                createForm.setData(
                                    "fundamental_type_id",
                                    event.target.value,
                                )
                            }
                            style={{ ...inputStyle, width: "180px" }}
                        >
                            <option value="">Select one</option>
                            {fundamentalTypes.map((fundamentalType) => (
                                <option
                                    key={fundamentalType.id}
                                    value={fundamentalType.id}
                                >
                                    {fundamentalType.name}
                                </option>
                            ))}
                        </select>
                        {(errors?.fundamental_type_id ||
                            createForm.errors.fundamental_type_id) && (
                            <p
                                style={{
                                    color: "#DC2626",
                                    fontSize: "14px",
                                    marginTop: "4px",
                                }}
                            >
                                {errors?.fundamental_type_id ??
                                    createForm.errors.fundamental_type_id}
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
                            New category name
                        </label>
                        <input
                            type="text"
                            value={createForm.data.name}
                            onChange={(event) =>
                                createForm.setData("name", event.target.value)
                            }
                            placeholder="e.g. Assets"
                            style={{ ...inputStyle, width: "200px" }}
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
                            Type
                        </label>
                        <input
                            type="text"
                            value={createForm.data.type}
                            onChange={(event) =>
                                createForm.setData("type", event.target.value)
                            }
                            placeholder="e.g. GL"
                            style={{ ...inputStyle, width: "140px" }}
                        />
                        {(errors?.type || createForm.errors.type) && (
                            <p
                                style={{
                                    color: "#DC2626",
                                    fontSize: "14px",
                                    marginTop: "4px",
                                }}
                            >
                                {errors?.type ?? createForm.errors.type}
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
                                Fundamental type
                            </th>
                            <th
                                className="text-left px-4 py-3"
                                style={{ color: headerText, fontWeight: 700 }}
                            >
                                Type
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
                                    colSpan={hasActions ? 5 : 4}
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
                                                value={editFundamentalTypeId}
                                                onChange={(event) =>
                                                    setEditFundamentalTypeId(
                                                        event.target.value,
                                                    )
                                                }
                                                style={{
                                                    ...inputStyle,
                                                    padding: "6px 8px",
                                                }}
                                            >
                                                {fundamentalTypes.map(
                                                    (fundamentalType) => (
                                                        <option
                                                            key={
                                                                fundamentalType.id
                                                            }
                                                            value={
                                                                fundamentalType.id
                                                            }
                                                        >
                                                            {
                                                                fundamentalType.name
                                                            }
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                        ) : (
                                            (ledgerCategory.fundamental_type
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
                                    <td
                                        className="px-4 py-3"
                                        style={{ color: text }}
                                    >
                                        {isEditing ? (
                                            <input
                                                type="text"
                                                value={editType}
                                                onChange={(event) =>
                                                    setEditType(
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
                                            ledgerCategory.type
                                        )}
                                    </td>
                                    <td
                                        className="px-4 py-3"
                                        style={{
                                            color: text,
                                            textTransform: "capitalize",
                                        }}
                                    >
                                        {ledgerCategory.class}
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