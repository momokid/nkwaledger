import AdminLayout from "@/Layouts/AdminLayout";
import { useTheme } from "@/Layouts/AuthenticatedLayout";
import { type } from "@/theme/typography";
import { shortDate } from "@/lib/format";
import { router } from "@inertiajs/react";
import { PageProps } from "@/types";
import { useState } from "react";

interface Row {
    uuid: string;
    farmer: string;
    submitted_by: string;
    record: string;
    amount: string | null;
    event_date: string;
    received_at: string;
    reason: string | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props extends PageProps {
    submissions: { data: Row[]; links: PaginationLink[] };
}

export default function Index(props: Props) {
    return (
        <AdminLayout title="Held records">
            <IndexContent {...props} />
        </AdminLayout>
    );
}

type ContentProps = Pick<Props, "submissions">;

function IndexContent({ submissions }: ContentProps) {
    const { dark } = useTheme();
    const [loading, setLoading] = useState(false);

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const headerBg = dark ? "rgba(29,158,117,0.15)" : "#EAF5F0";
    const headerText = "#1D9E75";
    const rowAlt = dark ? "#111827" : "#F9FAFB";
    const skeleton = dark ? "#374151" : "#E5E7EB";

    const cell = "px-4 py-3";
    const columns = [
        "Farmer",
        "Submitted by",
        "Record",
        "Amount",
        "Event date",
        "Received",
        "Reason",
    ];

    const visit = (url: string | null) => {
        if (!url) return;

        setLoading(true);
        router.visit(url, {
            preserveScroll: true,
            onFinish: () => setLoading(false),
        });
    };

    return (
        <>
            <div
                className="overflow-x-auto"
                style={{ background: surface, border: `1px solid ${border}` }}
            >
                <table
                    className="min-w-full"
                    style={{ fontSize: type.tableCell }}
                >
                    <thead>
                        <tr style={{ background: headerBg }}>
                            {columns.map((label) => (
                                <th
                                    key={label}
                                    className={`text-left ${cell}`}
                                    style={{
                                        color: headerText,
                                        fontWeight: 700,
                                        fontSize: type.tableHeader,
                                    }}
                                >
                                    {label}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {loading &&
                            Array.from({ length: 8 }).map((_, row) => (
                                <tr
                                    key={`placeholder-${row}`}
                                    style={{ borderTop: `1px solid ${border}` }}
                                >
                                    {columns.map((label, column) => (
                                        <td key={label} className={cell}>
                                            <div
                                                style={{
                                                    height: "16px",
                                                    width:
                                                        column === 0
                                                            ? "80%"
                                                            : "55%",
                                                    background: skeleton,
                                                }}
                                            />
                                        </td>
                                    ))}
                                </tr>
                            ))}

                        {!loading && submissions.data.length === 0 && (
                            <tr>
                                <td
                                    colSpan={columns.length}
                                    className="px-4 py-6 text-center"
                                    style={{
                                        color: textSecondary,
                                        fontSize: type.body,
                                    }}
                                >
                                    Nothing is waiting for review.
                                </td>
                            </tr>
                        )}

                        {!loading &&
                            submissions.data.map((row, index) => (
                                <tr
                                    key={row.uuid}
                                    style={{
                                        borderTop: `1px solid ${border}`,
                                        background:
                                            index % 2 === 1
                                                ? rowAlt
                                                : "transparent",
                                    }}
                                >
                                    <td className={cell} style={{ color: text }}>
                                        {row.farmer}
                                    </td>
                                    <td className={cell} style={{ color: text }}>
                                        {row.submitted_by}
                                    </td>
                                    <td className={cell} style={{ color: text }}>
                                        {row.record}
                                    </td>
                                    <td className={cell} style={{ color: text }}>
                                        {row.amount ?? "—"}
                                    </td>
                                    <td
                                        className={cell}
                                        style={{
                                            color: textSecondary,
                                            fontSize: type.secondary,
                                        }}
                                    >
                                        {shortDate(row.event_date)}
                                    </td>
                                    <td
                                        className={cell}
                                        style={{
                                            color: textSecondary,
                                            fontSize: type.secondary,
                                        }}
                                    >
                                        {shortDate(row.received_at)}
                                    </td>
                                    <td
                                        className={cell}
                                        style={{
                                            color: textSecondary,
                                            fontSize: type.secondary,
                                        }}
                                    >
                                        {row.reason ?? "—"}
                                    </td>
                                </tr>
                            ))}
                    </tbody>
                </table>
            </div>

            <div className="flex flex-wrap gap-2 mt-4">
                {submissions.links.map((link) => (
                    <button
                        key={link.label}
                        onClick={() => visit(link.url)}
                        disabled={!link.url || loading}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                        style={{
                            padding: "8px 14px",
                            fontSize: type.secondary,
                            border: `1px solid ${link.active ? "#1D9E75" : border}`,
                            background: link.active ? "#1D9E75" : surface,
                            color: link.active
                                ? "#FFFFFF"
                                : link.url
                                  ? text
                                  : textSecondary,
                            cursor:
                                link.url && !loading
                                    ? "pointer"
                                    : "not-allowed",
                            fontFamily: "inherit",
                        }}
                    />
                ))}
            </div>
        </>
    );
}
