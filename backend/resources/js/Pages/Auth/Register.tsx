import { Head, Link, useForm } from "@inertiajs/react";
import {
    IconArrowLeft,
    IconBrandFacebook,
    IconCheck,
    IconInfoCircle,
    IconPlant,
    IconPlant2,
} from "@tabler/icons-react";
import { FormEventHandler } from "react";

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

function InputField({
    label,
    optional = false,
    error,
    children,
}: {
    label: string;
    optional?: boolean;
    error?: string;
    children: React.ReactNode;
}) {
    return (
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
                {label}{" "}
                {optional && (
                    <span
                        style={{
                            fontSize: "15px",
                            fontWeight: 400,
                            color: "#9CA3AF",
                        }}
                    >
                        (optional)
                    </span>
                )}
            </label>
            {children}
            {error && (
                <p
                    style={{
                        marginTop: "4px",
                        fontSize: "15px",
                        color: "#DC2626",
                    }}
                >
                    {error}
                </p>
            )}
        </div>
    );
}

const inputStyle: React.CSSProperties = {
    width: "100%",
    border: "1px solid #9CA3AF",
    padding: "10px 12px",
    fontSize: "17px",
    color: "#111827",
    background: "#fff",
    outline: "none",
    fontFamily: "inherit",
};

const steps = [
    { number: "1", label: "Your details", active: true },
    { number: "2", label: "Verify phone", active: false },
    { number: "3", label: "Done", active: false },
];

