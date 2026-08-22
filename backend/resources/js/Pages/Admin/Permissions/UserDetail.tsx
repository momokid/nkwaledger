import AdminLayout from "@/Layouts/AdminLayout";
import { useTheme } from "@/Layouts/AuthenticatedLayout";
import { router, usePage } from "@inertiajs/react";
import { PageProps } from "@/types";

interface PermissionItem {
    id: number;
    label: string;
    state: "default" | "grant" | "deny";
}

interface ModuleGroup {
    label: string;
    permissions: PermissionItem[];
}

interface UserSummary {
    id: number;
    first_name: string;
    surname: string;
    phone: string;
    email: string | null;
}

interface Props extends PageProps {
    user: UserSummary;
    currentRole: string | null;
    roles: string[];
    modules: ModuleGroup[];
    standalone: PermissionItem[];
}

export default function UserDetail({
    user,
    currentRole,
    roles,
    modules,
    standalone,
}: Props) {
    return (
        <AdminLayout title="User Access">
            <UserDetailContent
                user={user}
                currentRole={currentRole}
                roles={roles}
                modules={modules}
                standalone={standalone}
            />
        </AdminLayout>
    );
}

type ContentProps = Pick<
    Props,
    "user" | "currentRole" | "roles" | "modules" | "standalone"
>;

function UserDetailContent({
    user,
    currentRole,
    roles,
    modules,
    standalone,
}: ContentProps) {
    const { errors } = usePage<Props>().props;
    const { dark } = useTheme();

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const inputBorder = dark ? "#4B5563" : "#9CA3AF";
    const inputBg = dark ? "#111827" : "#FFFFFF";
    const groupRowBg = dark ? "#111827" : "#F9FAFB";

    const changeRole = (role: string) => {
        router.put(
            route("admin.permissions.users.role.update", user.id),
            { role },
            { preserveScroll: true },
        );
    };

    const grant = (permissionId: number) => {
        router.post(
            route("admin.permissions.users.grants.store", user.id),
            { permission_id: permissionId },
            { preserveScroll: true },
        );
    };

    const deny = (permissionId: number) => {
        router.post(
            route("admin.permissions.users.denials.store", user.id),
            { permission_id: permissionId },
            { preserveScroll: true },
        );
    };

    // clears whichever override currently applies, restoring the role default
    const clearOverride = (permission: PermissionItem) => {
        if (permission.state === "grant") {
            router.delete(
                route("admin.permissions.users.grants.destroy", [
                    user.id,
                    permission.id,
                ]),
                { preserveScroll: true },
            );
        } else if (permission.state === "deny") {
            router.delete(
                route("admin.permissions.users.denials.destroy", [
                    user.id,
                    permission.id,
                ]),
                { preserveScroll: true },
            );
        }
    };

    const groups: ModuleGroup[] = [
        ...modules,
        ...(standalone.length > 0
            ? [{ label: "System", permissions: standalone }]
            : []),
    ];

    const stateButtonStyle = (isActive: boolean, activeColor: string) => ({
        fontSize: "18px",
        fontWeight: 600,
        padding: "6px 12px",
        border: `1px solid ${isActive ? activeColor : dark ? "#4B5563" : "#D1D5DB"}`,
        color: isActive ? "#fff" : text,
        background: isActive ? activeColor : dark ? "#111827" : "#fff",
        cursor: isActive ? "not-allowed" : "pointer",
    });

    return (
        <>
            {errors?.role && (
                <div
                    className="mb-4 px-4 py-3"
                    style={{
                        background: "#FEF2F2",
                        border: "1px solid #FCA5A5",
                        color: "#B91C1C",
                        fontSize: "20px",
                    }}
                >
                    {errors.role}
                </div>
            )}

            {/* NOTE: this summary card is reconstructed from partial fragments — compare against
                the actual file, since the exact heading/phone/email layout may differ slightly */}
            <div
                className="p-6 mb-6"
                style={{ background: surface, border: `1px solid ${border}` }}
            >
                <h2 style={{ fontSize: "22px", fontWeight: 700, color: text }}>
                    {user.first_name} {user.surname}
                </h2>
                <div
                    style={{
                        fontSize: "20px",
                        color: textSecondary,
                        marginTop: "4px",
                    }}
                >
                    {user.phone}
                    {user.email ? ` · ${user.email}` : ""}
                </div>

                <div className="mt-4">
                    <label
                        style={{
                            display: "block",
                            fontSize: "20px",
                            fontWeight: 600,
                            color: text,
                            marginBottom: "6px",
                        }}
                    >
                        Role
                    </label>
                    <select
                        value={currentRole ?? ""}
                        onChange={(event) => changeRole(event.target.value)}
                        style={{
                            fontSize: "20px",
                            border: `1px solid ${inputBorder}`,
                            padding: "8px 12px",
                            color: text,
                            background: inputBg,
                            fontFamily: "inherit",
                        }}
                    >
                        {roles.map((role) => (
                            <option key={role} value={role}>
                                {role}
                            </option>
                        ))}
                    </select>
                </div>
            </div>

            <div
                className="overflow-x-auto"
                style={{ background: surface, border: `1px solid ${border}` }}
            >
                <table className="min-w-full" style={{ fontSize: "20px" }}>
                    <tbody>
                        {groups.map((group) => (
                            <>
                                <tr
                                    key={`group-${group.label}`}
                                    style={{ borderTop: `1px solid ${border}` }}
                                >
                                    <td
                                        colSpan={2}
                                        className="px-4 py-2"
                                        style={{
                                            background: groupRowBg,
                                            fontWeight: 700,
                                            color: text,
                                        }}
                                    >
                                        {group.label}
                                    </td>
                                </tr>
                                {group.permissions.map((permission) => (
                                    <tr
                                        key={permission.id}
                                        style={{
                                            borderTop: `1px solid ${border}`,
                                        }}
                                    >
                                        <td
                                            className="px-4 py-2 pl-8"
                                            style={{ color: textSecondary }}
                                        >
                                            {permission.label}
                                        </td>
                                        <td className="px-4 py-2">
                                            <div
                                                style={{
                                                    display: "flex",
                                                    gap: "8px",
                                                }}
                                            >
                                                <button
                                                    onClick={() =>
                                                        clearOverride(
                                                            permission,
                                                        )
                                                    }
                                                    disabled={
                                                        permission.state ===
                                                        "default"
                                                    }
                                                    style={stateButtonStyle(
                                                        permission.state ===
                                                            "default",
                                                        "#6B7280",
                                                    )}
                                                >
                                                    Default
                                                </button>
                                                <button
                                                    onClick={() =>
                                                        grant(permission.id)
                                                    }
                                                    disabled={
                                                        permission.state ===
                                                        "grant"
                                                    }
                                                    style={stateButtonStyle(
                                                        permission.state ===
                                                            "grant",
                                                        "#0F6E56",
                                                    )}
                                                >
                                                    Grant
                                                </button>
                                                <button
                                                    onClick={() =>
                                                        deny(permission.id)
                                                    }
                                                    disabled={
                                                        permission.state ===
                                                        "deny"
                                                    }
                                                    style={stateButtonStyle(
                                                        permission.state ===
                                                            "deny",
                                                        "#B91C1C",
                                                    )}
                                                >
                                                    Deny
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </>
                        ))}
                    </tbody>
                </table>
            </div>
        </>
    );
}
