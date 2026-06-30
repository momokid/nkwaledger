import { Head, Link, useForm } from "@inertiajs/react";
import {
    IconArrowLeft,
    IconBrandFacebook,
    IconCheck,
    IconDeviceMobileMessage,
    IconEye,
    IconEyeOff,
    IconLock,
    IconPhone,
    IconPlant,
} from "@tabler/icons-react";
import { FormEventHandler, useState } from "react";

interface Props {
    canResetPassword: boolean;
    status?: string;
}

type Tab = "password" | "otp";

const features = [
    "MTN MoMo payments integrated",
    "AI-powered VetAI and CropAI",
    "Trusted by farmers across Ghana",
];

function Divider({ label }: { label?: string }) {
    return (
        <div
            style={{
                display: "flex",
                alignItems: "center",
                gap: "10px",
                margin: "14px 0",
            }}
        >
            <div style={{ flex: 1, height: "1px", background: "#E5E7EB" }} />
            {label && (
                <span
                    style={{
                        fontSize: "17px",
                        color: "#9CA3AF",
                        whiteSpace: "nowrap",
                    }}
                >
                    {label}
                </span>
            )}
            <div style={{ flex: 1, height: "1px", background: "#E5E7EB" }} />
        </div>
    );
}

function PasswordForm({ canResetPassword, status }: Props) {
    const [showPassword, setShowPassword] = useState(false);
    const { data, setData, post, processing, errors } = useForm({
        identifier: "",
        password: "",
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route("login"));
    };

    return (
        <form onSubmit={submit}>
            {status && (
                <div
                    style={{
                        marginBottom: "1rem",
                        fontSize: "17px",
                        color: "#1D9E75",
                    }}
                >
                    {status}
                </div>
            )}

            <div style={{ marginBottom: "16px" }}>
                <label
                    style={{
                        display: "block",
                        fontSize: "17px",
                        fontWeight: 600,
                        color: "#111827",
                        marginBottom: "6px",
                    }}
                >
                    Phone or email
                </label>
                <div style={{ position: "relative" }}>
                    <span
                        style={{
                            position: "absolute",
                            left: "12px",
                            top: "50%",
                            transform: "translateY(-50%)",
                            pointerEvents: "none",
                        }}
                    >
                        <IconPhone size={18} color="#9CA3AF" />
                    </span>
                    <input
                        type="text"
                        placeholder="+233 XX XXX XXXX or email"
                        value={data.identifier}
                        onChange={(e) => setData("identifier", e.target.value)}
                        style={{
                            width: "100%",
                            border: "1px solid #9CA3AF",
                            padding: "10px 12px 10px 40px",
                            fontSize: "17px",
                            color: "#111827",
                            background: "#fff",
                            outline: "none",
                            fontFamily: "inherit",
                        }}
                        onFocus={(e) => {
                            e.target.style.border = "2px solid #1D9E75";
                            e.target.style.paddingLeft = "39px";
                        }}
                        onBlur={(e) => {
                            e.target.style.border = "1px solid #9CA3AF";
                            e.target.style.paddingLeft = "40px";
                        }}
                    />
                </div>
                {errors.identifier && (
                    <p
                        style={{
                            marginTop: "4px",
                            fontSize: "15px",
                            color: "#DC2626",
                        }}
                    >
                        {errors.identifier}
                    </p>
                )}
            </div>

            <div style={{ marginBottom: "16px" }}>
                <label
                    style={{
                        display: "block",
                        fontSize: "17px",
                        fontWeight: 600,
                        color: "#111827",
                        marginBottom: "6px",
                    }}
                >
                    Password
                </label>
                <div style={{ position: "relative" }}>
                    <span
                        style={{
                            position: "absolute",
                            left: "12px",
                            top: "50%",
                            transform: "translateY(-50%)",
                            pointerEvents: "none",
                        }}
                    >
                        <IconLock size={18} color="#9CA3AF" />
                    </span>
                    <input
                        type={showPassword ? "text" : "password"}
                        placeholder="Your password"
                        value={data.password}
                        onChange={(e) => setData("password", e.target.value)}
                        style={{
                            width: "100%",
                            border: "1px solid #9CA3AF",
                            padding: "10px 40px 10px 40px",
                            fontSize: "17px",
                            color: "#111827",
                            background: "#fff",
                            outline: "none",
                            fontFamily: "inherit",
                        }}
                        onFocus={(e) => {
                            e.target.style.border = "2px solid #1D9E75";
                            e.target.style.paddingLeft = "39px";
                        }}
                        onBlur={(e) => {
                            e.target.style.border = "1px solid #9CA3AF";
                            e.target.style.paddingLeft = "40px";
                        }}
                    />
                    <button
                        type="button"
                        onClick={() => setShowPassword(!showPassword)}
                        style={{
                            position: "absolute",
                            right: "12px",
                            top: "50%",
                            transform: "translateY(-50%)",
                            background: "none",
                            border: "none",
                            cursor: "pointer",
                            padding: 0,
                            display: "flex",
                        }}
                    >
                        {showPassword ? (
                            <IconEyeOff size={18} color="#9CA3AF" />
                        ) : (
                            <IconEye size={18} color="#9CA3AF" />
                        )}
                    </button>
                </div>
                {errors.password && (
                    <p
                        style={{
                            marginTop: "4px",
                            fontSize: "15px",
                            color: "#DC2626",
                        }}
                    >
                        {errors.password}
                    </p>
                )}
            </div>

            {canResetPassword && (
                <div style={{ textAlign: "right", margin: "-8px 0 16px" }}>
                    <Link
                        href={route("password.request")}
                        style={{
                            fontSize: "15px",
                            fontWeight: 600,
                            color: "#1D9E75",
                            textDecoration: "none",
                        }}
                    >
                        Forgot password?
                    </Link>
                </div>
            )}

            <button
                type="submit"
                disabled={processing}
                style={{
                    width: "100%",
                    background: processing ? "#6B7280" : "#1D9E75",
                    color: "#fff",
                    border: "none",
                    padding: "13px 20px",
                    fontSize: "20px",
                    fontWeight: 600,
                    cursor: processing ? "not-allowed" : "pointer",
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "center",
                    fontFamily: "inherit",
                }}
                onMouseOver={(e) => {
                    if (!processing)
                        e.currentTarget.style.background = "#0F6E56";
                }}
                onMouseOut={(e) => {
                    if (!processing)
                        e.currentTarget.style.background = "#1D9E75";
                }}
            >
                {processing ? "Signing in..." : "Sign in"}
            </button>
        </form>
    );
}

