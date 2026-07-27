import AdminLayout from "@/Layouts/AdminLayout";
import { useTheme } from "@/Layouts/AuthenticatedLayout";
import { router, usePage } from "@inertiajs/react";
import { PageProps } from "@/types";
import { useState } from "react";
import TableSkeletonRows from "@/Components/Admin/TableSkeletonRows";
import QuickAddModal from "@/Components/Admin/QuickAddModal";

interface TypeItem {
    id: number;
    name: string;
    normal_balance: "debit" | "credit";
}

interface LedgerAccountData {
    id: number;
    name: string;
    type_id: number | null;
    type: TypeItem | null;
    normal_balance: "debit" | "credit" | null;
    is_system: boolean;
    is_active: boolean;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props extends PageProps {
    ledgerAccounts: {
        data: LedgerAccountData[];
        links: PaginationLink[];
    };
    types: TypeItem[];
    permissions: {
        create: boolean;
        update: boolean;
        delete: boolean;
    };
}

const emptyForm = {
    id: null as number | null,
    name: "",
    type_id: "",
    is_system: false,
    is_active: true,
};

export default function Index({ ledgerAccounts, types, permissions }: Props) {
    return (
        <AdminLayout title="Ledger Accounts">
            <IndexContent
                ledgerAccounts={ledgerAccounts}
                types={types}
                permissions={permissions}
            />
        </AdminLayout>
    );
}

type ContentProps = Pick<Props, "ledgerAccounts" | "types" | "permissions">;

function IndexContent({ ledgerAccounts, types, permissions }: ContentProps) {
    const { errors } = usePage<Props>().props;
    const { dark } = useTheme();

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const inputBorder = dark ? "#4B5563" : "#9CA3AF";
    const inputBg = dark ? "#111827" : "#FFFFFF";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const headerBg = dark ? "rgba(29,158,117,0.15)" : "#EAF5F0";
    const headerText = "#1D9E75";
    const rowAlt = dark ? "#111827" : "#F9FAFB";
    const errorTextStyle = {
        color: "#DC2626",
        fontSize: "14px",
        marginTop: "4px",
    };

    const [form, setForm] = useState(emptyForm);
    const [submitting, setSubmitting] = useState(false);
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [submitError, setSubmitError] = useState<string | null>(null);

    const [navLoading, setNavLoading] = useState(false);
    const [deletingId, setDeletingId] = useState<number | null>(null);
    const [rowError, setRowError] = useState<{
        id: number;
        message: string;
    } | null>(null);

    const [typeModalOpen, setTypeModalOpen] = useState(false);

    const inputStyle = {
        border: `1px solid ${inputBorder}`,
        background: inputBg,
        color: text,
        padding: "10px 12px",
        fontSize: "17px",
        outline: "none",
        fontFamily: "inherit",
        width: "100%",
    };

    const labelStyle = {
        display: "flex",
        alignItems: "center",
        gap: "6px",
        fontSize: "15px",
        fontWeight: 600,
        color: text,
        marginBottom: "6px",
    };

    const plusButtonStyle = {
        color: "#1D9E75",
        background: "transparent",
        border: `1px solid #1D9E75`,
        borderRadius: "999px",
        width: "18px",
        height: "18px",
        lineHeight: "16px",
        fontSize: "13px",
        fontWeight: 700,
        cursor: "pointer",
        padding: 0,
    };

    const startEdit = (account: LedgerAccountData) => {
        setForm({
            id: account.id,
            name: account.name,
            type_id: account.type_id ? String(account.type_id) : "",
            is_system: account.is_system,
            is_active: account.is_active,
        });
        setFormErrors({});
        setSubmitError(null);
        window.scrollTo({ top: 0, behavior: "smooth" });
    };

    const cancelEdit = () => {
        setForm(emptyForm);
        setFormErrors({});
        setSubmitError(null);
    };

    const submitForm = () => {
        if (!form.name.trim()) {
            setSubmitError("Name is required.");
            return;
        }
        setSubmitError(null);

        const payload = {
            name: form.name,
            type_id: form.type_id || null,
            is_active: form.is_active,
            ...(!form.id ? { is_system: form.is_system } : {}),
        };

        setSubmitting(true);

        const options = {
            preserveScroll: true,
            onError: (errs: Record<string, string>) => setFormErrors(errs),
            onSuccess: () => cancelEdit(),
            onFinish: () => setSubmitting(false),
        };

        if (form.id) {
            router.put(
                route("admin.ledger-accounts.update", form.id),
                payload,
                options,
            );
        } else {
            router.post(route("admin.ledger-accounts.store"), payload, options);
        }
    };

    const destroy = (account: LedgerAccountData) => {
        if (
            !window.confirm(
                `Delete "${account.name}"? This cannot be undone from here.`,
            )
        )
            return;
        setRowError(null);
        setDeletingId(account.id);
        router.delete(route("admin.ledger-accounts.destroy", account.id), {
            preserveScroll: true,
            onError: (errs) =>
                setRowError({
                    id: account.id,
                    message: errs.name ?? "Could not delete this account.",
                }),
            onFinish: () => setDeletingId(null),
        });
    };

    const goToPage = (url: string) => {
        router.get(
            url,
            {},
            {
                preserveScroll: true,
                onStart: () => setNavLoading(true),
                onFinish: () => setNavLoading(false),
            },
        );
    };

    const hasActions = permissions.update || permissions.delete;

    return (
        <>
            {permissions.create || form.id ? (
                <div
                    className="mb-6"
                    style={{
                        background: surface,
                        border: `1px solid ${border}`,
                        padding: "20px",
                    }}
                >
                    <h3
                        style={{
                            fontSize: "18px",
                            fontWeight: 700,
                            color: text,
                            marginBottom: "16px",
                        }}
                    >
                        {form.id ? "Edit ledger account" : "New ledger account"}
                    </h3>

                    <div className="mb-4">
                        <label style={labelStyle}>Name</label>
                        <input
                            type="text"
                            value={form.name}
                            onChange={(event) =>
                                setForm((c) => ({
                                    ...c,
                                    name: event.target.value,
                                }))
                            }
                            style={inputStyle}
                        />
                        {(errors?.name || formErrors.name) && (
                            <p style={errorTextStyle}>
                                {errors?.name ?? formErrors.name}
                            </p>
                        )}
                    </div>

                    <div className="mb-4">
                        <label style={labelStyle}>
                            Type
                            {permissions.create && (
                                <button
                                    type="button"
                                    onClick={() => setTypeModalOpen(true)}
                                    style={plusButtonStyle}
                                >
                                    +
                                </button>
                            )}
                        </label>
                        <select
                            value={form.type_id}
                            onChange={(event) =>
                                setForm((c) => ({
                                    ...c,
                                    type_id: event.target.value,
                                }))
                            }
                            style={inputStyle}
                        >
                            <option value="">None</option>
                            {types.map((type) => (
                                <option key={type.id} value={type.id}>
                                    {type.name} ({type.normal_balance})
                                </option>
                            ))}
                        </select>
                        {(errors?.type_id || formErrors.type_id) && (
                            <p style={errorTextStyle}>
                                {errors?.type_id ?? formErrors.type_id}
                            </p>
                        )}
                    </div>

                    <div className="flex gap-6 mb-6">
                        {!form.id && (
                            <label
                                style={{
                                    display: "flex",
                                    alignItems: "center",
                                    gap: "8px",
                                    color: text,
                                    fontSize: "15px",
                                }}
                            >
                                <input
                                    type="checkbox"
                                    checked={form.is_system}
                                    onChange={(event) =>
                                        setForm((c) => ({
                                            ...c,
                                            is_system: event.target.checked,
                                        }))
                                    }
                                />
                                System account
                            </label>
                        )}
                        <label
                            style={{
                                display: "flex",
                                alignItems: "center",
                                gap: "8px",
                                color: text,
                                fontSize: "15px",
                            }}
                        >
                            <input
                                type="checkbox"
                                checked={form.is_active}
                                onChange={(event) =>
                                    setForm((c) => ({
                                        ...c,
                                        is_active: event.target.checked,
                                    }))
                                }
                            />
                            Active
                        </label>
                    </div>

                    {form.id && form.is_system && (
                        <p
                            style={{
                                color: textSecondary,
                                fontSize: "14px",
                                marginBottom: "16px",
                            }}
                        >
                            This is a system account — its "System account"
                            status can't be changed here.
                        </p>
                    )}

                    {submitError && (
                        <p style={{ ...errorTextStyle, marginBottom: "12px" }}>
                            {submitError}
                        </p>
                    )}

                    <div style={{ display: "flex", gap: "12px" }}>
                        <button
                            onClick={submitForm}
                            disabled={submitting}
                            style={{
                                background: "#1D9E75",
                                color: "#FFFFFF",
                                border: "none",
                                padding: "10px 20px",
                                fontSize: "17px",
                                fontWeight: 600,
                                cursor: submitting ? "not-allowed" : "pointer",
                                opacity: submitting ? 0.7 : 1,
                            }}
                        >
                            {form.id ? "Save changes" : "Add ledger account"}
                        </button>
                        {form.id && (
                            <button
                                onClick={cancelEdit}
                                style={{
                                    background: "transparent",
                                    color: textSecondary,
                                    border: `1px solid ${border}`,
                                    padding: "10px 20px",
                                    fontSize: "17px",
                                    cursor: "pointer",
                                }}
                            >
                                Cancel
                            </button>
                        )}
                    </div>
                </div>
            ) : null}

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
                                Type
                            </th>
                            <th
                                className="text-left px-4 py-3"
                                style={{ color: headerText, fontWeight: 700 }}
                            >
                                Normal Balance
                            </th>
                            <th
                                className="text-left px-4 py-3"
                                style={{ color: headerText, fontWeight: 700 }}
                            >
                                System
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
                        {navLoading && (
                            <TableSkeletonRows rows={5} columns={6} />
                        )}

                        {!navLoading && ledgerAccounts.data.length === 0 && (
                            <tr>
                                <td
                                    colSpan={6}
                                    className="px-4 py-6 text-center"
                                    style={{ color: textSecondary }}
                                >
                                    No ledger accounts yet.
                                </td>
                            </tr>
                        )}

                        {!navLoading &&
                            ledgerAccounts.data.map((account, index) =>
                                deletingId === account.id ? (
                                    <TableSkeletonRows
                                        key={account.id}
                                        rows={1}
                                        columns={6}
                                    />
                                ) : (
                                    <>
                                        <tr
                                            key={account.id}
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
                                                {account.name}
                                            </td>
                                            <td
                                                className="px-4 py-3"
                                                style={{ color: text }}
                                            >
                                                {account.type?.name ?? (
                                                    <span
                                                        style={{
                                                            color: textSecondary,
                                                        }}
                                                    >
                                                        None
                                                    </span>
                                                )}
                                            </td>
                                            <td
                                                className="px-4 py-3"
                                                style={{
                                                    color: text,
                                                    textTransform: "capitalize",
                                                }}
                                            >
                                                {account.normal_balance ?? (
                                                    <span
                                                        style={{
                                                            color: textSecondary,
                                                        }}
                                                    >
                                                        —
                                                    </span>
                                                )}
                                            </td>
                                            <td
                                                className="px-4 py-3"
                                                style={{ color: text }}
                                            >
                                                {account.is_system
                                                    ? "Yes"
                                                    : "No"}
                                            </td>
                                            <td className="px-4 py-3">
                                                <span
                                                    style={{
                                                        color: account.is_active
                                                            ? "#1D9E75"
                                                            : textSecondary,
                                                        fontWeight: 600,
                                                    }}
                                                >
                                                    {account.is_active
                                                        ? "Active"
                                                        : "Inactive"}
                                                </span>
                                            </td>
                                            {hasActions && (
                                                <td className="px-4 py-3">
                                                    <div
                                                        style={{
                                                            display: "flex",
                                                            gap: "12px",
                                                        }}
                                                    >
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
                                                                            account,
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
                                                        {permissions.delete &&
                                                            account.is_system && (
                                                                <span
                                                                    style={{
                                                                        color: textSecondary,
                                                                        fontSize:
                                                                            "14px",
                                                                    }}
                                                                >
                                                                    System
                                                                </span>
                                                            )}
                                                    </div>
                                                </td>
                                            )}
                                        </tr>
                                        {rowError?.id === account.id && (
                                            <tr>
                                                <td
                                                    colSpan={6}
                                                    className="px-4 pb-2"
                                                    style={{
                                                        color: "#DC2626",
                                                        fontSize: "14px",
                                                    }}
                                                >
                                                    {rowError.message}
                                                </td>
                                            </tr>
                                        )}
                                    </>
                                ),
                            )}
                    </tbody>
                </table>
            </div>

            {ledgerAccounts.links.length > 3 && (
                <div className="flex gap-2 mt-4 flex-wrap">
                    {ledgerAccounts.links.map((link, index) => (
                        <button
                            key={index}
                            disabled={!link.url}
                            onClick={() => link.url && goToPage(link.url)}
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

            {typeModalOpen && (
                <QuickAddModal
                    title="Ledger Account Types"
                    items={types}
                    loading={false}
                    permissions={permissions}
                    onClose={() => setTypeModalOpen(false)}
                    extraFieldRequired
                    extraFieldDefault="debit"
                    extraField={(value, onChange) => (
                        <select
                            value={value || "debit"}
                            onChange={(event) => onChange(event.target.value)}
                            style={inputStyle}
                        >
                            <option value="debit">Debit normal</option>
                            <option value="credit">Credit normal</option>
                        </select>
                    )}
                    onCreate={(name, normalBalance) =>
                        new Promise<void>((resolve, reject) => {
                            router.post(
                                route("admin.ledger-account-types.store"),
                                { name, normal_balance: normalBalance },
                                {
                                    preserveScroll: true,
                                    onSuccess: () => resolve(),
                                    onError: (errs) =>
                                        reject(
                                            new Error(
                                                errs.name ??
                                                    errs.normal_balance ??
                                                    "Could not save. Please try again.",
                                            ),
                                        ),
                                },
                            );
                        })
                    }
                    onUpdate={(id, name, normalBalance) =>
                        new Promise<void>((resolve, reject) => {
                            router.put(
                                route("admin.ledger-account-types.update", id),
                                { name, normal_balance: normalBalance },
                                {
                                    preserveScroll: true,
                                    onSuccess: () => resolve(),
                                    onError: (errs) =>
                                        reject(
                                            new Error(
                                                errs.name ??
                                                    errs.normal_balance ??
                                                    "Could not save. Please try again.",
                                            ),
                                        ),
                                },
                            );
                        })
                    }
                    onDelete={(id) =>
                        new Promise<void>((resolve, reject) => {
                            router.delete(
                                route("admin.ledger-account-types.destroy", id),
                                {
                                    preserveScroll: true,
                                    onSuccess: () => resolve(),
                                    onError: (errs) =>
                                        reject(
                                            new Error(
                                                errs.name ??
                                                    "Could not delete — still in use by an account.",
                                            ),
                                        ),
                                },
                            );
                        })
                    }
                />
            )}
        </>
    );
}
