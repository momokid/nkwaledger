import { Link, router, usePage } from "@inertiajs/react";
import {
    IconActivityHeartbeat,
    IconBell,
    IconBook,
    IconBuildingStore,
    IconCheck,
    IconChevronLeft,
    IconChevronRight,
    IconCloudRain,
    IconCreditCard,
    IconLayoutDashboard,
    IconLogout,
    IconMessage,
    IconMoon,
    IconPlant,
    IconPlant2,
    IconSettings,
    IconStethoscope,
    IconSun,
    IconUser,
} from "@tabler/icons-react";
import {
    createContext,
    PropsWithChildren,
    useContext,
    useEffect,
    useRef,
    useState,
} from "react";

interface Props extends PropsWithChildren {
    title?: string;
}

interface AuthUser {
    first_name: string;
    surname: string;
    roles?: { name: string }[];
}

interface PageProps {
    auth: { user: AuthUser | null };
}

export const ThemeContext = createContext<{
    dark: boolean;
    toggleDark: () => void;
}>({
    dark: false,
    toggleDark: () => {},
});

export function useTheme() {
    return useContext(ThemeContext);
}

interface NavItemDef {
    label: string;
    icon: React.ElementType;
    href: string;
    roles: string[];
}

const allNavMain: NavItemDef[] = [
    {
        label: "Dashboard",
        icon: IconLayoutDashboard,
        href: "/farmer/dashboard",
        roles: ["farmer", "agent", "admin", "vet", "adviser", "supplier"],
    },
    {
        label: "Ledger",
        icon: IconBook,
        href: "/ledger",
        roles: ["farmer", "agent", "admin"],
    },
    {
        label: "Crops",
        icon: IconPlant2,
        href: "/crops",
        roles: ["farmer", "agent", "adviser"],
    },
    {
        label: "Livestock",
        icon: IconStethoscope,
        href: "/livestock",
        roles: ["farmer", "agent", "vet"],
    },
    {
        label: "Credit",
        icon: IconCreditCard,
        href: "/credit",
        roles: ["farmer", "agent", "admin"],
    },
];

const allNavTools: NavItemDef[] = [
    {
        label: "Weather",
        icon: IconCloudRain,
        href: "/weather",
        roles: ["farmer", "agent", "adviser"],
    },
    {
        label: "Marketplace",
        icon: IconBuildingStore,
        href: "/marketplace",
        roles: ["farmer", "agent", "supplier"],
    },
    {
        label: "Consultations",
        icon: IconMessage,
        href: "/consultations",
        roles: ["farmer", "vet", "adviser"],
    },
];

const allNavAccount: NavItemDef[] = [
    {
        label: "Profile",
        icon: IconUser,
        href: "/profile",
        roles: ["farmer", "agent", "admin", "vet", "adviser", "supplier"],
    },
    {
        label: "Settings",
        icon: IconSettings,
        href: "/settings",
        roles: ["farmer", "agent", "admin", "vet", "adviser", "supplier"],
    },
];

const sampleNotifications = [
    {
        id: 1,
        icon: IconCloudRain,
        title: "Rain alert for your region",
        sub: "Heavy rainfall expected in 48 hours",
        time: "5 min ago",
        read: false,
    },
    {
        id: 2,
        icon: IconCreditCard,
        title: "Loan repayment due in 3 days",
        sub: "GH₵ 2,000 due on your active loan",
        time: "2 hrs ago",
        read: false,
    },
    {
        id: 3,
        icon: IconStethoscope,
        title: "VetAI diagnosis ready",
        sub: "Results available for your cattle",
        time: "Yesterday",
        read: true,
    },
    {
        id: 4,
        icon: IconBook,
        title: "Monthly ledger summary ready",
        sub: "View your income and expense report",
        time: "2 days ago",
        read: true,
    },
];

function filterNav(items: NavItemDef[], role: string) {
    return items.filter((item) => item.roles.includes(role));
}

