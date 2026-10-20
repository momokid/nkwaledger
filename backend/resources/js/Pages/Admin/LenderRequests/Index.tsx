import AdminLayout from "@/Layouts/AdminLayout";
import { useTheme } from "@/Layouts/AuthenticatedLayout";
import Button from "@/Components/Button";
import { wording } from "@/lib/wording";
import { shortDate } from "@/lib/format";
import { csrfToken } from "@/lib/syncHttp";
import { PageProps } from "@/types";
import { ReactNode, useEffect, useState } from "react";

interface Row {
    uuid: string;
    requested_by: string;
    name: string;
    type: string;
    note: string | null;
    date: string;
}

interface Found {
    uuid: string;
    name: string;
    type: string;
}

interface Props extends PageProps {
    rows: Row[];
    types: string[];
}

type Panel = { uuid: string; kind: "existing" | "reject" } | null;

export default function Index(props: Props) {
    return (
        <AdminLayout title="Lender Requests">
            <IndexContent {...props} />
        </AdminLayout>
    );
}

type ContentProps = Pick<Props, "rows">;

function IndexContent({ rows: firstRows }: ContentProps) {
    const { dark } = useTheme();
    const [rows, setRows] = useState(firstRows);
    const [panel, setPanel] = useState<Panel>(null);
    const [busy, setBusy] = useState<string | null>(null);
    const [error, setError] = useState<{ uuid: string; message: string } | null>(null);
    const [reason, setReason] = useState("");
    const [search, setSearch] = useState("");
    const [found, setFound] = useState<Found[]>([]);

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const inputBorder = dark ? "#4B5563" : "#9CA3AF";
    const inputBg = dark ? "#111827" : "#FFFFFF";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";

    const field = { width: "100%", padding: "8px 10px", border: `1px solid ${inputBorder}`, background: inputBg, color: text, fontSize: "1.0625rem" } as const;
    const head = { textAlign: "left", padding: "10px 16px", fontSize: "0.9375rem", fontWeight: 600, color: textSecondary, borderBottom: `1px solid ${border}` } as const;
    const label = { display: "block", fontSize: "0.9375rem", fontWeight: 600, color: textSecondary, marginBottom: "4px" } as const;

    const open = (uuid: string, kind: "existing" | "reject") => {
        setPanel({ uuid, kind });
        setError(null);
        setReason("");
        setSearch("");
        setFound([]);
    };

    // what is typed only searches the list of lenders; only a picked one is sent
    useEffect(() => {
        if (panel?.kind !== "existing" || search.trim().length < 2) {
            setFound([]);

            return;
        }

        const controller = new AbortController();
        const timer = setTimeout(async () => {
            try {
                const response = await fetch("/lenders/search", {
                    method: "POST",
                    signal: controller.signal,
                    headers: { Accept: "application/json", "Content-Type": "application/json", "X-CSRF-TOKEN": csrfToken() },
                    body: JSON.stringify({ q: search }),
                });

                setFound(response.ok ? await response.json() : []);
            } catch {
                setFound([]);
            }
        }, 250);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [panel, search]);

    // every answer is sent in place: the row leaves the queue, or the reason it was refused is shown under it
    const answer = async (uuid: string, action: "add" | "use-existing" | "reject", body: Record<string, string> = {}) => {
        setBusy(uuid);
        setError(null);

        try {
            const response = await fetch(`/admin/lender-requests/${uuid}/${action}`, {
                method: "POST",
                headers: { Accept: "application/json", "Content-Type": "application/json", "X-CSRF-TOKEN": csrfToken() },
                body: JSON.stringify(body),
            });

            if (response.ok) {
                setRows((all) => all.filter((row) => row.uuid !== uuid));
                setPanel(null);
            } else {
                const json = await response.json().catch(() => ({}));
                const first = Object.values((json.errors ?? {}) as Record<string, string[]>)[0]?.[0];

                setError({ uuid, message: first ?? json.message ?? "" });
            }
        } catch {
            setError({ uuid, message: "" });
        } finally {
            setBusy(null);
        }
    };

    return (
        <div className="overflow-x-auto" style={{ background: surface, border: `1px solid ${border}` }}>
            <table className="w-full" style={{ fontSize: "1.0625rem", color: text }}>
                <thead>
                    <tr>
                        <th style={head}>Requested By</th>
                        <th style={head}>{wording("lender.name")}</th>
                        <th style={head}>{wording("lender.type")}</th>
                        <th style={head}>{wording("lender.request_note")}</th>
                        <th style={head}>Date</th>
                        <th style={head}></th>
                    </tr>
                </thead>
                {rows.length === 0 && (
                    <tbody>
                        <tr>
                            <td colSpan={6} className="px-4 py-6 text-center" style={{ color: textSecondary }}>
                                {wording("lender.request_empty")}
                            </td>
                        </tr>
                    </tbody>
                )}

                {rows.map((row) => (
                        <RowBlock key={row.uuid} border={border}>
                            <tr data-request={row.uuid}>
                                <td className="px-4 py-3">{row.requested_by}</td>
                                <td className="px-4 py-3">{row.name}</td>
                                <td className="px-4 py-3">{wording(`lender.type.${row.type}`)}</td>
                                <td className="px-4 py-3" style={{ whiteSpace: "pre-wrap" }}>
                                    {row.note}
                                </td>
                                <td className="px-4 py-3">{shortDate(row.date)}</td>
                                <td className="px-4 py-3">
                                    <div className="flex flex-wrap gap-2">
                                        <Button type="button" size="small" busy={busy === row.uuid} onClick={() => answer(row.uuid, "add")}>
                                            {wording("lender.add")}
                                        </Button>
                                        <Button type="button" size="small" look="secondary" disabled={busy === row.uuid} onClick={() => open(row.uuid, "existing")}>
                                            {wording("lender.request_use_existing")}
                                        </Button>
                                        <Button type="button" size="small" look="secondary" disabled={busy === row.uuid} onClick={() => open(row.uuid, "reject")}>
                                            {wording("lender.request_reject")}
                                        </Button>
                                    </div>
                                </td>
                            </tr>

                            {(panel?.uuid === row.uuid || error?.uuid === row.uuid) && (
                                <tr>
                                    <td colSpan={6} className="px-4 pb-4">
                                        {panel?.uuid === row.uuid && panel.kind === "existing" && (
                                            <div style={{ maxWidth: "420px" }}>
                                                <label htmlFor={`existing-${row.uuid}`} style={label}>
                                                    {wording("lender.name")}
                                                </label>
                                                <input id={`existing-${row.uuid}`} style={field} value={search} onChange={(event) => setSearch(event.target.value)} />
                                                {found.length > 0 && (
                                                    <ul role="listbox" style={{ border: `1px solid ${inputBorder}`, background: inputBg }}>
                                                        {found.map((item) => (
                                                            <li key={item.uuid}>
                                                                <button
                                                                    type="button"
                                                                    role="option"
                                                                    aria-selected={false}
                                                                    className="flex w-full justify-between gap-3 px-3 py-2 text-left"
                                                                    style={{ color: text }}
                                                                    onClick={() => answer(row.uuid, "use-existing", { lender: item.uuid })}
                                                                >
                                                                    <span>{item.name}</span>
                                                                    <span style={{ opacity: 0.75 }}>{wording(`lender.type.${item.type}`)}</span>
                                                                </button>
                                                            </li>
                                                        ))}
                                                    </ul>
                                                )}
                                            </div>
                                        )}

                                        {panel?.uuid === row.uuid && panel.kind === "reject" && (
                                            <div style={{ maxWidth: "420px" }}>
                                                <label htmlFor={`reason-${row.uuid}`} style={label}>
                                                    {wording("lender.request_reason")}
                                                </label>
                                                <textarea id={`reason-${row.uuid}`} rows={3} style={field} value={reason} onChange={(event) => setReason(event.target.value)} />
                                                <div className="mt-2 flex gap-2">
                                                    <Button type="button" size="small" look="danger" busy={busy === row.uuid} disabled={reason.trim() === ""} onClick={() => answer(row.uuid, "reject", { reason: reason.trim() })}>
                                                        {wording("lender.request_reject")}
                                                    </Button>
                                                </div>
                                            </div>
                                        )}

                                        {panel?.uuid === row.uuid && (
                                            <div className="mt-2">
                                                <Button type="button" size="small" look="secondary" onClick={() => setPanel(null)}>
                                                    {wording("lender.cancel")}
                                                </Button>
                                            </div>
                                        )}

                                        {error?.uuid === row.uuid && error.message !== "" && (
                                            <p role="alert" style={{ fontSize: "0.9375rem", color: "#B91C1C", marginTop: "4px" }}>
                                                {error.message}
                                            </p>
                                        )}
                                    </td>
                                </tr>
                            )}
                        </RowBlock>
                ))}
            </table>
        </div>
    );
}

// a request is a row, with its answer panel as a second row under it
function RowBlock({ children, border }: { children: ReactNode; border: string }) {
    return <tbody style={{ borderTop: `1px solid ${border}` }}>{children}</tbody>;
}
