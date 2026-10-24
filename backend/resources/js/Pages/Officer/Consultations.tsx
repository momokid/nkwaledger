import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import useAuthGuard from "@/hooks/useAuthGuard";
import { slashDate } from "@/lib/format";
import { Head, Link } from "@inertiajs/react";

interface Row {
    uuid: string;
    farm_unit_name: string | null;
    farmer_name: string;
    category: string;
    status: string;
    contact_method: string | null;
    note: string;
    responded_at: string;
}

interface Props {
    consultations: Row[];
    basePath: string;
}

// the same words as the answer form on the report page
const CONTACT: Record<string, string> = {
    call: "Called them",
    farm_visit: "Visited the farm",
    office_visit: "They visited the office",
};

export default function Consultations(props: Props) {
    useAuthGuard();

    return (
        <AuthenticatedLayout title="Consultations">
            <Head title="Consultations" />
            <Content {...props} />
        </AuthenticatedLayout>
    );
}

function Content({ consultations, basePath }: Props) {
    const { dark } = useTheme();
    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";

    return (
        <div className="p-6">
            <p style={{ fontSize: "1.625rem", fontWeight: 700, color: text, marginBottom: "16px" }}>Consultations</p>

            {consultations.length === 0 && (
                <div style={{ color: textSecondary, fontSize: "1.0625rem" }}>
                    You have not answered any reports yet.
                </div>
            )}

            {consultations.map((row) => (
                <Link
                    key={row.uuid}
                    href={`${basePath}/reports/${row.uuid}`}
                    className="block mb-3 p-4"
                    style={{ background: surface, border: `1px solid ${border}` }}
                >
                    <div className="flex justify-between items-baseline">
                        <span style={{ fontWeight: 700, color: text, fontSize: "1.125rem" }}>
                            {row.farm_unit_name} — {row.farmer_name}
                        </span>
                        <span style={{ color: row.status === "resolved" ? "#1D9E75" : "#BA7517", fontWeight: 600 }}>
                            {row.status === "resolved" ? "Resolved" : "Reviewed"}
                        </span>
                    </div>
                    <div style={{ color: textSecondary, marginTop: "4px", fontSize: "1rem" }}>
                        {row.category} · {row.contact_method ? CONTACT[row.contact_method] : ""} · Answered {slashDate(row.responded_at)}
                    </div>
                    {row.note && (
                        <div style={{ color: text, marginTop: "4px", fontSize: "1rem" }}>{row.note}</div>
                    )}
                </Link>
            ))}
        </div>
    );
}