function NavItem({
    label,
    icon: Icon,
    href,
    collapsed,
    active,
    dark,
}: {
    label: string;
    icon: React.ElementType;
    href: string;
    collapsed: boolean;
    active: boolean;
    dark: boolean;
}) {
    const [showTooltip, setShowTooltip] = useState(false);

    return (
        <div style={{ position: "relative" }}>
            <Link
                href={href}
                onMouseEnter={() => collapsed && setShowTooltip(true)}
                onMouseLeave={() => setShowTooltip(false)}
                style={{
                    display: "flex",
                    alignItems: "center",
                    gap: "12px",
                    padding: collapsed ? "10px 0" : "10px 16px",
                    justifyContent: collapsed ? "center" : "flex-start",
                    fontSize: "17px",
                    fontWeight: active ? 600 : 400,
                    color: active
                        ? "#1D9E75"
                        : dark
                          ? "rgba(255,255,255,0.65)"
                          : "#6B7280",
                    background: active ? "#EAF5F0" : "transparent",
                    textDecoration: "none",
                    borderLeft: active
                        ? "3px solid #1D9E75"
                        : "3px solid transparent",
                    fontFamily:
                        "'Inter', 'Segoe UI Variable', system-ui, sans-serif",
                }}
                onMouseOver={(e) => {
                    if (!active)
                        e.currentTarget.style.background = dark
                            ? "rgba(255,255,255,0.05)"
                            : "#F9FAFB";
                }}
                onMouseOut={(e) => {
                    if (!active)
                        e.currentTarget.style.background = "transparent";
                }}
            >
                <Icon size={22} style={{ flexShrink: 0 }} />
                {!collapsed && <span>{label}</span>}
            </Link>

            {collapsed && showTooltip && (
                <div
                    style={{
                        position: "absolute",
                        left: "110%",
                        top: "50%",
                        transform: "translateY(-50%)",
                        background: "#111827",
                        color: "#fff",
                        fontSize: "15px",
                        padding: "5px 12px",
                        whiteSpace: "nowrap",
                        zIndex: 100,
                        pointerEvents: "none",
                        fontFamily: "'Inter', system-ui, sans-serif",
                    }}
                >
                    {label}
                </div>
            )}
        </div>
    );
}

