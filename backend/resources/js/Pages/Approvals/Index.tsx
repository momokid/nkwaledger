import AdminLayout from "@/Layouts/AdminLayout";
import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import Button from "@/Components/Button";
import TableSkeletonRows from "@/Components/Admin/TableSkeletonRows";
import { cedis, shortDate } from "@/lib/format";
import { router, useForm, usePage } from "@inertiajs/react";
import { PageProps } from "@/types";
import { Fragment, ReactNode, useState } from "react";

interface Details {
    [key: string]: string | number | boolean | null;
}

interface Item {
    kind: "farm_unit" | "stock" | "stock_movement" | "reversal";
    id: number;
    uuid?: string;
    farmer: string;
    farmer_id: string;
    what: string;
    added_by: string | null;
    waiting_since: string;
    can_approve: boolean;
    unit_id?: number;
    stock_id?: number;
    details: Details;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props extends PageProps {
    items: { data: Item[]; links: PaginationLink[] };
    layout: "admin" | "agent";
    basePath: string;
    permissions: { approve: boolean; confirm: boolean };
}

const KIND_LABELS: Record<Item["kind"], string> = {
    farm_unit: "Farm unit",
    stock: "Count",
    stock_movement: "Change",
    reversal: "Cancellation",
};

// only these kinds have a reject endpoint behind them
const REFUSABLE_KINDS: Item["kind"][] = ["stock", "stock_movement", "reversal"];

const DETAIL_LABELS: Record<string, string> = {
    farm_type: "Farms",
    community: "Where",
    capacity: "Holds",
    capacity_unit: "Measured in",
    provisional_records: "Records waiting on this",
    source: "Came from",
    opening_quantity: "Started with",
    current_quantity: "There now",
    acquisition_cost: "Cost",
    started_on: "Started",
    unit_approved: "Pen checked",
    reason: "Why",
    quantity: "How many",
    is_increase: "Going up",
    occurred_on: "Happened",
    note: "Note",
    count_now: "Count now",
    reference: "Reference",
    what_happened: "What happened",
    amount: "Amount",
    recorded_on: "Recorded on",
};

const MONEY_KEYS = ["amount", "acquisition_cost"];
const DATE_KEYS = ["started_on", "occurred_on", "recorded_on"];

const waiting = (iso: string) => {
    const hours = Math.floor((Date.now() - new Date(iso).getTime()) / 3600000);

    if (hours < 1) return "just now";
    if (hours < 24) return `${hours} hour${hours === 1 ? "" : "s"}`;

    const days = Math.floor(hours / 24);
    return `${days} day${days === 1 ? "" : "s"}`;
};

const showValue = (name: string, value: Details[string]) => {
    if (value === null || value === "") return "—";
    if (typeof value === "boolean") return value ? "Yes" : "No";
    if (MONEY_KEYS.includes(name)) return `GHS ${cedis(Number(value))}`;
    if (DATE_KEYS.includes(name)) return shortDate(String(value));

    return String(value);
};

function Frame({
    layout,
    title,
    children,
}: {
    layout: "admin" | "agent";
    title: string;
    children: ReactNode;
}) {
    if (layout === "agent") {
        return (
            <AuthenticatedLayout title={title}>{children}</AuthenticatedLayout>
        );
    }

    return <AdminLayout title={title}>{children}</AdminLayout>;
}

export default function Index(props: Props) {
    return (
        <Frame layout={props.layout} title="Waiting for you">
            <IndexContent {...props} />
        </Frame>
    );
}

type ContentProps = Pick<Props, "items" | "basePath" | "permissions">;

function IndexContent({ items, basePath, permissions }: ContentProps) {
    const { errors } = usePage<Props>().props as ContentProps & {
        errors: Record<string, string>;
    };
    const { dark } = useTheme();

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const inputBorder = dark ? "#4B5563" : "#9CA3AF";
    const inputBg = dark ? "#111827" : "#FFFFFF";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const headerBg = dark ? "rgba(29,158,117,0.15)" : "#EAF5F0";
    const rowAlt = dark ? "#111827" : "#F9FAFB";
    const openBg = dark ? "#0B1220" : "#F3F4F6";
    const brand = "#1D9E75";

    const [openRow, setOpenRow] = useState<string | null>(null);
    const [busy, setBusy] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    const [refusing, setRefusing] = useState<Item | null>(null);

    const form = useForm({ reason: "" });

    const key = (item: Item) => `${item.kind}:${item.id}`;

    const approveUrl = (item: Item) => {
        if (item.kind === "reversal")
            return `${basePath}/reversals/${item.uuid}/approve`;

        const base = `${basePath}/farmers/${item.farmer_id}/units`;

        if (item.kind === "farm_unit") return `${base}/${item.id}/approve`;
        if (item.kind === "stock")
            return `${base}/${item.unit_id}/stocks/${item.id}/confirm`;

        return `${base}/${item.unit_id}/stocks/${item.stock_id}/movements/${item.id}/confirm`;
    };

    const refuseUrl = (item: Item) => {
        if (item.kind === "reversal")
            return `${basePath}/reversals/${item.uuid}/reject`;

        const base = `${basePath}/farmers/${item.farmer_id}/units`;

        if (item.kind === "stock")
            return `${base}/${item.unit_id}/stocks/${item.id}/reject`;

        if (item.kind === "stock_movement")
            return `${base}/${item.unit_id}/stocks/${item.stock_id}/movements/${item.id}/reject`;

        return null;
    };

    const sign = (item: Item) => {
        setBusy(key(item));

        router.patch(
            approveUrl(item),
            {},
            {
                preserveScroll: true,
                onFinish: () => setBusy(null),
            },
        );
    };

    const refuse = () => {
        if (refusing === null) return;

        const url = refuseUrl(refusing);
        if (!url) return;

        form.patch(url, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setRefusing(null);
            },
        });
    };

    const page = (url: string | null) => {
        if (!url) return;

        setLoading(true);
        router.visit(url, {
            preserveState: true,
            onFinish: () => setLoading(false),
        });
    };

    const thStyle = { color: brand, fontWeight: 700, fontSize: "16px" };

    const field = {
        padding: "8px 10px",
        border: `1px solid ${inputBorder}`,
        background: inputBg,
        color: text,
        fontSize: "17px",
    } as const;

    const canAct = permissions.approve || permissions.confirm;

    return (
        <div className="space-y-4">
            <div
                className="p-5"
                style={{ background: surface, border: `1px solid ${border}` }}
            >
                <h2 style={{ fontSize: "22px", fontWeight: 700, color: text }}>
                    Waiting for you
                </h2>
                <p
                    style={{
                        fontSize: "17px",
                        color: textSecondary,
                        marginTop: "4px",
                    }}
                >
                    Oldest first. Anything you added yourself is here too, but
                    somebody else has to sign it off.
                </p>
            </div>

            <div
                className="overflow-x-auto"
                style={{ background: surface, border: `1px solid ${border}` }}
            >
                <table className="min-w-full" style={{ fontSize: "18px" }}>
                    <thead>
                        <tr style={{ background: headerBg }}>
                            {[
                                "What",
                                "Farmer",
                                "Detail",
                                "Added by",
                                "Waiting",
                                "",
                            ].map((heading, index) => (
                                <th
                                    key={index}
                                    className="text-left px-4 py-3"
                                    style={thStyle}
                                >
                                    {heading}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {loading && <TableSkeletonRows rows={5} columns={6} />}

                        {!loading && items.data.length === 0 && (
                            <tr>
                                <td
                                    colSpan={6}
                                    className="px-4 py-6 text-center"
                                    style={{ color: textSecondary }}
                                >
                                    Nothing is waiting. Everything has been
                                    checked.
                                </td>
                            </tr>
                        )}

                        {!loading &&
                            items.data.map((item, index) => {
                                const rowKey = key(item);
                                const open = openRow === rowKey;

                                return (
                                    <Fragment key={rowKey}>
                                        <tr
                                            onClick={() =>
                                                setOpenRow(open ? null : rowKey)
                                            }
                                            style={{
                                                borderTop: `1px solid ${border}`,
                                                background:
                                                    index % 2 === 1
                                                        ? rowAlt
                                                        : "transparent",
                                                cursor: "pointer",
                                            }}
                                        >
                                            <td
                                                className="px-4 py-3"
                                                style={{ color: text }}
                                            >
                                                {KIND_LABELS[item.kind]}
                                            </td>
                                            <td
                                                className="px-4 py-3"
                                                style={{ color: text }}
                                            >
                                                {item.farmer}
                                            </td>
                                            <td
                                                className="px-4 py-3"
                                                style={{ color: text }}
                                            >
                                                {item.what}
                                            </td>
                                            <td
                                                className="px-4 py-3"
                                                style={{ color: textSecondary }}
                                            >
                                                {item.added_by ?? "—"}
                                            </td>
                                            <td
                                                className="px-4 py-3"
                                                style={{ color: "#B45309" }}
                                            >
                                                {waiting(item.waiting_since)}
                                            </td>
                                            <td className="px-4 py-3">
                                                {canAct && item.can_approve && (
                                                    <div className="flex gap-2">
                                                        <Button
                                                            size="small"
                                                            busy={
                                                                busy === rowKey
                                                            }
                                                            busyLabel="Saving..."
                                                            onClick={(
                                                                event,
                                                            ) => {
                                                                event.stopPropagation();
                                                                sign(item);
                                                            }}
                                                        >
                                                            {item.kind ===
                                                            "reversal"
                                                                ? "Agree"
                                                                : "Check it"}
                                                        </Button>

                                                        {REFUSABLE_KINDS.includes(
                                                            item.kind,
                                                        ) && (
                                                            <Button
                                                                look="danger"
                                                                size="small"
                                                                onClick={(
                                                                    event,
                                                                ) => {
                                                                    event.stopPropagation();
                                                                    setRefusing(
                                                                        item,
                                                                    );
                                                                }}
                                                            >
                                                                Refuse
                                                            </Button>
                                                        )}
                                                    </div>
                                                )}
                                                {!item.can_approve && (
                                                    <span
                                                        style={{
                                                            fontSize: "15px",
                                                            color: textSecondary,
                                                        }}
                                                    >
                                                        Waiting on somebody else
                                                    </span>
                                                )}
                                            </td>
                                        </tr>

                                        {open && (
                                            <tr style={{ background: openBg }}>
                                                <td
                                                    colSpan={6}
                                                    className="px-6 py-4"
                                                >
                                                    <div className="flex flex-wrap gap-6">
                                                        {Object.entries(
                                                            item.details,
                                                        ).map(
                                                            ([name, value]) => (
                                                                <div
                                                                    key={name}
                                                                    style={{
                                                                        minWidth:
                                                                            "140px",
                                                                    }}
                                                                >
                                                                    <p
                                                                        style={{
                                                                            fontSize:
                                                                                "15px",
                                                                            color: textSecondary,
                                                                        }}
                                                                    >
                                                                        {DETAIL_LABELS[
                                                                            name
                                                                        ] ??
                                                                            name}
                                                                    </p>
                                                                    <p
                                                                        style={{
                                                                            fontSize:
                                                                                "17px",
                                                                            color: text,
                                                                            marginTop:
                                                                                "2px",
                                                                        }}
                                                                    >
                                                                        {showValue(
                                                                            name,
                                                                            value,
                                                                        )}
                                                                    </p>
                                                                </div>
                                                            ),
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        )}
                                    </Fragment>
                                );
                            })}
                    </tbody>
                </table>
            </div>

            {items.links.length > 3 && (
                <div className="flex flex-wrap gap-2">
                    {items.links.map((link, index) => (
                        <Button
                            key={index}
                            size="small"
                            look={link.active ? "primary" : "secondary"}
                            onClick={() => page(link.url)}
                            disabled={!link.url}
                        >
                            <span
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        </Button>
                    ))}
                </div>
            )}

            {refusing !== null && (
                <div
                    className="fixed inset-0 flex items-center justify-center p-4"
                    style={{ background: "rgba(0,0,0,0.5)", zIndex: 50 }}
                >
                    <div
                        className="p-6"
                        style={{
                            background: surface,
                            border: `1px solid ${border}`,
                            maxWidth: "460px",
                            width: "100%",
                        }}
                    >
                        <h3
                            style={{
                                fontSize: "20px",
                                fontWeight: 700,
                                color: text,
                            }}
                        >
                            Turn this down?
                        </h3>

                        <p
                            style={{
                                fontSize: "17px",
                                color: textSecondary,
                                marginTop: "6px",
                            }}
                        >
                            {refusing.what} for {refusing.farmer}.
                        </p>

                        <p
                            style={{
                                fontSize: "16px",
                                color: textSecondary,
                                marginTop: "10px",
                            }}
                        >
                            Whoever added this will see why, so they can fix it
                            and try again.
                        </p>

                        <label
                            style={{
                                display: "block",
                                fontSize: "17px",
                                fontWeight: 600,
                                color: text,
                                marginTop: "16px",
                                marginBottom: "6px",
                            }}
                        >
                            Why are you turning it down?
                        </label>
                        <textarea
                            rows={3}
                            style={{ ...field, width: "100%" }}
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData("reason", event.target.value)
                            }
                        />
                        {errors.reason && (
                            <p
                                style={{
                                    fontSize: "15px",
                                    color: "#B91C1C",
                                    marginTop: "4px",
                                }}
                            >
                                {errors.reason}
                            </p>
                        )}

                        <div
                            className="flex gap-3"
                            style={{ marginTop: "18px" }}
                        >
                            <Button
                                look="danger"
                                onClick={refuse}
                                busy={form.processing}
                                busyLabel="Sending..."
                            >
                                Turn it down
                            </Button>
                            <Button
                                look="secondary"
                                onClick={() => {
                                    form.reset();
                                    setRefusing(null);
                                }}
                            >
                                Go back
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
