import { Head, Link, router, usePage } from "@inertiajs/react";
import { PropsWithChildren, useState } from "react";
import {
    IconChevronLeft,
    IconChevronRight,
    IconLayoutDashboard,
    IconLogout,
    IconMoon,
    IconPlant,
    IconShieldLock,
    IconSun,
    IconUsers,
} from "@tabler/icons-react";
import { PageProps } from "@/types";
import { ThemeContext } from "@/Layouts/AuthenticatedLayout"; // reuses the same theme context shape, not the whole layout

interface Props extends PropsWithChildren {
    title: string;
}

interface NavItem {
    label: string;
    routeName: string;
    icon: typeof IconLayoutDashboard;
}

const navItems: NavItem[] = [
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
    {
        label: "User Access",
        routeName: "admin.permissions.users.index",
        icon: IconUsers,
    },
];

export default function AdminLayout({ title, children }: Props) {
    const { auth } = usePage<PageProps>().props;
    const [collapsed, setCollapsed] = useState(false);
    const [dark, setDark] = useState(false);

    const sidebarWidth = collapsed ? 56 : 220;
    const primary = "#1D9E75";
    const gold = "#BA7517";
    const pageBg = dark ? "#111827" : "#F9FAFB";
    const surface = dark ? "#1F2937" : "#FFFFFF";
    const sidebarBg = dark ? "#0B1220" : "#FFFFFF";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const hoverBg = dark ? "rgba(29,158,117,0.15)" : "#EAF5F0";

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
        <ThemeContext.Provider value={{ dark, toggle: () => setDark(!dark) }}>
            <div
                style={{
                    minHeight: "100vh",
                    background: pageBg,
                    fontFamily: "'Inter', system-ui, sans-serif",
                    color: text,
                }}
            >
                <Head title={title} />

                <aside
                    className="hidden lg:flex"
                    style={{
                        position: "fixed",
                        top: 0,
                        left: 0,
                        bottom: 0,
                        width: `${sidebarWidth}px`,
                        background: sidebarBg,
                        flexDirection: "column",
                        zIndex: 40,
                        transition: "width 0.15s ease",
                    }}
                >
                    <div
                        style={{
                            display: "flex",
                            alignItems: "center",
                            gap: "10px",
                            padding: "20px 16px",
                        }}
                    >
                        <div
                            style={{
                                width: "34px",
                                height: "34px",
                                background: gold,
                                display: "flex",
                                alignItems: "center",
                                justifyContent: "center",
                                flexShrink: 0,
                            }}
                        >
                            <IconPlant size={22} color="#fff" />
                        </div>
                        {!collapsed && (
                            <div>
                                <div
                                    style={{
                                        fontSize: "18px",
                                        fontWeight: 700,
                                        color: dark ? "#A8D9C8" : "#0F6E56",
                                        lineHeight: 1.2,
                                    }}
                                >
                                    NkwaLedger
                                </div>
                                <div
                                    style={{
                                        fontSize: "10px",
                                        color: textSecondary,
                                        textTransform: "uppercase",
                                        letterSpacing: "0.8px",
                                    }}
                                >
                                    Admin
                                </div>
                            </div>
                        )}
                    </div>

                    <nav style={{ flex: 1, overflowY: "auto" }}>
                        <div style={{ marginBottom: "20px" }}>
                            {!collapsed && (
                                <p
                                    style={{
                                        fontSize: "12px",
                                        fontWeight: 600,
                                        color: textSecondary,
                                        textTransform: "uppercase",
                                        letterSpacing: "1px",
                                        padding: "0 16px",
                                        marginBottom: "8px",
                                    }}
                                >
                                    Admin
                                </p>
                            )}
                            {navItems.map((item) => {
                                const active = route().current(item.routeName);

                                return (
                                    <Link
                                        key={item.routeName}
                                        href={route(item.routeName)}
                                        style={{
                                            display: "flex",
                                            alignItems: "center",
                                            gap: "12px",
                                            padding: "12px 16px",
                                            fontSize: "15px",
                                            fontWeight: active ? 600 : 400,
                                            color: active ? primary : text,
                                            background: active
                                                ? hoverBg
                                                : "transparent",
                                            borderLeft: active
                                                ? `3px solid ${primary}`
                                                : "3px solid transparent",
                                            textDecoration: "none",
                                            whiteSpace: "nowrap",
                                            overflow: "hidden",
                                        }}
                                    >
                                        <item.icon size={24} stroke={1.6} />
                                        {!collapsed && item.label}
                                    </Link>
                                );
                            })}
                        </div>
                    </nav>

                    <div style={{ padding: "16px" }}>
                        {!collapsed && (
                            <div style={{ marginBottom: "12px" }}>
                                <div
                                    style={{
                                        fontSize: "15px",
                                        fontWeight: 600,
                                        color: text,
                                    }}
                                >
                                    {auth.user?.first_name} {auth.user?.surname}
                                </div>
                                <div
                                    style={{
                                        fontSize: "13px",
                                        color: textSecondary,
                                    }}
                                >
                                    Admin
                                </div>
                            </div>
                        )}
                        <button
                            onClick={logout}
                            style={{
                                display: "flex",
                                alignItems: "center",
                                gap: "10px",
                                width: "100%",
                                background: "transparent",
                                border: "none",
                                color: textSecondary,
                                fontSize: "15px",
                                cursor: "pointer",
                                padding: "8px 0",
                                fontFamily: "inherit",
                            }}
                        >
                            <IconLogout size={22} stroke={1.6} />
                            {!collapsed && "Sign out"}
                        </button>
                    </div>
                </aside>

                <div
                    className="lg:block"
                    style={{
                        marginLeft: 0,
                        transition: "margin-left 0.15s ease",
                    }}
                >
                    <div
                        className="hidden lg:block"
                        style={{
                            marginLeft: `${sidebarWidth}px`,
                            transition: "margin-left 0.15s ease",
                        }}
                    >
                        <header
                            style={{
                                background: surface,
                                padding: "16px 24px",
                                display: "flex",
                                alignItems: "center",
                                justifyContent: "space-between",
                                position: "sticky",
                                top: 0,
                                zIndex: 30,
                            }}
                        >
                            <div
                                style={{
                                    display: "flex",
                                    alignItems: "center",
                                    gap: "14px",
                                }}
                            >
                                <button
                                    onClick={() => setCollapsed(!collapsed)}
                                    style={{
                                        background: "transparent",
                                        border: "none",
                                        cursor: "pointer",
                                        color: textSecondary,
                                        display: "flex",
                                    }}
                                >
                                    {collapsed ? (
                                        <IconChevronRight size={20} />
                                    ) : (
                                        <IconChevronLeft size={20} />
                                    )}
                                </button>
                                <h1
                                    style={{
                                        fontSize: "20px",
                                        fontWeight: 700,
                                        color: text,
                                        margin: 0,
                                    }}
                                >
                                    {title}
                                </h1>
                            </div>

                            <div
                                style={{
                                    display: "flex",
                                    alignItems: "center",
                                    gap: "16px",
                                }}
                            >
                                <button
                                    onClick={() => setDark(!dark)}
                                    style={{
                                        background: "transparent",
                                        border: "none",
                                        cursor: "pointer",
                                        color: textSecondary,
                                        display: "flex",
                                    }}
                                >
                                    {dark ? (
                                        <IconSun size={20} />
                                    ) : (
                                        <IconMoon size={20} />
                                    )}
                                </button>
                            </div>
                        </header>

                        <main style={{ padding: "24px" }}>{children}</main>
                    </div>

                    {/* below the lg breakpoint, sidebar is hidden and content renders full-width, matching admin's web-only scope */}
                    <div className="lg:hidden">
                        <header
                            style={{
                                background: surface,
                                padding: "16px 24px",
                            }}
                        >
                            <h1
                                style={{
                                    fontSize: "20px",
                                    fontWeight: 700,
                                    color: text,
                                    margin: 0,
                                }}
                            >
                                {title}
                            </h1>
                        </header>
                        <main style={{ padding: "24px" }}>{children}</main>
                    </div>
                </div>
            </div>
        </ThemeContext.Provider>
    );
}
