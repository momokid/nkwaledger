import AdminLayout from "@/Layouts/AdminLayout";
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
    const [search, setSearch] = useState(query);

    const runSearch = (value: string) => {
        setSearch(value);
        router.get(
            route("admin.permissions.users.index"),
            { q: value },
            { preserveState: true, replace: true },
        );
    };

    return (
        <AdminLayout title="User Access">
            <div
                className="bg-white border p-6"
                style={{ borderColor: "#E5E7EB" }}
            >
                <label
                    style={{
                        display: "block",
                        fontSize: "17px",
                        fontWeight: 600,
                        color: "#111827",
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
                        border: "1px solid #9CA3AF",
                        padding: "10px 12px",
                        fontSize: "17px",
                        color: "#111827",
                        outline: "none",
                        fontFamily: "inherit",
                    }}
                />

                {search !== "" && results.length === 0 && (
                    <p
                        style={{
                            marginTop: "16px",
                            fontSize: "17px",
                            color: "#6B7280",
                        }}
                    >
                        No users found.
                    </p>
                )}

                {results.length > 0 && (
                    <div
                        className="mt-6 border"
                        style={{ borderColor: "#E5E7EB" }}
                    >
                        {results.map((user) => (
                            <Link
                                key={user.id}
                                href={route(
                                    "admin.permissions.users.show",
                                    user.id,
                                )}
                                className="flex items-center justify-between px-4 py-3"
                                style={{
                                    borderTop: "1px solid #E5E7EB",
                                    textDecoration: "none",
                                    color: "#111827",
                                }}
                            >
                                <span
                                    style={{
                                        fontSize: "17px",
                                        fontWeight: 600,
                                    }}
                                >
                                    {user.first_name} {user.surname}
                                </span>
                                <span
                                    style={{
                                        fontSize: "15px",
                                        color: "#6B7280",
                                    }}
                                >
                                    {user.phone}
                                    {user.email ? ` · ${user.email}` : ""}
                                </span>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
