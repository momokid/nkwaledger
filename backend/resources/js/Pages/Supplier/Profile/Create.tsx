import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import BackLink from "@/Components/BackLink";
import { useForm } from "@inertiajs/react";
import { FormEvent } from "react";

interface SupplierData {
    business_name: string | null;
    business_registration_number: string | null;
    email: string;
    email_verified: boolean;
    verification_status: string;
}

interface Props {
    supplier: SupplierData | null;
}

export default function Create({ supplier }: Props) {
    return (
        <AuthenticatedLayout title="Marketplace Profile">
            <BackLink fallbackHref="/supplier/dashboard" />
            <CreateContent supplier={supplier} />
        </AuthenticatedLayout>
    );
}

function CreateContent({ supplier }: Props) {
    const { dark } = useTheme();

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const inputBorder = dark ? "#4B5563" : "#9CA3AF";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const primary = "#1D9E75";

    const inputStyle = {
        border: `1px solid ${inputBorder}`,
        background: dark ? "#111827" : "#FFFFFF",
        color: text,
        padding: "10px 12px",
        fontSize: "1.125rem",
        width: "100%",
        maxWidth: "360px",
        outline: "none",
    };

    const profileForm = useForm({
        business_name: "",
        business_registration_number: "",
        email: "",
    });

    const submitProfile = (event: FormEvent) => {
        event.preventDefault();
        profileForm.post(route("supplier.profile.store"));
    };

    const codeForm = useForm({ code: "" });

    const submitCode = (event: FormEvent) => {
        event.preventDefault();
        codeForm.post(route("supplier.profile.verify-email"), {
            onSuccess: () => codeForm.reset("code"),
        });
    };

    if (supplier && supplier.email_verified) {
        return (
            <div
                style={{
                    background: surface,
                    border: `1px solid ${border}`,
                    padding: "24px",
                    maxWidth: "480px",
                }}
            >
                <p style={{ fontSize: "1.25rem", fontWeight: 700, color: text }}>
                    Your marketplace profile is set up
                </p>
                <p style={{ fontSize: "1.0625rem", color: textSecondary }}>
                    {supplier.email} is verified.
                </p>
            </div>
        );
    }

    if (supplier && !supplier.email_verified) {
        return (
            <form
                onSubmit={submitCode}
                style={{
                    background: surface,
                    border: `1px solid ${border}`,
                    padding: "24px",
                    maxWidth: "360px",
                }}
            >
                <p
                    style={{
                        fontSize: "1.25rem",
                        fontWeight: 700,
                        color: text,
                        marginBottom: "8px",
                    }}
                >
                    Verify your email
                </p>
                <p
                    style={{
                        fontSize: "1.0625rem",
                        color: textSecondary,
                        marginBottom: "16px",
                    }}
                >
                    Enter the code sent to {supplier.email}.
                </p>
                <input
                    type="text"
                    value={codeForm.data.code}
                    onChange={(event) =>
                        codeForm.setData("code", event.target.value)
                    }
                    placeholder="Verification code"
                    style={{ ...inputStyle, marginBottom: "8px" }}
                />
                {codeForm.errors.code && (
                    <p style={{ color: "#DC2626", fontSize: "1rem" }}>
                        {codeForm.errors.code}
                    </p>
                )}
                <button
                    type="submit"
                    disabled={codeForm.processing}
                    style={{
                        background: primary,
                        color: "#FFFFFF",
                        border: "none",
                        padding: "10px 20px",
                        fontSize: "1.125rem",
                        fontWeight: 600,
                        marginTop: "8px",
                        cursor: codeForm.processing ? "not-allowed" : "pointer",
                    }}
                >
                    Verify
                </button>
            </form>
        );
    }

    return (
        <form
            onSubmit={submitProfile}
            style={{
                background: surface,
                border: `1px solid ${border}`,
                padding: "24px",
                maxWidth: "420px",
                display: "flex",
                flexDirection: "column",
                gap: "16px",
            }}
        >
            <p style={{ fontSize: "1.25rem", fontWeight: 700, color: text }}>
                Marketplace profile
            </p>

            <div>
                <label
                    style={{
                        display: "block",
                        fontSize: "1.0625rem",
                        fontWeight: 600,
                        color: text,
                        marginBottom: "6px",
                    }}
                >
                    Business name
                </label>
                <input
                    type="text"
                    value={profileForm.data.business_name}
                    onChange={(event) =>
                        profileForm.setData("business_name", event.target.value)
                    }
                    style={inputStyle}
                />
                {profileForm.errors.business_name && (
                    <p style={{ color: "#DC2626", fontSize: "1rem" }}>
                        {profileForm.errors.business_name}
                    </p>
                )}
            </div>

            <div>
                <label
                    style={{
                        display: "block",
                        fontSize: "1.0625rem",
                        fontWeight: 600,
                        color: text,
                        marginBottom: "6px",
                    }}
                >
                    Business registration number (optional)
                </label>
                <input
                    type="text"
                    value={profileForm.data.business_registration_number}
                    onChange={(event) =>
                        profileForm.setData(
                            "business_registration_number",
                            event.target.value,
                        )
                    }
                    style={inputStyle}
                />
            </div>

            <div>
                <label
                    style={{
                        display: "block",
                        fontSize: "1.0625rem",
                        fontWeight: 600,
                        color: text,
                        marginBottom: "6px",
                    }}
                >
                    Email
                </label>
                <input
                    type="email"
                    value={profileForm.data.email}
                    onChange={(event) =>
                        profileForm.setData("email", event.target.value)
                    }
                    style={inputStyle}
                />
                {profileForm.errors.email && (
                    <p style={{ color: "#DC2626", fontSize: "1rem" }}>
                        {profileForm.errors.email}
                    </p>
                )}
            </div>

            <button
                type="submit"
                disabled={profileForm.processing}
                style={{
                    background: primary,
                    color: "#FFFFFF",
                    border: "none",
                    padding: "10px 20px",
                    fontSize: "1.125rem",
                    fontWeight: 600,
                    cursor: profileForm.processing ? "not-allowed" : "pointer",
                }}
            >
                Send verification code
            </button>
        </form>
    );
}
