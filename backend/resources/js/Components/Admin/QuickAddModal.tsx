import { useTheme } from "@/Layouts/AuthenticatedLayout";
import { ReactNode, useEffect, useState } from "react";
import TableSkeletonRows from "@/Components/Admin/TableSkeletonRows";

export interface QuickAddItem {
    id: number;
    name: string;
}

interface Props {
    title: string;
    items: QuickAddItem[] | null;
    loading: boolean;
    permissions: { create: boolean; update: boolean; delete: boolean };
    onClose: () => void;
    onCreate: (name: string, extra: string) => Promise<void>;
    onUpdate: (id: number, name: string, extra: string) => Promise<void>;
    onDelete: (id: number) => Promise<void>;
    extraField?: (
        value: string,
        onChange: (value: string) => void,
    ) => ReactNode;
    extraFieldRequired?: boolean;
    extraFieldDefault?: string;
}

export default function QuickAddModal({
    title,
    items,
    loading,
    permissions,
    onClose,
    onCreate,
    onUpdate,
    onDelete,
    extraField,
    extraFieldRequired = false,
    extraFieldDefault = "",
}: Props) {
    const { dark } = useTheme();
    const [newName, setNewName] = useState("");
    const [newExtra, setNewExtra] = useState(extraFieldDefault);
    const [createError, setCreateError] = useState<string | null>(null);
    const [editError, setEditError] = useState<string | null>(null);
    const [creating, setCreating] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [editName, setEditName] = useState("");
    const [editExtra, setEditExtra] = useState("");
    const [busyId, setBusyId] = useState<number | null>(null);

    useEffect(() => {
        setNewExtra(extraFieldDefault);
    }, [extraFieldDefault]);

    const overlay = "rgba(0,0,0,0.5)";
    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const inputBorder = dark ? "#4B5563" : "#9CA3AF";
    const inputBg = dark ? "#111827" : "#FFFFFF";

    const inputStyle = {
        border: `1px solid ${inputBorder}`,
        background: inputBg,
        color: text,
        padding: "8px 10px",
        fontSize: "15px",
        outline: "none",
        fontFamily: "inherit",
        width: "100%",
    };

    const errorTextStyle = {
        color: "#DC2626",
        fontSize: "13px",
        marginTop: "4px",
    };

    const submitCreate = async () => {
        if (!newName.trim()) {
            setCreateError("Name is required.");
            return;
        }
        if (extraFieldRequired && !newExtra) {
            setCreateError("Please make a selection above before adding.");
            return;
        }
        setCreateError(null);
        setCreating(true);
        try {
            await onCreate(newName, newExtra);
            setNewName("");
            // newExtra is intentionally left as-is — clearing it would blank the Region/District
            // select after every create, breaking the ability to add several items to the same parent in a row
        } catch (error) {
            setCreateError(
                error instanceof Error
                    ? error.message
                    : "Something went wrong. Please try again.",
            );
        } finally {
            setCreating(false);
        }
    };

    const startEdit = (item: QuickAddItem) => {
        setEditingId(item.id);
        setEditName(item.name);
        setEditExtra("");
        setEditError(null);
    };

    const submitEdit = async (id: number) => {
        if (!editName.trim()) {
            setEditError("Name is required.");
            return;
        }
        if (extraFieldRequired && !editExtra) {
            setEditError("Please make a selection above before saving.");
            return;
        }
        setEditError(null);
        setBusyId(id);
        try {
            await onUpdate(id, editName, editExtra);
            setEditingId(null);
        } catch (error) {
            setEditError(
                error instanceof Error
                    ? error.message
                    : "Something went wrong. Please try again.",
            );
        } finally {
            setBusyId(null);
        }
    };

    const submitDelete = async (id: number) => {
        if (
            !window.confirm(
                "Delete this item? This cannot be undone from here.",
            )
        )
            return;
        setBusyId(id);
        try {
            await onDelete(id);
        } finally {
            setBusyId(null);
        }
    };

    return (
        <div
            style={{
                position: "fixed",
                inset: 0,
                background: overlay,
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
                zIndex: 100,
            }}
            onClick={onClose}
        >
            <div
                onClick={(event) => event.stopPropagation()}
                style={{
                    background: surface,
                    border: `1px solid ${border}`,
                    width: "420px",
                    maxHeight: "80vh",
                    display: "flex",
                    flexDirection: "column",
                }}
            >
                <div
                    style={{
                        padding: "16px 20px",
                        borderBottom: `1px solid ${border}`,
                        display: "flex",
                        justifyContent: "space-between",
                        alignItems: "center",
                    }}
                >
                    <h3
                        style={{
                            fontSize: "17px",
                            fontWeight: 700,
                            color: text,
                            margin: 0,
                        }}
                    >
                        Manage {title}
                    </h3>
                    <button
                        onClick={onClose}
                        style={{
                            background: "transparent",
                            border: "none",
                            color: textSecondary,
                            cursor: "pointer",
                            fontSize: "20px",
                        }}
                    >
                        ×
                    </button>
                </div>

                <div style={{ overflowY: "auto", flex: 1 }}>
                    <table className="min-w-full" style={{ fontSize: "15px" }}>
                        <tbody>
                            {loading && (
                                <TableSkeletonRows rows={4} columns={2} />
                            )}

                            {!loading && items?.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={2}
                                        className="px-4 py-4 text-center"
                                        style={{ color: textSecondary }}
                                    >
                                        None yet.
                                    </td>
                                </tr>
                            )}

                            {!loading &&
                                items?.map((item) => {
                                    const isEditing = editingId === item.id;
                                    const isBusy = busyId === item.id;

                                    return (
                                        <tr
                                            key={item.id}
                                            style={{
                                                borderTop: `1px solid ${border}`,
                                            }}
                                        >
                                            {isBusy ? (
                                                <TableSkeletonRows
                                                    rows={1}
                                                    columns={2}
                                                />
                                            ) : isEditing ? (
                                                <td
                                                    colSpan={2}
                                                    className="px-4 py-2"
                                                >
                                                    {extraField &&
                                                        extraField(
                                                            editExtra,
                                                            setEditExtra,
                                                        )}
                                                    <input
                                                        type="text"
                                                        value={editName}
                                                        onChange={(event) =>
                                                            setEditName(
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        style={{
                                                            ...inputStyle,
                                                            marginTop:
                                                                extraField
                                                                    ? "6px"
                                                                    : 0,
                                                        }}
                                                    />
                                                    {editError && (
                                                        <p
                                                            style={
                                                                errorTextStyle
                                                            }
                                                        >
                                                            {editError}
                                                        </p>
                                                    )}
                                                    <div
                                                        style={{
                                                            display: "flex",
                                                            gap: "10px",
                                                            marginTop: "8px",
                                                        }}
                                                    >
                                                        <button
                                                            onClick={() =>
                                                                submitEdit(
                                                                    item.id,
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
                                                                    "14px",
                                                            }}
                                                        >
                                                            Save
                                                        </button>
                                                        <button
                                                            onClick={() => {
                                                                setEditingId(
                                                                    null,
                                                                );
                                                                setEditError(
                                                                    null,
                                                                );
                                                            }}
                                                            style={{
                                                                color: textSecondary,
                                                                background:
                                                                    "transparent",
                                                                border: "none",
                                                                cursor: "pointer",
                                                                fontSize:
                                                                    "14px",
                                                            }}
                                                        >
                                                            Cancel
                                                        </button>
                                                    </div>
                                                </td>
                                            ) : (
                                                <>
                                                    <td
                                                        className="px-4 py-2"
                                                        style={{ color: text }}
                                                    >
                                                        {item.name}
                                                    </td>
                                                    <td className="px-4 py-2 text-right">
                                                        <div
                                                            style={{
                                                                display: "flex",
                                                                gap: "10px",
                                                                justifyContent:
                                                                    "flex-end",
                                                            }}
                                                        >
                                                            {permissions.update && (
                                                                <button
                                                                    onClick={() =>
                                                                        startEdit(
                                                                            item,
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
                                                                            "14px",
                                                                    }}
                                                                >
                                                                    Edit
                                                                </button>
                                                            )}
                                                            {permissions.delete && (
                                                                <button
                                                                    onClick={() =>
                                                                        submitDelete(
                                                                            item.id,
                                                                        )
                                                                    }
                                                                    style={{
                                                                        color: "#DC2626",
                                                                        background:
                                                                            "transparent",
                                                                        border: "none",
                                                                        cursor: "pointer",
                                                                        fontSize:
                                                                            "14px",
                                                                    }}
                                                                >
                                                                    Delete
                                                                </button>
                                                            )}
                                                        </div>
                                                    </td>
                                                </>
                                            )}
                                        </tr>
                                    );
                                })}
                        </tbody>
                    </table>
                </div>

                {permissions.create && (
                    <div
                        style={{
                            padding: "16px 20px",
                            borderTop: `1px solid ${border}`,
                        }}
                    >
                        {extraField && extraField(newExtra, setNewExtra)}
                        <input
                            type="text"
                            value={newName}
                            onChange={(event) => setNewName(event.target.value)}
                            placeholder={`New ${title.toLowerCase()} name`}
                            style={{
                                ...inputStyle,
                                marginTop: extraField ? "6px" : 0,
                            }}
                        />
                        {createError && (
                            <p style={errorTextStyle}>{createError}</p>
                        )}
                        <button
                            onClick={submitCreate}
                            disabled={creating}
                            style={{
                                marginTop: "10px",
                                background: "#1D9E75",
                                color: "#FFFFFF",
                                border: "none",
                                padding: "8px 16px",
                                fontSize: "15px",
                                fontWeight: 600,
                                cursor: creating ? "not-allowed" : "pointer",
                                opacity: creating ? 0.7 : 1,
                                width: "100%",
                            }}
                        >
                            Add
                        </button>
                    </div>
                )}
            </div>
        </div>
    );
}
