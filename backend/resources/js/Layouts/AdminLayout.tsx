import { Head, Link, router, usePage } from "@inertiajs/react";
import { PropsWithChildren } from "react";
import {
    IconLayoutDashboard,
    IconLogout,
    IconPlant,
    IconShieldLock,
} from "@tabler/icons-react";
import { PageProps } from "@/types";

interface Props extends PropsWithChildren {
    title: string;
}

const navItems = [
    {
        label: "Dashboard",
        routeName: "admin.dashboard",
        icon: IconLayoutDashboard,
    },
    {
        label: "Roles & Permissions",
        routeName: "admin.permissions.roles.index",
        icon: IconShieldLock,
    },
];

export default function AdminLayout({ title, children }: Props) {
    const { auth } = usePage<PageProps>().props;

    const logout = () => {
        router.post(
            route("logout"),
            {},
            {
                onSuccess: () => {
                    window.history.replaceState({ loggedOut: true }, "");
                },
            },
        );
    };

    return (
        <div
            className="min-h-screen flex bg-gray-50"
            style={{ fontFamily: "'Inter', system-ui, sans-serif" }}
        >
            <Head title={title} />

            <aside
                className="hidden lg:flex flex-col flex-shrink-0"
                style={{ width: "238px", background: "#0F6E56" }}
            >
                <div className="flex items-center gap-3 px-6 py-6">
                    <div
                        className="flex items-center justify-center flex-shrink-0"
                        style={{
                            width: "42px",
                            height: "42px",
                            background: "#BA7517",
                        }}
                    >
                        <IconPlant size={24} color="#fff" />
                    </div>
                    <div>
                        {/* brand title sized to match the Login sidebar (21px), not the smaller default */}
                        <div
                            style={{
                                fontSize: "21px",
                                fontWeight: 700,
                                color: "#fff",
                                letterSpacing: "-0.2px",
                            }}
                        >
                            NkwaLedger
                        </div>
                        <div
                            style={{
                                fontSize: "13px",
                                fontWeight: 600,
                                color: "#A8D9C8",
                                textTransform: "uppercase",
                                letterSpacing: "0.8px",
                            }}
                        >
                            Admin
                        </div>
                    </div>
                </div>

                <nav className="flex-1 px-3 mt-4">
                    {navItems.map((item) => {
                        const active = route().current(item.routeName);

                        return (
                            <Link
                                key={item.routeName}
                                href={route(item.routeName)}
                                className="flex items-center gap-3 px-3 py-2.5 mb-1"
                                style={{
                                    fontSize: "17px",
                                    fontWeight: 600,
                                    color: active ? "#0F6E56" : "#EAF5F0",
                                    background: active
                                        ? "#EAF5F0"
                                        : "transparent",
                                }}
                            >
                                <item.icon
                                    size={20}
                                    color={active ? "#0F6E56" : "#A8D9C8"}
                                />
                                {item.label}
                            </Link>
                        );
                    })}
                </nav>
            </aside>

            <div className="flex-1 flex flex-col min-w-0">
                <header
                    className="flex items-center justify-between px-6 bg-white border-b"
                    style={{ height: "72px", borderColor: "#E5E7EB" }}
                >
                    {/* page title bumped up from the original 20px, stops short of the 38px hero size so it still fits a slim topbar */}
                    <h1
                        style={{
                            fontSize: "24px",
                            fontWeight: 700,
                            color: "#111827",
                            letterSpacing: "-0.3px",
                        }}
                    >
                        {title}
                    </h1>

                    <div className="flex items-center gap-4">
                        <span style={{ fontSize: "17px", color: "#374151" }}>
                            {auth.user?.first_name} {auth.user?.surname}
                        </span>
                        <button
                            onClick={logout}
                            className="flex items-center gap-2 px-3 py-2"
                            style={{
                                fontSize: "17px",
                                fontWeight: 600,
                                color: "#DC2626",
                                border: "1px solid #FCA5A5",
                                background: "#fff",
                            }}
                        >
                            <IconLogout size={18} />
                            Log out
                        </button>
                    </div>
                </header>

                <main className="flex-1 p-6">{children}</main>
            </div>
        </div>
    );
}
