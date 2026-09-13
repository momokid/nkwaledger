import AdminLayout from "@/Layouts/AdminLayout";
import { useTheme } from "@/Layouts/AuthenticatedLayout";
import { PageProps } from "@/types";

interface ReportRow {
    uuid: string;
    farm_unit_name: string | null;
    farmer_name: string;
    category: string;
    routed_role: string;
    created_at: string;
}

interface Props extends PageProps {
    reports: ReportRow[];
}

export default function Index({ reports }: Props) {
    return (
        <AdminLayout title="Disease & Health Reports">
            <IndexContent reports={reports} />
        </AdminLayout>
    );
}

function IndexContent({ reports }: Pick<Props, "reports">) {
    const { dark } = useTheme();

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const headerBg = dark ? "rgba(29,158,117,0.15)" : "#EAF5F0";
    const headerText = "#1D9E75";

    return (
        <div className="p-6">
            <h1
                style={{ fontSize: "1.375rem", fontWeight: 700, color: text }}
            >
                Waiting for an officer
            </h1>
            <p
                style={{
                    fontSize: "1.0625rem",
                    color: textSecondary,
                    marginTop: "4px",
                }}
            >
                These reports are waiting because the farmer's agent has no
                vet or adviser linked yet. Add the link on the Officer
                Assignments page and these will move over automatically.
            </p>

            {reports.length === 0 ? (
                <div
                    className="mt-4 p-4"
                    style={{
                        background: surface,
                        border: `1px solid ${border}`,
                        color: textSecondary,
                        fontSize: "1.0625rem",
                    }}
                >
                    Nothing is waiting right now.
                </div>
            ) : (
                <div
                    className="mt-4"
                    style={{
                        background: surface,
                        border: `1px solid ${border}`,
                        overflowX: "auto",
                    }}
                >
                    <table
                        className="w-full"
                        style={{ borderCollapse: "collapse", fontSize: "1rem" }}
                    >
                        <thead>
                            <tr style={{ background: headerBg }}>
                                {[
                                    "Farm unit",
                                    "Farmer",
                                    "Category",
                                    "Needs a",
                                    "Reported",
                                ].map((label) => (
                                    <th
                                        key={label}
                                        className="text-left px-4 py-2"
                                        style={{ color: headerText }}
                                    >
                                        {label}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {reports.map((report) => (
                                <tr
                                    key={report.uuid}
                                    style={{ borderTop: `1px solid ${border}` }}
                                >
                                    <td className="px-4 py-2" style={{ color: text }}>
                                        {report.farm_unit_name}
                                    </td>
                                    <td className="px-4 py-2" style={{ color: text }}>
                                        {report.farmer_name}
                                    </td>
                                    <td className="px-4 py-2" style={{ color: text }}>
                                        {report.category}
                                    </td>
                                    <td className="px-4 py-2" style={{ color: text }}>
                                        {report.routed_role === "vet"
                                            ? "Vet"
                                            : "Adviser"}
                                    </td>
                                    <td
                                        className="px-4 py-2"
                                        style={{ color: textSecondary }}
                                    >
                                        {report.created_at}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}
