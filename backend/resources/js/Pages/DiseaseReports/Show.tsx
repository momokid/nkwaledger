import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import { Head } from "@inertiajs/react";
import { statusColor, statusLabel } from "@/Pages/DiseaseReports/Index";

interface ReportDetail {
    uuid: string;
    farm_unit_name: string | null;
    category: string;
    status: string;
    description: string;
    photo_url: string;
    audio_url: string | null;
    contact_method: string | null;
    response_note: string | null;
    created_at: string;
}

interface Props {
    report: ReportDetail;
}

function contactMethodLabel(method: string | null): string {
    switch (method) {
        case "call":
            return "They called you";
        case "farm_visit":
            return "They visited your farm";
        case "office_visit":
            return "You visited their office";
        default:
            return "";
    }
}

export default function Show({ report }: Props) {
    return (
        <AuthenticatedLayout title="Report">
            <Head title="Report" />
            <ShowContent report={report} />
        </AuthenticatedLayout>
    );
}

function ShowContent({ report }: Props) {
    const { dark } = useTheme();

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const noticeBg = dark ? "rgba(29,158,117,0.15)" : "#EAF5F0";
    const brand = "#1D9E75";

    const hasResponse = report.response_note !== null;

    return (
        <div className="p-6" style={{ maxWidth: "640px" }}>
            <div
                className="p-6 mb-5"
                style={{ background: surface, border: `1px solid ${border}` }}
            >
                <div className="flex justify-between items-baseline">
                    <h2
                        style={{ fontSize: "1.375rem", fontWeight: 700, color: text }}
                    >
                        {report.farm_unit_name}
                    </h2>
                    <span
                        style={{
                            color: statusColor(report.status),
                            fontWeight: 600,
                            fontSize: "1.0625rem",
                        }}
                    >
                        {statusLabel(report.status)}
                    </span>
                </div>
                <p
                    style={{
                        fontSize: "1.0625rem",
                        color: textSecondary,
                        marginTop: "4px",
                    }}
                >
                    {report.category} · Reported {report.created_at}
                </p>

                <p
                    style={{
                        fontSize: "0.9375rem",
                        fontWeight: 600,
                        color: textSecondary,
                        marginTop: "16px",
                        textTransform: "uppercase",
                        letterSpacing: "0.03em",
                    }}
                >
                    What you reported
                </p>

                <img
                    src={report.photo_url}
                    alt="Photo of the problem"
                    className="mt-2"
                    style={{
                        maxWidth: "100%",
                        maxHeight: "320px",
                        border: `1px solid ${border}`,
                    }}
                />

                {report.audio_url && (
                    <audio
                        controls
                        src={report.audio_url}
                        className="mt-3"
                        style={{ width: "100%" }}
                    />
                )}

                <p style={{ marginTop: "12px", color: text, fontSize: "1.0625rem" }}>
                    {report.description}
                </p>
            </div>

            <div
                className="p-6"
                style={{ background: surface, border: `1px solid ${border}` }}
            >
                <p
                    style={{
                        fontSize: "0.9375rem",
                        fontWeight: 600,
                        color: textSecondary,
                        textTransform: "uppercase",
                        letterSpacing: "0.03em",
                    }}
                >
                    The officer's response
                </p>

                {hasResponse ? (
                    <div
                        className="mt-2 p-3"
                        style={{ background: noticeBg }}
                    >
                        <p style={{ color: brand, fontWeight: 600, fontSize: "1.0625rem" }}>
                            {contactMethodLabel(report.contact_method)}
                        </p>
                        <p style={{ color: text, marginTop: "6px", fontSize: "1.0625rem" }}>
                            {report.response_note}
                        </p>
                    </div>
                ) : (
                    <p
                        className="mt-2"
                        style={{ color: textSecondary, fontSize: "1.0625rem" }}
                    >
                        Waiting for review. We will let you know as soon as
                        someone responds.
                    </p>
                )}
            </div>
        </div>
    );
}
