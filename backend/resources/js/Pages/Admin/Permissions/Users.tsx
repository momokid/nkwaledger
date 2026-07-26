import AdminLayout from "@/Layouts/AdminLayout";
import { useTheme } from "@/Layouts/AuthenticatedLayout";
import { Link, router } from "@inertiajs/react";
import { PageProps } from "@/types";
import { useState } from "react";

interface UserResult {
    id: number;
    first_name: string;
    surname: string;
    phone: string;
    email: string | null;
}

interface Props extends PageProps {
    query: string;
    results: UserResult[];
}

export default function Users({ query, results }: Props) {
    return (
        <AdminLayout title="User Access">
            <UsersContent query={query} results={results} />
        </AdminLayout>
    );
}

type ContentProps = Pick<Props, "query" | "results">;

function UsersContent({ query, results }: ContentProps) {
    const [search, setSearch] = useState(query);
    const { dark } = useTheme();

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const inputBorder = dark ? "#4B5563" : "#9CA3AF";
    const inputBg = dark ? "#111827" : "#FFFFFF";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const rowAlt = dark ? "#111827" : "#F9FAFB";
    const headerBg = dark ? "rgba(29,158,117,0.15)" : "#EAF5F0";

    const runSearch = (value: string) => {
        setSearch(value);
        router.get(
            route("admin.permissions.users.index"),
            { q: value },
            { preserveState: true, replace: true },
        );
    };

    return (
        <div
            className="p-6"
            style={{ background: surface, border: `1px solid ${border}` }}
        >
            <label
                style={{
                    display: "block",
                    fontSize: "17px",
                    fontWeight: 600,
                    color: text,
                    marginBottom: "6px",
                }}
            >
                Search by phone or email
            </label>
            <input
                type="text"
                value={search}
                onChange={(event) => runSearch(event.target.value)}
                placeholder="+233 XX XXX XXXX or email"
                style={{
                    width: "100%",
                    maxWidth: "420px",
                    border: `1px solid ${inputBorder}`,
                    background: inputBg,
                    padding: "10px 12px",
                    fontSize: "17px",
                    color: text,
                    outline: "none",
                    fontFamily: "inherit",
                }}
            />

            {search !== "" && results.length === 0 && (
                <p
                    style={{
                        marginTop: "16px",
                        fontSize: "17px",
                        color: textSecondary,
                    }}
                >
                    No users found.
                </p>
            )}

            {results.length > 0 && (
                <div className="mt-6 overflow-x-auto">
                    <table className="min-w-full" style={{ fontSize: "17px" }}>
                        <thead>
                            <tr style={{ background: headerBg }}>
                                <th
                                    className="text-left px-4 py-3"
                                    style={{
                                        color: "#1D9E75",
                                        fontWeight: 700,
                                    }}
                                >
                                    Name
                                </th>
                                <th
                                    className="text-left px-4 py-3"
                                    style={{
                                        color: "#1D9E75",
                                        fontWeight: 700,
                                    }}
                                >
                                    Phone
                                </th>
                                <th
                                    className="text-left px-4 py-3"
                                    style={{
                                        color: "#1D9E75",
                                        fontWeight: 700,
                                    }}
                                >
                                    Email
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {results.map((user, index) => (
                                <tr
                                    key={user.id}
                                    style={{
                                        borderTop: `1px solid ${border}`,
                                        background:
                                            index % 2 === 1
                                                ? rowAlt
                                                : "transparent",
                                    }}
                                >
                                    <td className="px-4 py-3">
                                        <Link
                                            href={route(
                                                "admin.permissions.users.show",
                                                user.id,
                                            )}
                                            style={{
                                                color: "#1D9E75",
                                                fontWeight: 600,
                                                textDecoration: "none",
                                            }}
                                        >
                                            {user.first_name} {user.surname}
                                        </Link>
                                    </td>
                                    <td
                                        className="px-4 py-3"
                                        style={{ color: text }}
                                    >
                                        {user.phone}
                                    </td>
                                    <td
                                        className="px-4 py-3"
                                        style={{ color: text }}
                                    >
                                        {user.email ?? "—"}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}
