import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import { PageProps } from "@/types";
import { useState } from "react";
import { router } from "@inertiajs/react";
import { shortDate, cedis } from "@/lib/format";

interface Movement {
    id: number;
    reason: string | null;
    quantity: string;
    occurred_on: string | null;
    recorded_by: string | null;
    is_confirmed: boolean;
    is_rejected: boolean;
    rejection_reason: string | null;
}

interface Stock {
    id: number;
    source: string;
    opening_quantity: string;
    current_quantity: string;
    unit_of_measure: string | null;
    started_on: string | null;
    expected_ready_on: string | null;
    ended_on: string | null;
    is_confirmed: boolean;
    is_rejected: boolean;
    rejection_reason: string | null;
    movements: Movement[];
}

interface Analysis {
    total_income: number;
    total_expense: number;
    total_loss: number;
    net: number;
    produce_quantity_sold: string;
}

interface FarmUnitRow {
    id: number;
    name: string;
    farm_type: string | null;
    farm_type_category: string | null;
    capacity: string | null;
    capacity_unit: string | null;
    is_approved: boolean;
    analysis: Analysis;
    stocks: Stock[];
}

interface Filters {
    from: string;
    to: string;
}

interface Props extends PageProps {
    units: FarmUnitRow[];
    filters: Filters;
}

export default function Index(props: Props) {
    return (
        <AuthenticatedLayout title="My Farm">
            <IndexContent units={props.units} filters={props.filters} />
        </AuthenticatedLayout>
    );
}

type ContentProps = Pick<Props, "units" | "filters">;

