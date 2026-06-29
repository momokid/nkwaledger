import { Head, Link, router, useForm } from "@inertiajs/react";
import { IconDeviceMobileMessage } from "@tabler/icons-react";
import { FormEventHandler, useEffect, useRef, useState } from "react";

interface Props {
    identifier: string;
    type: "registration" | "login";
}

export default function VerifyOtp({ identifier, type }: Props) {
    const [digits, setDigits] = useState<string[]>(["", "", "", "", "", ""]);
    const [countdown, setCountdown] = useState(60);
    const [canResend, setCanResend] = useState(false);
    const inputRefs = useRef<(HTMLInputElement | null)[]>([]);

    const { post, setData, processing, errors } = useForm({
        identifier: identifier,
        code: "",
        type: type,
    });

    useEffect(() => {
        inputRefs.current[0]?.focus();
    }, []);

    useEffect(() => {
        if (countdown <= 0) {
            setCanResend(true);
            return;
        }
        const timer = setTimeout(() => setCountdown((c) => c - 1), 1000);
        return () => clearTimeout(timer);
    }, [countdown]);

    useEffect(() => {
        const code = digits.join("");
        setData("code", code);
        if (code.length === 6) {
            post(route("otp.store"));
        }
    }, [digits]);

    const handleChange = (index: number, value: string) => {
        if (!/^\d*$/.test(value)) return;
        const updated = [...digits];
        updated[index] = value.slice(-1);
        setDigits(updated);
        if (value && index < 5) {
            inputRefs.current[index + 1]?.focus();
        }
    };

    const handleKeyDown = (
        index: number,
        e: React.KeyboardEvent<HTMLInputElement>,
    ) => {
        if (e.key === "Backspace" && !digits[index] && index > 0) {
            inputRefs.current[index - 1]?.focus();
        }
    };

    const handlePaste = (e: React.ClipboardEvent<HTMLInputElement>) => {
        e.preventDefault();
        const pasted = e.clipboardData
            .getData("text")
            .replace(/\D/g, "")
            .slice(0, 6);
        if (!pasted) return;
        const updated = [...digits];
        pasted.split("").forEach((char, i) => {
            updated[i] = char;
        });
        setDigits(updated);
        inputRefs.current[Math.min(pasted.length, 5)]?.focus();
    };

    const handleResend = (e: React.MouseEvent<HTMLAnchorElement>) => {
        e.preventDefault();
        router.post(
            route("otp.resend"),
            {
                identifier,
                type,
            },
            {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    setCountdown(60);
                    setCanResend(false);
                    setDigits(["", "", "", "", "", ""]);
                },
            },
        );
    };
    const formatCountdown = (seconds: number) => {
        const m = Math.floor(seconds / 60);
        const s = seconds % 60;
        return `${m}:${s.toString().padStart(2, "0")}`;
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route("otp.store"));
    };

    const isFilled = (index: number) => digits[index] !== "";

    return (
        <div
            className="min-h-screen flex items-center justify-center p-4"
            style={{
                background: "#F3F4F6",
                fontFamily:
                    "'Inter', 'Segoe UI Variable', 'Segoe UI', system-ui, sans-serif",
            }}
        >
            <Head title="Verify your phone" />

            <div className="w-full" style={{ maxWidth: "420px" }}>
                <div
                    style={{ border: "1px solid #D1D5DB", overflow: "hidden" }}
                >
                    <div
                        className="text-center"
                        style={{
                            background: "#0F6E56",
                            padding: "2.5rem 2rem",
                        }}
                    >
                        <div
                            className="flex items-center justify-center mx-auto mb-4"
                            style={{
                                width: "64px",
                                height: "64px",
                                background: "rgba(255,255,255,0.12)",
                            }}
                        >
                            <IconDeviceMobileMessage size={32} color="#fff" />
                        </div>
                        <h2
                            style={{
                                margin: "0 0 6px",
                                fontSize: "31px",
                                fontWeight: 700,
                                color: "#fff",
                                letterSpacing: "-0.4px",
                            }}
                        >
                            Verify your phone
                        </h2>
                        <p
                            style={{
                                margin: 0,
                                fontSize: "18px",
                                color: "rgba(255,255,255,0.62)",
                            }}
                        >
                            Code sent to {identifier}
                        </p>
                    </div>

                    <div style={{ background: "#fff", padding: "2rem" }}>
                        <p
                            style={{
                                margin: "0 0 1.75rem",
                                fontSize: "20px",
                                color: "#6B7280",
                                lineHeight: 1.6,
                                textAlign: "center",
                            }}
                        >
                            Enter the 6-digit code sent to your phone number.
                        </p>

                        {errors.code && (
                            <p
                                style={{
                                    marginBottom: "1rem",
                                    fontSize: "17px",
                                    color: "#DC2626",
                                    textAlign: "center",
                                }}
                            >
                                {errors.code}
                            </p>
                        )}

                        <form onSubmit={submit}>
                            <div className="flex justify-center gap-2 mb-6">
                                {digits.map((digit, index) => (
                                    <input
                                        key={index}
                                        ref={(el) => {
                                            inputRefs.current[index] = el;
                                        }}
                                        type="text"
                                        inputMode="numeric"
                                        maxLength={1}
                                        value={digit}
                                        onChange={(e) =>
                                            handleChange(index, e.target.value)
                                        }
                                        onKeyDown={(e) =>
                                            handleKeyDown(index, e)
                                        }
                                        onPaste={handlePaste}
                                        style={{
                                            width: "52px",
                                            height: "60px",
                                            textAlign: "center",
                                            fontSize: "28px",
                                            fontWeight: 700,
                                            border: isFilled(index)
                                                ? "2px solid #1D9E75"
                                                : "1px solid #9CA3AF",
                                            background: isFilled(index)
                                                ? "#EAF5F0"
                                                : "#fff",
                                            color: "#111827",
                                            outline: "none",
                                            fontFamily: "inherit",
                                        }}
                                        onFocus={(e) => {
                                            e.target.style.border =
                                                "2px solid #1D9E75";
                                            e.target.style.background =
                                                "#EAF5F0";
                                        }}
                                        onBlur={(e) => {
                                            if (!isFilled(index)) {
                                                e.target.style.border =
                                                    "1px solid #9CA3AF";
                                                e.target.style.background =
                                                    "#fff";
                                            }
                                        }}
                                    />
                                ))}
                            </div>

                            <p
                                style={{
                                    textAlign: "center",
                                    fontSize: "17px",
                                    color: "#6B7280",
                                    margin: "0 0 1.5rem",
                                }}
                            >
                                Didn't receive it?{" "}
                                {canResend ? (
                                    <a
                                        href="#"
                                        onClick={handleResend}
                                        style={{
                                            color: "#1D9E75",
                                            textDecoration: "none",
                                            fontWeight: 600,
                                        }}
                                    >
                                        Resend code
                                    </a>
                                ) : (
                                    <span
                                        style={{
                                            color: "#1D9E75",
                                            fontWeight: 600,
                                        }}
                                    >
                                        Resend in {formatCountdown(countdown)}
                                    </span>
                                )}
                            </p>

                            <div className="flex flex-col gap-2">
                                <button
                                    type="submit"
                                    disabled={
                                        processing || digits.join("").length < 6
                                    }
                                    style={{
                                        width: "100%",
                                        background:
                                            processing ||
                                            digits.join("").length < 6
                                                ? "#6B7280"
                                                : "#1D9E75",
                                        color: "#fff",
                                        border: "none",
                                        padding: "13px 20px",
                                        fontSize: "20px",
                                        fontWeight: 600,
                                        cursor:
                                            processing ||
                                            digits.join("").length < 6
                                                ? "not-allowed"
                                                : "pointer",
                                        display: "flex",
                                        alignItems: "center",
                                        justifyContent: "center",
                                        fontFamily: "inherit",
                                    }}
                                    onMouseOver={(e) => {
                                        if (
                                            !processing &&
                                            digits.join("").length === 6
                                        )
                                            e.currentTarget.style.background =
                                                "#0F6E56";
                                    }}
                                    onMouseOut={(e) => {
                                        if (
                                            !processing &&
                                            digits.join("").length === 6
                                        )
                                            e.currentTarget.style.background =
                                                "#1D9E75";
                                    }}
                                >
                                    {processing ? "Verifying..." : "Verify"}
                                </button>

                                <Link
                                    href={route("login")}
                                    style={{
                                        width: "100%",
                                        background: "#fff",
                                        color: "#111827",
                                        border: "1px solid #9CA3AF",
                                        padding: "13px 20px",
                                        fontSize: "20px",
                                        fontWeight: 400,
                                        display: "flex",
                                        alignItems: "center",
                                        justifyContent: "center",
                                        textDecoration: "none",
                                        fontFamily: "inherit",
                                    }}
                                    onMouseOver={(e) =>
                                        (e.currentTarget.style.background =
                                            "#F3F4F6")
                                    }
                                    onMouseOut={(e) =>
                                        (e.currentTarget.style.background =
                                            "#fff")
                                    }
                                >
                                    Go back
                                </Link>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    );
}
