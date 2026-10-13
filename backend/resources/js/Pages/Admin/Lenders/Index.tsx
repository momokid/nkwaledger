import AdminLayout from "@/Layouts/AdminLayout";
import { useTheme } from "@/Layouts/AuthenticatedLayout";
import Button from "@/Components/Button";
import { csrfToken } from "@/lib/syncHttp";
import { PageProps } from "@/types";
import { router } from "@inertiajs/react";
import { FormEvent, useState } from "react";

interface LenderRow {
    uuid: string;
    type: string;
    name: string;
}

interface Props extends PageProps {
    lenders: LenderRow[];
    pagination: { page: number; last: number };
    types: string[];
}

// placeholder keys: the wording for these is not approved yet
const TEXT = {
    name: "lender.name",
    type: "lender.type",
    add: "lender.add",
    edit: "lender.edit",
    save: "lender.save",
    cancel: "lender.cancel",
    saved: "lender.saved",
    failed: "lender.save_failed",
};

export default function Index(props: Props) {
    return (
        <AdminLayout title="Lenders">
            <IndexContent {...props} />
        </AdminLayout>
    );
}

type ContentProps = Pick<Props, "lenders" | "pagination" | "types">;

// every save goes to the server and the row changes in place, with no page reload
async function send(url: string, method: "POST" | "PATCH", body: Record<string, string>) {
    const response = await fetch(url, {
        method,
        headers: { Accept: "application/json", "Content-Type": "application/json", "X-CSRF-TOKEN": csrfToken() },
        body: JSON.stringify(body),
    });
    const json = await response.json().catch(() => ({}));

    return { ok: response.ok, json };
}

