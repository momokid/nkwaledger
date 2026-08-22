import AdminLayout from "@/Layouts/AdminLayout";
import { useTheme } from "@/Layouts/AuthenticatedLayout";
import { router } from "@inertiajs/react";
import { PageProps } from "@/types";
import { useState } from "react";

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

function IndexContent({ staff, permissions }: ContentProps) {
    const { dark } = useTheme();
    const [loading, setLoading] = useState(false);
    const [resendingId, setResendingId] = useState<number | null>(null);

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const headerBg = dark ? "rgba(29,158,117,0.15)" : "#EAF5F0";
    const headerText = "#1D9E75";
    const rowAlt = dark ? "#111827" : "#F9FAFB";
    const skeleton = dark ? "#374151" : "#E5E7EB";

    const columns = permissions.create ? 6 : 5;

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

    const cell = "px-4 py-3";

    return (
        <>
            <div
                className="overflow-x-auto"
                style={{ background: surface, border: `1px solid ${border}` }}
            >
                <table className="min-w-full" style={{ fontSize: "17px" }}>
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
                                                        height: "14px",
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
                                    style={{ color: textSecondary }}
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
                                                    fontSize: "15px",
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
                                                fontSize: "15px",
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
                                            fontSize: "15px",
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
                                                        fontSize: "15px",
                                                        cursor:
                                                            resendingId ===
                                                            member.id
                                                                ? "wait"
                                                                : "pointer",
                                                        padding: 0,
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
                            padding: "6px 12px",
                            fontSize: "15px",
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
        </>
    );
}
