import { Head, Link, router, usePage } from "@inertiajs/react";
import VerificationGate from "@/Components/VerificationGate";
import useIsVerified from "@/hooks/useIsVerified";
import { PropsWithChildren, useEffect, useState } from "react";
import {
    IconCategory,
    IconChevronDown,
    IconChevronLeft,
    IconChevronRight,
    IconLayoutDashboard,
    IconLogout,
    IconMoon,
    IconPlant,
    IconShieldLock,
    IconSun,
    IconUsers,
    IconUsersGroup,
    IconBook,
    IconStack2,
    IconListDetails,
    IconUserPlus,
    IconHistory,
} from "@tabler/icons-react";
import FlashMessages from "@/Components/FlashMessages";
import { PageProps } from "@/types";
import { ThemeContext } from "@/Layouts/AuthenticatedLayout"; // reuses the same theme context shape, not the whole layout

interface Props extends PropsWithChildren {
    title: string;
}

interface NavLeaf {
    label: string;
    routeName: string;
    icon: typeof IconLayoutDashboard;
}

interface NavGroup {
    label: string;
    icon: typeof IconLayoutDashboard;
    children: NavLeaf[];
}

type NavEntry = NavLeaf | NavGroup;

function isGroup(entry: NavEntry): entry is NavGroup {
    return "children" in entry;
}

