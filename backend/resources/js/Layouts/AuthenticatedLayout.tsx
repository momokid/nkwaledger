import { Head, Link, router, usePage } from "@inertiajs/react";
import NotificationBell from "@/Components/NotificationBell";
import {
    IconBell,
    IconChevronLeft,
    IconChevronRight,
    IconClipboardList,
    IconCloudRain,
    IconCreditCard,
    IconHelpCircle,
    IconLayoutDashboard,
    IconLogout,
    IconMoon,
    IconPencilPlus,
    IconPlant,
    IconReportAnalytics,
    IconSettings,
    IconShoppingCart,
    IconStethoscope,
    IconSun,
    IconUser,
    IconUserCheck,
    IconUserCircle,
    IconWallet,
    IconChecklist,
} from "@tabler/icons-react";
import {
    createContext,
    PropsWithChildren,
    useContext,
    useEffect,
    useRef,
    useState,
} from "react";
import FlashMessages from "@/Components/FlashMessages";
import VerificationGate from "@/Components/VerificationGate";
import useIsVerified from "@/hooks/useIsVerified";

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
    // false means the page is not built, so the item shows but cannot be opened
    ready: boolean;
    // names which count this item wants beside its label
    badge?: string;
    count?: number;
}

interface NavSet {
    main: NavItem[];
    tools: NavItem[];
    account: NavItem[];
}

const account = (): NavItem[] => [
    { label: "Profile", href: "#", icon: IconUser, ready: false },
    { label: "Settings", href: "#", icon: IconSettings, ready: false },
];

