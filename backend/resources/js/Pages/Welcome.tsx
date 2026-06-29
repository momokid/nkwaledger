import { Link } from "@inertiajs/react";
import {
    IconBook,
    IconCreditCard,
    IconDeviceMobileMessage,
    IconPlant,
    IconStethoscope,
} from "@tabler/icons-react";

interface Props {
    canLogin: boolean;
    canRegister: boolean;
}

const features = [
    {
        icon: <IconBook size={22} color="#fff" />,
        title: "Financial Ledger",
        description: "Log daily transactions and farm activities",
    },
    {
        icon: <IconStethoscope size={22} color="#fff" />,
        title: "VetAI & CropAI",
        description: "AI diagnosis for livestock and crops",
    },
    {
        icon: <IconCreditCard size={22} color="#fff" />,
        title: "Credit Scoring",
        description: "Build a bankable profile to access loans",
    },
    {
        icon: <IconDeviceMobileMessage size={22} color="#fff" />,
        title: "Works on any phone",
        description: "Web, mobile app, and USSD (*384*534#)",
    },
];

const pills = ["🌱 Crops", "🐄 Livestock", "💳 Credit"];

export default function Welcome({ canLogin, canRegister }: Props) {
    return (
        <div
            className="min-h-screen flex"
            style={{
                fontFamily:
                    "'Inter', 'Segoe UI Variable', 'Segoe UI', system-ui, sans-serif",
            }}
        >
            <div
                className="hidden lg:flex flex-col justify-between w-1/2 p-10"
                style={{ background: "#0F6E56" }}
            >
                <div className="flex items-center gap-3">
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
                                color: "rgba(255,255,255,0.45)",
                                letterSpacing: "0.8px",
                                textTransform: "uppercase",
                            }}
                        >
                            Farm Finance Platform
                        </div>
                    </div>
                </div>

                <div>
                    <h1
                        style={{
                            margin: "0 0 16px",
                            fontSize: "45px",
                            fontWeight: 700,
                            color: "#fff",
                            lineHeight: 1.25,
                            letterSpacing: "-0.6px",
                        }}
                    >
                        Built for Ghanaian farmers.
                    </h1>
                    <p
                        style={{
                            margin: "0 0 2rem",
                            fontSize: "20px",
                            color: "rgba(255,255,255,0.65)",
                            lineHeight: 1.65,
                        }}
                    >
                        Turn your daily farm activities into a trusted, bankable
                        financial record.
                    </p>

                    <div
                        style={{
                            height: "1px",
                            background: "rgba(255,255,255,0.15)",
                            marginBottom: "2rem",
                        }}
                    />

                    <div className="flex flex-col gap-5">
                        {features.map((f) => (
                            <div
                                key={f.title}
                                className="flex items-start gap-4"
                            >
                                <div
                                    className="flex items-center justify-center flex-shrink-0 mt-0.5"
                                    style={{
                                        width: "38px",
                                        height: "38px",
                                        background: "rgba(255,255,255,0.1)",
                                    }}
                                >
                                    {f.icon}
                                </div>
                                <div>
                                    <div
                                        style={{
                                            fontSize: "18px",
                                            fontWeight: 600,
                                            color: "#fff",
                                            marginBottom: "2px",
                                        }}
                                    >
                                        {f.title}
                                    </div>
                                    <div
                                        style={{
                                            fontSize: "17px",
                                            color: "rgba(255,255,255,0.55)",
                                            lineHeight: 1.5,
                                        }}
                                    >
                                        {f.description}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                <div
                    style={{
                        fontSize: "15px",
                        color: "rgba(255,255,255,0.35)",
                    }}
                >
                    © {new Date().getFullYear()} NkwaLedger. All rights
                    reserved.
                </div>
            </div>

            <div className="w-full lg:w-1/2 flex flex-col justify-center items-center px-8 py-12 sm:px-14 bg-white">
                <div className="lg:hidden flex items-center gap-3 mb-10">
                    <div
                        className="flex items-center justify-center flex-shrink-0"
                        style={{
                            width: "38px",
                            height: "38px",
                            background: "#BA7517",
                        }}
                    >
                        <IconPlant size={20} color="#fff" />
                    </div>
                    <div>
                        <div
                            style={{
                                fontSize: "20px",
                                fontWeight: 700,
                                color: "#0F6E56",
                                letterSpacing: "-0.2px",
                            }}
                        >
                            NkwaLedger
                        </div>
                        <div
                            style={{
                                fontSize: "13px",
                                fontWeight: 600,
                                color: "#0F6E56",
                                opacity: 0.55,
                                letterSpacing: "0.8px",
                                textTransform: "uppercase",
                            }}
                        >
                            Farm Finance Platform
                        </div>
                    </div>
                </div>

                <div className="w-[90%] mx-auto text-center">
                    <h2
                        style={{
                            margin: "0 0 14px",
                            fontSize: "45px",
                            fontWeight: 700,
                            color: "#111827",
                            lineHeight: 1.25,
                            letterSpacing: "-0.6px",
                        }}
                    >
                        Your farm. Your money. Your record.
                    </h2>

                    <p
                        style={{
                            margin: "0 0 1.75rem",
                            fontSize: "21px",
                            color: "#6B7280",
                            lineHeight: 1.6,
                        }}
                    >
                        NkwaLedger turns your daily farm activities into a
                        trusted, bankable financial record right from your
                        phone.
                    </p>

                    <div className="flex items-center justify-center gap-2 mb-8 flex-wrap">
                        {pills.map((pill) => (
                            <span
                                key={pill}
                                style={{
                                    fontSize: "17px",
                                    fontWeight: 600,
                                    color: "#0F6E56",
                                    background: "#EAF5F0",
                                    border: "1px solid #A8D9C8",
                                    padding: "6px 14px",
                                }}
                            >
                                {pill}
                            </span>
                        ))}
                    </div>

                    <Link
                        href="/register"
                        className="flex items-center justify-center w-full mb-3"
                        style={{
                            background: "#1D9E75",
                            color: "#fff",
                            padding: "13px 20px",
                            fontSize: "20px",
                            fontWeight: 600,
                            textDecoration: "none",
                        }}
                        onMouseOver={(e) =>
                            (e.currentTarget.style.background = "#0F6E56")
                        }
                        onMouseOut={(e) =>
                            (e.currentTarget.style.background = "#1D9E75")
                        }
                    >
                        Get started — it's free
                    </Link>

                    <Link
                        href="/login"
                        className="flex items-center justify-center w-full"
                        style={{
                            background: "#fff",
                            color: "#111827",
                            padding: "13px 20px",
                            fontSize: "20px",
                            fontWeight: 400,
                            border: "1px solid #9CA3AF",
                            textDecoration: "none",
                        }}
                        onMouseOver={(e) =>
                            (e.currentTarget.style.background = "#F3F4F6")
                        }
                        onMouseOut={(e) =>
                            (e.currentTarget.style.background = "#fff")
                        }
                    >
                        Sign in
                    </Link>

                    <p
                        style={{
                            marginTop: "2rem",
                            fontSize: "15px",
                            color: "#9CA3AF",
                            textAlign: "center",
                        }}
                    >
                        Used by farmers across Ghana · MTN MoMo integrated
                    </p>
                </div>
            </div>
        </div>
    );
}