function OtpForm() {
    const { data, setData, post, processing, errors } = useForm({ phone: "" });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route("login.otp"));
    };

    return (
        <form onSubmit={submit}>
            <div style={{ marginBottom: "16px" }}>
                <label
                    style={{
                        display: "block",
                        fontSize: "17px",
                        fontWeight: 600,
                        color: "#111827",
                        marginBottom: "6px",
                    }}
                >
                    Phone number
                </label>
                <div style={{ position: "relative" }}>
                    <span
                        style={{
                            position: "absolute",
                            left: "12px",
                            top: "50%",
                            transform: "translateY(-50%)",
                            pointerEvents: "none",
                        }}
                    >
                        <IconPhone size={18} color="#9CA3AF" />
                    </span>
                    <input
                        type="tel"
                        placeholder="+233 XX XXX XXXX"
                        value={data.phone}
                        onChange={(e) => setData("phone", e.target.value)}
                        style={{
                            width: "100%",
                            border: "1px solid #9CA3AF",
                            padding: "10px 12px 10px 40px",
                            fontSize: "17px",
                            color: "#111827",
                            background: "#fff",
                            outline: "none",
                            fontFamily: "inherit",
                        }}
                        onFocus={(e) => {
                            e.target.style.border = "2px solid #1D9E75";
                            e.target.style.paddingLeft = "39px";
                        }}
                        onBlur={(e) => {
                            e.target.style.border = "1px solid #9CA3AF";
                            e.target.style.paddingLeft = "40px";
                        }}
                        autoComplete="off"
                    />
                </div>
                {errors.phone && (
                    <p
                        style={{
                            marginTop: "4px",
                            fontSize: "15px",
                            color: "#DC2626",
                        }}
                    >
                        {errors.phone}
                    </p>
                )}
            </div>

            <button
                type="submit"
                disabled={processing}
                style={{
                    width: "100%",
                    background: processing ? "#6B7280" : "#1D9E75",
                    color: "#fff",
                    border: "none",
                    padding: "13px 20px",
                    fontSize: "20px",
                    fontWeight: 600,
                    cursor: processing ? "not-allowed" : "pointer",
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "center",
                    gap: "8px",
                    fontFamily: "inherit",
                }}
                onMouseOver={(e) => {
                    if (!processing)
                        e.currentTarget.style.background = "#0F6E56";
                }}
                onMouseOut={(e) => {
                    if (!processing)
                        e.currentTarget.style.background = "#1D9E75";
                }}
            >
                <IconDeviceMobileMessage size={20} color="#fff" />
                {processing ? "Sending..." : "Send one-time code"}
            </button>

            <p
                style={{
                    marginTop: "14px",
                    fontSize: "15px",
                    color: "#6B7280",
                    textAlign: "center",
                }}
            >
                A 6-digit code will be sent to your registered phone number.
            </p>
        </form>
    );
}

