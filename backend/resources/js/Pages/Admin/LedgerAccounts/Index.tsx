import AdminLayout from "@/Layouts/AdminLayout";
import { useTheme } from "@/Layouts/AuthenticatedLayout";
import { router, useForm, usePage } from "@inertiajs/react";
import { PageProps } from "@/types";
import { FormEvent, useState } from "react";
import axios from "axios";

interface Option {
    id: number;
    name: string;
}

interface SubcategoryOption extends Option {
    category_id: number;
    category: Option | null;
}

interface LedgerAccountData {
    id: number;
    name: string;
    account_code: string | null;
    control_id: number;
    subcategory_id: number;
    type_id: number;
    is_system: boolean;
    is_active: boolean;
    control: Option | null;
    type: Option | null;
    subcategory: {
        id: number;
        name: string;
        category: { id: number; name: string; class: Option | null } | null;
    } | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props extends PageProps {
    ledgerAccounts: { data: LedgerAccountData[]; links: PaginationLink[] };
    controls: Option[];
    subcategories: SubcategoryOption[];
    types: Option[];
    categories: Option[];
    permissions: { create: boolean; update: boolean; delete: boolean };
}

export default function Index(props: Props) {
    return (
        <AdminLayout title="Ledger Accounts">
            <IndexContent {...props} />
        </AdminLayout>
    );
}

type ContentProps = Pick<
    Props,
    | "ledgerAccounts"
    | "controls"
    | "subcategories"
    | "types"
    | "categories"
    | "permissions"
>;

function IndexContent({
    ledgerAccounts,
    controls,
    subcategories,
    types,
    categories,
    permissions,
}: ContentProps) {
    const { errors } = usePage<Props>().props;
    const { dark } = useTheme();
    const [editingId, setEditingId] = useState<number | null>(null);
    const [editName, setEditName] = useState("");
    const [editCode, setEditCode] = useState("");
    const [editControlId, setEditControlId] = useState("");
    const [editSubcategoryId, setEditSubcategoryId] = useState("");
    const [editTypeId, setEditTypeId] = useState("");
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

    const createForm = useForm({
        name: "",
        account_code: "",
        control_id: "",
        subcategory_id: "",
        type_id: "",
    });

    const submitCreate = (event: FormEvent) => {
        event.preventDefault();
        createForm.post(route("admin.ledger-accounts.store"), {
            preserveScroll: true,
            onSuccess: () => createForm.reset("name", "account_code"),
        });
    };

    const startEdit = (account: LedgerAccountData) => {
        setEditingId(account.id);
        setEditName(account.name);
        setEditCode(account.account_code ?? "");
        setEditControlId(String(account.control_id));
        setEditSubcategoryId(String(account.subcategory_id));
        setEditTypeId(String(account.type_id));
    };

    const saveEdit = (id: number) => {
        router.put(
            route("admin.ledger-accounts.update", id),
            {
                name: editName,
                account_code: editCode === "" ? null : editCode,
                control_id: Number(editControlId),
                subcategory_id: Number(editSubcategoryId),
                type_id: Number(editTypeId),
            },
            { preserveScroll: true, onSuccess: () => setEditingId(null) },
        );
    };

    const destroy = (id: number, name: string) => {
        if (
            !window.confirm(
                `Delete "${name}"? This cannot be undone from here.`,
            )
        )
            return;
        router.delete(route("admin.ledger-accounts.destroy", id), {
            preserveScroll: true,
        });
    };

    const quickAdd = async (
        label: string,
        routeName: string,
        propName: string,
        extra?: Record<string, unknown>,
    ) => {
        const name = window.prompt(`New ${label} name`);
        if (!name || !name.trim()) return;

        setQuickAddBusy(true);
        try {
            await axios.post(route(routeName), { name: name.trim(), ...extra });
            router.reload({ only: [propName] });
        } catch {
            window.alert(
                `Could not create the ${label}. It may already exist.`,
            );
        } finally {
            setQuickAddBusy(false);
        }
    };

    const quickAddSubcategory = async () => {
        if (categories.length === 0) {
            window.alert("Create a ledger category first.");
            return;
        }
        const list = categories.map((c) => `${c.id} - ${c.name}`).join("\n");
        const answer = window.prompt(
            `Enter the category ID for this subcategory:\n\n${list}`,
        );
        if (!answer) return;

        const categoryId = Number(answer.trim());
        if (!categories.some((c) => c.id === categoryId)) {
            window.alert("That category ID was not in the list.");
            return;
        }
        await quickAdd(
            "subcategory",
            "admin.ledger-subcategories.store",
            "subcategories",
            { category_id: categoryId },
        );
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

    const plusStyle = {
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

    const labelStyle = {
        display: "block",
        fontSize: "15px",
        fontWeight: 600,
        color: text,
        marginBottom: "6px",
    };

    const errorStyle = { color: "#DC2626", fontSize: "14px", marginTop: "4px" };
    const thStyle = { color: headerText, fontWeight: 700 };

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
                        <label style={labelStyle}>Account name</label>
                        <input
                            type="text"
                            value={createForm.data.name}
                            onChange={(e) =>
                                createForm.setData("name", e.target.value)
                            }
                            placeholder="e.g. Cash & MoMo"
                            style={{ ...inputStyle, width: "200px" }}
                        />
                        {(errors?.name || createForm.errors.name) && (
                            <p style={errorStyle}>
                                {errors?.name ?? createForm.errors.name}
                            </p>
                        )}
                    </div>

                    <div>
                        <label style={labelStyle}>Account code</label>
                        <input
                            type="text"
                            value={createForm.data.account_code}
                            onChange={(e) =>
                                createForm.setData(
                                    "account_code",
                                    e.target.value,
                                )
                            }
                            placeholder="Optional"
                            style={{ ...inputStyle, width: "120px" }}
                        />
                        {(errors?.account_code ||
                            createForm.errors.account_code) && (
                            <p style={errorStyle}>
                                {errors?.account_code ??
                                    createForm.errors.account_code}
                            </p>
                        )}
                    </div>

                    <div>
                        <label style={labelStyle}>Control</label>
                        <div style={{ display: "flex", gap: "8px" }}>
                            <select
                                value={createForm.data.control_id}
                                onChange={(e) =>
                                    createForm.setData(
                                        "control_id",
                                        e.target.value,
                                    )
                                }
                                style={{ ...inputStyle, width: "170px" }}
                            >
                                <option value="">Select one</option>
                                {controls.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name}
                                    </option>
                                ))}
                            </select>
                            <button
                                type="button"
                                onClick={() =>
                                    quickAdd(
                                        "control",
                                        "admin.ledger-controls.store",
                                        "controls",
                                    )
                                }
                                disabled={quickAddBusy}
                                title="Add a new control"
                                style={plusStyle}
                            >
                                +
                            </button>
                        </div>
                        {(errors?.control_id ||
                            createForm.errors.control_id) && (
                            <p style={errorStyle}>
                                {errors?.control_id ??
                                    createForm.errors.control_id}
                            </p>
                        )}
                    </div>

