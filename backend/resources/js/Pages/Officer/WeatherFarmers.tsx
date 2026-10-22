import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import useAuthGuard from "@/hooks/useAuthGuard";
import { Head, Link } from "@inertiajs/react";

interface FarmerRow {
    uuid: string;
    name: string;
    community: string;
}

interface Props {
    farmers: FarmerRow[];
    basePath: string;
}

export default function WeatherFarmers(props: Props) {
    useAuthGuard();

    return (
        <AuthenticatedLayout title="Weather">
            <Head title="Weather" />
            <Content {...props} />
        </AuthenticatedLayout>
    );
}

function Content({ farmers, basePath }: Props) {
    const { dark } = useTheme();
    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";

    return (
        <div className="p-6">
            <p style={{ fontSize: "1.625rem", fontWeight: 700, color: text, marginBottom: "16px" }}>
                Farmers who sent you reports
            </p>

            {farmers.length === 0 && (
                <div style={{ color: textSecondary, fontSize: "1.0625rem" }}>
                    No farmers have sent you a report yet.
                </div>
            )}

            {farmers.map((farmer) => (
                <Link
                    key={farmer.uuid}
                    href={`${basePath}/weather/${farmer.uuid}`}
                    className="block mb-3 p-4"
                    style={{ background: surface, border: `1px solid ${border}` }}
                >
                    <span style={{ fontWeight: 700, color: text, fontSize: "1.125rem" }}>
                        {farmer.name} · {farmer.community}
                    </span>
                </Link>
            ))}
        </div>
    );
}