export default function Register() {
    const { data, setData, post, processing, errors } = useForm({
        surname: "",
        first_name: "",
        other_name: "",
        phone: "",
        email: "",
        password: "",
        password_confirmation: "",
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route("register"));
    };

    const focusStyle = (e: React.FocusEvent<HTMLInputElement>) => {
        e.target.style.border = "2px solid #1D9E75";
        e.target.style.padding = "9px 11px";
    };

    const blurStyle = (e: React.FocusEvent<HTMLInputElement>) => {
        e.target.style.border = "1px solid #9CA3AF";
        e.target.style.padding = "10px 12px";
    };

    return (
        <div
            className="min-h-screen flex"
            style={{
                fontFamily:
                    "'Inter', 'Segoe UI Variable', 'Segoe UI', system-ui, sans-serif",
            }}
        >
            <Head title="Create account" />

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

                    <div
                        style={{
                            marginBottom: "6px",
                            fontSize: "13px",
                            fontWeight: 600,
                            color: "rgba(255,255,255,0.4)",
                            letterSpacing: "0.8px",
                            textTransform: "uppercase",
                        }}
                    >
                        Steps
                    </div>

                    <div className="flex flex-col">
                        {steps.map((step, index) => (
                            <div
                                key={step.number}
                                style={{
                                    display: "flex",
                                    gap: "12px",
                                    alignItems: "flex-start",
                                    paddingBottom:
                                        index < steps.length - 1 ? "0" : "0",
                                }}
                            >
                                <div
                                    style={{
                                        display: "flex",
                                        flexDirection: "column",
                                        alignItems: "center",
                                        flexShrink: 0,
                                    }}
                                >
                                    <div
                                        style={{
                                            width: "28px",
                                            height: "28px",
                                            background: step.active
                                                ? "#BA7517"
                                                : "rgba(255,255,255,0.13)",
                                            display: "flex",
                                            alignItems: "center",
                                            justifyContent: "center",
                                            fontSize: "13px",
                                            fontWeight: 700,
                                            color: "#fff",
                                        }}
                                    >
                                        {step.active ? (
                                            <IconCheck size={14} color="#fff" />
                                        ) : (
                                            step.number
                                        )}
                                    </div>
                                    {index < steps.length - 1 && (
                                        <div
                                            style={{
                                                width: "1.5px",
                                                height: "24px",
                                                background: step.active
                                                    ? "#BA7517"
                                                    : "rgba(255,255,255,0.13)",
                                            }}
                                        />
                                    )}
                                </div>
                                <div
                                    style={{
                                        paddingTop: "4px",
                                        paddingBottom:
                                            index < steps.length - 1
                                                ? "24px"
                                                : "0",
                                    }}
                                >
                                    <div
                                        style={{
                                            fontSize: "17px",
                                            fontWeight: step.active ? 600 : 400,
                                            color: step.active
                                                ? "#fff"
                                                : "rgba(255,255,255,0.4)",
                                        }}
                                    >
                                        {step.label}
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

                    <div className="flex items-center gap-3 mb-1">
                        <h2
                            style={{
                                margin: 0,
                                fontSize: "38px",
                                fontWeight: 700,
                                color: "#111827",
                                letterSpacing: "-0.5px",
                            }}
                        >
                            Create account
                        </h2>
                        <span
                            className="inline-flex items-center gap-1"
                            style={{
                                fontSize: "15px",
                                fontWeight: 600,
                                color: "#0F6E56",
                                background: "#EAF5F0",
                                border: "1px solid #A8D9C8",
                                padding: "4px 10px",
                            }}
                        >
                            <IconPlant2 size={14} color="#0F6E56" />
                            Farmer
                        </span>
                    </div>

                    <p
                        style={{
                            margin: "0 0 1.75rem",
                            fontSize: "20px",
                            color: "#6B7280",
                        }}
                    >
                        Fill in your details to get started
                    </p>

                    <form onSubmit={submit}>
                        <div
                            style={{
                                display: "grid",
                                gridTemplateColumns: "1fr 1fr",
                                gap: "0 16px",
                            }}
                        >
                            <InputField label="Surname" error={errors.surname}>
                                <input
                                    type="text"
                                    placeholder="Mensah"
                                    value={data.surname}
                                    onChange={(e) =>
                                        setData("surname", e.target.value)
                                    }
                                    style={inputStyle}
                                    onFocus={focusStyle}
                                    onBlur={blurStyle}
                                />
                            </InputField>

                            <InputField
                                label="First name"
                                error={errors.first_name}
                            >
                                <input
                                    type="text"
                                    placeholder="Kwame"
                                    value={data.first_name}
                                    onChange={(e) =>
                                        setData("first_name", e.target.value)
                                    }
                                    style={inputStyle}
                                    onFocus={focusStyle}
                                    onBlur={blurStyle}
                                />
                            </InputField>
                        </div>

                        <InputField
                            label="Other name"
                            optional
                            error={errors.other_name}
                        >
                            <input
                                type="text"
                                placeholder="Asante"
                                value={data.other_name}
                                onChange={(e) =>
                                    setData("other_name", e.target.value)
                                }
                                style={inputStyle}
                                onFocus={focusStyle}
                                onBlur={blurStyle}
                            />
                        </InputField>

                        <InputField label="Phone number" error={errors.phone}>
                            <div style={{ display: "flex" }}>
                                <div
                                    style={{
                                        display: "flex",
                                        alignItems: "center",
                                        padding: "10px 12px",
                                        background: "#F9FAFB",
                                        border: "1px solid #9CA3AF",
                                        borderRight: "none",
                                        fontSize: "17px",
                                        color: "#111827",
                                        whiteSpace: "nowrap",
                                        flexShrink: 0,
                                    }}
                                >
                                    🇬🇭 +233
                                </div>
                                <input
                                    type="tel"
                                    placeholder="XX XXX XXXX"
                                    value={data.phone}
                                    onChange={(e) =>
                                        setData("phone", e.target.value)
                                    }
                                    style={{ ...inputStyle, flex: 1 }}
                                    onFocus={(e) => {
                                        e.target.style.border =
                                            "2px solid #1D9E75";
                                        e.target.style.padding = "9px 11px";
                                    }}
                                    onBlur={(e) => {
                                        e.target.style.border =
                                            "1px solid #9CA3AF";
                                        e.target.style.padding = "10px 12px";
                                    }}
                                />
                            </div>
                        </InputField>

                        <InputField
                            label="Email address"
                            optional
                            error={errors.email}
                        >
                            <input
                                type="email"
                                placeholder="kwame@example.com"
                                value={data.email}
                                onChange={(e) =>
                                    setData("email", e.target.value)
                                }
                                style={inputStyle}
                                onFocus={focusStyle}
                                onBlur={blurStyle}
                            />
                        </InputField>

                        <InputField label="Password" error={errors.password}>
                            <input
                                type="password"
                                placeholder="At least 6 characters"
                                value={data.password}
                                onChange={(e) =>
                                    setData("password", e.target.value)
                                }
                                style={inputStyle}
                                onFocus={focusStyle}
                                onBlur={blurStyle}
                            />
                        </InputField>

                        <InputField
                            label="Confirm password"
                            error={errors.password_confirmation}
                        >
                            <input
                                type="password"
                                placeholder="Repeat your password"
                                value={data.password_confirmation}
                                onChange={(e) =>
                                    setData(
                                        "password_confirmation",
                                        e.target.value,
                                    )
                                }
                                style={inputStyle}
                                onFocus={focusStyle}
                                onBlur={blurStyle}
                            />
                        </InputField>

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
                                    e.currentTarget.style.background =
                                        "#0F6E56";
                            }}
                            onMouseOut={(e) => {
                                if (!processing)
                                    e.currentTarget.style.background =
                                        "#1D9E75";
                            }}
                        >
                            {processing
                                ? "Creating account..."
                                : "Create account"}
                        </button>
                    </form>

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

                    <div
                        style={{
                            padding: "14px 16px",
                            background: "#F9FAFB",
                            border: "1px solid #E5E7EB",
                            display: "flex",
                            gap: "12px",
                            alignItems: "flex-start",
                            marginBottom: "1.25rem",
                        }}
                    >
                        <IconInfoCircle
                            size={18}
                            color="#9CA3AF"
                            style={{ flexShrink: 0, marginTop: "2px" }}
                        />
                        <p
                            style={{
                                margin: 0,
                                fontSize: "15px",
                                color: "#6B7280",
                                lineHeight: 1.55,
                            }}
                        >
                            Are you an Agent, Vet, Adviser, or Supplier?
                            Self-registration is for farmers only.{" "}
                            <a
                                href="#"
                                style={{
                                    color: "#1D9E75",
                                    textDecoration: "none",
                                    fontWeight: 600,
                                }}
                            >
                                Contact your admin for an invite.
                            </a>
                        </p>
                    </div>

                    <p
                        style={{
                            textAlign: "center",
                            fontSize: "17px",
                            color: "#6B7280",
                            margin: 0,
                        }}
                    >
                        Already have an account?{" "}
                        <Link
                            href={route("login")}
                            style={{
                                color: "#1D9E75",
                                textDecoration: "none",
                                fontWeight: 600,
                            }}
                        >
                            Sign in
                        </Link>
                    </p>
                </div>
            </div>
        </div>
    );
}
