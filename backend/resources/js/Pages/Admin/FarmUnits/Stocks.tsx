import AdminLayout from "@/Layouts/AdminLayout";
import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import { router, useForm, usePage } from "@inertiajs/react";
import { PageProps } from "@/types";
import { FormEvent, ReactNode, useState } from "react";

interface Choice {
    value: string;
    label: string;
}

interface MovementRow {
    id: number;
    reason: string;
    quantity: string;
    is_increase: boolean;
    occurred_on: string | null;
    note: string | null;
    recorded_by: string | null;
    is_confirmed: boolean;
    is_rejected: boolean;
    rejection_reason: string | null;
    can_confirm: boolean;
}

interface StockRow {
    id: number;
    source: string;
    opening_quantity: string;
    current_quantity: string;
    unit_of_measure: string | null;
    acquisition_cost: string;
    cost_per_unit: string | null;
    started_on: string | null;
    expected_ready_on: string | null;
    ended_on: string | null;
    is_confirmed: boolean;
    confirmed_by: string | null;
    is_rejected: boolean;
    rejection_reason: string | null;
    counts_toward_credit: boolean;
    can_confirm: boolean;
    movements: MovementRow[];
}

interface Props extends PageProps {
    farmer: { id: number; name: string };
    unit: {
        id: number;
        name: string;
        farm_type: string | null;
        farm_type_category: string | null;
        is_approved: boolean;
    };
    stocks: StockRow[];
    sources: Choice[];
    reasons: Choice[];
    layout: "admin" | "agent";
    basePath: string;
    permissions: { create: boolean; confirm: boolean };
}

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

export default function Stocks(props: Props) {
    return (
        <Frame
            layout={props.layout}
            title={`${props.unit.name} — what is in it`}
        >
            <StocksContent {...props} />
        </Frame>
    );
}

type ContentProps = Pick<
    Props,
    | "farmer"
    | "unit"
    | "stocks"
    | "sources"
    | "reasons"
    | "basePath"
    | "permissions"
>;

