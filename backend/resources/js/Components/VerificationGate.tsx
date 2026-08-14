import { useForm, usePage, router } from "@inertiajs/react";
import { PropsWithChildren, useState } from "react";
import { IconShieldLock } from "@tabler/icons-react";
import { useTheme } from "@/Layouts/AuthenticatedLayout";
import VerificationGate from "@/Components/VerificationGate";

interface GateProps {
    auth: {
        user: {
            phone?: string;
            is_phone_verified?: boolean;
        } | null;
    };
}

export default function VerificationGate({ children }: PropsWithChildren) {
    const { auth } = usePage().props as unknown as GateProps;
    const { dark } = useTheme();
    const [sent, setSent] = useState(false);
    const [sending, setSending] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        code: "",
    });

    // verified users see the real page
    if (auth?.user?.is_phone_verified !== false) {
        return <>{children}</>;
    }

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const primary = "#1D9E75";
    const danger = "#DC2626";

    const sendCode = () => {
        setSending(true);
        router.post(
            route("otp.phone.send"),
            {},
            {
                preserveScroll: true,
                onSuccess: () => setSent(true),
                onFinish: () => setSending(false),
            },
        );
    };

    const confirmCode = () => {
        post(route("otp.phone.confirm"), {
            preserveScroll: true,
            onError: () => reset("code"),
        });
    };

    const inputStyle = {
        width: "100%",
        padding: "12px 14px",
        fontSize: "16px",
        letterSpacing: "4px",
        background: dark ? "#111827" : "#FFFFFF",
        border: `1px solid ${errors.code ? danger : border}`,
        borderRadius: 0,
        color: text,
        fontFamily: "'Inter', system-ui, sans-serif",
        outline: "none",
    };

    const buttonStyle = {
        width: "100%",
        padding: "12px 14px",
        fontSize: "15px",
        fontWeight: 600,
        background: primary,
        color: "#FFFFFF",
        border: "none",
        borderRadius: 0,
        cursor: "pointer",
        fontFamily: "'Inter', system-ui, sans-serif",
    };

    return (
        <div
            style={{
                display: "flex",
                justifyContent: "center",
                padding: "48px 24px",
            }}
        >
            <div
                style={{
                    width: "100%",
                    maxWidth: "440px",
                    background: surface,
                    border: `1px solid ${border}`,
                    padding: "32px",
                }}
            >
                <IconShieldLock size={40} stroke={1.5} color={primary} />

                <h2
                    style={{
                        fontSize: "20px",
                        fontWeight: 600,
                        color: text,
                        margin: "16px 0 8px",
                    }}
                >
                    Verify your phone number
                </h2>

                <p
                    style={{
                        fontSize: "15px",
                        color: textSecondary,
                        lineHeight: 1.6,
                        marginBottom: "24px",
                    }}
                >
                    We need to confirm you own{" "}
                    {auth.user?.phone ?? "this number"} before you can use your
                    account.
                </p>

                {!sent ? (
                    <button
                        onClick={sendCode}
                        disabled={sending}
                        style={buttonStyle}
                    >
                        {sending ? "Sending..." : "Send me a code"}
                    </button>
                ) : (
                    <>
                        <label
                            style={{
                                display: "block",
                                fontSize: "14px",
                                color: textSecondary,
                                marginBottom: "8px",
                            }}
                        >
                            Enter the 6 digit code
                        </label>

                        <input
                            type="text"
                            inputMode="numeric"
                            maxLength={6}
                            value={data.code}
                            onChange={(e) =>
                                setData(
                                    "code",
                                    e.target.value.replace(/\D/g, ""),
                                )
                            }
                            style={inputStyle}
                        />

                        {errors.code && (
                            <p
                                style={{
                                    fontSize: "14px",
                                    color: danger,
                                    marginTop: "8px",
                                }}
                            >
                                {errors.code}
                            </p>
                        )}

                        <button
                            onClick={confirmCode}
                            disabled={processing || data.code.length < 6}
                            style={{ ...buttonStyle, marginTop: "16px" }}
                        >
                            {processing ? "Checking..." : "Verify"}
                        </button>

                        <button
                            onClick={sendCode}
                            disabled={sending}
                            style={{
                                width: "100%",
                                marginTop: "12px",
                                padding: "10px",
                                background: "transparent",
                                border: "none",
                                color: textSecondary,
                                fontSize: "14px",
                                cursor: "pointer",
                                fontFamily: "'Inter', system-ui, sans-serif",
                            }}
                        >
                            Send a new code
                        </button>
                    </>
                )}
            </div>
        </div>
    );
}
