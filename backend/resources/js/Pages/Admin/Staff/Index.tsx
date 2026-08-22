import AdminLayout from "@/Layouts/AdminLayout";
import { useTheme } from "@/Layouts/AuthenticatedLayout";
import { type } from "@/theme/typography";
import { router, useForm } from "@inertiajs/react";
import { PageProps } from "@/types";
import { FormEvent, useState } from "react";

interface StaffData {
    id: number;
    surname: string;
    first_name: string;
    other_name: string | null;
    phone: string;
    email: string | null;
    role: string | null;
    is_active: boolean;
    is_activated: boolean;
    invited_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props extends PageProps {
    staff: { data: StaffData[]; links: PaginationLink[] };
    roles: string[];
    permissions: { create: boolean };
}

export default function Index(props: Props) {
    return (
        <AdminLayout title="Staff Accounts">
            <IndexContent {...props} />
        </AdminLayout>
    );
}

type ContentProps = Pick<Props, "staff" | "roles" | "permissions">;

function IndexContent({ staff, roles, permissions }: ContentProps) {
    const { dark } = useTheme();
    const [loading, setLoading] = useState(false);
    const [resendingId, setResendingId] = useState<number | null>(null);
    const [inviting, setInviting] = useState(false);

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

    const columns = permissions.create ? 6 : 5;
    const cell = "px-4 py-3";

    const form = useForm({
        surname: "",
        first_name: "",
        other_name: "",
        phone: "",
        email: "",
        role: "",
    });

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

    const openInvite = () => {
        form.clearErrors();
        setInviting(true);
    };

    const required = (): boolean => {
        const missing: Record<string, string> = {};

        if (!form.data.surname.trim())
            missing.surname = "We need their surname to set the account up.";
        if (!form.data.first_name.trim())
            missing.first_name = "We need their first name too.";
        if (!form.data.phone.trim())
            missing.phone =
                "The invitation code goes to this number, so it can't be blank.";
        if (!form.data.role)
            missing.role = "Choose what this person will do on NkwaLedger.";

        form.clearErrors();

        if (Object.keys(missing).length > 0) {
            form.setError(missing);
            return false;
        }

        return true;
    };

    // caught here so a blank field costs nothing, though the server checks again regardless
    const submitInvite = (event: FormEvent) => {
        event.preventDefault();

        if (!required()) return;

        form.post(route("admin.staff.store"), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setInviting(false);
            },
        });
    };

    // pagination swaps the whole table, so the rows become placeholders while it travels
    const visit = (url: string | null) => {
        if (!url) return;

        setLoading(true);
        router.visit(url, {
            preserveScroll: true,
            onFinish: () => setLoading(false),
        });
    };

    const resend = (member: StaffData) => {
        setResendingId(member.id);
        router.post(
            route("admin.staff.resend", member.id),
            {},
            {
                preserveScroll: true,
                onFinish: () => setResendingId(null),
            },
        );
    };

    const fullName = (member: StaffData) =>
        [member.surname, member.first_name, member.other_name]
            .filter(Boolean)
            .join(" ");

    return (
        <>
            {permissions.create && (
                <div className="flex justify-end mb-4">
                    <button
                        onClick={openInvite}
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
                        Invite staff
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
                            {["Name", "Phone", "Role", "Status", "Invited"].map(
                                (label) => (
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
                                ),
                            )}
                            {permissions.create && (
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

                        {!loading && staff.data.length === 0 && (
                            <tr>
                                <td
                                    colSpan={columns}
                                    className="px-4 py-6 text-center"
                                    style={{
                                        color: textSecondary,
                                        fontSize: type.body,
                                    }}
                                >
                                    No staff accounts yet. Invite your first
                                    agent, vet, adviser or supplier.
                                </td>
                            </tr>
                        )}

                        {!loading &&
                            staff.data.map((member, index) => (
                                <tr
                                    key={member.id}
                                    style={{
                                        borderTop: `1px solid ${border}`,
                                        background:
                                            index % 2 === 1
                                                ? rowAlt
                                                : "transparent",
                                    }}
                                >
                                    <td
                                        className={cell}
                                        style={{ color: text }}
                                    >
                                        {fullName(member)}
                                        {member.email && (
                                            <div
                                                style={{
                                                    fontSize: type.secondary,
                                                    color: textSecondary,
                                                }}
                                            >
                                                {member.email}
                                            </div>
                                        )}
                                    </td>
                                    <td
                                        className={cell}
                                        style={{ color: text }}
                                    >
                                        {member.phone}
                                    </td>
                                    <td
                                        className={cell}
                                        style={{
                                            color: text,
                                            textTransform: "capitalize",
                                        }}
                                    >
                                        {member.role}
                                    </td>
                                    <td className={cell}>
                                        <span
                                            style={{
                                                fontSize: type.secondary,
                                                fontWeight: 600,
                                                color: member.is_activated
                                                    ? "#1D9E75"
                                                    : "#BA7517",
                                            }}
                                        >
                                            {member.is_activated
                                                ? "Active"
                                                : "Awaiting activation"}
                                        </span>
                                    </td>
                                    <td
                                        className={cell}
                                        style={{
                                            color: textSecondary,
                                            fontSize: type.secondary,
                                        }}
                                    >
                                        {new Date(
                                            member.invited_at,
                                        ).toLocaleDateString("en-GB", {
                                            day: "numeric",
                                            month: "short",
                                            year: "numeric",
                                        })}
                                    </td>
                                    {permissions.create && (
                                        <td className={cell}>
                                            {!member.is_activated && (
                                                <button
                                                    onClick={() =>
                                                        resend(member)
                                                    }
                                                    disabled={
                                                        resendingId ===
                                                        member.id
                                                    }
                                                    style={{
                                                        color: "#1D9E75",
                                                        background:
                                                            "transparent",
                                                        border: "none",
                                                        fontWeight: 600,
                                                        fontSize:
                                                            type.secondary,
                                                        cursor:
                                                            resendingId ===
                                                            member.id
                                                                ? "wait"
                                                                : "pointer",
                                                        padding: 0,
                                                        fontFamily: "inherit",
                                                    }}
                                                >
                                                    {resendingId === member.id
                                                        ? "Sending..."
                                                        : "Resend invite"}
                                                </button>
                                            )}
                                        </td>
                                    )}
                                </tr>
                            ))}
                    </tbody>
                </table>
            </div>

            <div className="flex flex-wrap gap-2 mt-4">
                {staff.links.map((link) => (
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

            {inviting && (
                <div
                    onClick={() => setInviting(false)}
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
                            maxWidth: "540px",
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
                            Invite a staff member
                        </h2>
                        <p
                            style={{
                                margin: "6px 0 20px",
                                fontSize: type.body,
                                color: textSecondary,
                            }}
                        >
                            They'll get a code by SMS and set their own
                            password. You never choose it for them.
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
                            onSubmit={submitInvite}
                            style={{
                                opacity: form.processing ? 0.55 : 1,
                                pointerEvents: form.processing
                                    ? "none"
                                    : "auto",
                            }}
                        >
                            <div
                                style={{
                                    display: "grid",
                                    gridTemplateColumns: "1fr 1fr",
                                    gap: "0 16px",
                                }}
                            >
                                <Field
                                    label="Surname"
                                    error={form.errors.surname}
                                    text={text}
                                >
                                    <input
                                        type="text"
                                        placeholder="Mensah"
                                        value={form.data.surname}
                                        onChange={(e) =>
                                            form.setData(
                                                "surname",
                                                e.target.value,
                                            )
                                        }
                                        style={inputStyle}
                                    />
                                </Field>

                                <Field
                                    label="First name"
                                    error={form.errors.first_name}
                                    text={text}
                                >
                                    <input
                                        type="text"
                                        placeholder="Kofi"
                                        value={form.data.first_name}
                                        onChange={(e) =>
                                            form.setData(
                                                "first_name",
                                                e.target.value,
                                            )
                                        }
                                        style={inputStyle}
                                    />
                                </Field>
                            </div>

                            <Field
                                label="Other name (optional)"
                                error={form.errors.other_name}
                                text={text}
                            >
                                <input
                                    type="text"
                                    value={form.data.other_name}
                                    onChange={(e) =>
                                        form.setData(
                                            "other_name",
                                            e.target.value,
                                        )
                                    }
                                    style={inputStyle}
                                />
                            </Field>

                            <Field
                                label="Phone number"
                                error={form.errors.phone}
                                text={text}
                            >
                                <input
                                    type="tel"
                                    placeholder="0244 445 566"
                                    value={form.data.phone}
                                    onChange={(e) =>
                                        form.setData("phone", e.target.value)
                                    }
                                    style={inputStyle}
                                    autoComplete="off"
                                />
                            </Field>

                            <Field
                                label="Email (optional)"
                                error={form.errors.email}
                                text={text}
                            >
                                <input
                                    type="email"
                                    placeholder="kofi@nkwaledger.com"
                                    value={form.data.email}
                                    onChange={(e) =>
                                        form.setData("email", e.target.value)
                                    }
                                    style={inputStyle}
                                />
                            </Field>

                            <Field
                                label="Role"
                                error={form.errors.role}
                                text={text}
                            >
                                <select
                                    value={form.data.role}
                                    onChange={(e) =>
                                        form.setData("role", e.target.value)
                                    }
                                    style={{
                                        ...inputStyle,
                                        textTransform: "capitalize",
                                    }}
                                >
                                    <option value="">Choose a role</option>
                                    {roles.map((role) => (
                                        <option
                                            key={role}
                                            value={role}
                                            style={{
                                                textTransform: "capitalize",
                                            }}
                                        >
                                            {role}
                                        </option>
                                    ))}
                                </select>
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
                                        ? "Sending..."
                                        : "Send invitation"}
                                </button>

                                <button
                                    type="button"
                                    onClick={() => setInviting(false)}
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
