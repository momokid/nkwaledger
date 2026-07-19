import { Link, router, usePage } from "@inertiajs/react";
import {
    IconBell,
    IconChevronLeft,
    IconChevronRight,
    IconCloudRain,
    IconCreditCard,
    IconLayoutDashboard,
    IconLogout,
    IconMoon,
    IconNotebook,
    IconPlant,
    IconSettings,
    IconShoppingCart,
    IconStethoscope,
    IconSun,
    IconUser,
    IconUserCircle,
} from "@tabler/icons-react";
import {
    createContext,
    PropsWithChildren,
    useContext,
    useEffect,
    useRef,
    useState,
} from "react";

interface ThemeValue {
    dark: boolean;
    toggle: () => void;
}

export const ThemeContext = createContext<ThemeValue>({
    dark: false,
    toggle: () => {},
});

export function useTheme() {
    return useContext(ThemeContext);
}

interface NavItem {
    label: string;
    href: string;
    icon: typeof IconLayoutDashboard;
    roles: string[];
}

const navMain: NavItem[] = [
    {
        label: "Dashboard",
        href: "/farmer/dashboard",
        icon: IconLayoutDashboard,
        roles: ["farmer", "agent", "vet", "adviser", "admin", "supplier"],
    },
    {
        label: "Ledger",
        href: "#",
        icon: IconNotebook,
        roles: ["farmer", "agent", "admin"],
    },
    {
        label: "Crops",
        href: "#",
        icon: IconPlant,
        roles: ["farmer", "agent", "adviser", "admin"],
    },
    {
        label: "Livestock",
        href: "#",
        icon: IconStethoscope,
        roles: ["farmer", "agent", "vet", "admin"],
    },
    {
        label: "Credit",
        href: "#",
        icon: IconCreditCard,
        roles: ["farmer", "agent", "admin"],
    },
];

const navTools: NavItem[] = [
    {
        label: "Weather",
        href: "#",
        icon: IconCloudRain,
        roles: ["farmer", "agent", "adviser", "admin"],
    },
    {
        label: "Marketplace",
        href: "#",
        icon: IconShoppingCart,
        roles: ["farmer", "agent", "supplier", "admin"],
    },
    {
        label: "Consultations",
        href: "#",
        icon: IconStethoscope,
        roles: ["farmer", "vet", "adviser", "admin"],
    },
];

const navAccount: NavItem[] = [
    {
        label: "Profile",
        href: "#",
        icon: IconUser,
        roles: ["farmer", "agent", "vet", "adviser", "admin", "supplier"],
    },
    {
        label: "Settings",
        href: "#",
        icon: IconSettings,
        roles: ["farmer", "agent", "vet", "adviser", "admin", "supplier"],
    },
];

const sampleNotifications = [
    {
        id: 1,
        title: "Rain expected",
        body: "Heavy rainfall in your region within 48 hours.",
        time: "10 min ago",
        unread: true,
    },
    {
        id: 2,
        title: "Loan repayment due",
        body: "GH 500 due in 14 days.",
        time: "2 hours ago",
        unread: true,
    },
    {
        id: 3,
        title: "Vet consultation confirmed",
        body: "Dr Mensah will call you tomorrow at 9am.",
        time: "Yesterday",
        unread: false,
    },
];

interface PageProps {
    auth: {
        user: {
            first_name?: string;
            surname?: string;
            roles?: string[];
        } | null;
    };
}

interface Props extends PropsWithChildren {
    title: string;
}