// each role gets its own menu, since the same word can mean different things to different people
const navSets: Record<string, (dashboard: string) => NavSet> = {
    agent: (dashboard) => ({
        main: [
            {
                label: "Dashboard",
                href: dashboard,
                icon: IconLayoutDashboard,
                ready: true,
            },
            {
                label: "Farmers",
                href: "/agent/farmers",
                icon: IconUserCheck,
                ready: true,
            },
            {
                label: "Farm Units",
                href: "/agent/farm-units",
                icon: IconClipboardList,
                ready: true,
            },
            {
                label: "Approvals",
                href: "/agent/approvals",
                icon: IconChecklist,
                ready: true,
                badge: "approvals",
            },
            { label: "Record", href: "#", icon: IconPencilPlus, ready: false },
            {
                label: "Reports",
                href: "#",
                icon: IconReportAnalytics,
                ready: false,
            },
        ],
        tools: [
            {
                label: "Marketplace",
                href: "#",
                icon: IconShoppingCart,
                ready: false,
            },
            {
                label: "Requests",
                href: "#",
                icon: IconHelpCircle,
                ready: false,
            },
        ],
        account: account(),
    }),

    farmer: (dashboard) => ({
        main: [
            {
                label: "Dashboard",
                href: dashboard,
                icon: IconLayoutDashboard,
                ready: true,
            },
            {
                label: "Record",
                href: "/my-records/create",
                icon: IconPencilPlus,
                ready: true,
            },
            // no accounting words reach a farmer, so the ledger is called what they would call it
            {
                label: "My Money",
                href: "/my-records",
                icon: IconWallet,
                ready: true,
            },
            { label: "My Farm", href: "#", icon: IconPlant, ready: false },
            { label: "Credit", href: "#", icon: IconCreditCard, ready: false },
        ],
        tools: [
            { label: "Help", href: "#", icon: IconHelpCircle, ready: false },
            { label: "Weather", href: "#", icon: IconCloudRain, ready: false },
            {
                label: "Marketplace",
                href: "#",
                icon: IconShoppingCart,
                ready: false,
            },
        ],
        account: account(),
    }),

    vet: (dashboard) => ({
        main: [
            {
                label: "Dashboard",
                href: dashboard,
                icon: IconLayoutDashboard,
                ready: true,
            },
            {
                label: "Livestock",
                href: "#",
                icon: IconStethoscope,
                ready: false,
            },
            {
                label: "Consultations",
                href: "#",
                icon: IconHelpCircle,
                ready: false,
            },
        ],
        tools: [],
        account: account(),
    }),

    adviser: (dashboard) => ({
        main: [
            {
                label: "Dashboard",
                href: dashboard,
                icon: IconLayoutDashboard,
                ready: true,
            },
            { label: "Crops", href: "#", icon: IconPlant, ready: false },
            {
                label: "Consultations",
                href: "#",
                icon: IconHelpCircle,
                ready: false,
            },
        ],
        tools: [
            { label: "Weather", href: "#", icon: IconCloudRain, ready: false },
        ],
        account: account(),
    }),

    supplier: (dashboard) => ({
        main: [
            {
                label: "Dashboard",
                href: dashboard,
                icon: IconLayoutDashboard,
                ready: true,
            },
            {
                label: "Marketplace",
                href: "#",
                icon: IconShoppingCart,
                ready: false,
            },
        ],
        tools: [],
        account: account(),
    }),
};

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
    // only what this person can sign off, counted on the server
    const pendingApprovals =
        (auth as { pendingApprovals?: number })?.pendingApprovals ?? 0;
    const verified = useIsVerified();

    const [dark, setDark] = useState(false);
    const [collapsed, setCollapsed] = useState(false);
    const [isMobile, setIsMobile] = useState(false);
    const [hovered, setHovered] = useState<string | null>(null);
    const [cogOpen, setCogOpen] = useState(false);

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

    // every role has its own home page, so the link follows whoever is signed in
    const dashboardHref = `/${primaryRole}/dashboard`;
    const built = (navSets[primaryRole] ?? navSets.farmer)(dashboardHref);

    const withCount = (items: NavItem[]) =>
        items.map((item) =>
            item.badge === "approvals"
                ? { ...item, count: pendingApprovals }
                : item,
        );

    const nav = {
        ...built,
        main: withCount(built.main),
        tools: withCount(built.tools),
    };

    const mobileNav = nav.main.slice(0, 5);

    const renderItem = (item: NavItem) => {
        const Icon = item.icon;
        const active = currentPath === item.href;

        const shared = {
            display: "flex",
            alignItems: "center",
            gap: "12px",
            padding: "12px 16px",
            fontSize: "18px",
            fontFamily: "'Inter', system-ui, sans-serif",
            whiteSpace: "nowrap" as const,
            overflow: "hidden",
        };

        if (!item.ready) {
            return (
                <div
                    style={{
                        ...shared,
                        color: textSecondary,
                        opacity: 0.55,
                        cursor: "default",
                        borderLeft: "3px solid transparent",
                    }}
                >
                    <Icon size={24} stroke={1.6} />
                    {!collapsed && (
                        <>
                            {item.label}
                            <span
                                style={{
                                    fontSize: "13px",
                                    padding: "1px 6px",
                                    border: `1px solid ${textSecondary}`,
                                    marginLeft: "auto",
                                }}
                            >
                                soon
                            </span>
                        </>
                    )}
                </div>
            );
        }

        return (
            <Link
                href={item.href}
                style={{
                    ...shared,
                    fontWeight: active ? 600 : 400,
                    color: active ? primary : text,
                    background: active ? hoverBg : "transparent",
                    borderLeft: active
                        ? `3px solid ${primary}`
                        : "3px solid transparent",
                    textDecoration: "none",
                }}
            >
                <Icon size={24} stroke={1.6} />
                {!collapsed && item.label}
                {/* a zero means nothing is waiting, so the badge stays away */}
                {!collapsed && (item.count ?? 0) > 0 && (
                    <span
                        style={{
                            marginLeft: "auto",
                            fontSize: "14px",
                            fontWeight: 700,
                            color: "#FFFFFF",
                            background: gold,
                            padding: "1px 8px",
                        }}
                    >
                        {item.count}
                    </span>
                )}
            </Link>
        );
    };

    const renderSection = (label: string, items: NavItem[]) => {
        if (items.length === 0) return null;

        return (
            <div style={{ marginBottom: "20px" }}>
                {!collapsed && (
                    <p
                        style={{
                            fontSize: "15px",
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
                {items.map((item) => (
                    <div
                        key={item.label}
                        style={{ position: "relative" }}
                        onMouseEnter={() => setHovered(item.label)}
                        onMouseLeave={() => setHovered(null)}
                    >
                        {renderItem(item)}

                        {collapsed && hovered === item.label && (
                            <span
                                style={{
                                    position: "absolute",
                                    left: "60px",
                                    top: "50%",
                                    transform: "translateY(-50%)",
                                    background: dark ? "#374151" : "#111827",
                                    color: "#FFFFFF",
                                    fontSize: "18px",
                                    padding: "6px 12px",
                                    whiteSpace: "nowrap",
                                    zIndex: 60,
                                    fontFamily:
                                        "'Inter', system-ui, sans-serif",
                                }}
                            >
                                {item.label}
                                {!item.ready && " (soon)"}
                            </span>
                        )}
                    </div>
                ))}
            </div>
        );
    };

    return (
        <ThemeContext.Provider value={{ dark, toggle: toggleTheme }}>
            <Head title={title} />
            <FlashMessages />
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
                                            fontSize: "21px",
                                            fontWeight: 700,
                                            color: dark ? "#A8D9C8" : "#0F6E56",
                                            lineHeight: 1.2,
                                        }}
                                    >
                                        NkwaLedger
                                    </div>
                                    <div
                                        style={{
                                            fontSize: "12px",
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

                        <nav
                            style={{
                                flex: 1,
                                overflowY: "auto",
                                // greyed until the phone is proved, the server does the real blocking
                                opacity: verified ? 1 : 0.4,
                                pointerEvents: verified ? "auto" : "none",
                            }}
                        >
                            {renderSection("Main", nav.main)}
                            {renderSection("Tools", nav.tools)}
                            {renderSection("Account", nav.account)}
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
                                        fontSize: "18px",
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
                                                fontSize: "18px",
                                                fontWeight: 600,
                                                whiteSpace: "nowrap",
                                            }}
                                        >
                                            {firstName} {surname}
                                        </div>
                                        <div
                                            style={{
                                                fontSize: "15px",
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
                                    fontSize: "18px",
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
                                    fontSize: "23px",
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

                            <NotificationBell dark={dark} />

                            <div ref={cogRef} style={{ position: "relative" }}>
                                <button
                                    onClick={() => setCogOpen(!cogOpen)}
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
                                                    fontSize: "18px",
                                                    fontWeight: 600,
                                                    margin: 0,
                                                }}
                                            >
                                                {firstName} {surname}
                                            </p>
                                            <p
                                                style={{
                                                    fontSize: "15px",
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
                                                    fontSize: "18px",
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
                                                fontSize: "18px",
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

                    <main style={{ padding: "24px" }}>
                        <VerificationGate>{children}</VerificationGate>
                    </main>
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

                            const style = {
                                display: "flex",
                                flexDirection: "column" as const,
                                alignItems: "center",
                                fontSize: "15px",
                                paddingTop: "6px",
                                textDecoration: "none",
                                fontFamily: "'Inter', system-ui, sans-serif",
                            };

                            if (!item.ready) {
                                return (
                                    <div
                                        key={item.label}
                                        style={{
                                            ...style,
                                            color: textSecondary,
                                            opacity: 0.5,
                                        }}
                                    >
                                        <Icon size={24} stroke={1.6} />
                                        {item.label}
                                    </div>
                                );
                            }

                            return (
                                <Link
                                    key={item.label}
                                    href={item.href}
                                    style={{
                                        ...style,
                                        color: active ? primary : textSecondary,
                                        fontWeight: active ? 600 : 400,
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
