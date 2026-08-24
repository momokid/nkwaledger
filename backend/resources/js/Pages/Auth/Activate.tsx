import FlashMessages from "@/Components/FlashMessages";
import { Head, Link, useForm } from "@inertiajs/react";
import { FormEvent } from "react";

export default function Activate() {
    const { data, setData, post, processing, errors, clearErrors, setError } =
        useForm({ phone: "" });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (!data.phone.trim()) {
            clearErrors();
            setError("phone", "Enter the number your invitation was sent to.");
            return;
        }

        post(route("activation.store"));
    };

    return (
        <div
            style={{
                minHeight: "100vh",
                background: "#F9FAFB",
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
                padding: "20px",
                fontFamily: "'Inter', system-ui, sans-serif",
            }}
        >
            <Head title="Activate your account" />
            <FlashMessages />

            <div
                style={{
                    width: "100%",
                    maxWidth: "460px",
                    background: "#FFFFFF",
                    border: "1px solid #E5E7EB",
                    padding: "36px 32px",
                }}
            >
                <h1
                    style={{
                        margin: 0,
                        fontSize: "26px",
                        fontWeight: 700,
                        color: "#111827",
                    }}
                >
                    Activate your account
                </h1>

                <p
                    style={{
                        margin: "8px 0 28px",
                        fontSize: "20px",
                        lineHeight: 1.5,
                        color: "#6B7280",
                    }}
                >
                    Enter the phone number your invitation was sent to. We'll
                    check your code and get you set up.
                </p>

                <form onSubmit={submit}>
                    <label
                        htmlFor="phone"
                        style={{
                            display: "block",
                            fontSize: "20px",
                            fontWeight: 600,
                            color: "#111827",
                            marginBottom: "6px",
                        }}
                    >
                        Phone number
                    </label>

                    <input
                        id="phone"
                        type="tel"
                        inputMode="tel"
                        autoFocus
                        placeholder="0244 445 566"
                        value={data.phone}
                        onChange={(event) =>
                            setData("phone", event.target.value)
                        }
                        style={{
                            width: "100%",
                            border: errors.phone
                                ? "1px solid #DC2626"
                                : "1px solid #9CA3AF",
                            background: "#FFFFFF",
                            color: "#111827",
                            padding: "13px 14px",
                            fontSize: "23px",
                            outline: "none",
                            fontFamily: "inherit",
                        }}
                        onFocus={(event) => {
                            event.target.style.border = "2px solid #1D9E75";
                        }}
                        onBlur={(event) => {
                            event.target.style.border = errors.phone
                                ? "1px solid #DC2626"
                                : "1px solid #9CA3AF";
                        }}
                    />

                    {errors.phone && (
                        <p
                            style={{
                                margin: "6px 0 0",
                                fontSize: "20px",
                                color: "#DC2626",
                            }}
                        >
                            {errors.phone}
                        </p>
                    )}

                    <button
                        type="submit"
                        disabled={processing}
                        style={{
                            width: "100%",
                            marginTop: "24px",
                            background: processing ? "#6B7280" : "#1D9E75",
                            color: "#FFFFFF",
                            border: "none",
                            padding: "13px 20px",
                            fontSize: "23px",
                            fontWeight: 600,
                            cursor: processing ? "not-allowed" : "pointer",
                            fontFamily: "inherit",
                        }}
                    >
                        {processing ? "Checking..." : "Continue"}
                    </button>
                </form>

                <p
                    style={{
                        margin: "24px 0 0",
                        fontSize: "20px",
                        color: "#6B7280",
                        textAlign: "center",
                    }}
                >
                    Already set up?{" "}
                    <Link
                        href={route("login")}
                        style={{ color: "#1D9E75", fontWeight: 600 }}
                    >
                        Sign in
                    </Link>
                </p>
            </div>
        </div>
    );
}
