import { Head, router } from "@inertiajs/react";
import useAuthGuard from "@/hooks/useAuthGuard";

export default function Dashboard() {
    useAuthGuard();

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
            className="min-h-screen flex flex-col items-center justify-center bg-gray-50"
            style={{ fontFamily: "'Inter', system-ui, sans-serif" }}
        >
            <Head title="Dashboard" />

            <div
                style={{
                    border: "1px solid #D1D5DB",
                    background: "#fff",
                    padding: "3rem",
                    maxWidth: "480px",
                    width: "100%",
                }}
            >
                <div className="flex items-center gap-3 mb-6">
                    <div
                        style={{
                            width: "36px",
                            height: "36px",
                            background: "#BA7517",
                            display: "flex",
                            alignItems: "center",
                            justifyContent: "center",
                        }}
                    >
                        <span style={{ color: "#fff", fontSize: "18px" }}>
                            🌱
                        </span>
                    </div>
                    <div>
                        <div
                            style={{
                                fontSize: "18px",
                                fontWeight: 700,
                                color: "#0F6E56",
                            }}
                        >
                            NkwaLedger
                        </div>
                        <div
                            style={{
                                fontSize: "11px",
                                color: "#9CA3AF",
                                textTransform: "uppercase",
                                letterSpacing: "0.8px",
                            }}
                        >
                            Farm Finance Platform
                        </div>
                    </div>
                </div>

                <h1
                    style={{
                        fontSize: "28px",
                        fontWeight: 700,
                        color: "#111827",
                        marginBottom: "8px",
                        letterSpacing: "-0.5px",
                    }}
                >
                    You're in. 🎉
                </h1>
                <p
                    style={{
                        fontSize: "17px",
                        color: "#6B7280",
                        lineHeight: 1.6,
                        marginBottom: "2rem",
                    }}
                >
                    Your account has been verified successfully. The full farmer
                    dashboard is coming in the next phase.
                </p>

                <button
                    onClick={logout}
                    style={{
                        width: "100%",
                        background: "#fff",
                        color: "#111827",
                        border: "1px solid #9CA3AF",
                        padding: "13px 20px",
                        fontSize: "17px",
                        fontWeight: 500,
                        cursor: "pointer",
                        fontFamily: "inherit",
                    }}
                    onMouseOver={(e) =>
                        (e.currentTarget.style.background = "#F3F4F6")
                    }
                    onMouseOut={(e) =>
                        (e.currentTarget.style.background = "#fff")
                    }
                >
                    Sign out
                </button>
            </div>
        </div>
    );
}