export default function Login({ canResetPassword, status }: Props) {
    const [tab, setTab] = useState<Tab>("password");

    return (
        <div
            className="min-h-screen flex"
            style={{
                fontFamily:
                    "'Inter', 'Segoe UI Variable', 'Segoe UI', system-ui, sans-serif",
            }}
        >
            <Head title="Sign in" />

            <div
                className="hidden lg:flex flex-col justify-between w-2/5 p-10"
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
                            fontSize: "38px",
                            fontWeight: 700,
                            color: "#fff",
                            lineHeight: 1.25,
                            letterSpacing: "-0.6px",
                        }}
                    >
                        Your farm's financial story, in one place.
                    </h1>
                    <p
                        style={{
                            margin: "0 0 2rem",
                            fontSize: "20px",
                            color: "rgba(255,255,255,0.65)",
                            lineHeight: 1.65,
                        }}
                    >
                        Track income, crops, and livestock. Build a bankable
                        credit profile for your farm.
                    </p>

                    <div
                        style={{
                            height: "1px",
                            background: "rgba(255,255,255,0.15)",
                            marginBottom: "2rem",
                        }}
                    />

                    <div className="flex flex-col gap-4">
                        {features.map((text) => (
                            <div key={text} className="flex items-center gap-3">
                                <div
                                    className="flex items-center justify-center flex-shrink-0"
                                    style={{
                                        width: "22px",
                                        height: "22px",
                                        background: "rgba(255,255,255,0.12)",
                                    }}
                                >
                                    <IconCheck size={13} color="#fff" />
                                </div>
                                <span
                                    style={{
                                        fontSize: "17px",
                                        color: "rgba(255,255,255,0.75)",
                                    }}
                                >
                                    {text}
                                </span>
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

            <div className="w-full lg:w-3/5 flex flex-col justify-center items-center px-8 py-12 bg-white">
                <div className="w-[90%] mx-auto">
                    <Link
                        href="/"
                        className="inline-flex items-center gap-2 mb-8"
                        style={{
                            fontSize: "17px",
                            color: "#6B7280",
                            textDecoration: "none",
                        }}
                        onMouseOver={(e) =>
                            (e.currentTarget.style.color = "#111827")
                        }
                        onMouseOut={(e) =>
                            (e.currentTarget.style.color = "#6B7280")
                        }
                    >
                        <IconArrowLeft size={18} />
                        Back to home
                    </Link>

                    <h2
                        style={{
                            margin: "0 0 4px",
                            fontSize: "38px",
                            fontWeight: 700,
                            color: "#111827",
                            letterSpacing: "-0.5px",
                        }}
                    >
                        Sign in
                    </h2>
                    <p
                        style={{
                            margin: "0 0 1.75rem",
                            fontSize: "20px",
                            color: "#6B7280",
                        }}
                    >
                        to continue to NkwaLedger
                    </p>

                    <div
                        style={{
                            borderBottom: "1px solid #E5E7EB",
                            display: "flex",
                            marginBottom: "1.75rem",
                        }}
                    >
                        {(["password", "otp"] as Tab[]).map((key) => (
                            <button
                                key={key}
                                type="button"
                                onClick={() => setTab(key)}
                                style={{
                                    padding: "10px 18px",
                                    fontSize: "17px",
                                    fontWeight: tab === key ? 600 : 400,
                                    color: tab === key ? "#1D9E75" : "#6B7280",
                                    background: "none",
                                    border: "none",
                                    borderBottom:
                                        tab === key
                                            ? "2px solid #1D9E75"
                                            : "2px solid transparent",
                                    cursor: "pointer",
                                    fontFamily: "inherit",
                                    marginBottom: "-1px",
                                }}
                            >
                                {key === "password"
                                    ? "Password"
                                    : "One-time code"}
                            </button>
                        ))}
                    </div>

                    {tab === "password" ? (
                        <PasswordForm
                            canResetPassword={canResetPassword}
                            status={status}
                        />
                    ) : (
                        <OtpForm />
                    )}

                    <Divider label="or continue with" />

                    <div
                        style={{
                            display: "grid",
                            gridTemplateColumns: "1fr 1fr",
                            gap: "10px",
                            marginBottom: "1.75rem",
                        }}
                    >
                        <a
                            href="/auth/google"
                            style={{
                                padding: "13px 12px",
                                background: "#fff",
                                border: "1px solid #9CA3AF",
                                fontSize: "17px",
                                color: "#111827",
                                cursor: "pointer",
                                display: "flex",
                                alignItems: "center",
                                justifyContent: "center",
                                gap: "8px",
                                textDecoration: "none",
                                fontFamily: "inherit",
                            }}
                            onMouseOver={(e) =>
                                (e.currentTarget.style.background = "#F3F4F6")
                            }
                            onMouseOut={(e) =>
                                (e.currentTarget.style.background = "#fff")
                            }
                        >
                            <svg width="18" height="18" viewBox="0 0 24 24">
                                <path
                                    fill="#4285F4"
                                    d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
                                />
                                <path
                                    fill="#34A853"
                                    d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
                                />
                                <path
                                    fill="#FBBC05"
                                    d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"
                                />
                                <path
                                    fill="#EA4335"
                                    d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"
                                />
                            </svg>
                            Google
                        </a>

                        <a
                            href="/auth/facebook"
                            style={{
                                padding: "13px 12px",
                                background: "#1877F2",
                                border: "none",
                                fontSize: "17px",
                                color: "#fff",
                                cursor: "pointer",
                                display: "flex",
                                alignItems: "center",
                                justifyContent: "center",
                                gap: "8px",
                                textDecoration: "none",
                                fontFamily: "inherit",
                            }}
                            onMouseOver={(e) =>
                                (e.currentTarget.style.background = "#1568D3")
                            }
                            onMouseOut={(e) =>
                                (e.currentTarget.style.background = "#1877F2")
                            }
                        >
                            <IconBrandFacebook size={18} color="#fff" />
                            Facebook
                        </a>
                    </div>

                    <p
                        style={{
                            textAlign: "center",
                            fontSize: "17px",
                            color: "#6B7280",
                            margin: 0,
                        }}
                    >
                        New farmer?{" "}
                        <Link
                            href={route("register")}
                            style={{
                                color: "#1D9E75",
                                textDecoration: "none",
                                fontWeight: 600,
                            }}
                        >
                            Create an account
                        </Link>
                    </p>
                </div>
            </div>
        </div>
    );
}
