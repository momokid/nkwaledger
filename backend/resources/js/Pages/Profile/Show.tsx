import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import { PageProps } from "@/types";

interface Props extends PageProps {
    profile: { name: string; phone: string; email: string | null };
}

export default function Show(props: Props) {
    return (
        <AuthenticatedLayout title="Profile">
            <ShowContent profile={props.profile} />
        </AuthenticatedLayout>
    );
}

function ShowContent({ profile }: Pick<Props, "profile">) {
    const { dark } = useTheme();

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";

    const rows: [string, string | null][] = [
        ["Name", profile.name],
        ["Phone", profile.phone],
        ["Email", profile.email],
    ];

    return (
        <div className="p-6">
            <p style={{ fontSize: "1.625rem", fontWeight: 700, color: text, marginBottom: "16px" }}>
                Profile
            </p>
            <div style={{ background: surface, border: `1px solid ${border}` }}>
                {rows.map(([label, value], i) => (
                    <div
                        key={label}
                        style={{
                            padding: "16px 24px",
                            borderTop: i === 0 ? "none" : `1px solid ${border}`,
                        }}
                    >
                        <p style={{ fontSize: "1rem", color: textSecondary }}>{label}</p>
                        <p style={{ fontSize: "1.125rem", fontWeight: 600, color: text }}>
                            {value || "—"}
                        </p>
                    </div>
                ))}
            </div>
        </div>
    );
}