function IndexContent({ lenders, pagination, types }: ContentProps) {
    const { dark } = useTheme();
    const [rows, setRows] = useState(lenders);
    const [editing, setEditing] = useState<string | null>(null);
    const [draft, setDraft] = useState("");
    const [adding, setAdding] = useState({ type: types[0] ?? "bank", name: "" });
    const [error, setError] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);
    const [typeState, setTypeState] = useState<Record<string, "saving" | "saved" | "error">>({});
    const [typeError, setTypeError] = useState<Record<string, string>>({});

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const inputBorder = dark ? "#4B5563" : "#9CA3AF";
    const inputBg = dark ? "#111827" : "#FFFFFF";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";

    const field = { padding: "8px 10px", border: `1px solid ${inputBorder}`, background: inputBg, color: text, fontSize: "1.0625rem" } as const;
    const errorText = { fontSize: "0.9375rem", color: "#B91C1C", marginTop: "4px" } as const;
    const head = { textAlign: "left", padding: "10px 16px", fontSize: "0.9375rem", fontWeight: 600, color: textSecondary, borderBottom: `1px solid ${border}` } as const;

    const firstError = (json: { errors?: Record<string, string[]> }, key: string) => json.errors?.[key]?.[0] ?? "";

    const add = async (event: FormEvent) => {
        event.preventDefault();
        setBusy(true);
        const { ok, json } = await send("/admin/lenders", "POST", adding);
        setBusy(false);

        if (!ok) {
            setError({ add: firstError(json, "name") || firstError(json, "type") });

            return;
        }

        setError({});
        setRows((all) => [json, ...all]);
        setAdding({ ...adding, name: "" });
    };

    const save = async (uuid: string) => {
        setBusy(true);
        const { ok, json } = await send(`/admin/lenders/${uuid}`, "PATCH", { name: draft });
        setBusy(false);

        if (!ok) {
            setError({ [uuid]: firstError(json, "name") });

            return;
        }

        setError({});
        setRows((all) => all.map((row) => (row.uuid === uuid ? json : row)));
        setEditing(null);
    };

    // picking a type saves at once; if the save fails the old type goes back
    const changeType = async (uuid: string, type: string) => {
        const old = rows.find((row) => row.uuid === uuid)?.type ?? "";

        setRows((all) => all.map((row) => (row.uuid === uuid ? { ...row, type } : row)));
        setTypeState((all) => ({ ...all, [uuid]: "saving" }));
        setTypeError((all) => ({ ...all, [uuid]: "" }));

        const { ok, json } = await send(`/admin/lenders/${uuid}`, "PATCH", { type }).catch(() => ({ ok: false, json: {} as { errors?: Record<string, string[]> } }));

        if (!ok) {
            setRows((all) => all.map((row) => (row.uuid === uuid ? { ...row, type: old } : row)));
            setTypeState((all) => ({ ...all, [uuid]: "error" }));
            setTypeError((all) => ({ ...all, [uuid]: firstError(json, "type") || TEXT.failed }));

            return;
        }

        setRows((all) => all.map((row) => (row.uuid === uuid ? { ...row, type: json.type } : row)));
        setTypeState((all) => ({ ...all, [uuid]: "saved" }));
    };

    return (
        <div className="p-6" style={{ background: surface, border: `1px solid ${border}` }}>
            <h2 style={{ fontSize: "1.375rem", fontWeight: 700, color: text }}>Lenders</h2>

            <form onSubmit={add} className="mt-4 flex flex-wrap items-end gap-3">
                <select aria-label={TEXT.type} style={field} value={adding.type} onChange={(event) => setAdding({ ...adding, type: event.target.value })}>
                    {types.map((type) => (
                        <option key={type} value={type}>
                            {`lender.type.${type}`}
                        </option>
                    ))}
                </select>
                <input aria-label={TEXT.name} style={field} value={adding.name} onChange={(event) => setAdding({ ...adding, name: event.target.value })} />
                <Button type="submit" busy={busy}>
                    {TEXT.add}
                </Button>
            </form>
            {error.add && <p style={errorText}>{error.add}</p>}

            <div className="mt-5" style={{ overflowX: "auto" }}>
                <table style={{ width: "100%", borderCollapse: "collapse" }}>
                    <thead>
                        <tr>
                            <th style={head}>{TEXT.name}</th>
                            <th style={head}>{TEXT.type}</th>
                            <th style={head}></th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr key={row.uuid} style={{ borderBottom: `1px solid ${border}`, color: text }}>
                                <td className="px-4 py-3">
                                    {editing === row.uuid ? (
                                        <>
                                            <input aria-label={TEXT.name} style={field} value={draft} onChange={(event) => setDraft(event.target.value)} />
                                            {error[row.uuid] && <p style={errorText}>{error[row.uuid]}</p>}
                                        </>
                                    ) : (
                                        row.name
                                    )}
                                </td>
                                <td className="px-4 py-3">
                                    <select
                                        aria-label={TEXT.type}
                                        style={field}
                                        value={row.type}
                                        disabled={typeState[row.uuid] === "saving"}
                                        onChange={(event) => changeType(row.uuid, event.target.value)}
                                    >
                                        {types.map((type) => (
                                            <option key={type} value={type}>
                                                {`lender.type.${type}`}
                                            </option>
                                        ))}
                                    </select>
                                    {typeState[row.uuid] === "saved" && <p style={{ fontSize: "0.9375rem", color: "#0F6E56", marginTop: "4px" }}>{TEXT.saved}</p>}
                                    {typeState[row.uuid] === "error" && <p style={errorText}>{typeError[row.uuid]}</p>}
                                </td>
                                <td className="px-4 py-3">
                                    {editing === row.uuid ? (
                                        <div className="flex gap-2">
                                            <Button type="button" size="small" busy={busy} onClick={() => save(row.uuid)}>
                                                {TEXT.save}
                                            </Button>
                                            <Button type="button" size="small" look="secondary" onClick={() => setEditing(null)}>
                                                {TEXT.cancel}
                                            </Button>
                                        </div>
                                    ) : (
                                        <Button
                                            type="button"
                                            size="small"
                                            look="secondary"
                                            onClick={() => {
                                                setEditing(row.uuid);
                                                setDraft(row.name);
                                                setError({});
                                            }}
                                        >
                                            {TEXT.edit}
                                        </Button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {pagination.last > 1 && (
                <div className="mt-4 flex gap-3">
                    {pagination.page > 1 && (
                        <Button type="button" look="secondary" size="small" onClick={() => router.get("/admin/lenders", { page: pagination.page - 1 })}>
                            &larr;
                        </Button>
                    )}
                    {pagination.page < pagination.last && (
                        <Button type="button" look="secondary" size="small" onClick={() => router.get("/admin/lenders", { page: pagination.page + 1 })}>
                            &rarr;
                        </Button>
                    )}
                </div>
            )}
        </div>
    );
}