                    <div>
                        <label style={labelStyle}>Subcategory</label>
                        <div style={{ display: "flex", gap: "8px" }}>
                            <select
                                value={createForm.data.subcategory_id}
                                onChange={(e) =>
                                    createForm.setData(
                                        "subcategory_id",
                                        e.target.value,
                                    )
                                }
                                style={{ ...inputStyle, width: "230px" }}
                            >
                                <option value="">Select one</option>
                                {subcategories.map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.category?.name
                                            ? `${s.category.name} — ${s.name}`
                                            : s.name}
                                    </option>
                                ))}
                            </select>
                            <button
                                type="button"
                                onClick={quickAddSubcategory}
                                disabled={quickAddBusy}
                                title="Add a new subcategory"
                                style={plusStyle}
                            >
                                +
                            </button>
                        </div>
                        {(errors?.subcategory_id ||
                            createForm.errors.subcategory_id) && (
                            <p style={errorStyle}>
                                {errors?.subcategory_id ??
                                    createForm.errors.subcategory_id}
                            </p>
                        )}
                    </div>

                    <div>
                        <label style={labelStyle}>Type</label>
                        <div style={{ display: "flex", gap: "8px" }}>
                            <select
                                value={createForm.data.type_id}
                                onChange={(e) =>
                                    createForm.setData(
                                        "type_id",
                                        e.target.value,
                                    )
                                }
                                style={{ ...inputStyle, width: "150px" }}
                            >
                                <option value="">Select one</option>
                                {types.map((t) => (
                                    <option key={t.id} value={t.id}>
                                        {t.name}
                                    </option>
                                ))}
                            </select>
                            <button
                                type="button"
                                onClick={() =>
                                    quickAdd(
                                        "type",
                                        "admin.ledger-types.store",
                                        "types",
                                    )
                                }
                                disabled={quickAddBusy}
                                title="Add a new type"
                                style={plusStyle}
                            >
                                +
                            </button>
                        </div>
                        {(errors?.type_id || createForm.errors.type_id) && (
                            <p style={errorStyle}>
                                {errors?.type_id ?? createForm.errors.type_id}
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
                        Add account
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
                            <th className="text-left px-4 py-3" style={thStyle}>
                                Name
                            </th>
                            <th className="text-left px-4 py-3" style={thStyle}>
                                Code
                            </th>
                            <th className="text-left px-4 py-3" style={thStyle}>
                                Control
                            </th>
                            <th className="text-left px-4 py-3" style={thStyle}>
                                Subcategory
                            </th>
                            <th className="text-left px-4 py-3" style={thStyle}>
                                Type
                            </th>
                            <th className="text-left px-4 py-3" style={thStyle}>
                                Class
                            </th>
                            {hasActions && (
                                <th
                                    className="text-left px-4 py-3"
                                    style={thStyle}
                                >
                                    Actions
                                </th>
                            )}
                        </tr>
                    </thead>
                    <tbody>
                        {ledgerAccounts.data.length === 0 && (
                            <tr>
                                <td
                                    colSpan={hasActions ? 7 : 6}
                                    className="px-4 py-6 text-center"
                                    style={{ color: textSecondary }}
                                >
                                    No ledger accounts yet.
                                </td>
                            </tr>
                        )}

                        {ledgerAccounts.data.map((account, index) => {
                            const isEditing = editingId === account.id;
                            const rowStyle = {
                                borderTop: `1px solid ${border}`,
                                background:
                                    index % 2 === 1 ? rowAlt : "transparent",
                            };
                            const cellInput = {
                                ...inputStyle,
                                width: "100%",
                                padding: "6px 8px",
                            };

                            return (
                                <tr key={account.id} style={rowStyle}>
                                    <td
                                        className="px-4 py-3"
                                        style={{ color: text }}
                                    >
                                        {isEditing ? (
                                            <input
                                                type="text"
                                                value={editName}
                                                onChange={(e) =>
                                                    setEditName(e.target.value)
                                                }
                                                style={cellInput}
                                            />
                                        ) : (
                                            <span
                                                style={{
                                                    display: "flex",
                                                    alignItems: "center",
                                                    gap: "8px",
                                                }}
                                            >
                                                {account.name}
                                                {account.is_system && (
                                                    <span
                                                        style={{
                                                            fontSize: "12px",
                                                            fontWeight: 700,
                                                            color: "#BA7517",
                                                            border: "1px solid #BA7517",
                                                            padding: "1px 6px",
                                                        }}
                                                    >
                                                        SYSTEM
                                                    </span>
                                                )}
                                            </span>
                                        )}
                                    </td>
                                    <td
                                        className="px-4 py-3"
                                        style={{ color: text }}
                                    >
                                        {isEditing ? (
                                            <input
                                                type="text"
                                                value={editCode}
                                                onChange={(e) =>
                                                    setEditCode(e.target.value)
                                                }
                                                style={cellInput}
                                            />
                                        ) : (
                                            (account.account_code ?? (
                                                <span
                                                    style={{
                                                        color: textSecondary,
                                                    }}
                                                >
                                                    —
                                                </span>
                                            ))
                                        )}
                                    </td>
                                    <td
                                        className="px-4 py-3"
                                        style={{ color: text }}
                                    >
                                        {isEditing ? (
                                            <select
                                                value={editControlId}
                                                onChange={(e) =>
                                                    setEditControlId(
                                                        e.target.value,
                                                    )
                                                }
                                                style={{
                                                    ...inputStyle,
                                                    padding: "6px 8px",
                                                }}
                                            >
                                                {controls.map((c) => (
                                                    <option
                                                        key={c.id}
                                                        value={c.id}
                                                    >
                                                        {c.name}
                                                    </option>
                                                ))}
                                            </select>
                                        ) : (
                                            (account.control?.name ?? (
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
                                            <select
                                                value={editSubcategoryId}
                                                onChange={(e) =>
                                                    setEditSubcategoryId(
                                                        e.target.value,
                                                    )
                                                }
                                                style={{
                                                    ...inputStyle,
                                                    padding: "6px 8px",
                                                }}
                                            >
                                                {subcategories.map((s) => (
                                                    <option
                                                        key={s.id}
                                                        value={s.id}
                                                    >
                                                        {s.category?.name
                                                            ? `${s.category.name} — ${s.name}`
                                                            : s.name}
                                                    </option>
                                                ))}
                                            </select>
                                        ) : (
                                            (account.subcategory?.name ?? (
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
                                            <select
                                                value={editTypeId}
                                                onChange={(e) =>
                                                    setEditTypeId(
                                                        e.target.value,
                                                    )
                                                }
                                                style={{
                                                    ...inputStyle,
                                                    padding: "6px 8px",
                                                }}
                                            >
                                                {types.map((t) => (
                                                    <option
                                                        key={t.id}
                                                        value={t.id}
                                                    >
                                                        {t.name}
                                                    </option>
                                                ))}
                                            </select>
                                        ) : (
                                            (account.type?.name ?? (
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
                                        style={{ color: text, fontWeight: 600 }}
                                    >
                                        {account.subcategory?.category?.class
                                            ?.name ?? (
                                            <span
                                                style={{
                                                    color: textSecondary,
                                                    fontWeight: 400,
                                                }}
                                            >
                                                —
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
                                                                    account.id,
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
                                                            onClick={() =>
                                                                setEditingId(
                                                                    null,
                                                                )
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
                                                                        account,
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
                                                        {permissions.delete &&
                                                            !account.is_system && (
                                                                <button
                                                                    onClick={() =>
                                                                        destroy(
                                                                            account.id,
                                                                            account.name,
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

            {ledgerAccounts.links.length > 3 && (
                <div className="flex gap-2 mt-4 flex-wrap">
                    {ledgerAccounts.links.map((link, index) => (
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
