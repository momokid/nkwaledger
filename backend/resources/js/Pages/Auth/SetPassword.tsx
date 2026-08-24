import FlashMessages from "@/Components/FlashMessages";
import { Head, useForm } from "@inertiajs/react";
import { FormEvent, useState } from "react";

interface Props {
    firstName: string;
}

export default function SetPassword({ firstName }: Props) {
    const { data, setData, post, processing, errors, clearErrors, setError } =
        useForm({ password: "", password_confirmation: "" });

    const [showing, setShowing] = useState(false);

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (!data.password) {
            clearErrors();
            setError("password", "Choose a password to finish setting up.");
            return;
        }

        if (data.password !== data.password_confirmation) {
            clearErrors();
            setError(
                "password",
                "Those two don't match yet. Type them again to be sure.",
            );
            return;
        }

        post(route("activation.password.store"));
    };

    const inputStyle = (hasError: boolean) => ({
        width: "100%",
        border: hasError ? "1px solid #DC2626" : "1px solid #9CA3AF",
        background: "#FFFFFF",
        color: "#111827",
        padding: "13px 14px",
        fontSize: "23px",
        outline: "none",
        fontFamily: "inherit",
    });

    const labelStyle = {
        display: "block",
        fontSize: "21px",
        fontWeight: 600,
        color: "#111827",
        marginBottom: "6px",
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
            <Head title="Set your password" />
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
                    Welcome, {firstName}
                </h1>

                <p
                    style={{
                        margin: "8px 0 28px",
                        fontSize: "21px",
                        lineHeight: 1.5,
                        color: "#6B7280",
                    }}
                >
                    One last step. Choose a password only you know, and your
                    account is ready.
                </p>

                <form onSubmit={submit}>
                    <div style={{ marginBottom: "20px" }}>
                        <label htmlFor="password" style={labelStyle}>
                            New password
                        </label>

                        <input
                            id="password"
                            type={showing ? "text" : "password"}
                            autoFocus
                            autoComplete="new-password"
                            value={data.password}
                            onChange={(event) =>
                                setData("password", event.target.value)
                            }
                            style={inputStyle(!!errors.password)}
                        />

                        {errors.password && (
                            <p
                                style={{
                                    margin: "6px 0 0",
                                    fontSize: "20px",
                                    color: "#DC2626",
                                }}
                            >
                                {errors.password}
                            </p>
                        )}

                        <p
                            style={{
                                margin: "6px 0 0",
                                fontSize: "20px",
                                color: "#6B7280",
                            }}
                        >
                            At least 6 characters.
                        </p>
                    </div>

                    <div style={{ marginBottom: "16px" }}>
                        <label
                            htmlFor="password_confirmation"
                            style={labelStyle}
                        >
                            Type it again
                        </label>

                        <input
                            id="password_confirmation"
                            type={showing ? "text" : "password"}
                            autoComplete="new-password"
                            value={data.password_confirmation}
                            onChange={(event) =>
                                setData(
                                    "password_confirmation",
                                    event.target.value,
                                )
                            }
                            style={inputStyle(false)}
                        />
                    </div>

                    <label
                        style={{
                            display: "flex",
                            alignItems: "center",
                            gap: "8px",
                            fontSize: "20px",
                            color: "#6B7280",
                            cursor: "pointer",
                        }}
                    >
                        <input
                            type="checkbox"
                            checked={showing}
                            onChange={(event) =>
                                setShowing(event.target.checked)
                            }
                            style={{
                                width: "18px",
                                height: "18px",
                                cursor: "pointer",
                            }}
                        />
                        Show password
                    </label>

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
                        {processing ? "Setting up..." : "Finish setting up"}
                    </button>
                </form>
            </div>
        </div>
    );
}
