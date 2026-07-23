import AdminLayout from "@/Layouts/AdminLayout";
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
    const { errors } = usePage<Props>().props;

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
        fontSize: "15px",
        fontWeight: 600,
        padding: "6px 12px",
        border: `1px solid ${isActive ? activeColor : "#D1D5DB"}`,
        color: isActive ? "#fff" : "#374151",
        background: isActive ? activeColor : "#fff",
        cursor: isActive ? "default" : "pointer",
    });

    return (
        <AdminLayout title="User Access">
            {(errors?.role || errors?.permission) && (
                <div
                    className="mb-4 px-4 py-3"
                    style={{
                        background: "#FEF2F2",
                        border: "1px solid #FCA5A5",
                        color: "#B91C1C",
                        fontSize: "17px",
                    }}
                >
                    {errors.role || errors.permission}
                </div>
            )}

            <div
                className="bg-white border p-6 mb-6"
                style={{ borderColor: "#E5E7EB" }}
            >
                <div
                    style={{
                        fontSize: "20px",
                        fontWeight: 700,
                        color: "#111827",
                    }}
                >
                    {user.first_name} {user.surname}
                </div>
                <div
                    style={{
                        fontSize: "15px",
                        color: "#6B7280",
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
                            fontSize: "17px",
                            fontWeight: 600,
                            color: "#111827",
                            marginBottom: "6px",
                        }}
                    >
                        Role
                    </label>
                    <select
                        value={currentRole ?? ""}
                        onChange={(event) => changeRole(event.target.value)}
                        style={{
                            fontSize: "17px",
                            border: "1px solid #9CA3AF",
                            padding: "8px 12px",
                            color: "#111827",
                            background: "#fff",
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
                className="bg-white border overflow-x-auto"
                style={{ borderColor: "#E5E7EB" }}
            >
                <table className="min-w-full" style={{ fontSize: "17px" }}>
                    <tbody>
                        {groups.map((group) => (
                            <>
                                <tr
                                    key={`group-${group.label}`}
                                    style={{ borderTop: "1px solid #E5E7EB" }}
                                >
                                    <td
                                        colSpan={2}
                                        className="px-4 py-2"
                                        style={{
                                            background: "#F9FAFB",
                                            fontWeight: 700,
                                            color: "#111827",
                                        }}
                                    >
                                        {group.label}
                                    </td>
                                </tr>
                                {group.permissions.map((permission) => (
                                    <tr
                                        key={permission.id}
                                        style={{
                                            borderTop: "1px solid #E5E7EB",
                                        }}
                                    >
                                        <td
                                            className="px-4 py-2 pl-8"
                                            style={{ color: "#374151" }}
                                        >
                                            {permission.label}
                                        </td>
                                        <td className="px-4 py-2 text-right">
                                            <div className="inline-flex gap-2">
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
        </AdminLayout>
    );
}
