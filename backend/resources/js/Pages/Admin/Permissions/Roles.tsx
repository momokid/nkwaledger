import AdminLayout from "@/Layouts/AdminLayout";
import { useTheme } from "@/Layouts/AuthenticatedLayout";
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
    return (
        <AdminLayout title="Roles & Permissions">
            <RolesContent
                roles={roles}
                modules={modules}
                standalone={standalone}
            />
        </AdminLayout>
    );
}

type ContentProps = Pick<Props, "roles" | "modules" | "standalone">;

function RolesContent({ roles, modules, standalone }: ContentProps) {
    const { errors } = usePage<Props>().props;
    const { dark } = useTheme();

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#D1D5DB" : "#374151";
    const headerBg = dark ? "rgba(29,158,117,0.15)" : "#EAF5F0";
    const headerText = "#1D9E75";
    const groupRowBg = dark ? "#111827" : "#F9FAFB";

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
        <>
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
                                Permission
                            </th>
                            {roles.map((role) => (
                                <th
                                    key={role.id}
                                    className="text-center px-4 py-3 capitalize"
                                    style={{
                                        color: headerText,
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
                                    style={{ borderTop: `1px solid ${border}` }}
                                >
                                    <td
                                        colSpan={roles.length + 1}
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
                                                    onChange={(e) =>
                                                        togglePermission(
                                                            role,
                                                            permission.id,
                                                            e.target.checked,
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
        </>
    );
}
