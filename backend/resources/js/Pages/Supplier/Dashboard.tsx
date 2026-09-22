import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import { Link } from "@inertiajs/react";

interface Props {
    has_profile: boolean;
    business_name: string | null;
    verification_status: string | null;
    kiosks_count: number;
}

export default function Dashboard(props: Props) {
    return (
        <AuthenticatedLayout title="Dashboard">
            <DashboardContent {...props} />
        </AuthenticatedLayout>
    );
}

const verificationLabel: Record<string, string> = {
    unverified: "Not verified yet",
    email_and_phone_verified: "Email and phone verified",
    id_verified: "ID verified",
};

function DashboardContent({
    has_profile,
    business_name,
    verification_status,
    kiosks_count,
}: Props) {
    const { dark } = useTheme();

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const primary = "#1D9E75";

    if (!has_profile) {
        return (
            <div
                style={{
                    background: surface,
                    border: `1px solid ${border}`,
                    padding: "24px",
                    maxWidth: "480px",
                }}
            >
                <p
                    style={{
                        fontSize: "1.375rem",
                        fontWeight: 700,
                        color: text,
                        marginBottom: "8px",
                    }}
                >
                    Set up your marketplace profile
                </p>
                <p
                    style={{
                        fontSize: "1.0625rem",
                        color: textSecondary,
                        marginBottom: "16px",
                    }}
                >
                    Add your business details and a marketplace email before
                    you can register a kiosk.
                </p>
                <Link
                    href={route("supplier.profile.create")}
                    style={{
                        display: "inline-block",
                        background: primary,
                        color: "#FFFFFF",
                        padding: "10px 20px",
                        fontSize: "1.125rem",
                        fontWeight: 600,
                        textDecoration: "none",
                    }}
                >
                    Get started
                </Link>
            </div>
        );
    }

    return (
        <div
            style={{
                display: "grid",
                gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))",
                gap: "16px",
            }}
        >
            <div
                style={{
                    background: surface,
                    border: `1px solid ${border}`,
                    padding: "18px",
                }}
            >
                <p
                    style={{
                        fontSize: "1.125rem",
                        color: textSecondary,
                        marginBottom: "8px",
                    }}
                >
                    Business
                </p>
                <p
                    style={{
                        fontSize: "1.375rem",
                        fontWeight: 700,
                        color: text,
                    }}
                >
                    {business_name}
                </p>
                <p style={{ fontSize: "1rem", color: textSecondary }}>
                    {verificationLabel[verification_status ?? "unverified"]}
                </p>
            </div>

            <div
                style={{
                    background: surface,
                    border: `1px solid ${border}`,
                    padding: "18px",
                }}
            >
                <p
                    style={{
                        fontSize: "1.125rem",
                        color: textSecondary,
                        marginBottom: "8px",
                    }}
                >
                    Kiosks
                </p>
                <p
                    style={{
                        fontSize: "1.625rem",
                        fontWeight: 700,
                        color: text,
                        marginBottom: "8px",
                    }}
                >
                    {kiosks_count}
                </p>
                <Link
                    href={route("supplier.kiosks.index")}
                    style={{
                        fontSize: "1.0625rem",
                        fontWeight: 600,
                        color: primary,
                    }}
                >
                    Manage kiosks →
                </Link>
            </div>
        </div>
    );
}
