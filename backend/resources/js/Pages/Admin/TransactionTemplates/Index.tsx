import AdminLayout from "@/Layouts/AdminLayout";
import { useTheme } from "@/Layouts/AuthenticatedLayout";
import TableSkeletonRows from "@/Components/Admin/TableSkeletonRows";
import { router, useForm, usePage } from "@inertiajs/react";
import { PageProps } from "@/types";
import { FormEvent, useEffect, useState } from "react";

interface Option {
    id: number;
    name: string;
}

interface AccountOption extends Option {
    account_code: string | null;
}

interface TemplateData {
    id: number;
    name: string;
    slug: string;
    transaction_type: string;
    debit_account_id: number;
    credit_account_id: number;
    settlement_side: string;
    requires_farm_unit: boolean;
    farm_type_category_id: number | null;
    is_system: boolean;
    is_used: boolean;
    is_active: boolean;
    debit_account: Option | null;
    credit_account: Option | null;
    farm_type_category: Option | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props extends PageProps {
    transactionTemplates: { data: TemplateData[]; links: PaginationLink[] };
    ledgerAccounts: AccountOption[];
    farmTypeCategories: Option[];
    transactionTypes: string[];
    settlementSides: string[];
    permissions: { create: boolean; update: boolean; delete: boolean };
}

export default function Index(props: Props) {
    return (
        <AdminLayout title="Transaction Templates">
            <IndexContent {...props} />
        </AdminLayout>
    );
}

type ContentProps = Pick<
    Props,
    | "transactionTemplates"
    | "ledgerAccounts"
    | "farmTypeCategories"
    | "transactionTypes"
    | "settlementSides"
    | "permissions"
>;

const toSlug = (value: string) =>
    value
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "_")
        .replace(/^_+|_+$/g, "");

