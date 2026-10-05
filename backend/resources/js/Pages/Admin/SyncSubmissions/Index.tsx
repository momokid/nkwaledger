import AdminLayout from "@/Layouts/AdminLayout";
import Button from "@/Components/Button";
import { useTheme } from "@/Layouts/AuthenticatedLayout";
import { type } from "@/theme/typography";
import { shortDate } from "@/lib/format";
import { router } from "@inertiajs/react";
import { PageProps } from "@/types";
import axios from "axios";
import { useEffect, useState } from "react";

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
    permissions: { approve: boolean; reject: boolean };
}

// what one row is doing right now
interface RowState {
    mode: "approve" | "reject" | null;
    reason: string;
    busy: boolean;
    error: string | null;
}

const FALLBACK = "Something went wrong. Please try again.";

const IDLE: RowState = { mode: null, reason: "", busy: false, error: null };

export default function Index(props: Props) {
    return (
        <AdminLayout title="Held records">
            <IndexContent {...props} />
        </AdminLayout>
    );
}

type ContentProps = Pick<Props, "submissions" | "permissions">;

function IndexContent({ submissions, permissions }: ContentProps) {
    const { dark } = useTheme();
    const [loading, setLoading] = useState(false);
    const [rows, setRows] = useState<Row[]>(submissions.data);
    const [states, setStates] = useState<Record<string, RowState>>({});
    const [notice, setNotice] = useState<string | null>(null);

    // a new page of results replaces what the in-place changes left behind
    useEffect(() => setRows(submissions.data), [submissions.data]);

    useEffect(() => {
        if (notice === null) return;

        const timer = setTimeout(() => setNotice(null), 3000);

        return () => clearTimeout(timer);
    }, [notice]);

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const headerBg = dark ? "rgba(29,158,117,0.15)" : "#EAF5F0";
    const headerText = "#1D9E75";
    const rowAlt = dark ? "#111827" : "#F9FAFB";
    const skeleton = dark ? "#374151" : "#E5E7EB";

    const cell = "px-4 py-3";
    const showActions = permissions.approve || permissions.reject;
    const columns = [
        "Farmer",
        "Submitted by",
        "Record",
        "Amount",
        "Event date",
        "Received",
        "Reason",
        ...(showActions ? [""] : []),
    ];

    const visit = (url: string | null) => {
        if (!url) return;

        setLoading(true);
        router.visit(url, {
            preserveScroll: true,
            onFinish: () => setLoading(false),
        });
    };

    const stateOf = (uuid: string): RowState => states[uuid] ?? IDLE;

    const change = (uuid: string, patch: Partial<RowState>) =>
        setStates((all) => ({
            ...all,
            [uuid]: { ...(all[uuid] ?? IDLE), ...patch },
        }));

    // the existing routes and their JSON answers; a row leaves the table only when the server says it is done
    const send = async (row: Row, action: "approve" | "reject") => {
        const { reason } = stateOf(row.uuid);

        change(row.uuid, { busy: true, error: null });

        try {
            const { data } = await axios.post(
                route(`admin.sync-submissions.${action}`, row.uuid),
                action === "reject" ? { reason } : {},
                { headers: { Accept: "application/json" } },
            );

            // approving can come back sent back for fixing, which is not "Approved."
            if (action === "approve" && data.status !== "accepted") {
                change(row.uuid, {
                    busy: false,
                    mode: null,
                    error: data.reason ?? FALLBACK,
                });

                return;
            }

            setRows((all) => all.filter((item) => item.uuid !== row.uuid));
            setNotice(action === "approve" ? "Approved." : "Rejected.");
        } catch (failure) {
            const message = axios.isAxiosError(failure)
                ? failure.response?.data?.message
                : null;

            change(row.uuid, {
                busy: false,
                error:
                    typeof message === "string" && message !== ""
                        ? message
                        : FALLBACK,
            });
        }
    };

    const actions = (row: Row) => {
        const state = stateOf(row.uuid);

        return (
            <div style={{ minWidth: "240px" }}>
                {state.mode === null && (
                    <div className="flex gap-2">
                        {permissions.approve && (
                            <Button
                                size="small"
                                onClick={() =>
                                    change(row.uuid, {
                                        mode: "approve",
                                        error: null,
                                    })
                                }
                            >
                                Approve
                            </Button>
                        )}
                        {permissions.reject && (
                            <Button
                                look="danger"
                                size="small"
                                onClick={() =>
                                    change(row.uuid, {
                                        mode: "reject",
                                        error: null,
                                    })
                                }
                            >
                                Reject
                            </Button>
                        )}
                    </div>
                )}

                {state.mode === "approve" && (
                    <>
                        <p style={{ color: text, marginBottom: "8px" }}>
                            Approve and post this record?
                        </p>
                        <div className="flex gap-2">
                            <Button
                                size="small"
                                disabled={state.busy}
                                onClick={() => send(row, "approve")}
                            >
                                Confirm
                            </Button>
                            <Button
                                look="secondary"
                                size="small"
                                disabled={state.busy}
                                onClick={() => change(row.uuid, IDLE)}
                            >
                                Cancel
                            </Button>
                        </div>
                    </>
                )}

                {state.mode === "reject" && (
                    <>
                        <label
                            style={{
                                display: "block",
                                color: text,
                                fontWeight: 600,
                                marginBottom: "6px",
                            }}
                        >
                            Reason for rejecting
                        </label>
                        <textarea
                            rows={2}
                            disabled={state.busy}
                            value={state.reason}
                            onChange={(event) =>
                                change(row.uuid, { reason: event.target.value })
                            }
                            style={{
                                width: "100%",
                                border: `1px solid ${border}`,
                                background: surface,
                                color: text,
                                padding: "8px 10px",
                                fontFamily: "inherit",
                            }}
                        />
                        <div className="flex gap-2" style={{ marginTop: "8px" }}>
                            <Button
                                size="small"
                                disabled={state.busy || state.reason.trim() === ""}
                                onClick={() => send(row, "reject")}
                            >
                                Confirm
                            </Button>
                            <Button
                                look="secondary"
                                size="small"
                                disabled={state.busy}
                                onClick={() => change(row.uuid, IDLE)}
                            >
                                Cancel
                            </Button>
                        </div>
                    </>
                )}

                {state.error !== null && (
                    <p
                        style={{
                            color: "#B91C1C",
                            fontSize: type.secondary,
                            marginTop: "6px",
                        }}
                    >
                        {state.error}
                    </p>
                )}
            </div>
        );
    };

    return (
        <>
            {notice !== null && (
                <p
                    role="status"
                    className="mb-4 px-4 py-3"
                    style={{
                        background: "rgba(29,158,117,0.12)",
                        color: "#1D9E75",
                        fontWeight: 600,
                    }}
                >
                    {notice}
                </p>
            )}

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
                            {columns.map((label, column) => (
                                <th
                                    key={`${label}-${column}`}
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
                                        <td
                                            key={`${label}-${column}`}
                                            className={cell}
                                        >
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

                        {!loading && rows.length === 0 && (
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
                            rows.map((row, index) => (
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
                                    {showActions && (
                                        <td className={cell}>{actions(row)}</td>
                                    )}
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