const navItems: NavEntry[] = [
    {
        label: "Dashboard",
        routeName: "admin.dashboard",
        icon: IconLayoutDashboard,
    },
    {
        label: "Farm Setup",
        icon: IconPlant,
        children: [
            {
                label: "Farm Type Categories",
                routeName: "admin.farm-type-categories.index",
                icon: IconCategory,
            },
            {
                label: "Farm Types",
                routeName: "admin.farm-types.index",
                icon: IconPlant,
            },
            {
                label: "Farmer Groups",
                routeName: "admin.farmer-groups.index",
                icon: IconUsersGroup,
            },
        ],
    },
    {
        label: "Ledger Setup",
        icon: IconBook,
        children: [
            {
                label: "Ledger Classes",
                routeName: "admin.ledger-classes.index",
                icon: IconListDetails,
            },
            {
                label: "Ledger Types",
                routeName: "admin.ledger-types.index",
                icon: IconListDetails,
            },
            {
                label: "Ledger Controls",
                routeName: "admin.ledger-controls.index",
                icon: IconListDetails,
            },
            {
                label: "Ledger Categories",
                routeName: "admin.ledger-categories.index",
                icon: IconStack2,
            },
            {
                label: "Ledger Subcategories",
                routeName: "admin.ledger-subcategories.index",
                icon: IconStack2,
            },
            {
                label: "Ledger Accounts",
                routeName: "admin.ledger-accounts.index",
                icon: IconBook,
            },
        ],
    },
    {
        label: "Access Control",
        icon: IconShieldLock,
        children: [
            {
                label: "Staff Accounts",
                routeName: "admin.staff.index",
                icon: IconUserPlus,
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
            {
                label: "Audit Log",
                routeName: "admin.audit.index",
                icon: IconHistory,
            },
        ],
    },
];

// a group is active if any of its children match the current route
function groupIsActive(group: NavGroup): boolean {
    return group.children.some((child) => route().current(child.routeName));
}

export default function AdminLayout({ title, children }: Props) {
    const { auth } = usePage<PageProps>().props;
    const [collapsed, setCollapsed] = useState(false);
    const [dark, setDark] = useState(false);
    const verified = useIsVerified();

    useEffect(() => {
        const saved = localStorage.getItem("nkwa_theme");
        if (saved === "dark") setDark(true);
    }, []);

    const toggleTheme = () => {
        setDark((previous) => {
            localStorage.setItem("nkwa_theme", !previous ? "dark" : "light");
            return !previous;
        });
    };
    const [hovered, setHovered] = useState<string | null>(null);
    const [expanded, setExpanded] = useState<string | null>(
        navItems.filter(isGroup).find(groupIsActive)?.label ?? null,
    );

    // wider than before, since the larger labels no longer fit the old width
    const sidebarWidth = collapsed ? 60 : 260;
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

    const toggleGroup = (label: string) => {
        if (collapsed) {
            // a collapsed sidebar has no room for a submenu flyout, so open the full sidebar first
            setCollapsed(false);
        }
        setExpanded((current) => (current === label ? null : label));
    };

    const renderLeaf = (item: NavLeaf, indented: boolean) => {
        const Icon = item.icon;
        const active = route().current(item.routeName);

        return (
            <div
                key={item.label}
                style={{ position: "relative" }}
                onMouseEnter={() => setHovered(item.label)}
                onMouseLeave={() => setHovered(null)}
            >
                <Link
                    href={route(item.routeName)}
                    style={{
                        display: "flex",
                        alignItems: "center",
                        gap: "12px",
                        padding:
                            indented && !collapsed
                                ? "11px 16px 11px 48px"
                                : "13px 16px",
                        fontSize: indented ? "18px" : "19px",
                        fontWeight: active ? 600 : 400,
                        color: active ? primary : text,
                        background: active ? hoverBg : "transparent",
                        borderLeft: active
                            ? `3px solid ${primary}`
                            : "3px solid transparent",
                        textDecoration: "none",
                        fontFamily: "'Inter', system-ui, sans-serif",
                        whiteSpace: "nowrap",
                        overflow: "hidden",
                    }}
                >
                    {!indented && <Icon size={26} stroke={1.6} />}
                    {!collapsed && item.label}
                </Link>

                {collapsed && hovered === item.label && (
                    <span
                        style={{
                            position: "absolute",
                            left: "64px",
                            top: "50%",
                            transform: "translateY(-50%)",
                            background: dark ? "#374151" : "#111827",
                            color: "#FFFFFF",
                            fontSize: "17px",
                            padding: "7px 14px",
                            whiteSpace: "nowrap",
                            zIndex: 60,
                            fontFamily: "'Inter', system-ui, sans-serif",
                        }}
                    >
                        {item.label}
                    </span>
                )}
            </div>
        );
    };

    const renderGroup = (group: NavGroup) => {
        const Icon = group.icon;
        const isOpen = expanded === group.label;
        const active = groupIsActive(group);

        return (
            <div key={group.label}>
                <div
                    style={{ position: "relative" }}
                    onMouseEnter={() => setHovered(group.label)}
                    onMouseLeave={() => setHovered(null)}
                >
                    <button
                        onClick={() => toggleGroup(group.label)}
                        style={{
                            display: "flex",
                            alignItems: "center",
                            gap: "12px",
                            width: "100%",
                            padding: "13px 16px",
                            fontSize: "19px",
                            fontWeight: active ? 600 : 400,
                            color: active ? primary : text,
                            background: "transparent",
                            border: "none",
                            borderLeft: "3px solid transparent",
                            cursor: "pointer",
                            fontFamily: "'Inter', system-ui, sans-serif",
                            whiteSpace: "nowrap",
                            overflow: "hidden",
                        }}
                    >
                        <Icon size={26} stroke={1.6} />
                        {!collapsed && (
                            <>
                                <span style={{ flex: 1, textAlign: "left" }}>
                                    {group.label}
                                </span>
                                <IconChevronDown
                                    size={18}
                                    stroke={2}
                                    style={{
                                        transform: isOpen
                                            ? "rotate(180deg)"
                                            : "rotate(0deg)",
                                        transition: "transform 0.15s ease",
                                    }}
                                />
                            </>
                        )}
                    </button>

                    {collapsed && hovered === group.label && (
                        <span
                            style={{
                                position: "absolute",
                                left: "64px",
                                top: "50%",
                                transform: "translateY(-50%)",
                                background: dark ? "#374151" : "#111827",
                                color: "#FFFFFF",
                                fontSize: "17px",
                                padding: "7px 14px",
                                whiteSpace: "nowrap",
                                zIndex: 60,
                                fontFamily: "'Inter', system-ui, sans-serif",
                            }}
                        >
                            {group.label}
                        </span>
                    )}
                </div>

                {!collapsed &&
                    isOpen &&
                    group.children.map((child) => renderLeaf(child, true))}
            </div>
        );
    };

    return (
        <ThemeContext.Provider value={{ dark, toggle: toggleTheme }}>
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
                                width: "38px",
                                height: "38px",
                                background: gold,
                                display: "flex",
                                alignItems: "center",
                                justifyContent: "center",
                                flexShrink: 0,
                            }}
                        >
                            <IconPlant size={24} color="#fff" />
                        </div>
                        {!collapsed && (
                            <div>
                                <div
                                    style={{
                                        fontSize: "22px",
                                        fontWeight: 700,
                                        color: text,
                                    }}
                                >
                                    NkwaLedger
                                </div>
                                <div
                                    style={{
                                        fontSize: "17px",
                                        color: textSecondary,
                                    }}
                                >
                                    Admin
                                </div>
                            </div>
                        )}
                    </div>

                    <nav
                        style={{
                            flex: 1,
                            overflowY: "auto",
                            // greyed until the phone is proved, the server does the real blocking
                            opacity: verified ? 1 : 0.4,
                            pointerEvents: verified ? "auto" : "none",
                        }}
                    >
                        <div style={{ marginBottom: "20px" }}>
                            {navItems.map((item) =>
                                isGroup(item)
                                    ? renderGroup(item)
                                    : renderLeaf(item, false),
                            )}
                        </div>
                    </nav>

                    <div style={{ padding: "16px" }}>
                        {!collapsed && (
                            <div style={{ marginBottom: "12px" }}>
                                <div
                                    style={{
                                        fontSize: "19px",
                                        fontWeight: 600,
                                        color: text,
                                    }}
                                >
                                    {auth.user?.first_name} {auth.user?.surname}
                                </div>
                                <div
                                    style={{
                                        fontSize: "17px",
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
                                fontSize: "19px",
                                cursor: "pointer",
                                padding: "8px 0",
                                fontFamily: "inherit",
                            }}
                        >
                            <IconLogout size={24} stroke={1.6} />
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
                                        <IconChevronRight size={24} />
                                    ) : (
                                        <IconChevronLeft size={24} />
                                    )}
                                </button>
                                <h1
                                    style={{
                                        fontSize: "26px",
                                        fontWeight: 700,
                                        color: text,
                                        margin: 0,
                                    }}
                                >
                                    {title}
                                </h1>
                            </div>
                            <div>
                                <button
                                    onClick={toggleTheme}
                                    style={{
                                        background: "transparent",
                                        border: "none",
                                        cursor: "pointer",
                                        color: textSecondary,
                                        display: "flex",
                                    }}
                                >
                                    {dark ? (
                                        <IconSun size={24} />
                                    ) : (
                                        <IconMoon size={24} />
                                    )}
                                </button>
                            </div>
                        </header>

                        <main style={{ padding: "24px" }}>
                            <VerificationGate>{children}</VerificationGate>
                        </main>
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
                                    fontSize: "26px",
                                    fontWeight: 700,
                                    color: text,
                                    margin: 0,
                                }}
                            >
                                {title}
                            </h1>
                        </header>
                        <main style={{ padding: "24px" }}>
                            <VerificationGate>{children}</VerificationGate>
                        </main>
                    </div>
                </div>
            </div>
            <FlashMessages />
        </ThemeContext.Provider>
    );
}
