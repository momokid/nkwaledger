import AdminLayout from "@/Layouts/AdminLayout";
import { useTheme } from "@/Layouts/AuthenticatedLayout";
import { type } from "@/theme/typography";
import { router, useForm } from "@inertiajs/react";
import { PageProps } from "@/types";
import { FormEvent, useState } from "react";

interface Period {
    id: number;
    name: string;
    starts_on: string;
    ends_on: string;
    status: string;
    closed_at: string | null;
    closed_by: string | null;
    reopened_at: string | null;
    reopened_by: string | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props extends PageProps {
    periods: { data: Period[]; links: PaginationLink[] };
    permissions: { create: boolean; close: boolean; reopen: boolean };
}

export default function Index(props: Props) {
    return (
        <AdminLayout title="Accounting Periods">
            <IndexContent {...props} />
        </AdminLayout>
    );
}

type ContentProps = Pick<Props, "periods" | "permissions">;

function IndexContent({ periods, permissions }: ContentProps) {
    const { dark } = useTheme();
    const [loading, setLoading] = useState(false);
    const [busyId, setBusyId] = useState<number | null>(null);
    const [adding, setAdding] = useState(false);

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const inputBorder = dark ? "#4B5563" : "#9CA3AF";
    const inputBg = dark ? "#111827" : "#FFFFFF";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const headerBg = dark ? "rgba(29,158,117,0.15)" : "#EAF5F0";
    const headerText = "#1D9E75";
    const rowAlt = dark ? "#111827" : "#F9FAFB";
    const skeleton = dark ? "#374151" : "#E5E7EB";

    const hasActions = permissions.close || permissions.reopen;
    const columns = hasActions ? 5 : 4;
    const cell = "px-4 py-3";

    const form = useForm({ name: "", starts_on: "", ends_on: "" });

    const inputStyle = {
        width: "100%",
        border: `1px solid ${inputBorder}`,
        background: inputBg,
        color: text,
        padding: "12px 14px",
        fontSize: type.input,
        outline: "none",
        fontFamily: "inherit",
    };

    const openAdd = () => {
        form.clearErrors();
        setAdding(true);
    };

    const required = (): boolean => {
        const missing: Record<string, string> = {};

        if (!form.data.name.trim())
            missing.name = "Give the period a name, like January 2026.";
        if (!form.data.starts_on)
            missing.starts_on = "Pick the first day of the period.";
        if (!form.data.ends_on)
            missing.ends_on = "Pick the last day of the period.";

        form.clearErrors();

        if (Object.keys(missing).length > 0) {
            form.setError(missing);
            return false;
        }

        return true;
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (!required()) return;

        form.post(route("admin.accounting-periods.store"), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setAdding(false);
            },
        });
    };

    const visit = (url: string | null) => {
        if (!url) return;

        setLoading(true);
        router.visit(url, {
            preserveScroll: true,
            onFinish: () => setLoading(false),
        });
    };

    const toggle = (period: Period) => {
        const closing = period.status === "open";

        if (
            !closing &&
            !window.confirm(
                `Reopen ${period.name}? Reports already built from it may change.`,
            )
        ) {
            return;
        }

        setBusyId(period.id);
        router.patch(
            closing
                ? route("admin.accounting-periods.close", period.id)
                : route("admin.accounting-periods.reopen", period.id),
            {},
            { preserveScroll: true, onFinish: () => setBusyId(null) },
        );
    };

    const asDate = (value: string) =>
        new Date(value).toLocaleDateString("en-GB", {
            day: "numeric",
            month: "short",
            year: "numeric",
        });

    const canToggle = (period: Period) =>
        period.status === "open" ? permissions.close : permissions.reopen;

    return (
        <>
            {permissions.create && (
                <div className="flex justify-end mb-4">
                    <button
                        onClick={openAdd}
                        style={{
                            background: "#1D9E75",
                            color: "#FFFFFF",
                            border: "none",
                            padding: "12px 20px",
                            fontSize: type.button,
                            fontWeight: 600,
                            cursor: "pointer",
                            fontFamily: "inherit",
                        }}
                    >
                        Add period
                    </button>
                </div>
            )}

            <div
                className="overflow-x-auto"
                style={{ background: surface, border: `1px solid ${border}` }}
            >
                <table
                    className="min-w-full"
                    style={{ fontSize: type.tableCell }}
                >
                    <thead>
                        <tr style={{ background: headerBg }}>
                            {["Period", "From", "To", "Status"].map((label) => (
                                <th
                                    key={label}
                                    className={`text-left ${cell}`}
                                    style={{
                                        color: headerText,
                                        fontWeight: 700,
                                        fontSize: type.tableHeader,
                                    }}
                                >
                                    {label}
                                </th>
                            ))}
                            {hasActions && (
                                <th
                                    className={`text-left ${cell}`}
                                    style={{
                                        color: headerText,
                                        fontWeight: 700,
                                        fontSize: type.tableHeader,
                                    }}
                                >
                                    Actions
                                </th>
                            )}
                        </tr>
                    </thead>
                    <tbody>
                        {loading &&
                            Array.from({ length: 6 }).map((_, row) => (
                                <tr
                                    key={`placeholder-${row}`}
                                    style={{ borderTop: `1px solid ${border}` }}
                                >
                                    {Array.from({ length: columns }).map(
                                        (__, column) => (
                                            <td key={column} className={cell}>
                                                <div
                                                    style={{
                                                        height: "16px",
                                                        width:
                                                            column === 0
                                                                ? "70%"
                                                                : "50%",
                                                        background: skeleton,
                                                    }}
                                                />
                                            </td>
                                        ),
                                    )}
                                </tr>
                            ))}

                        {!loading && periods.data.length === 0 && (
                            <tr>
                                <td
                                    colSpan={columns}
                                    className="px-4 py-6 text-center"
                                    style={{
                                        color: textSecondary,
                                        fontSize: type.body,
                                    }}
                                >
                                    No periods yet. Add the first one so
                                    transactions have somewhere to land.
                                </td>
                            </tr>
                        )}

                        {!loading &&
                            periods.data.map((period, index) => {
                                const busy = busyId === period.id;
                                const open = period.status === "open";

                                return (
                                    <tr
                                        key={period.id}
                                        style={{
                                            borderTop: `1px solid ${border}`,
                                            background:
                                                index % 2 === 1
                                                    ? rowAlt
                                                    : "transparent",
                                            opacity: busy ? 0.5 : 1,
                                        }}
                                    >
                                        <td
                                            className={cell}
                                            style={{
                                                color: text,
                                                fontWeight: 600,
                                            }}
                                        >
                                            {period.name}
                                        </td>
                                        <td
                                            className={cell}
                                            style={{ color: text }}
                                        >
                                            {asDate(period.starts_on)}
                                        </td>
                                        <td
                                            className={cell}
                                            style={{ color: text }}
                                        >
                                            {asDate(period.ends_on)}
                                        </td>
                                        <td className={cell}>
                                            <span
                                                style={{
                                                    fontSize: type.secondary,
                                                    fontWeight: 600,
                                                    color: open
                                                        ? "#1D9E75"
                                                        : "#BA7517",
                                                }}
                                            >
                                                {open ? "Open" : "Closed"}
                                            </span>
                                            {!open && period.closed_at && (
                                                <div
                                                    style={{
                                                        fontSize: type.hint,
                                                        color: textSecondary,
                                                    }}
                                                >
                                                    by {period.closed_by} on{" "}
                                                    {asDate(period.closed_at)}
                                                </div>
                                            )}
                                            {open && period.reopened_at && (
                                                <div
                                                    style={{
                                                        fontSize: type.hint,
                                                        color: textSecondary,
                                                    }}
                                                >
                                                    reopened by{" "}
                                                    {period.reopened_by} on{" "}
                                                    {asDate(period.reopened_at)}
                                                </div>
                                            )}
                                        </td>
                                        {hasActions && (
                                            <td className={cell}>
                                                {canToggle(period) && (
                                                    <button
                                                        onClick={() =>
                                                            toggle(period)
                                                        }
                                                        disabled={busy}
                                                        style={{
                                                            color: open
                                                                ? "#BA7517"
                                                                : "#1D9E75",
                                                            background:
                                                                "transparent",
                                                            border: "none",
                                                            fontWeight: 600,
                                                            fontSize:
                                                                type.secondary,
                                                            cursor: busy
                                                                ? "wait"
                                                                : "pointer",
                                                            padding: 0,
                                                            fontFamily:
                                                                "inherit",
                                                        }}
                                                    >
                                                        {open
                                                            ? "Close"
                                                            : "Reopen"}
                                                    </button>
                                                )}
                                            </td>
                                        )}
                                    </tr>
                                );
                            })}
                    </tbody>
                </table>
            </div>

            <div className="flex flex-wrap gap-2 mt-4">
                {periods.links.map((link) => (
                    <button
                        key={link.label}
                        onClick={() => visit(link.url)}
                        disabled={!link.url || loading}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                        style={{
                            padding: "8px 14px",
                            fontSize: type.secondary,
                            border: `1px solid ${link.active ? "#1D9E75" : border}`,
                            background: link.active ? "#1D9E75" : surface,
                            color: link.active
                                ? "#FFFFFF"
                                : link.url
                                  ? text
                                  : textSecondary,
                            cursor:
                                link.url && !loading
                                    ? "pointer"
                                    : "not-allowed",
                            fontFamily: "inherit",
                        }}
                    />
                ))}
            </div>

            {adding && (
                <div
                    onClick={() => setAdding(false)}
                    style={{
                        position: "fixed",
                        inset: 0,
                        background: "rgba(17,24,39,0.55)",
                        display: "flex",
                        alignItems: "flex-start",
                        justifyContent: "center",
                        padding: "40px 20px",
                        zIndex: 70,
                        overflowY: "auto",
                    }}
                >
                    <div
                        onClick={(event) => event.stopPropagation()}
                        style={{
                            background: surface,
                            border: `1px solid ${border}`,
                            width: "100%",
                            maxWidth: "480px",
                            padding: "28px",
                        }}
                    >
                        <h2
                            style={{
                                margin: 0,
                                fontSize: type.sectionTitle,
                                fontWeight: 700,
                                color: text,
                            }}
                        >
                            Add an accounting period
                        </h2>
                        <p
                            style={{
                                margin: "6px 0 20px",
                                fontSize: type.body,
                                color: textSecondary,
                            }}
                        >
                            Every transaction belongs to the period its date
                            falls in. Periods cannot overlap.
                        </p>

                        {Object.keys(form.errors).length > 0 && (
                            <div
                                role="alert"
                                style={{
                                    marginBottom: "16px",
                                    padding: "12px 14px",
                                    background: dark
                                        ? "rgba(220,38,38,0.12)"
                                        : "#FEF2F2",
                                    borderLeft: "4px solid #DC2626",
                                    fontSize: type.body,
                                    color: dark ? "#FCA5A5" : "#991B1B",
                                }}
                            >
                                A few things need your attention below.
                            </div>
                        )}

                        <form
                            onSubmit={submit}
                            style={{
                                opacity: form.processing ? 0.55 : 1,
                                pointerEvents: form.processing
                                    ? "none"
                                    : "auto",
                            }}
                        >
                            <Field
                                label="Name"
                                error={form.errors.name}
                                text={text}
                            >
                                <input
                                    type="text"
                                    placeholder="January 2026"
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData("name", e.target.value)
                                    }
                                    style={inputStyle}
                                />
                            </Field>

                            <Field
                                label="First day"
                                error={form.errors.starts_on}
                                text={text}
                            >
                                <input
                                    type="date"
                                    value={form.data.starts_on}
                                    onChange={(e) =>
                                        form.setData(
                                            "starts_on",
                                            e.target.value,
                                        )
                                    }
                                    style={inputStyle}
                                />
                            </Field>

                            <Field
                                label="Last day"
                                error={form.errors.ends_on}
                                text={text}
                            >
                                <input
                                    type="date"
                                    value={form.data.ends_on}
                                    onChange={(e) =>
                                        form.setData("ends_on", e.target.value)
                                    }
                                    style={inputStyle}
                                />
                            </Field>

                            <div className="flex gap-3 mt-2">
                                <button
                                    type="submit"
                                    disabled={form.processing}
                                    style={{
                                        flex: 1,
                                        background: form.processing
                                            ? "#6B7280"
                                            : "#1D9E75",
                                        color: "#FFFFFF",
                                        border: "none",
                                        padding: "13px 20px",
                                        fontSize: type.button,
                                        fontWeight: 600,
                                        cursor: form.processing
                                            ? "not-allowed"
                                            : "pointer",
                                        fontFamily: "inherit",
                                    }}
                                >
                                    {form.processing
                                        ? "Saving..."
                                        : "Add period"}
                                </button>

                                <button
                                    type="button"
                                    onClick={() => setAdding(false)}
                                    style={{
                                        background: "transparent",
                                        color: text,
                                        border: `1px solid ${inputBorder}`,
                                        padding: "13px 20px",
                                        fontSize: type.button,
                                        cursor: "pointer",
                                        fontFamily: "inherit",
                                    }}
                                >
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </>
    );
}

interface FieldProps {
    label: string;
    error?: string;
    text: string;
    children: React.ReactNode;
}

function Field({ label, error, text, children }: FieldProps) {
    return (
        <div style={{ marginBottom: "18px" }}>
            <label
                style={{
                    display: "block",
                    fontSize: type.body,
                    fontWeight: 600,
                    color: text,
                    marginBottom: "6px",
                }}
            >
                {label}
            </label>
            {children}
            {error && (
                <p
                    style={{
                        marginTop: "5px",
                        fontSize: type.secondary,
                        color: "#DC2626",
                    }}
                >
                    {error}
                </p>
            )}
        </div>
    );
}