function IndexContent({
    transactionTemplates,
    ledgerAccounts,
    farmTypeCategories,
    transactionTypes,
    settlementSides,
    permissions,
}: ContentProps) {
    const { errors } = usePage<Props>().props;
    const { dark } = useTheme();

    const [navLoading, setNavLoading] = useState(false);
    const [busyId, setBusyId] = useState<number | null>(null);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [slugTouched, setSlugTouched] = useState(false);

    const [editName, setEditName] = useState("");
    const [editSlug, setEditSlug] = useState("");
    const [editType, setEditType] = useState("");
    const [editDebitId, setEditDebitId] = useState("");
    const [editCreditId, setEditCreditId] = useState("");
    const [editSide, setEditSide] = useState("");
    const [editRequiresUnit, setEditRequiresUnit] = useState(false);
    const [editCategoryId, setEditCategoryId] = useState("");

    // full refetches through router.visit show the skeleton instead of stale rows
    useEffect(() => {
        const start = router.on("start", () => setNavLoading(true));
        const finish = router.on("finish", () => setNavLoading(false));
        return () => {
            start();
            finish();
        };
    }, []);

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
        slug: "",
        transaction_type: "",
        debit_account_id: "",
        credit_account_id: "",
        settlement_side: "none",
        requires_farm_unit: false as boolean,
        farm_type_category_id: "",
    });

    const setName = (value: string) => {
        createForm.setData("name", value);
        if (!slugTouched) {
            createForm.setData("slug", toSlug(value));
        }
    };

    const submitCreate = (event: FormEvent) => {
        event.preventDefault();
        createForm.post(route("admin.transaction-templates.store"), {
            preserveScroll: true,
            onSuccess: () => {
                createForm.reset("name", "slug");
                setSlugTouched(false);
            },
        });
    };

    const startEdit = (template: TemplateData) => {
        setEditingId(template.id);
        setEditName(template.name);
        setEditSlug(template.slug);
        setEditType(template.transaction_type);
        setEditDebitId(String(template.debit_account_id));
        setEditCreditId(String(template.credit_account_id));
        setEditSide(template.settlement_side);
        setEditRequiresUnit(template.requires_farm_unit);
        setEditCategoryId(
            template.farm_type_category_id === null
                ? ""
                : String(template.farm_type_category_id),
        );
    };

    const saveEdit = (id: number) => {
        setBusyId(id);
        router.put(
            route("admin.transaction-templates.update", id),
            {
                name: editName,
                slug: editSlug,
                transaction_type: editType,
                debit_account_id: Number(editDebitId),
                credit_account_id: Number(editCreditId),
                settlement_side: editSide,
                requires_farm_unit: editRequiresUnit,
                farm_type_category_id:
                    editCategoryId === "" ? null : Number(editCategoryId),
            },
            {
                preserveScroll: true,
                onSuccess: () => setEditingId(null),
                onFinish: () => setBusyId(null),
            },
        );
    };

    const destroy = (id: number, name: string) => {
        if (
            !window.confirm(
                `Remove "${name}"? Farmers will no longer see this entry.`,
            )
        )
            return;

        setBusyId(id);
        router.delete(route("admin.transaction-templates.destroy", id), {
            preserveScroll: true,
            onFinish: () => setBusyId(null),
        });
    };

    const hasActions = permissions.update || permissions.delete;
    const columnCount = hasActions ? 9 : 8;

    const inputStyle = {
        border: `1px solid ${inputBorder}`,
        background: inputBg,
        color: text,
        padding: "10px 12px",
        fontSize: "20px",
        outline: "none",
        fontFamily: "inherit",
    };

    const cellInput = { ...inputStyle, width: "100%", padding: "6px 8px" };

    const labelStyle = {
        display: "block",
        fontSize: "18px",
        fontWeight: 600,
        color: text,
        marginBottom: "6px",
    };

    const errorStyle = { color: "#DC2626", fontSize: "18px", marginTop: "4px" };
    const thStyle = { color: headerText, fontWeight: 700 };

    const accountLabel = (account: AccountOption) =>
        account.account_code
            ? `${account.account_code} ${account.name}`
            : account.name;

    const fieldError = (field: string) =>
        (errors as Record<string, string | undefined>)?.[field] ??
        (createForm.errors as Record<string, string | undefined>)[field];

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
                        <label style={labelStyle}>What the farmer sees</label>
                        <input
                            type="text"
                            value={createForm.data.name}
                            onChange={(e) => setName(e.target.value)}
                            placeholder="e.g. I sold crops"
                            style={{ ...inputStyle, width: "220px" }}
                        />
                        {fieldError("name") && (
                            <p style={errorStyle}>{fieldError("name")}</p>
                        )}
                    </div>

                    <div>
                        <label style={labelStyle}>Short code</label>
                        <input
                            type="text"
                            value={createForm.data.slug}
                            onChange={(e) => {
                                setSlugTouched(true);
                                createForm.setData("slug", e.target.value);
                            }}
                            placeholder="crop_sale"
                            style={{ ...inputStyle, width: "170px" }}
                        />
                        {fieldError("slug") && (
                            <p style={errorStyle}>{fieldError("slug")}</p>
                        )}
                    </div>

                    <div>
                        <label style={labelStyle}>Type</label>
                        <select
                            value={createForm.data.transaction_type}
                            onChange={(e) =>
                                createForm.setData(
                                    "transaction_type",
                                    e.target.value,
                                )
                            }
                            style={{ ...inputStyle, width: "160px" }}
                        >
                            <option value="">Select one</option>
                            {transactionTypes.map((type) => (
                                <option key={type} value={type}>
                                    {type}
                                </option>
                            ))}
                        </select>
                        {fieldError("transaction_type") && (
                            <p style={errorStyle}>
                                {fieldError("transaction_type")}
                            </p>
                        )}
                    </div>

                    <div>
                        <label style={labelStyle}>Value goes to</label>
                        <select
                            value={createForm.data.debit_account_id}
                            onChange={(e) =>
                                createForm.setData(
                                    "debit_account_id",
                                    e.target.value,
                                )
                            }
                            style={{ ...inputStyle, width: "240px" }}
                        >
                            <option value="">Select one</option>
                            {ledgerAccounts.map((account) => (
                                <option key={account.id} value={account.id}>
                                    {accountLabel(account)}
                                </option>
                            ))}
                        </select>
                        {fieldError("debit_account_id") && (
                            <p style={errorStyle}>
                                {fieldError("debit_account_id")}
                            </p>
                        )}
                    </div>

                    <div>
                        <label style={labelStyle}>Value comes from</label>
                        <select
                            value={createForm.data.credit_account_id}
                            onChange={(e) =>
                                createForm.setData(
                                    "credit_account_id",
                                    e.target.value,
                                )
                            }
                            style={{ ...inputStyle, width: "240px" }}
                        >
                            <option value="">Select one</option>
                            {ledgerAccounts.map((account) => (
                                <option key={account.id} value={account.id}>
                                    {accountLabel(account)}
                                </option>
                            ))}
                        </select>
                        {fieldError("credit_account_id") && (
                            <p style={errorStyle}>
                                {fieldError("credit_account_id")}
                            </p>
                        )}
                    </div>

                    <div>
                        <label style={labelStyle}>Payment replaces</label>
                        <select
                            value={createForm.data.settlement_side}
                            onChange={(e) =>
                                createForm.setData(
                                    "settlement_side",
                                    e.target.value,
                                )
                            }
                            style={{ ...inputStyle, width: "150px" }}
                        >
                            {settlementSides.map((side) => (
                                <option key={side} value={side}>
                                    {side}
                                </option>
                            ))}
                        </select>
                        {fieldError("settlement_side") && (
                            <p style={errorStyle}>
                                {fieldError("settlement_side")}
                            </p>
                        )}
                    </div>

                    <div>
                        <label style={labelStyle}>Only for</label>
                        <select
                            value={createForm.data.farm_type_category_id}
                            onChange={(e) =>
                                createForm.setData(
                                    "farm_type_category_id",
                                    e.target.value,
                                )
                            }
                            style={{ ...inputStyle, width: "180px" }}
                        >
                            <option value="">Every farmer</option>
                            {farmTypeCategories.map((category) => (
                                <option key={category.id} value={category.id}>
                                    {category.name}
                                </option>
                            ))}
                        </select>
                        {fieldError("farm_type_category_id") && (
                            <p style={errorStyle}>
                                {fieldError("farm_type_category_id")}
                            </p>
                        )}
                    </div>

                    <label
                        style={{
                            display: "flex",
                            alignItems: "center",
                            gap: "8px",
                            fontSize: "18px",
                            color: text,
                            paddingBottom: "10px",
                        }}
                    >
                        <input
                            type="checkbox"
                            checked={createForm.data.requires_farm_unit}
                            onChange={(e) =>
                                createForm.setData(
                                    "requires_farm_unit",
                                    e.target.checked,
                                )
                            }
                            style={{ width: "18px", height: "18px" }}
                        />
                        Ask which pen or plot
                    </label>

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
                        Add template
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
                            <th className="text-left px-4 py-3" style={thStyle}>
                                Farmer sees
                            </th>
                            <th className="text-left px-4 py-3" style={thStyle}>
                                Code
                            </th>
                            <th className="text-left px-4 py-3" style={thStyle}>
                                Type
                            </th>
                            <th className="text-left px-4 py-3" style={thStyle}>
                                Value to
                            </th>
                            <th className="text-left px-4 py-3" style={thStyle}>
                                Value from
                            </th>
                            <th className="text-left px-4 py-3" style={thStyle}>
                                Replaces
                            </th>
                            <th className="text-left px-4 py-3" style={thStyle}>
                                Pen or plot
                            </th>
                            <th className="text-left px-4 py-3" style={thStyle}>
                                Only for
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
                        {navLoading && (
                            <TableSkeletonRows rows={5} columns={columnCount} />
                        )}

                        {!navLoading &&
                            transactionTemplates.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={columnCount}
                                        className="px-4 py-6 text-center"
                                        style={{ color: textSecondary }}
                                    >
                                        No transaction templates yet.
                                    </td>
                                </tr>
                            )}

                        {!navLoading &&
                            transactionTemplates.data.map((template, index) => {
                                if (busyId === template.id) {
                                    return (
                                        <TableSkeletonRows
                                            key={template.id}
                                            rows={1}
                                            columns={columnCount}
                                        />
                                    );
                                }

                                const isEditing = editingId === template.id;
                                // the words stay open, the books do not
                                const locked =
                                    template.is_system || template.is_used;
                                const rowStyle = {
                                    borderTop: `1px solid ${border}`,
                                    background:
                                        index % 2 === 1
                                            ? rowAlt
                                            : "transparent",
                                };

                                return (
                                    <tr key={template.id} style={rowStyle}>
                                        <td
                                            className="px-4 py-3"
                                            style={{ color: text }}
                                        >
                                            {isEditing ? (
                                                <>
                                                    <input
                                                        type="text"
                                                        value={editName}
                                                        onChange={(e) =>
                                                            setEditName(
                                                                e.target.value,
                                                            )
                                                        }
                                                        style={cellInput}
                                                    />
                                                    {locked && (
                                                        <span
                                                            style={{
                                                                display:
                                                                    "block",
                                                                fontSize:
                                                                    "15px",
                                                                color: textSecondary,
                                                                marginTop:
                                                                    "4px",
                                                            }}
                                                        >
                                                            Only the words can
                                                            change here.
                                                        </span>
                                                    )}
                                                </>
                                            ) : (
                                                <span
                                                    style={{
                                                        display: "flex",
                                                        alignItems: "center",
                                                        gap: "8px",
                                                    }}
                                                >
                                                    {template.name}
                                                    {template.is_system && (
                                                        <span
                                                            style={{
                                                                fontSize:
                                                                    "17px",
                                                                fontWeight: 700,
                                                                color: "#BA7517",
                                                                border: "1px solid #BA7517",
                                                                padding:
                                                                    "1px 6px",
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
                                            style={{ color: textSecondary }}
                                        >
                                            {isEditing ? (
                                                <input
                                                    type="text"
                                                    value={editSlug}
                                                    onChange={(e) =>
                                                        setEditSlug(
                                                            e.target.value,
                                                        )
                                                    }
                                                    style={cellInput}
                                                />
                                            ) : (
                                                template.slug
                                            )}
                                        </td>
                                        <td
                                            className="px-4 py-3"
                                            style={{ color: text }}
                                        >
                                            {isEditing ? (
                                                <select
                                                    value={editType}
                                                    disabled={locked}
                                                    onChange={(e) =>
                                                        setEditType(
                                                            e.target.value,
                                                        )
                                                    }
                                                    style={cellInput}
                                                >
                                                    {transactionTypes.map(
                                                        (type) => (
                                                            <option
                                                                key={type}
                                                                value={type}
                                                            >
                                                                {type}
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                            ) : (
                                                template.transaction_type
                                            )}
                                        </td>
                                        <td
                                            className="px-4 py-3"
                                            style={{ color: text }}
                                        >
                                            {isEditing ? (
                                                <select
                                                    value={editDebitId}
                                                    onChange={(e) =>
                                                        setEditDebitId(
                                                            e.target.value,
                                                        )
                                                    }
                                                    style={cellInput}
                                                >
                                                    {ledgerAccounts.map(
                                                        (account) => (
                                                            <option
                                                                key={account.id}
                                                                value={
                                                                    account.id
                                                                }
                                                            >
                                                                {accountLabel(
                                                                    account,
                                                                )}
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                            ) : (
                                                (template.debit_account
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
                                                <select
                                                    value={editCreditId}
                                                    onChange={(e) =>
                                                        setEditCreditId(
                                                            e.target.value,
                                                        )
                                                    }
                                                    style={cellInput}
                                                >
                                                    {ledgerAccounts.map(
                                                        (account) => (
                                                            <option
                                                                key={account.id}
                                                                value={
                                                                    account.id
                                                                }
                                                            >
                                                                {accountLabel(
                                                                    account,
                                                                )}
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                            ) : (
                                                (template.credit_account
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
                                                <select
                                                    value={editSide}
                                                    onChange={(e) =>
                                                        setEditSide(
                                                            e.target.value,
                                                        )
                                                    }
                                                    style={cellInput}
                                                >
                                                    {settlementSides.map(
                                                        (side) => (
                                                            <option
                                                                key={side}
                                                                value={side}
                                                            >
                                                                {side}
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                            ) : (
                                                template.settlement_side
                                            )}
                                        </td>
                                        <td
                                            className="px-4 py-3"
                                            style={{ color: text }}
                                        >
                                            {isEditing ? (
                                                <input
                                                    type="checkbox"
                                                    checked={editRequiresUnit}
                                                    onChange={(e) =>
                                                        setEditRequiresUnit(
                                                            e.target.checked,
                                                        )
                                                    }
                                                    style={{
                                                        width: "18px",
                                                        height: "18px",
                                                    }}
                                                />
                                            ) : template.requires_farm_unit ? (
                                                "Yes"
                                            ) : (
                                                <span
                                                    style={{
                                                        color: textSecondary,
                                                    }}
                                                >
                                                    No
                                                </span>
                                            )}
                                        </td>
                                        <td
                                            className="px-4 py-3"
                                            style={{ color: text }}
                                        >
                                            {isEditing ? (
                                                <select
                                                    value={editCategoryId}
                                                    onChange={(e) =>
                                                        setEditCategoryId(
                                                            e.target.value,
                                                        )
                                                    }
                                                    style={cellInput}
                                                >
                                                    <option value="">
                                                        Every farmer
                                                    </option>
                                                    {farmTypeCategories.map(
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
                                                (template.farm_type_category
                                                    ?.name ?? (
                                                    <span
                                                        style={{
                                                            color: textSecondary,
                                                        }}
                                                    >
                                                        Every farmer
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
                                                                        template.id,
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
                                                                            template,
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
                                                                !template.is_system &&
                                                                !template.is_used && (
                                                                    <button
                                                                        onClick={() =>
                                                                            destroy(
                                                                                template.id,
                                                                                template.name,
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

            {transactionTemplates.links.length > 3 && (
                <div className="flex gap-2 mt-4 flex-wrap">
                    {transactionTemplates.links.map((link, index) => (
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
