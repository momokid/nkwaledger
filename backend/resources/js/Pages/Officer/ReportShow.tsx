import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import { Head, useForm, usePage } from "@inertiajs/react";
import { FormEvent } from "react";
import Button from "@/Components/Button";

interface ReportDetail {
    uuid: string;
    farm_unit_name: string | null;
    farmer_name: string;
    category: string;
    status: string;
    description: string;
    photo_url: string;
    contact_method: string | null;
    response_note: string | null;
    created_at: string;
}

interface HistoryRow {
    category: string;
    status: string;
    description: string;
    created_at: string;
}

interface Props {
    report: ReportDetail;
    history: HistoryRow[];
    basePath: string;
}

export default function ReportShow(props: Props) {
    return (
        <AuthenticatedLayout title="Report">
            <Head title="Report" />
            <ReportShowContent {...props} />
        </AuthenticatedLayout>
    );
}

function ReportShowContent({ report, history, basePath }: Props) {
    const { errors, flash } = usePage().props as unknown as {
        errors: Record<string, string>;
        flash: { success?: string };
    };
    const { dark } = useTheme();

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const inputBorder = dark ? "#4B5563" : "#9CA3AF";
    const inputBg = dark ? "#111827" : "#FFFFFF";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const noticeBg = dark ? "rgba(29,158,117,0.15)" : "#EAF5F0";
    const brand = "#1D9E75";

    const alreadyResolved = report.status === "resolved";

    const form = useForm({
        status: report.status === "new" ? "reviewed" : report.status,
        contact_method: report.contact_method ?? "",
        note: report.response_note ?? "",
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.post(`${basePath}/reports/${report.uuid}/respond`, {
            preserveScroll: true,
        });
    };

    const field = {
        width: "100%",
        padding: "10px 12px",
        border: `1px solid ${inputBorder}`,
        background: inputBg,
        color: text,
        fontSize: "1.125rem",
    } as const;

    const label = {
        display: "block",
        fontSize: "1.0625rem",
        fontWeight: 600,
        color: text,
        marginBottom: "6px",
    } as const;

    const errorText = {
        fontSize: "0.9375rem",
        color: "#B91C1C",
        marginTop: "4px",
    } as const;

    return (
        <div className="p-6" style={{ maxWidth: "640px" }}>
            <div
                className="p-6 mb-5"
                style={{ background: surface, border: `1px solid ${border}` }}
            >
                <h2
                    style={{ fontSize: "1.375rem", fontWeight: 700, color: text }}
                >
                    {report.farm_unit_name} — {report.farmer_name}
                </h2>
                <p
                    style={{
                        fontSize: "1.0625rem",
                        color: textSecondary,
                        marginTop: "4px",
                    }}
                >
                    {report.category} · Reported {report.created_at}
                </p>

                <img
                    src={report.photo_url}
                    alt="Photo of the problem"
                    className="mt-4"
                    style={{
                        maxWidth: "100%",
                        maxHeight: "360px",
                        border: `1px solid ${border}`,
                    }}
                />

                <p style={{ marginTop: "12px", color: text, fontSize: "1.0625rem" }}>
                    {report.description}
                </p>
            </div>

            {flash?.success && (
                <div
                    className="p-3 mb-5"
                    style={{ background: noticeBg, color: brand, fontSize: "1.0625rem" }}
                >
                    {flash.success}
                </div>
            )}

            <div
                className="p-6 mb-5"
                style={{ background: surface, border: `1px solid ${border}` }}
            >
                <h3
                    style={{ fontSize: "1.1875rem", fontWeight: 700, color: text }}
                >
                    {alreadyResolved ? "Your response" : "Respond to this report"}
                </h3>

                <form onSubmit={submit} className="mt-4">
                    <div className="mb-4">
                        <label style={label}>Status</label>
                        <select
                            style={field}
                            value={form.data.status}
                            onChange={(event) =>
                                form.setData("status", event.target.value)
                            }
                        >
                            <option value="reviewed">Reviewed</option>
                            <option value="resolved">Resolved</option>
                        </select>
                        {errors.status && <p style={errorText}>{errors.status}</p>}
                    </div>

                    <div className="mb-4">
                        <label style={label}>How did you reach the farmer?</label>
                        <select
                            style={field}
                            value={form.data.contact_method}
                            onChange={(event) =>
                                form.setData("contact_method", event.target.value)
                            }
                        >
                            <option value="">Choose one</option>
                            <option value="call">Called them</option>
                            <option value="farm_visit">Visited the farm</option>
                            <option value="office_visit">They visited the office</option>
                        </select>
                        {errors.contact_method && (
                            <p style={errorText}>{errors.contact_method}</p>
                        )}
                    </div>

                    <div className="mb-5">
                        <label style={label}>Note</label>
                        <textarea
                            style={{ ...field, minHeight: "100px" }}
                            value={form.data.note}
                            onChange={(event) =>
                                form.setData("note", event.target.value)
                            }
                        />
                        {errors.note && <p style={errorText}>{errors.note}</p>}
                    </div>

                    <Button type="submit" busy={form.processing} busyLabel="Saving...">
                        Save response
                    </Button>
                </form>
            </div>

            {history.length > 0 && (
                <div
                    className="p-6"
                    style={{ background: surface, border: `1px solid ${border}` }}
                >
                    <h3
                        style={{ fontSize: "1.1875rem", fontWeight: 700, color: text }}
                    >
                        Past reports on this farm unit
                    </h3>

                    {history.map((past, index) => (
                        <div
                            key={index}
                            className="mt-3 pt-3"
                            style={{ borderTop: `1px solid ${border}` }}
                        >
                            <div
                                className="flex justify-between"
                                style={{ fontSize: "1rem", color: textSecondary }}
                            >
                                <span>{past.category}</span>
                                <span>{past.created_at}</span>
                            </div>
                            <p style={{ color: text, marginTop: "2px" }}>
                                {past.description}
                            </p>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