export default function AuthenticatedLayout({ children, title }: Props) {
    const { auth } = usePage().props as unknown as PageProps;
    const user = auth?.user ?? null;
    const userRoles = user?.roles ?? ["farmer"];
    const firstName = user?.first_name ?? "Farmer";
    const surname = user?.surname ?? "";
    const primaryRole = userRoles[0] ?? "farmer";

    const [dark, setDark] = useState(false);
    const [collapsed, setCollapsed] = useState(false);
    const [isMobile, setIsMobile] = useState(false);
    const [hovered, setHovered] = useState<string | null>(null);
    const [bellOpen, setBellOpen] = useState(false);
    const [cogOpen, setCogOpen] = useState(false);
    const [notifications, setNotifications] = useState(sampleNotifications);

    const bellRef = useRef<HTMLDivElement>(null);
    const cogRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const savedTheme = localStorage.getItem("nkwa_theme");
        const savedCollapse = localStorage.getItem("nkwa_sidebar");
        if (savedTheme === "dark") setDark(true);
        if (savedCollapse === "collapsed") setCollapsed(true);
    }, []);

    useEffect(() => {
        const check = () => setIsMobile(window.innerWidth < 1024);
        check();
        window.addEventListener("resize", check);
        return () => window.removeEventListener("resize", check);
    }, []);

    useEffect(() => {
        const handleClick = (e: MouseEvent) => {
            if (
                bellRef.current &&
                !bellRef.current.contains(e.target as Node)
            ) {
                setBellOpen(false);
            }
            if (cogRef.current && !cogRef.current.contains(e.target as Node)) {
                setCogOpen(false);
            }
        };
        document.addEventListener("mousedown", handleClick);
        return () => document.removeEventListener("mousedown", handleClick);
    }, []);

    const toggleTheme = () => {
        setDark((prev) => {
            localStorage.setItem("nkwa_theme", !prev ? "dark" : "light");
            return !prev;
        });
    };

    const toggleCollapse = () => {
        setCollapsed((prev) => {
            localStorage.setItem(
                "nkwa_sidebar",
                !prev ? "collapsed" : "expanded",
            );
            return !prev;
        });
    };

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

    const markAllRead = () => {
        setNotifications((prev) => prev.map((n) => ({ ...n, unread: false })));
    };

    const unreadCount = notifications.filter((n) => n.unread).length;

    const pageBg = dark ? "#111827" : "#F9FAFB";
    const surface = dark ? "#1F2937" : "#FFFFFF";
    const sidebarBg = dark ? "#0B1220" : "#FFFFFF";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const hoverBg = dark ? "rgba(29,158,117,0.15)" : "#EAF5F0";
    const primary = "#1D9E75";
    const gold = "#BA7517";

    const sidebarWidth = collapsed ? 56 : 220;
    const currentPath =
        typeof window !== "undefined" ? window.location.pathname : "";

    const visible = (items: NavItem[]) =>
        items.filter((item) =>
            item.roles.some((role) => userRoles.includes(role)),
        );

    const mobileNav = visible(navMain).slice(0, 5);

    const renderSection = (label: string, items: NavItem[]) => {
        const shown = visible(items);
        if (shown.length === 0) return null;

        return (
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
                        {label}
                    </p>
                )}
                {shown.map((item) => {
                    const Icon = item.icon;
                    const active = currentPath === item.href;
                    return (
                        <div
                            key={item.label}
                            style={{ position: "relative" }}
                            onMouseEnter={() => setHovered(item.label)}
                            onMouseLeave={() => setHovered(null)}
                        >
                            <Link
                                href={item.href}
                                style={{
                                    display: "flex",
                                    alignItems: "center",
                                    gap: "12px",
                                    padding: collapsed
                                        ? "12px 16px"
                                        : "12px 16px",
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
                                    fontFamily:
                                        "'Inter', system-ui, sans-serif",
                                    whiteSpace: "nowrap",
                                    overflow: "hidden",
                                }}
                            >
                                <Icon size={24} stroke={1.6} />
                                {!collapsed && item.label}
                            </Link>

                            {collapsed && hovered === item.label && (
                                <span
                                    style={{
                                        position: "absolute",
                                        left: "60px",
                                        top: "50%",
                                        transform: "translateY(-50%)",
                                        background: dark
                                            ? "#374151"
                                            : "#111827",
                                        color: "#FFFFFF",
                                        fontSize: "14px",
                                        padding: "6px 12px",
                                        whiteSpace: "nowrap",
                                        zIndex: 60,
                                        fontFamily:
                                            "'Inter', system-ui, sans-serif",
                                    }}
                                >
                                    {item.label}
                                </span>
                            )}
                        </div>
                    );
                })}
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
                {!isMobile && (
                    <aside
                        style={{
                            position: "fixed",
                            top: 0,
                            left: 0,
                            bottom: 0,
                            width: `${sidebarWidth}px`,
                            background: sidebarBg,
                            display: "flex",
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
                                <IconPlant size={22} color="#FFFFFF" />
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
                                        Farm Finance
                                    </div>
                                </div>
                            )}
                        </div>

                        <nav style={{ flex: 1, overflowY: "auto" }}>
                            {renderSection("Main", navMain)}
                            {renderSection("Tools", navTools)}
                            {renderSection("Account", navAccount)}
                        </nav>

                        <div style={{ padding: "16px" }}>
                            <div
                                style={{
                                    display: "flex",
                                    alignItems: "center",
                                    gap: "10px",
                                    marginBottom: "12px",
                                }}
                            >
                                <div
                                    style={{
                                        width: "34px",
                                        height: "34px",
                                        background: primary,
                                        color: "#FFFFFF",
                                        display: "flex",
                                        alignItems: "center",
                                        justifyContent: "center",
                                        fontSize: "15px",
                                        fontWeight: 600,
                                        flexShrink: 0,
                                    }}
                                >
                                    {firstName.charAt(0).toUpperCase()}
                                </div>
                                {!collapsed && (
                                    <div style={{ overflow: "hidden" }}>
                                        <div
                                            style={{
                                                fontSize: "15px",
                                                fontWeight: 600,
                                                whiteSpace: "nowrap",
                                            }}
                                        >
                                            {firstName} {surname}
                                        </div>
                                        <div
                                            style={{
                                                fontSize: "12px",
                                                color: textSecondary,
                                                textTransform: "capitalize",
                                            }}
                                        >
                                            {primaryRole}
                                        </div>
                                    </div>
                                )}
                            </div>

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
                                    fontFamily:
                                        "'Inter', system-ui, sans-serif",
                                }}
                            >
                                <IconLogout size={22} stroke={1.6} />
                                {!collapsed && "Sign out"}
                            </button>
                        </div>
                    </aside>
                )}

                <div
                    style={{
                        marginLeft: isMobile ? 0 : `${sidebarWidth}px`,
                        paddingBottom: isMobile ? "72px" : 0,
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
                            {!isMobile && (
                                <button
                                    onClick={toggleCollapse}
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
                            )}
                            <h1
                                style={{
                                    fontSize: "20px",
                                    fontWeight: 600,
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
                                gap: "18px",
                            }}
                        >
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
                                    <IconSun size={24} stroke={1.6} />
                                ) : (
                                    <IconMoon size={24} stroke={1.6} />
                                )}
                            </button>

                            <div ref={bellRef} style={{ position: "relative" }}>
                                <button
                                    onClick={() => {
                                        setBellOpen(!bellOpen);
                                        setCogOpen(false);
                                    }}
                                    style={{
                                        background: "transparent",
                                        border: "none",
                                        cursor: "pointer",
                                        color: textSecondary,
                                        display: "flex",
                                        position: "relative",
                                    }}
                                >
                                    <IconBell size={24} stroke={1.6} />
                                    {unreadCount > 0 && (
                                        <span
                                            style={{
                                                position: "absolute",
                                                top: "-4px",
                                                right: "-4px",
                                                background: "#DC2626",
                                                color: "#FFFFFF",
                                                fontSize: "10px",
                                                fontWeight: 600,
                                                width: "16px",
                                                height: "16px",
                                                display: "flex",
                                                alignItems: "center",
                                                justifyContent: "center",
                                            }}
                                        >
                                            {unreadCount}
                                        </span>
                                    )}
                                </button>

                                {bellOpen && (
                                    <div
                                        style={{
                                            position: "absolute",
                                            right: 0,
                                            top: "36px",
                                            width: "320px",
                                            background: surface,
                                            border: `1px solid ${dark ? "#374151" : "#E5E7EB"}`,
                                            zIndex: 50,
                                        }}
                                    >
                                        <div
                                            style={{
                                                display: "flex",
                                                alignItems: "center",
                                                justifyContent: "space-between",
                                                padding: "12px 16px",
                                                borderBottom: `1px solid ${dark ? "#374151" : "#E5E7EB"}`,
                                            }}
                                        >
                                            <span
                                                style={{
                                                    fontSize: "15px",
                                                    fontWeight: 600,
                                                }}
                                            >
                                                Notifications
                                            </span>
                                            <button
                                                onClick={markAllRead}
                                                style={{
                                                    background: "transparent",
                                                    border: "none",
                                                    color: primary,
                                                    fontSize: "13px",
                                                    cursor: "pointer",
                                                    fontFamily:
                                                        "'Inter', system-ui, sans-serif",
                                                }}
                                            >
                                                Mark all read
                                            </button>
                                        </div>

                                        {notifications.map((n) => (
                                            <div
                                                key={n.id}
                                                style={{
                                                    padding: "12px 16px",
                                                    borderBottom: `1px solid ${dark ? "#374151" : "#F3F4F6"}`,
                                                    background: n.unread
                                                        ? hoverBg
                                                        : "transparent",
                                                }}
                                            >
                                                <p
                                                    style={{
                                                        fontSize: "15px",
                                                        fontWeight: 600,
                                                        margin: 0,
                                                    }}
                                                >
                                                    {n.title}
                                                </p>
                                                <p
                                                    style={{
                                                        fontSize: "14px",
                                                        color: textSecondary,
                                                        margin: "4px 0 0",
                                                        lineHeight: 1.5,
                                                    }}
                                                >
                                                    {n.body}
                                                </p>
                                                <p
                                                    style={{
                                                        fontSize: "12px",
                                                        color: textSecondary,
                                                        margin: "6px 0 0",
                                                    }}
                                                >
                                                    {n.time}
                                                </p>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>

                            <div ref={cogRef} style={{ position: "relative" }}>
                                <button
                                    onClick={() => {
                                        setCogOpen(!cogOpen);
                                        setBellOpen(false);
                                    }}
                                    style={{
                                        background: "transparent",
                                        border: "none",
                                        cursor: "pointer",
                                        color: textSecondary,
                                        display: "flex",
                                    }}
                                >
                                    <IconUserCircle size={26} stroke={1.6} />
                                </button>

                                {cogOpen && (
                                    <div
                                        style={{
                                            position: "absolute",
                                            right: 0,
                                            top: "36px",
                                            width: "220px",
                                            background: surface,
                                            border: `1px solid ${dark ? "#374151" : "#E5E7EB"}`,
                                            zIndex: 50,
                                        }}
                                    >
                                        <div
                                            style={{
                                                padding: "12px 16px",
                                                borderBottom: `1px solid ${dark ? "#374151" : "#E5E7EB"}`,
                                            }}
                                        >
                                            <p
                                                style={{
                                                    fontSize: "15px",
                                                    fontWeight: 600,
                                                    margin: 0,
                                                }}
                                            >
                                                {firstName} {surname}
                                            </p>
                                            <p
                                                style={{
                                                    fontSize: "12px",
                                                    color: textSecondary,
                                                    margin: "2px 0 0",
                                                    textTransform: "capitalize",
                                                }}
                                            >
                                                {primaryRole}
                                            </p>
                                        </div>

                                        {[
                                            "Profile",
                                            "My Activity",
                                            "Settings",
                                        ].map((label) => (
                                            <Link
                                                key={label}
                                                href="#"
                                                style={{
                                                    display: "block",
                                                    padding: "12px 16px",
                                                    fontSize: "15px",
                                                    color: text,
                                                    textDecoration: "none",
                                                    fontFamily:
                                                        "'Inter', system-ui, sans-serif",
                                                }}
                                            >
                                                {label}
                                            </Link>
                                        ))}

                                        <button
                                            onClick={logout}
                                            style={{
                                                display: "block",
                                                width: "100%",
                                                textAlign: "left",
                                                padding: "12px 16px",
                                                fontSize: "15px",
                                                color: "#DC2626",
                                                background: "transparent",
                                                border: "none",
                                                borderTop: `1px solid ${dark ? "#374151" : "#E5E7EB"}`,
                                                cursor: "pointer",
                                                fontFamily:
                                                    "'Inter', system-ui, sans-serif",
                                            }}
                                        >
                                            Sign out
                                        </button>
                                    </div>
                                )}
                            </div>
                        </div>
                    </header>

                    <main style={{ padding: "24px" }}>{children}</main>
                </div>

                {isMobile && (
                    <nav
                        style={{
                            position: "fixed",
                            bottom: 0,
                            left: 0,
                            right: 0,
                            height: "72px",
                            background: surface,
                            display: "flex",
                            alignItems: "center",
                            justifyContent: "space-around",
                            zIndex: 40,
                        }}
                    >
                        {mobileNav.map((item) => {
                            const Icon = item.icon;
                            const active = currentPath === item.href;
                            return (
                                <Link
                                    key={item.label}
                                    href={item.href}
                                    style={{
                                        display: "flex",
                                        flexDirection: "column",
                                        alignItems: "center",
                                        color: active ? primary : textSecondary,
                                        fontSize: "13px",
                                        fontWeight: active ? 600 : 400,
                                        paddingTop: "6px",
                                        textDecoration: "none",
                                        fontFamily:
                                            "'Inter', system-ui, sans-serif",
                                    }}
                                >
                                    <Icon size={24} stroke={1.6} />
                                    {item.label}
                                </Link>
                            );
                        })}
                    </nav>
                )}
            </div>
        </ThemeContext.Provider>
    );
}