function IndexContent({ units, filters }: ContentProps) {
    const { dark } = useTheme();
    const [openUnit, setOpenUnit] = useState<number | null>(null);
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);

    const applyFilter = () => {
        router.visit("/my-farm", { data: { from, to }, preserveScroll: true });
    };

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const warnBg = dark ? "rgba(180,83,9,0.15)" : "#FEF3C7";
    const rejectBg = dark ? "rgba(185,28,28,0.15)" : "#FEE2E2";

    const statusLabel = (confirmed: boolean, rejected: boolean) => {
        if (rejected) return "Rejected";
        if (confirmed) return "Confirmed";
        return "Pending for approval";
    };

    const statusBg = (confirmed: boolean, rejected: boolean) => {
        if (rejected) return rejectBg;
        if (!confirmed) return warnBg;
        return "transparent";
    };

    const field = {
        padding: "8px 10px",
        border: `1px solid ${border}`,
        background: dark ? "#111827" : "#FFFFFF",
        color: text,
        fontSize: "17px",
    } as const;

    const dateFilter = (
        <div className="flex flex-wrap items-end gap-3 mb-4">
            <div>
                <label
                    style={{
                        display: "block",
                        fontSize: "16px",
                        color: textSecondary,
                        marginBottom: "4px",
                    }}
                >
                    From
                </label>
                <input
                    type="date"
                    style={field}
                    value={from}
                    onChange={(event) => setFrom(event.target.value)}
                />
            </div>
            <div>
                <label
                    style={{
                        display: "block",
                        fontSize: "16px",
                        color: textSecondary,
                        marginBottom: "4px",
                    }}
                >
                    To
                </label>
                <input
                    type="date"
                    style={field}
                    value={to}
                    onChange={(event) => setTo(event.target.value)}
                />
            </div>
            <button
                onClick={applyFilter}
                style={{
                    padding: "9px 20px",
                    background: "#1D9E75",
                    color: "#FFFFFF",
                    fontSize: "17px",
                    fontWeight: 600,
                    border: "none",
                }}
            >
                Show
            </button>
        </div>
    );

    if (units.length === 0) {
        return (
            <div className="p-6" style={{ color: textSecondary }}>
                {dateFilter}
                You have no farm units yet.
            </div>
        );
    }

    return (
        <div className="p-6" style={{ color: text, fontSize: "18px" }}>
            {dateFilter}
            {units.map((unit) => (
                <div
                    key={unit.id}
                    className="mb-4"
                    style={{
                        background: surface,
                        border: `1px solid ${border}`,
                    }}
                >
                    <button
                        onClick={() =>
                            setOpenUnit(openUnit === unit.id ? null : unit.id)
                        }
                        className="w-full text-left px-4 py-3 flex justify-between items-center"
                    >
                        <div>
                            <div style={{ fontWeight: 600 }}>{unit.name}</div>
                            <div
                                style={{
                                    color: textSecondary,
                                    fontSize: "17px",
                                }}
                            >
                                {unit.farm_type ?? "—"}
                                {unit.capacity
                                    ? ` · Capacity ${unit.capacity} ${unit.capacity_unit ?? ""}`
                                    : ""}
                            </div>
                        </div>
                        <span style={{ color: textSecondary }}>
                            {unit.is_approved
                                ? "Approved"
                                : "Pending for approval"}
                        </span>
                    </button>

                    <div
                        className="px-4 pb-4 grid grid-cols-2 gap-3"
                        style={{
                            borderTop: `1px solid ${border}`,
                            paddingTop: "12px",
                            fontSize: "18px",
                        }}
                    >
                        <div>
                            <span
                                style={{
                                    color: textSecondary,
                                    fontSize: "16px",
                                }}
                            >
                                Produce sold
                            </span>
                            <div style={{ color: text, fontWeight: 600 }}>
                                {unit.analysis.produce_quantity_sold}{" "}
                                {unit.capacity_unit ?? ""}
                            </div>
                        </div>
                        <div>
                            <span
                                style={{
                                    color: textSecondary,
                                    fontSize: "16px",
                                }}
                            >
                                Income
                            </span>
                            <div style={{ color: "#1D9E75", fontWeight: 600 }}>
                                GHS {cedis(unit.analysis.total_income)}
                            </div>
                        </div>
                        <div>
                            <span
                                style={{
                                    color: textSecondary,
                                    fontSize: "16px",
                                }}
                            >
                                Expenses
                            </span>
                            <div style={{ color: "#B45309", fontWeight: 600 }}>
                                GHS {cedis(unit.analysis.total_expense)}
                            </div>
                        </div>
                        <div>
                            <span
                                style={{
                                    color: textSecondary,
                                    fontSize: "16px",
                                }}
                            >
                                Lost (no cash)
                            </span>
                            <div style={{ color: "#B91C1C", fontWeight: 600 }}>
                                GHS {cedis(unit.analysis.total_loss)}
                            </div>
                        </div>
                        <div style={{ gridColumn: "span 2" }}>
                            <span
                                style={{
                                    color: textSecondary,
                                    fontSize: "16px",
                                }}
                            >
                                Net profit
                            </span>
                            <div
                                style={{
                                    color:
                                        unit.analysis.net >= 0
                                            ? "#1D9E75"
                                            : "#B91C1C",
                                    fontWeight: 700,
                                    fontSize: "20px",
                                }}
                            >
                                GHS {cedis(unit.analysis.net)}
                            </div>
                        </div>
                    </div>

                    {openUnit === unit.id && (
                        <div className="px-4 pb-4">
                            {unit.stocks.length === 0 && (
                                <div style={{ color: textSecondary }}>
                                    No stock recorded on this unit yet.
                                </div>
                            )}

                            {unit.stocks.map((stock) => (
                                <div
                                    key={stock.id}
                                    className="mt-3 p-3"
                                    style={{
                                        border: `1px solid ${border}`,
                                        background: statusBg(
                                            stock.is_confirmed,
                                            stock.is_rejected,
                                        ),
                                    }}
                                >
                                    <div className="flex justify-between">
                                        <span>
                                            {stock.source} —{" "}
                                            {stock.current_quantity}{" "}
                                            {stock.unit_of_measure}
                                        </span>
                                        <span style={{ fontSize: "17px" }}>
                                            {statusLabel(
                                                stock.is_confirmed,
                                                stock.is_rejected,
                                            )}
                                        </span>
                                    </div>
                                    <div
                                        style={{
                                            color: textSecondary,
                                            fontSize: "16px",
                                        }}
                                    >
                                        Started {shortDate(stock.started_on)}
                                        {stock.expected_ready_on
                                            ? ` · Ready by ${shortDate(stock.expected_ready_on)}`
                                            : ""}
                                    </div>
                                    {stock.is_rejected &&
                                        stock.rejection_reason && (
                                            <div
                                                style={{
                                                    fontSize: "16px",
                                                    marginTop: "4px",
                                                }}
                                            >
                                                Reason: {stock.rejection_reason}
                                            </div>
                                        )}

                                    {stock.movements.length > 0 && (
                                        <div className="mt-2">
                                            {stock.movements.map((movement) => (
                                                <div
                                                    key={movement.id}
                                                    className="flex justify-between"
                                                    style={{
                                                        fontSize: "16px",
                                                        color: textSecondary,
                                                        padding: "2px 0",
                                                    }}
                                                >
                                                    <span>
                                                        {movement.reason} —{" "}
                                                        {movement.quantity} on{" "}
                                                        {shortDate(
                                                            movement.occurred_on,
                                                        )}
                                                    </span>
                                                    <span>
                                                        {statusLabel(
                                                            movement.is_confirmed,
                                                            movement.is_rejected,
                                                        )}
                                                    </span>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            ))}
        </div>
    );
}