export default function AuthenticatedLayout({ children, title }: Props) {
    const { auth } = usePage().props as unknown as PageProps;
    const user = auth?.user;
    const userRole = user?.roles?.[0]?.name ?? "farmer";
    const userName = user ? `${user.first_name} ${user.surname}` : "Farmer";
    const userInitials = user
        ? `${user.first_name[0]}${user.surname[0]}`.toUpperCase()
        : "F";

    const [collapsed, setCollapsed] = useState<boolean>(() => {
        try {
            return localStorage.getItem("sidebar_collapsed") === "true";
        } catch {
            return false;
        }
    });

    const [dark, setDark] = useState<boolean>(() => {
        try {
            const stored = localStorage.getItem("theme");
            if (stored) return stored === "dark";
            return window.matchMedia("(prefers-color-scheme: dark)").matches;
        } catch {
            return false;
        }
    });

    const [isMobile, setIsMobile] = useState(false);
    const [showBell, setShowBell] = useState(false);
    const [showCog, setShowCog] = useState(false);
    const [notifications, setNotifications] = useState(sampleNotifications);

    const bellRef = useRef<HTMLDivElement>(null);
    const cogRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const check = () => setIsMobile(window.innerWidth < 1024);
        check();
        window.addEventListener("resize", check);
        return () => window.removeEventListener("resize", check);
    }, []);

    useEffect(() => {
        try {
            localStorage.setItem("sidebar_collapsed", String(collapsed));
        } catch {}
    }, [collapsed]);

    useEffect(() => {
        const handler = (e: MouseEvent) => {
            if (bellRef.current && !bellRef.current.contains(e.target as Node))
                setShowBell(false);
            if (cogRef.current && !cogRef.current.contains(e.target as Node))
                setShowCog(false);
        };
        document.addEventListener("mousedown", handler);
        return () => document.removeEventListener("mousedown", handler);
    }, []);

    const toggleDark = () => {
        setDark((d) => {
            const next = !d;
            try {
                localStorage.setItem("theme", next ? "dark" : "light");
            } catch {}
            return next;
        });
    };

    const currentPath = usePage().url;
    const logout = () =>
        router.post(
            route("logout"),
            {},
            {
                onSuccess: () => {
                    window.history.replaceState({ loggedOut: true }, "");
                },
            },
        );

    const markAllRead = () =>
        setNotifications((n) => n.map((item) => ({ ...item, read: true })));
    const unreadCount = notifications.filter((n) => !n.read).length;

    const navMain = filterNav(allNavMain, userRole);
    const navTools = filterNav(allNavTools, userRole);
    const navAccount = filterNav(allNavAccount, userRole);
    const mobileNav = filterNav(allNavMain, userRole);

    const sidebarWidth = collapsed ? 56 : 220;

    const bg = dark ? "#111827" : "#F3F4F6";
    const surface = dark ? "#1F2937" : "#FFFFFF";
    const surfaceHover = dark ? "#374151" : "#F9FAFB";
    const textPrimary = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "rgba(255,255,255,0.65)" : "#6B7280";
    const textMuted = dark ? "rgba(255,255,255,0.35)" : "rgba(0,0,0,0.3)";
    const dropdownBorder = dark ? "rgba(255,255,255,0.08)" : "#E5E7EB";

    const sectionLabel = {
        fontSize: "13px",
        fontWeight: 600 as const,
        color: textMuted,
        letterSpacing: "0.8px",
        textTransform: "uppercase" as const,
        padding: "14px 16px 4px",
        fontFamily: "'Inter', system-ui, sans-serif",
    };

    const dropdownStyle: React.CSSProperties = {
        position: "absolute",
        top: "calc(100% + 8px)",
        right: 0,
        background: surface,
        border: `1px solid ${dropdownBorder}`,
        zIndex: 200,
        minWidth: "280px",
        boxShadow: dark
            ? "0 8px 24px rgba(0,0,0,0.4)"
            : "0 8px 24px rgba(0,0,0,0.12)",
    };

    const dropdownItem = (
        onClick?: () => void,
        href?: string,
    ): React.CSSProperties => ({
        display: "flex",
        alignItems: "center",
        gap: "12px",
        padding: "11px 16px",
        fontSize: "17px",
        color: textPrimary,
        textDecoration: "none",
        background: "transparent",
        border: "none",
        width: "100%",
        cursor: "pointer",
        fontFamily: "'Inter', system-ui, sans-serif",
        fontWeight: 400,
    });

    return (
        <ThemeContext.Provider value={{ dark, toggleDark }}>
            <div
                style={{
                    display: "flex",
                    minHeight: "100vh",
                    background: bg,
                    fontFamily:
                        "'Inter', 'Segoe UI Variable', system-ui, sans-serif",
                    color: textPrimary,
                }}
            >
                {!isMobile && (
                    <aside
                        style={{
                            width: `${sidebarWidth}px`,
                            flexShrink: 0,
                            background: surface,
                            display: "flex",
                            flexDirection: "column",
                            position: "fixed",
                            top: 0,
                            left: 0,
                            bottom: 0,
                            zIndex: 40,
                            transition: "width 0.2s ease",
                            overflow: "hidden",
                        }}
                    >
                        <div
                            style={{
                                height: "60px",
                                display: "flex",
                                alignItems: "center",
                                justifyContent: collapsed
                                    ? "center"
                                    : "space-between",
                                padding: collapsed ? "0" : "0 14px",
                                flexShrink: 0,
                            }}
                        >
                            {!collapsed && (
                                <div
                                    style={{
                                        display: "flex",
                                        alignItems: "center",
                                        gap: "10px",
                                    }}
                                >
                                    <div
                                        style={{
                                            width: "32px",
                                            height: "32px",
                                            background: "#BA7517",
                                            display: "flex",
                                            alignItems: "center",
                                            justifyContent: "center",
                                            flexShrink: 0,
                                        }}
                                    >
                                        <IconPlant size={18} color="#fff" />
                                    </div>
                                    <div>
                                        <div
                                            style={{
                                                fontSize: "18px",
                                                fontWeight: 700,
                                                color: dark
                                                    ? "#F9FAFB"
                                                    : "#0F6E56",
                                                letterSpacing: "-0.2px",
                                            }}
                                        >
                                            NkwaLedger
                                        </div>
                                        <div
                                            style={{
                                                fontSize: "11px",
                                                fontWeight: 600,
                                                color: textMuted,
                                                letterSpacing: "0.8px",
                                                textTransform: "uppercase",
                                            }}
                                        >
                                            Farm Finance
                                        </div>
                                    </div>
                                </div>
                            )}
                            {collapsed && (
                                <div
                                    style={{
                                        width: "32px",
                                        height: "32px",
                                        background: "#BA7517",
                                        display: "flex",
                                        alignItems: "center",
                                        justifyContent: "center",
                                    }}
                                >
                                    <IconPlant size={18} color="#fff" />
                                </div>
                            )}
                            {!collapsed && (
                                <button
                                    onClick={() => setCollapsed(true)}
                                    style={{
                                        background: "none",
                                        border: "none",
                                        cursor: "pointer",
                                        color: textSecondary,
                                        display: "flex",
                                        padding: "4px",
                                    }}
                                >
                                    <IconChevronLeft size={20} />
                                </button>
                            )}
                        </div>

                        {collapsed && (
                            <div
                                style={{
                                    display: "flex",
                                    justifyContent: "center",
                                    padding: "8px 0",
                                }}
                            >
                                <button
                                    onClick={() => setCollapsed(false)}
                                    style={{
                                        background: "none",
                                        border: "none",
                                        cursor: "pointer",
                                        color: textSecondary,
                                        display: "flex",
                                        padding: "4px",
                                    }}
                                >
                                    <IconChevronRight size={20} />
                                </button>
                            </div>
                        )}

                        <div
                            style={{
                                flex: 1,
                                overflowY: "auto",
                                padding: "8px 0",
                            }}
                        >
                            {!collapsed && navMain.length > 0 && (
                                <div style={sectionLabel}>Main</div>
                            )}
                            {collapsed && <div style={{ height: "8px" }} />}
                            {navMain.map((item) => (
                                <NavItem
                                    key={item.href}
                                    {...item}
                                    collapsed={collapsed}
                                    active={currentPath.startsWith(item.href)}
                                    dark={dark}
                                />
                            ))}

                            {!collapsed && navTools.length > 0 && (
                                <div style={sectionLabel}>Tools</div>
                            )}
                            {collapsed && <div style={{ height: "8px" }} />}
                            {navTools.map((item) => (
                                <NavItem
                                    key={item.href}
                                    {...item}
                                    collapsed={collapsed}
                                    active={currentPath.startsWith(item.href)}
                                    dark={dark}
                                />
                            ))}

                            {!collapsed && navAccount.length > 0 && (
                                <div style={sectionLabel}>Account</div>
                            )}
                            {collapsed && <div style={{ height: "8px" }} />}
                            {navAccount.map((item) => (
                                <NavItem
                                    key={item.href}
                                    {...item}
                                    collapsed={collapsed}
                                    active={currentPath.startsWith(item.href)}
                                    dark={dark}
                                />
                            ))}
                        </div>

                        <div
                            style={{
                                padding: collapsed ? "12px 0" : "12px 14px",
                                display: "flex",
                                alignItems: "center",
                                gap: "10px",
                                justifyContent: collapsed
                                    ? "center"
                                    : "flex-start",
                            }}
                        >
                            <div
                                style={{
                                    width: "36px",
                                    height: "36px",
                                    background: "#1D9E75",
                                    display: "flex",
                                    alignItems: "center",
                                    justifyContent: "center",
                                    fontSize: "15px",
                                    fontWeight: 600,
                                    color: "#fff",
                                    flexShrink: 0,
                                }}
                            >
                                {userInitials}
                            </div>
                            {!collapsed && (
                                <>
                                    <div style={{ flex: 1, minWidth: 0 }}>
                                        <div
                                            style={{
                                                fontSize: "17px",
                                                fontWeight: 600,
                                                color: textPrimary,
                                                whiteSpace: "nowrap",
                                                overflow: "hidden",
                                                textOverflow: "ellipsis",
                                            }}
                                        >
                                            {userName}
                                        </div>
                                        <div
                                            style={{
                                                fontSize: "13px",
                                                color: textSecondary,
                                                textTransform: "capitalize",
                                            }}
                                        >
                                            {userRole}
                                        </div>
                                    </div>
                                    <button
                                        onClick={logout}
                                        title="Sign out"
                                        style={{
                                            background: "none",
                                            border: "none",
                                            cursor: "pointer",
                                            color: textSecondary,
                                            display: "flex",
                                            padding: "4px",
                                            flexShrink: 0,
                                        }}
                                    >
                                        <IconLogout size={20} />
                                    </button>
                                </>
                            )}
                        </div>
                    </aside>
                )}

                <div
                    style={{
                        flex: 1,
                        display: "flex",
                        flexDirection: "column",
                        marginLeft: isMobile ? 0 : `${sidebarWidth}px`,
                        transition: "margin-left 0.2s ease",
                        minWidth: 0,
                    }}
                >
                    <header
                        style={{
                            height: "60px",
                            background: surface,
                            display: "flex",
                            alignItems: "center",
                            justifyContent: "space-between",
                            padding: "0 24px",
                            position: "sticky",
                            top: 0,
                            zIndex: 30,
                            flexShrink: 0,
                        }}
                    >
                        <div
                            style={{
                                display: "flex",
                                alignItems: "center",
                                gap: "12px",
                            }}
                        >
                            {isMobile && (
                                <div
                                    style={{
                                        width: "28px",
                                        height: "28px",
                                        background: "#BA7517",
                                        display: "flex",
                                        alignItems: "center",
                                        justifyContent: "center",
                                    }}
                                >
                                    <IconPlant size={15} color="#fff" />
                                </div>
                            )}
                            <span
                                style={{
                                    fontSize: "21px",
                                    fontWeight: 700,
                                    color: textPrimary,
                                    letterSpacing: "-0.3px",
                                }}
                            >
                                {title ?? "Dashboard"}
                            </span>
                        </div>

                        <div
                            style={{
                                display: "flex",
                                alignItems: "center",
                                gap: "12px",
                            }}
                        >
                            <button
                                onClick={toggleDark}
                                style={{
                                    display: "flex",
                                    alignItems: "center",
                                    gap: "7px",
                                    padding: "7px 14px",
                                    background: surfaceHover,
                                    border: "none",
                                    cursor: "pointer",
                                    fontSize: "15px",
                                    color: textSecondary,
                                    fontFamily:
                                        "'Inter', system-ui, sans-serif",
                                }}
                            >
                                {dark ? (
                                    <IconSun size={18} />
                                ) : (
                                    <IconMoon size={18} />
                                )}
                                {dark ? "Light" : "Dark"}
                            </button>

                            <div ref={bellRef} style={{ position: "relative" }}>
                                <button
                                    onClick={() => {
                                        setShowBell((v) => !v);
                                        setShowCog(false);
                                    }}
                                    style={{
                                        width: "38px",
                                        height: "38px",
                                        display: "flex",
                                        alignItems: "center",
                                        justifyContent: "center",
                                        background: surfaceHover,
                                        border: "none",
                                        cursor: "pointer",
                                        position: "relative",
                                    }}
                                >
                                    <IconBell size={20} color={textSecondary} />
                                    {unreadCount > 0 && (
                                        <div
                                            style={{
                                                width: "8px",
                                                height: "8px",
                                                background: "#1D9E75",
                                                position: "absolute",
                                                top: "7px",
                                                right: "7px",
                                                borderRadius: "50%",
                                            }}
                                        />
                                    )}
                                </button>

                                {showBell && (
                                    <div style={dropdownStyle}>
                                        <div
                                            style={{
                                                padding: "14px 16px",
                                                borderBottom: `1px solid ${dropdownBorder}`,
                                                display: "flex",
                                                justifyContent: "space-between",
                                                alignItems: "center",
                                            }}
                                        >
                                            <span
                                                style={{
                                                    fontSize: "17px",
                                                    fontWeight: 600,
                                                    color: textPrimary,
                                                }}
                                            >
                                                Notifications
                                            </span>
                                            <button
                                                onClick={markAllRead}
                                                style={{
                                                    fontSize: "14px",
                                                    color: "#1D9E75",
                                                    background: "none",
                                                    border: "none",
                                                    cursor: "pointer",
                                                    fontFamily: "inherit",
                                                }}
                                            >
                                                Mark all as read
                                            </button>
                                        </div>

                                        {notifications.map((n) => {
                                            const Icon = n.icon;
                                            return (
                                                <div
                                                    key={n.id}
                                                    style={{
                                                        display: "flex",
                                                        gap: "12px",
                                                        padding: "12px 16px",
                                                        borderBottom: `1px solid ${dropdownBorder}`,
                                                        background: n.read
                                                            ? "transparent"
                                                            : dark
                                                              ? "rgba(29,158,117,0.08)"
                                                              : "#F0FDF8",
                                                    }}
                                                >
                                                    <div
                                                        style={{
                                                            width: "36px",
                                                            height: "36px",
                                                            background: dark
                                                                ? "rgba(255,255,255,0.08)"
                                                                : "#EAF5F0",
                                                            display: "flex",
                                                            alignItems:
                                                                "center",
                                                            justifyContent:
                                                                "center",
                                                            flexShrink: 0,
                                                        }}
                                                    >
                                                        <Icon
                                                            size={18}
                                                            color="#1D9E75"
                                                        />
                                                    </div>
                                                    <div
                                                        style={{
                                                            flex: 1,
                                                            minWidth: 0,
                                                        }}
                                                    >
                                                        <p
                                                            style={{
                                                                fontSize:
                                                                    "15px",
                                                                fontWeight:
                                                                    n.read
                                                                        ? 400
                                                                        : 600,
                                                                color: textPrimary,
                                                                marginBottom:
                                                                    "2px",
                                                            }}
                                                        >
                                                            {n.title}
                                                        </p>
                                                        <p
                                                            style={{
                                                                fontSize:
                                                                    "13px",
                                                                color: textSecondary,
                                                                marginBottom:
                                                                    "4px",
                                                            }}
                                                        >
                                                            {n.sub}
                                                        </p>
                                                        <p
                                                            style={{
                                                                fontSize:
                                                                    "12px",
                                                                color: textMuted,
                                                            }}
                                                        >
                                                            {n.time}
                                                        </p>
                                                    </div>
                                                    {!n.read && (
                                                        <div
                                                            style={{
                                                                width: "8px",
                                                                height: "8px",
                                                                background:
                                                                    "#1D9E75",
                                                                borderRadius:
                                                                    "50%",
                                                                flexShrink: 0,
                                                                marginTop:
                                                                    "6px",
                                                            }}
                                                        />
                                                    )}
                                                </div>
                                            );
                                        })}

                                        <div
                                            style={{
                                                padding: "12px 16px",
                                                textAlign: "center",
                                            }}
                                        >
                                            <a
                                                href="/notifications"
                                                style={{
                                                    fontSize: "15px",
                                                    color: "#1D9E75",
                                                    textDecoration: "none",
                                                    fontWeight: 500,
                                                }}
                                            >
                                                View all notifications
                                            </a>
                                        </div>
                                    </div>
                                )}
                            </div>

                            <div ref={cogRef} style={{ position: "relative" }}>
                                <button
                                    onClick={() => {
                                        setShowCog((v) => !v);
                                        setShowBell(false);
                                    }}
                                    style={{
                                        width: "38px",
                                        height: "38px",
                                        display: "flex",
                                        alignItems: "center",
                                        justifyContent: "center",
                                        background: "#1D9E75",
                                        border: "none",
                                        cursor: "pointer",
                                        fontSize: "15px",
                                        fontWeight: 600,
                                        color: "#fff",
                                        fontFamily: "inherit",
                                    }}
                                >
                                    {userInitials}
                                </button>

                                {showCog && (
                                    <div style={dropdownStyle}>
                                        <div
                                            style={{
                                                padding: "14px 16px",
                                                borderBottom: `1px solid ${dropdownBorder}`,
                                            }}
                                        >
                                            <p
                                                style={{
                                                    fontSize: "17px",
                                                    fontWeight: 600,
                                                    color: textPrimary,
                                                }}
                                            >
                                                {userName}
                                            </p>
                                            <p
                                                style={{
                                                    fontSize: "14px",
                                                    color: textSecondary,
                                                    textTransform: "capitalize",
                                                    marginTop: "2px",
                                                }}
                                            >
                                                {userRole}
                                            </p>
                                        </div>

                                        {[
                                            {
                                                label: "Profile",
                                                icon: IconUser,
                                                href: "/profile",
                                            },
                                            {
                                                label: "My Activity",
                                                icon: IconActivityHeartbeat,
                                                href: "/my-activity",
                                            },
                                            {
                                                label: "Settings",
                                                icon: IconSettings,
                                                href: "/settings",
                                            },
                                        ].map((item) => (
                                            <Link
                                                key={item.href}
                                                href={item.href}
                                                style={dropdownItem()}
                                                onMouseOver={(e) =>
                                                    (e.currentTarget.style.background =
                                                        surfaceHover)
                                                }
                                                onMouseOut={(e) =>
                                                    (e.currentTarget.style.background =
                                                        "transparent")
                                                }
                                                onClick={() =>
                                                    setShowCog(false)
                                                }
                                            >
                                                <item.icon
                                                    size={20}
                                                    color={textSecondary}
                                                />
                                                {item.label}
                                            </Link>
                                        ))}

                                        <div
                                            style={{
                                                height: "1px",
                                                background: dropdownBorder,
                                                margin: "6px 0",
                                            }}
                                        />

                                        <button
                                            onClick={() => {
                                                setShowCog(false);
                                                logout();
                                            }}
                                            style={{
                                                ...dropdownItem(),
                                                color: "#E24B4A",
                                                display: "flex",
                                                alignItems: "center",
                                                gap: "12px",
                                                padding: "11px 16px",
                                                marginBottom: "4px",
                                            }}
                                            onMouseOver={(e) =>
                                                (e.currentTarget.style.background =
                                                    surfaceHover)
                                            }
                                            onMouseOut={(e) =>
                                                (e.currentTarget.style.background =
                                                    "transparent")
                                            }
                                        >
                                            <IconLogout
                                                size={20}
                                                color="#E24B4A"
                                            />
                                            Sign out
                                        </button>
                                    </div>
                                )}
                            </div>
                        </div>
                    </header>

                    <main
                        style={{
                            flex: 1,
                            padding: "24px",
                            paddingBottom: isMobile ? "80px" : "24px",
                        }}
                    >
                        {children}
                    </main>
                </div>

                {isMobile && (
                    <nav
                        style={{
                            position: "fixed",
                            bottom: 0,
                            left: 0,
                            right: 0,
                            height: "64px",
                            background: surface,
                            display: "flex",
                            alignItems: "center",
                            zIndex: 40,
                        }}
                    >
                        {mobileNav.map((item) => {
                            const active = currentPath.startsWith(item.href);
                            const Icon = item.icon;
                            return (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    style={{
                                        flex: 1,
                                        display: "flex",
                                        flexDirection: "column",
                                        alignItems: "center",
                                        justifyContent: "center",
                                        gap: "4px",
                                        textDecoration: "none",
                                        color: active
                                            ? "#1D9E75"
                                            : textSecondary,
                                        fontSize: "13px",
                                        fontWeight: active ? 600 : 400,
                                        paddingTop: "6px",
                                        fontFamily:
                                            "'Inter', system-ui, sans-serif",
                                    }}
                                >
                                    <Icon size={24} />
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
