import AdminLayout from "@/Layouts/AdminLayout";
import { router, usePage } from "@inertiajs/react";
import { PageProps } from "@/types";

interface RoleData {
    id: number;
    name: string;
    permission_ids: number[];
}

interface PermissionItem {
    id: number;
    label: string;
}

interface ModuleGroup {
    label: string;
    permissions: PermissionItem[];
}

interface Props extends PageProps {
    roles: RoleData[];
    modules: ModuleGroup[];
    standalone: PermissionItem[];
}

export default function Roles({ roles, modules, standalone }: Props) {
    const { errors } = usePage<Props>().props;

    const togglePermission = (
        role: RoleData,
        permissionId: number,
        checked: boolean,
    ) => {
        const newPermissionIds = checked
            ? [...role.permission_ids, permissionId]
            : role.permission_ids.filter((id) => id !== permissionId);

        router.put(
            route("admin.permissions.roles.update", role.id),
            { permission_ids: newPermissionIds },
            { preserveScroll: true },
        );
    };

    // groups mirror the server's module structure, with a trailing "standalone" group for ungrouped permissions
    const groups: ModuleGroup[] = [
        ...modules,
        ...(standalone.length > 0
            ? [{ label: "System", permissions: standalone }]
            : []),
    ];

    return (
        <AdminLayout title="Roles & Permissions">
            {errors?.permission_ids && (
                <div
                    className="mb-4 px-4 py-3"
                    style={{
                        background: "#FEF2F2",
                        border: "1px solid #FCA5A5",
                        color: "#B91C1C",
                        fontSize: "17px",
                    }}
                >
                    {errors.permission_ids}
                </div>
            )}

            <div
                className="bg-white border overflow-x-auto"
                style={{ borderColor: "#E5E7EB" }}
            >
                <table className="min-w-full" style={{ fontSize: "17px" }}>
                    <thead>
                        <tr style={{ background: "#EAF5F0" }}>
                            <th
                                className="text-left px-4 py-3"
                                style={{ color: "#0F6E56", fontWeight: 700 }}
                            >
                                Permission
                            </th>
                            {roles.map((role) => (
                                <th
                                    key={role.id}
                                    className="text-center px-4 py-3 capitalize"
                                    style={{
                                        color: "#0F6E56",
                                        fontWeight: 700,
                                    }}
                                >
                                    {role.name}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {groups.map((group) => (
                            <>
                                {/* group header row, spans every role column plus the label column */}
                                <tr
                                    key={`group-${group.label}`}
                                    style={{ borderTop: "1px solid #E5E7EB" }}
                                >
                                    <td
                                        colSpan={roles.length + 1}
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
                                        {roles.map((role) => (
                                            <td
                                                key={role.id}
                                                className="text-center px-4 py-2"
                                            >
                                                <input
                                                    type="checkbox"
                                                    checked={role.permission_ids.includes(
                                                        permission.id,
                                                    )}
                                                    onChange={(event) =>
                                                        togglePermission(
                                                            role,
                                                            permission.id,
                                                            event.target
                                                                .checked,
                                                        )
                                                    }
                                                />
                                            </td>
                                        ))}
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
