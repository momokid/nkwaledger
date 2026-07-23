import AdminLayout from "@/Layouts/AdminLayout";
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

const stateStyles: Record<
    PermissionItem["state"],
    { label: string; color: string; background: string }
> = {
    default: { label: "Default", color: "#6B7280", background: "#F3F4F6" },
    grant: { label: "Granted", color: "#0F6E56", background: "#EAF5F0" },
    deny: { label: "Denied", color: "#B91C1C", background: "#FEF2F2" },
};

export default function UserDetail({
    user,
    currentRole,
    modules,
    standalone,
}: Props) {
    const groups: ModuleGroup[] = [
        ...modules,
        ...(standalone.length > 0
            ? [{ label: "System", permissions: standalone }]
            : []),
    ];

    return (
        <AdminLayout title="User Access">
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
                <div style={{ marginTop: "12px", fontSize: "17px" }}>
                    Role:{" "}
                    <span
                        style={{ fontWeight: 600, textTransform: "capitalize" }}
                    >
                        {currentRole ?? "None"}
                    </span>
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
                                {group.permissions.map((permission) => {
                                    const style = stateStyles[permission.state];

                                    return (
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
                                                <span
                                                    className="inline-block px-2 py-1"
                                                    style={{
                                                        fontSize: "15px",
                                                        fontWeight: 600,
                                                        color: style.color,
                                                        background:
                                                            style.background,
                                                    }}
                                                >
                                                    {style.label}
                                                </span>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </>
                        ))}
                    </tbody>
                </table>
            </div>
        </AdminLayout>
    );
}
