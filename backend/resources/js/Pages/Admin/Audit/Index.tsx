import AdminLayout from "@/Layouts/AdminLayout";
import { useTheme } from "@/Layouts/AuthenticatedLayout";
import { type } from "@/theme/typography";
import { router } from "@inertiajs/react";
import { PageProps } from "@/types";
import { Fragment, useState } from "react";

interface Entry {
    id: number;
    action: string;
    user_name: string | null;
    record: string | null;
    record_id: number | null;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    ip_address: string | null;
    created_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Person {
    id: number;
    name: string;
}

interface Filters {
    action?: string;
    user_id?: string;
    from?: string;
    to?: string;
}

interface Props extends PageProps {
    entries: { data: Entry[]; links: PaginationLink[] };
    filters: Filters;
    actions: string[];
    people: Person[];
}

export default function Index(props: Props) {
    return (
        <AdminLayout title="Audit Log">
            <IndexContent {...props} />
        </AdminLayout>
    );
}

type ContentProps = Pick<Props, "entries" | "filters" | "actions" | "people">;

function IndexContent({ entries, filters, actions, people }: ContentProps) {
    const { dark } = useTheme();
    const [loading, setLoading] = useState(false);
    const [openId, setOpenId] = useState<number | null>(null);
    const [draft, setDraft] = useState<Filters>(filters);

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const inputBorder = dark ? "#4B5563" : "#9CA3AF";
    const inputBg = dark ? "#111827" : "#FFFFFF";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const headerBg = dark ? "rgba(29,158,117,0.15)" : "#EAF5F0";
    const headerText = "#1D9E75";
    const rowAlt = dark ? "#111827" : "#F9FAFB";
    const skeleton = dark ? "#374151" : "#E5E7EB";

    const cell = "px-4 py-3";

    const inputStyle = {
        border: `1px solid ${inputBorder}`,
        background: inputBg,
        color: text,
        padding: "10px 12px",
        fontSize: type.secondary,
        outline: "none",
        fontFamily: "inherit",
    };

    const go = (params: Filters) => {
        setLoading(true);
        router.get(
            route("admin.audit.index"),
            params as Record<string, string>,
            {
                preserveState: true,
                preserveScroll: true,
                onFinish: () => setLoading(false),
            },
        );
    };

    const visit = (url: string | null) => {
        if (!url) return;

        setLoading(true);
        router.visit(url, {
            preserveScroll: true,
            onFinish: () => setLoading(false),
        });
    };

    const clear = () => {
        setDraft({});
        go({});
    };

    // an action name is stored for machines; this is the version a person reads
    const readable = (action: string) =>
        action
            .replace(/[._]/g, " ")
            .replace(/^./, (letter) => letter.toUpperCase());

    const when = (value: string) =>
        new Date(value).toLocaleString("en-GB", {
            day: "numeric",
            month: "short",
            year: "numeric",
            hour: "2-digit",
            minute: "2-digit",
        });

    const changedKeys = (entry: Entry) =>
        Object.keys(entry.new_values ?? entry.old_values ?? {});

    const summary = (entry: Entry) => {
        const keys = changedKeys(entry);

        if (keys.length === 0) return "—";
        if (keys.length <= 3) return keys.join(", ");

        return `${keys.slice(0, 3).join(", ")} +${keys.length - 3} more`;
    };

    return (
        <>
            <div
                className="flex flex-wrap gap-3 mb-4 items-end"
                style={{
                    background: surface,
                    border: `1px solid ${border}`,
                    padding: "16px",
                }}
            >
                <div>
                    <label
                        style={{
                            display: "block",
                            fontSize: type.secondary,
                            color: textSecondary,
                            marginBottom: "4px",
                        }}
                    >
                        Action
                    </label>
                    <select
                        value={draft.action ?? ""}
                        onChange={(event) => {
                            const next = {
                                ...draft,
                                action: event.target.value || undefined,
                            };
                            setDraft(next);
                            go(next);
                        }}
                        style={inputStyle}
                    >
                        <option value="">All actions</option>
                        {actions.map((action) => (
                            <option key={action} value={action}>
                                {readable(action)}
                            </option>
                        ))}
                    </select>
                </div>

                <div>
                    <label
                        style={{
                            display: "block",
                            fontSize: type.secondary,
                            color: textSecondary,
                            marginBottom: "4px",
                        }}
                    >
                        Person
                    </label>
                    <select
                        value={draft.user_id ?? ""}
                        onChange={(event) => {
                            const next = {
                                ...draft,
                                user_id: event.target.value || undefined,
                            };
                            setDraft(next);
                            go(next);
                        }}
                        style={inputStyle}
                    >
                        <option value="">Anyone</option>
                        {people.map((person) => (
                            <option key={person.id} value={person.id}>
                                {person.name}
                            </option>
                        ))}
                    </select>
                </div>

                <div>
                    <label
                        style={{
                            display: "block",
                            fontSize: type.secondary,
                            color: textSecondary,
                            marginBottom: "4px",
                        }}
                    >
                        From
                    </label>
                    <input
                        type="date"
                        value={draft.from ?? ""}
                        onChange={(event) => {
                            const next = {
                                ...draft,
                                from: event.target.value || undefined,
                            };
                            setDraft(next);
                            go(next);
                        }}
                        style={inputStyle}
                    />
                </div>

                <div>
                    <label
                        style={{
                            display: "block",
                            fontSize: type.secondary,
                            color: textSecondary,
                            marginBottom: "4px",
                        }}
                    >
                        To
                    </label>
                    <input
                        type="date"
                        value={draft.to ?? ""}
                        onChange={(event) => {
                            const next = {
                                ...draft,
                                to: event.target.value || undefined,
                            };
                            setDraft(next);
                            go(next);
                        }}
                        style={inputStyle}
                    />
                </div>

                <button
                    onClick={clear}
                    style={{
                        background: "transparent",
                        color: text,
                        border: `1px solid ${inputBorder}`,
                        padding: "10px 16px",
                        fontSize: type.secondary,
                        cursor: "pointer",
                        fontFamily: "inherit",
                    }}
                >
                    Clear
                </button>
            </div>

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
                            {[
                                "When",
                                "Who",
                                "Action",
                                "Record",
                                "Changed",
                                "",
                            ].map((label, index) => (
                                <th
                                    key={index}
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
                                    {Array.from({ length: 6 }).map(
                                        (__, column) => (
                                            <td key={column} className={cell}>
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
                                        ),
                                    )}
                                </tr>
                            ))}

                        {!loading && entries.data.length === 0 && (
                            <tr>
                                <td
                                    colSpan={6}
                                    className="px-4 py-6 text-center"
                                    style={{
                                        color: textSecondary,
                                        fontSize: type.body,
                                    }}
                                >
                                    Nothing recorded for those filters.
                                </td>
                            </tr>
                        )}

                        {!loading &&
                            entries.data.map((entry, index) => (
                                <Fragment key={entry.id}>
                                    <tr
                                        style={{
                                            borderTop: `1px solid ${border}`,
                                            background:
                                                index % 2 === 1
                                                    ? rowAlt
                                                    : "transparent",
                                        }}
                                    >
                                        <td
                                            className={cell}
                                            style={{
                                                color: textSecondary,
                                                fontSize: type.secondary,
                                            }}
                                        >
                                            {when(entry.created_at)}
                                        </td>
                                        <td
                                            className={cell}
                                            style={{ color: text }}
                                        >
                                            {entry.user_name ?? (
                                                <span
                                                    style={{
                                                        color: textSecondary,
                                                    }}
                                                >
                                                    System
                                                </span>
                                            )}
                                        </td>
                                        <td
                                            className={cell}
                                            style={{ color: text }}
                                        >
                                            {readable(entry.action)}
                                        </td>
                                        <td
                                            className={cell}
                                            style={{ color: text }}
                                        >
                                            {entry.record ?? "—"}
                                            {entry.record_id && (
                                                <span
                                                    style={{
                                                        color: textSecondary,
                                                        fontSize:
                                                            type.secondary,
                                                    }}
                                                >
                                                    {" "}
                                                    #{entry.record_id}
                                                </span>
                                            )}
                                        </td>
                                        <td
                                            className={cell}
                                            style={{
                                                color: textSecondary,
                                                fontSize: type.secondary,
                                            }}
                                        >
                                            {summary(entry)}
                                        </td>
                                        <td className={cell}>
                                            {changedKeys(entry).length > 0 && (
                                                <button
                                                    onClick={() =>
                                                        setOpenId(
                                                            openId === entry.id
                                                                ? null
                                                                : entry.id,
                                                        )
                                                    }
                                                    style={{
                                                        color: "#1D9E75",
                                                        background:
                                                            "transparent",
                                                        border: "none",
                                                        fontWeight: 600,
                                                        fontSize:
                                                            type.secondary,
                                                        cursor: "pointer",
                                                        padding: 0,
                                                        fontFamily: "inherit",
                                                    }}
                                                >
                                                    {openId === entry.id
                                                        ? "Hide"
                                                        : "Details"}
                                                </button>
                                            )}
                                        </td>
                                    </tr>

                                    {openId === entry.id && (
                                        <tr style={{ background: rowAlt }}>
                                            <td
                                                colSpan={6}
                                                className="px-4 py-4"
                                            >
                                                <table
                                                    style={{
                                                        width: "100%",
                                                        fontSize:
                                                            type.secondary,
                                                    }}
                                                >
                                                    <thead>
                                                        <tr>
                                                            <th
                                                                className="text-left pb-2"
                                                                style={{
                                                                    color: textSecondary,
                                                                }}
                                                            >
                                                                Field
                                                            </th>
                                                            <th
                                                                className="text-left pb-2"
                                                                style={{
                                                                    color: textSecondary,
                                                                }}
                                                            >
                                                                Before
                                                            </th>
                                                            <th
                                                                className="text-left pb-2"
                                                                style={{
                                                                    color: textSecondary,
                                                                }}
                                                            >
                                                                After
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {changedKeys(entry).map(
                                                            (key) => (
                                                                <tr key={key}>
                                                                    <td
                                                                        className="py-1 pr-4"
                                                                        style={{
                                                                            color: text,
                                                                            fontWeight: 600,
                                                                        }}
                                                                    >
                                                                        {key}
                                                                    </td>
                                                                    <td
                                                                        className="py-1 pr-4"
                                                                        style={{
                                                                            color: textSecondary,
                                                                        }}
                                                                    >
                                                                        {String(
                                                                            entry
                                                                                .old_values?.[
                                                                                key
                                                                            ] ??
                                                                                "—",
                                                                        )}
                                                                    </td>
                                                                    <td
                                                                        className="py-1"
                                                                        style={{
                                                                            color: text,
                                                                        }}
                                                                    >
                                                                        {String(
                                                                            entry
                                                                                .new_values?.[
                                                                                key
                                                                            ] ??
                                                                                "—",
                                                                        )}
                                                                    </td>
                                                                </tr>
                                                            ),
                                                        )}
                                                    </tbody>
                                                </table>

                                                {entry.ip_address && (
                                                    <p
                                                        style={{
                                                            margin: "12px 0 0",
                                                            fontSize: type.hint,
                                                            color: textSecondary,
                                                        }}
                                                    >
                                                        From {entry.ip_address}
                                                    </p>
                                                )}
                                            </td>
                                        </tr>
                                    )}
                                </Fragment>
                            ))}
                    </tbody>
                </table>
            </div>

            <div className="flex flex-wrap gap-2 mt-4">
                {entries.links.map((link) => (
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