function StocksContent({
    farmer,
    unit,
    stocks,
    sources,
    reasons,
    basePath,
    permissions,
}: ContentProps) {
    const { errors } = usePage<Props>().props;
    const { dark } = useTheme();

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const inputBorder = dark ? "#4B5563" : "#9CA3AF";
    const inputBg = dark ? "#111827" : "#FFFFFF";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const warnBg = dark ? "rgba(180,83,9,0.15)" : "#FEF6E7";
    const rejectBg = dark ? "rgba(220,38,38,0.15)" : "#FEF2F2";
    const headerText = "#1D9E75";
    const rejectColor = "#DC2626";

    const [showStockForm, setShowStockForm] = useState(false);
    const [movementFor, setMovementFor] = useState<number | null>(null);
    const [rejectingStock, setRejectingStock] = useState<number | null>(null);
    const [stockRejectReason, setStockRejectReason] = useState("");
    const [rejectingMovement, setRejectingMovement] = useState<number | null>(
        null,
    );
    const [movementRejectReason, setMovementRejectReason] = useState("");

    const unitPath = `${basePath}/${farmer.id}/units/${unit.id}`;

    const readyLabel =
        unit.farm_type_category === "Crop"
            ? "When do you expect to harvest?"
            : "When will these be ready to sell?";

    const stockForm = useForm({
        source: "purchase",
        opening_quantity: "",
        unit_of_measure: "",
        acquisition_cost: "",
        started_on: "",
        expected_ready_on: "",
    });

    const movementForm = useForm({
        reason: "",
        quantity: "",
        occurred_on: "",
        note: "",
        is_increase: "true",
    });

    const submitStock = (event: FormEvent) => {
        event.preventDefault();
        stockForm.post(`${unitPath}/stocks`, {
            preserveScroll: true,
            onSuccess: () => {
                stockForm.reset();
                setShowStockForm(false);
            },
        });
    };

    const submitMovement = (event: FormEvent, stockId: number) => {
        event.preventDefault();
        movementForm.post(`${unitPath}/stocks/${stockId}/movements`, {
            preserveScroll: true,
            onSuccess: () => {
                movementForm.reset();
                setMovementFor(null);
            },
        });
    };

    const confirmStock = (stockId: number) => {
        router.patch(
            `${unitPath}/stocks/${stockId}/confirm`,
            {},
            { preserveScroll: true },
        );
    };

    const confirmMovement = (stockId: number, movementId: number) => {
        router.patch(
            `${unitPath}/stocks/${stockId}/movements/${movementId}/confirm`,
            {},
            { preserveScroll: true },
        );
    };

    const rejectStock = (event: FormEvent, stockId: number) => {
        event.preventDefault();
        router.patch(
            `${unitPath}/stocks/${stockId}/reject`,
            { reason: stockRejectReason },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setRejectingStock(null);
                    setStockRejectReason("");
                },
            },
        );
    };

    const rejectMovement = (
        event: FormEvent,
        stockId: number,
        movementId: number,
    ) => {
        event.preventDefault();
        router.patch(
            `${unitPath}/stocks/${stockId}/movements/${movementId}/reject`,
            { reason: movementRejectReason },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setRejectingMovement(null);
                    setMovementRejectReason("");
                },
            },
        );
    };

    const labelStyle = {
        color: text,
        fontSize: "16px",
        fontWeight: 600,
        display: "block",
        marginBottom: "4px",
    };
    const fieldStyle = {
        width: "100%",
        padding: "10px",
        fontSize: "17px",
        background: inputBg,
        color: text,
        border: `1px solid ${inputBorder}`,
    };
    const errorStyle = { color: "#DC2626", fontSize: "15px", marginTop: "4px" };
    const cardStyle = {
        background: surface,
        border: `1px solid ${border}`,
        padding: "20px",
    };
    const buttonStyle = {
        background: "#1D9E75",
        color: "#FFFFFF",
        border: "none",
        padding: "10px 20px",
        fontSize: "17px",
        fontWeight: 600,
        cursor: "pointer",
    };
    const linkStyle = {
        background: "none",
        border: "none",
        color: headerText,
        fontSize: "17px",
        fontWeight: 600,
        cursor: "pointer",
        padding: 0,
    };
    const rejectLinkStyle = { ...linkStyle, color: rejectColor };
    const rejectButtonStyle = { ...buttonStyle, background: rejectColor };

    return (
        <div className="space-y-6">
            <button
                onClick={() => router.visit(`${basePath}/${farmer.id}/units`)}
                style={linkStyle}
            >
                Back to units
            </button>

            <div style={cardStyle} className="space-y-2">
                <p style={{ color: text, fontSize: "22px", fontWeight: 700 }}>
                    {unit.name}
                </p>
                <p style={{ color: textSecondary, fontSize: "16px" }}>
                    {unit.farm_type} · {farmer.name}
                </p>
                {!unit.is_approved && (
                    <p style={{ color: "#B45309", fontSize: "16px" }}>
                        This unit has not been checked yet, so nothing here
                        counts toward credit.
                    </p>
                )}
            </div>

            {permissions.create && (
                <button
                    onClick={() => setShowStockForm(!showStockForm)}
                    style={buttonStyle}
                >
                    {showStockForm ? "Close" : "Add a count"}
                </button>
            )}

            {showStockForm && (
                <form
                    onSubmit={submitStock}
                    style={cardStyle}
                    className="space-y-4"
                >
                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <label style={labelStyle}>Where it came from</label>
                            <select
                                value={stockForm.data.source}
                                onChange={(event) =>
                                    stockForm.setData(
                                        "source",
                                        event.target.value,
                                    )
                                }
                                style={fieldStyle}
                            >
                                {sources.map((source) => (
                                    <option
                                        key={source.value}
                                        value={source.value}
                                    >
                                        {source.label}
                                    </option>
                                ))}
                            </select>
                            {(stockForm.errors.source || errors.source) && (
                                <p style={errorStyle}>
                                    {stockForm.errors.source || errors.source}
                                </p>
                            )}
                        </div>

                        <div>
                            <label style={labelStyle}>When it started</label>
                            <input
                                type="date"
                                value={stockForm.data.started_on}
                                onChange={(event) =>
                                    stockForm.setData(
                                        "started_on",
                                        event.target.value,
                                    )
                                }
                                style={fieldStyle}
                            />
                            {(stockForm.errors.started_on ||
                                errors.started_on) && (
                                <p style={errorStyle}>
                                    {stockForm.errors.started_on ||
                                        errors.started_on}
                                </p>
                            )}
                        </div>

                        <div>
                            <label style={labelStyle}>{readyLabel}</label>
                            <input
                                type="date"
                                value={stockForm.data.expected_ready_on}
                                onChange={(event) =>
                                    stockForm.setData(
                                        "expected_ready_on",
                                        event.target.value,
                                    )
                                }
                                style={fieldStyle}
                            />
                            {(stockForm.errors.expected_ready_on ||
                                errors.expected_ready_on) && (
                                <p style={errorStyle}>
                                    {stockForm.errors.expected_ready_on ||
                                        errors.expected_ready_on}
                                </p>
                            )}
                        </div>

                        <div>
                            <label style={labelStyle}>How many</label>
                            <div className="flex gap-2">
                                <input
                                    value={stockForm.data.opening_quantity}
                                    onChange={(event) =>
                                        stockForm.setData(
                                            "opening_quantity",
                                            event.target.value,
                                        )
                                    }
                                    placeholder="200"
                                    style={fieldStyle}
                                />
                                <input
                                    value={stockForm.data.unit_of_measure}
                                    onChange={(event) =>
                                        stockForm.setData(
                                            "unit_of_measure",
                                            event.target.value,
                                        )
                                    }
                                    placeholder="birds"
                                    style={fieldStyle}
                                />
                            </div>
                            {(stockForm.errors.opening_quantity ||
                                errors.opening_quantity) && (
                                <p style={errorStyle}>
                                    {stockForm.errors.opening_quantity ||
                                        errors.opening_quantity}
                                </p>
                            )}
                        </div>

                        <div>
                            <label style={labelStyle}>What it cost</label>
                            <input
                                value={stockForm.data.acquisition_cost}
                                onChange={(event) =>
                                    stockForm.setData(
                                        "acquisition_cost",
                                        event.target.value,
                                    )
                                }
                                placeholder="4000"
                                style={fieldStyle}
                            />
                            {(stockForm.errors.acquisition_cost ||
                                errors.acquisition_cost) && (
                                <p style={errorStyle}>
                                    {stockForm.errors.acquisition_cost ||
                                        errors.acquisition_cost}
                                </p>
                            )}
                        </div>
                    </div>

                    <p style={{ color: textSecondary, fontSize: "15px" }}>
                        Enter zero if nothing was paid.
                    </p>

                    <button
                        type="submit"
                        disabled={stockForm.processing}
                        style={{
                            ...buttonStyle,
                            opacity: stockForm.processing ? 0.7 : 1,
                        }}
                    >
                        Save count
                    </button>
                </form>
            )}

            {stocks.length === 0 && (
                <div style={cardStyle}>
                    <p style={{ color: textSecondary, fontSize: "17px" }}>
                        Nothing recorded in this unit yet.
                    </p>
                </div>
            )}

            {stocks.map((stock) => (
                <div
                    key={stock.id}
                    style={{
                        ...cardStyle,
                        background: stock.is_rejected ? rejectBg : surface,
                    }}
                    className="space-y-4"
                >
                    <div className="flex flex-wrap gap-6">
                        <div>
                            <p
                                style={{
                                    color: textSecondary,
                                    fontSize: "15px",
                                }}
                            >
                                There now
                            </p>
                            <p
                                style={{
                                    color: text,
                                    fontSize: "22px",
                                    fontWeight: 700,
                                }}
                            >
                                {stock.current_quantity} {stock.unit_of_measure}
                            </p>
                        </div>
                        <div>
                            <p
                                style={{
                                    color: textSecondary,
                                    fontSize: "15px",
                                }}
                            >
                                Started with
                            </p>
                            <p style={{ color: text, fontSize: "18px" }}>
                                {stock.opening_quantity}
                            </p>
                        </div>
                        <div>
                            <p
                                style={{
                                    color: textSecondary,
                                    fontSize: "15px",
                                }}
                            >
                                Cost
                            </p>
                            <p style={{ color: text, fontSize: "18px" }}>
                                {stock.acquisition_cost}
                            </p>
                        </div>
                        <div>
                            <p
                                style={{
                                    color: textSecondary,
                                    fontSize: "15px",
                                }}
                            >
                                Cost each
                            </p>
                            <p style={{ color: text, fontSize: "18px" }}>
                                {stock.cost_per_unit ?? "—"}
                            </p>
                        </div>
                        <div>
                            <p
                                style={{
                                    color: textSecondary,
                                    fontSize: "15px",
                                }}
                            >
                                Since
                            </p>
                            <p style={{ color: text, fontSize: "18px" }}>
                                {stock.started_on}
                            </p>
                        </div>
                        <div>
                            <p
                                style={{
                                    color: textSecondary,
                                    fontSize: "15px",
                                }}
                            >
                                Expected ready
                            </p>
                            <p style={{ color: text, fontSize: "18px" }}>
                                {stock.expected_ready_on ?? "—"}
                            </p>
                        </div>
                    </div>

                    {stock.is_rejected ? (
                        <p style={{ color: rejectColor, fontSize: "16px" }}>
                            Sent back: {stock.rejection_reason}
                        </p>
                    ) : (
                        <p
                            style={{
                                color: stock.is_confirmed
                                    ? headerText
                                    : "#B45309",
                                fontSize: "16px",
                            }}
                        >
                            {stock.is_confirmed
                                ? `Checked by ${stock.confirmed_by}`
                                : "Not checked yet"}
                            {stock.is_confirmed &&
                                !stock.counts_toward_credit &&
                                " · the unit still needs checking"}
                        </p>
                    )}

                    {!stock.is_rejected && (
                        <div className="flex flex-wrap gap-4">
                            {permissions.confirm &&
                                !stock.is_confirmed &&
                                stock.can_confirm && (
                                    <>
                                        <button
                                            onClick={() =>
                                                confirmStock(stock.id)
                                            }
                                            style={linkStyle}
                                        >
                                            Check this count
                                        </button>
                                        <button
                                            onClick={() =>
                                                setRejectingStock(
                                                    rejectingStock === stock.id
                                                        ? null
                                                        : stock.id,
                                                )
                                            }
                                            style={rejectLinkStyle}
                                        >
                                            {rejectingStock === stock.id
                                                ? "Close"
                                                : "Send back"}
                                        </button>
                                    </>
                                )}
                            {permissions.create && (
                                <button
                                    onClick={() =>
                                        setMovementFor(
                                            movementFor === stock.id
                                                ? null
                                                : stock.id,
                                        )
                                    }
                                    style={linkStyle}
                                >
                                    {movementFor === stock.id
                                        ? "Close"
                                        : "Record a change"}
                                </button>
                            )}
                        </div>
                    )}

                    {rejectingStock === stock.id && (
                        <form
                            onSubmit={(event) => rejectStock(event, stock.id)}
                            className="space-y-3"
                            style={{
                                borderTop: `1px solid ${border}`,
                                paddingTop: "16px",
                            }}
                        >
                            <div>
                                <label style={labelStyle}>
                                    Why is this being sent back?
                                </label>
                                <input
                                    value={stockRejectReason}
                                    onChange={(event) =>
                                        setStockRejectReason(event.target.value)
                                    }
                                    placeholder="Wrong number of animals"
                                    style={fieldStyle}
                                />
                                {errors.reason && (
                                    <p style={errorStyle}>{errors.reason}</p>
                                )}
                            </div>
                            <button type="submit" style={rejectButtonStyle}>
                                Send back
                            </button>
                        </form>
                    )}

                    {movementFor === stock.id && (
                        <form
                            onSubmit={(event) =>
                                submitMovement(event, stock.id)
                            }
                            className="space-y-4"
                            style={{
                                borderTop: `1px solid ${border}`,
                                paddingTop: "16px",
                            }}
                        >
                            <div className="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label style={labelStyle}>
                                        What happened
                                    </label>
                                    <select
                                        value={movementForm.data.reason}
                                        onChange={(event) =>
                                            movementForm.setData(
                                                "reason",
                                                event.target.value,
                                            )
                                        }
                                        style={fieldStyle}
                                    >
                                        <option value="">Choose one</option>
                                        {reasons.map((reason) => (
                                            <option
                                                key={reason.value}
                                                value={reason.value}
                                            >
                                                {reason.label}
                                            </option>
                                        ))}
                                    </select>
                                    {(movementForm.errors.reason ||
                                        errors.reason) && (
                                        <p style={errorStyle}>
                                            {movementForm.errors.reason ||
                                                errors.reason}
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <label style={labelStyle}>When</label>
                                    <input
                                        type="date"
                                        value={movementForm.data.occurred_on}
                                        onChange={(event) =>
                                            movementForm.setData(
                                                "occurred_on",
                                                event.target.value,
                                            )
                                        }
                                        style={fieldStyle}
                                    />
                                    {(movementForm.errors.occurred_on ||
                                        errors.occurred_on) && (
                                        <p style={errorStyle}>
                                            {movementForm.errors.occurred_on ||
                                                errors.occurred_on}
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <label style={labelStyle}>How many</label>
                                    <input
                                        value={movementForm.data.quantity}
                                        onChange={(event) =>
                                            movementForm.setData(
                                                "quantity",
                                                event.target.value,
                                            )
                                        }
                                        placeholder="5"
                                        style={fieldStyle}
                                    />
                                    {(movementForm.errors.quantity ||
                                        errors.quantity) && (
                                        <p style={errorStyle}>
                                            {movementForm.errors.quantity ||
                                                errors.quantity}
                                        </p>
                                    )}
                                </div>

                                {movementForm.data.reason === "correction" && (
                                    <div>
                                        <label style={labelStyle}>
                                            Which way
                                        </label>
                                        <select
                                            value={
                                                movementForm.data.is_increase
                                            }
                                            onChange={(event) =>
                                                movementForm.setData(
                                                    "is_increase",
                                                    event.target.value,
                                                )
                                            }
                                            style={fieldStyle}
                                        >
                                            <option value="true">
                                                There are more than we thought
                                            </option>
                                            <option value="false">
                                                There are fewer than we thought
                                            </option>
                                        </select>
                                        {(movementForm.errors.is_increase ||
                                            errors.is_increase) && (
                                            <p style={errorStyle}>
                                                {movementForm.errors
                                                    .is_increase ||
                                                    errors.is_increase}
                                            </p>
                                        )}
                                    </div>
                                )}

                                <div>
                                    <label style={labelStyle}>Note</label>
                                    <input
                                        value={movementForm.data.note}
                                        onChange={(event) =>
                                            movementForm.setData(
                                                "note",
                                                event.target.value,
                                            )
                                        }
                                        style={fieldStyle}
                                    />
                                    {(movementForm.errors.note ||
                                        errors.note) && (
                                        <p style={errorStyle}>
                                            {movementForm.errors.note ||
                                                errors.note}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <button
                                type="submit"
                                disabled={movementForm.processing}
                                style={{
                                    ...buttonStyle,
                                    opacity: movementForm.processing ? 0.7 : 1,
                                }}
                            >
                                Save change
                            </button>
                        </form>
                    )}

                    <div
                        style={{
                            borderTop: `1px solid ${border}`,
                            paddingTop: "16px",
                        }}
                    >
                        <p
                            style={{
                                color: text,
                                fontSize: "17px",
                                fontWeight: 600,
                                marginBottom: "8px",
                            }}
                        >
                            Changes
                        </p>

                        <table
                            className="min-w-full"
                            style={{ fontSize: "17px" }}
                        >
                            <tbody>
                                {stock.movements.map((movement) => (
                                    <>
                                        <tr
                                            key={movement.id}
                                            style={{
                                                borderTop: `1px solid ${border}`,
                                                background: movement.is_rejected
                                                    ? rejectBg
                                                    : movement.is_confirmed
                                                      ? "transparent"
                                                      : warnBg,
                                            }}
                                        >
                                            <td
                                                className="px-3 py-2"
                                                style={{ color: text }}
                                            >
                                                {movement.occurred_on}
                                            </td>
                                            <td
                                                className="px-3 py-2"
                                                style={{ color: text }}
                                            >
                                                {movement.reason}
                                            </td>
                                            <td
                                                className="px-3 py-2"
                                                style={{
                                                    color: movement.is_increase
                                                        ? headerText
                                                        : "#B45309",
                                                }}
                                            >
                                                {movement.is_increase
                                                    ? "+"
                                                    : "−"}
                                                {movement.quantity}
                                            </td>
                                            <td
                                                className="px-3 py-2"
                                                style={{
                                                    color: textSecondary,
                                                }}
                                            >
                                                {movement.recorded_by}
                                            </td>
                                            <td
                                                className="px-3 py-2"
                                                style={{
                                                    color: textSecondary,
                                                }}
                                            >
                                                {movement.note}
                                            </td>
                                            <td className="px-3 py-2">
                                                {movement.is_rejected ? (
                                                    <span
                                                        style={{
                                                            color: rejectColor,
                                                        }}
                                                    >
                                                        Sent back:{" "}
                                                        {
                                                            movement.rejection_reason
                                                        }
                                                    </span>
                                                ) : movement.is_confirmed ? (
                                                    <span
                                                        style={{
                                                            color: headerText,
                                                        }}
                                                    >
                                                        Checked
                                                    </span>
                                                ) : permissions.confirm &&
                                                  movement.can_confirm ? (
                                                    <div className="flex gap-3">
                                                        <button
                                                            onClick={() =>
                                                                confirmMovement(
                                                                    stock.id,
                                                                    movement.id,
                                                                )
                                                            }
                                                            style={linkStyle}
                                                        >
                                                            Check
                                                        </button>
                                                        <button
                                                            onClick={() =>
                                                                setRejectingMovement(
                                                                    rejectingMovement ===
                                                                        movement.id
                                                                        ? null
                                                                        : movement.id,
                                                                )
                                                            }
                                                            style={
                                                                rejectLinkStyle
                                                            }
                                                        >
                                                            {rejectingMovement ===
                                                            movement.id
                                                                ? "Close"
                                                                : "Send back"}
                                                        </button>
                                                    </div>
                                                ) : (
                                                    <span
                                                        style={{
                                                            color: "#B45309",
                                                        }}
                                                    >
                                                        Not checked
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                        {rejectingMovement === movement.id && (
                                            <tr
                                                key={`${movement.id}-reject`}
                                                style={{
                                                    borderTop: `1px solid ${border}`,
                                                }}
                                            >
                                                <td
                                                    colSpan={6}
                                                    className="px-3 py-3"
                                                >
                                                    <form
                                                        onSubmit={(event) =>
                                                            rejectMovement(
                                                                event,
                                                                stock.id,
                                                                movement.id,
                                                            )
                                                        }
                                                        className="flex flex-wrap items-end gap-3"
                                                    >
                                                        <div className="flex-1">
                                                            <label
                                                                style={
                                                                    labelStyle
                                                                }
                                                            >
                                                                Why is this
                                                                being sent back?
                                                            </label>
                                                            <input
                                                                value={
                                                                    movementRejectReason
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    setMovementRejectReason(
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    )
                                                                }
                                                                placeholder="Wrong reason chosen"
                                                                style={
                                                                    fieldStyle
                                                                }
                                                            />
                                                            {errors.reason && (
                                                                <p
                                                                    style={
                                                                        errorStyle
                                                                    }
                                                                >
                                                                    {
                                                                        errors.reason
                                                                    }
                                                                </p>
                                                            )}
                                                        </div>
                                                        <button
                                                            type="submit"
                                                            style={
                                                                rejectButtonStyle
                                                            }
                                                        >
                                                            Send back
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        )}
                                    </>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            ))}
        </div>
    );
}
