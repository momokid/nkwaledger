import AdminLayout from "@/Layouts/AdminLayout";
import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import { router, useForm, usePage } from "@inertiajs/react";
import { PageProps } from "@/types";
import { FormEvent, ReactNode, useState } from "react";

interface Option {
    id: number;
    name: string;
}

interface FarmerSummary {
    id: number;
    name: string;
    community_id: number;
}

interface UnitRow {
    id: number;
    name: string;
    farm_type_id: number;
    farm_type: string | null;
    community_id: number;
    community: string | null;
    capacity: string | null;
    capacity_unit: string | null;
    is_approved: boolean;
    approved_by: string | null;
    can_approve: boolean;
    is_active: boolean;
}

interface Props extends PageProps {
    farmer: FarmerSummary;
    units: UnitRow[];
    communities: Option[];
    farmTypes: Option[];
    layout: "admin" | "agent";
    basePath: string;
    permissions: { create: boolean; update: boolean; approve: boolean };
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

export default function Index(props: Props) {
    return (
        <Frame
            layout={props.layout}
            title={`${props.farmer.name} — farm units`}
        >
            <IndexContent {...props} />
        </Frame>
    );
}

type ContentProps = Pick<
    Props,
    | "farmer"
    | "units"
    | "communities"
    | "farmTypes"
    | "basePath"
    | "permissions"
>;

function IndexContent({
    farmer,
    units,
    communities,
    farmTypes,
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
    const headerBg = dark ? "rgba(29,158,117,0.15)" : "#EAF5F0";
    const headerText = "#1D9E75";

    const [showForm, setShowForm] = useState(false);
    const [editing, setEditing] = useState<number | null>(null);

    const form = useForm({
        farm_type_id: "",
        community_id: String(farmer.community_id),
        name: "",
        capacity: "",
        capacity_unit: "",
        is_active: true,
    });

    const startAdd = () => {
        setEditing(null);
        form.setData({
            farm_type_id: "",
            community_id: String(farmer.community_id),
            name: "",
            capacity: "",
            capacity_unit: "",
            is_active: true,
        });
        setShowForm(true);
    };

    const startEdit = (unit: UnitRow) => {
        setEditing(unit.id);
        form.setData({
            farm_type_id: String(unit.farm_type_id),
            community_id: String(unit.community_id),
            name: unit.name,
            capacity: unit.capacity ?? "",
            capacity_unit: unit.capacity_unit ?? "",
            is_active: unit.is_active,
        });
        setShowForm(true);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        const done = {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setShowForm(false);
                setEditing(null);
            },
        };

        if (editing === null) {
            form.post(`${basePath}/${farmer.id}/units`, done);
            return;
        }

        form.put(`${basePath}/${farmer.id}/units/${editing}`, done);
    };

    const approve = (unit: UnitRow) => {
        router.patch(
            `${basePath}/${farmer.id}/units/${unit.id}/approve`,
            {},
            { preserveScroll: true },
        );
    };

    const thStyle = { color: headerText, fontWeight: 700 };
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

    const columnCount = permissions.update || permissions.approve ? 6 : 5;

    return (
        <div className="space-y-6">
            <button
                onClick={() => router.visit(`${basePath}/${farmer.id}`)}
                style={linkStyle}
            >
                Back to {farmer.name}
            </button>

            {permissions.create && (
                <button
                    onClick={showForm ? () => setShowForm(false) : startAdd}
                    style={buttonStyle}
                >
                    {showForm ? "Close" : "Add a unit"}
                </button>
            )}

            {showForm && (
                <form
                    onSubmit={submit}
                    className="space-y-4"
                    style={{
                        background: surface,
                        border: `1px solid ${border}`,
                        padding: "20px",
                    }}
                >
                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <label style={labelStyle}>Name</label>
                            <input
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData("name", event.target.value)
                                }
                                placeholder="Pen A"
                                style={fieldStyle}
                            />
                            {(form.errors.name || errors.name) && (
                                <p style={errorStyle}>
                                    {form.errors.name || errors.name}
                                </p>
                            )}
                        </div>

                        <div>
                            <label style={labelStyle}>
                                What is farmed here
                            </label>
                            <select
                                value={form.data.farm_type_id}
                                onChange={(event) =>
                                    form.setData(
                                        "farm_type_id",
                                        event.target.value,
                                    )
                                }
                                style={fieldStyle}
                            >
                                <option value="">Choose one</option>
                                {farmTypes.map((farmType) => (
                                    <option
                                        key={farmType.id}
                                        value={farmType.id}
                                    >
                                        {farmType.name}
                                    </option>
                                ))}
                            </select>
                            {(form.errors.farm_type_id ||
                                errors.farm_type_id) && (
                                <p style={errorStyle}>
                                    {form.errors.farm_type_id ||
                                        errors.farm_type_id}
                                </p>
                            )}
                        </div>

                        <div>
                            <label style={labelStyle}>Where it is</label>
                            <select
                                value={form.data.community_id}
                                onChange={(event) =>
                                    form.setData(
                                        "community_id",
                                        event.target.value,
                                    )
                                }
                                style={fieldStyle}
                            >
                                <option value="">Choose a community</option>
                                {communities.map((community) => (
                                    <option
                                        key={community.id}
                                        value={community.id}
                                    >
                                        {community.name}
                                    </option>
                                ))}
                            </select>
                            {(form.errors.community_id ||
                                errors.community_id) && (
                                <p style={errorStyle}>
                                    {form.errors.community_id ||
                                        errors.community_id}
                                </p>
                            )}
                        </div>

                        <div>
                            <label style={labelStyle}>How much it holds</label>
                            <div className="flex gap-2">
                                <input
                                    value={form.data.capacity}
                                    onChange={(event) =>
                                        form.setData(
                                            "capacity",
                                            event.target.value,
                                        )
                                    }
                                    placeholder="250"
                                    style={fieldStyle}
                                />
                                <input
                                    value={form.data.capacity_unit}
                                    onChange={(event) =>
                                        form.setData(
                                            "capacity_unit",
                                            event.target.value,
                                        )
                                    }
                                    placeholder="birds"
                                    style={fieldStyle}
                                />
                            </div>
                            {(form.errors.capacity || errors.capacity) && (
                                <p style={errorStyle}>
                                    {form.errors.capacity || errors.capacity}
                                </p>
                            )}
                            {(form.errors.capacity_unit ||
                                errors.capacity_unit) && (
                                <p style={errorStyle}>
                                    {form.errors.capacity_unit ||
                                        errors.capacity_unit}
                                </p>
                            )}
                        </div>
                    </div>

                    {editing !== null && (
                        <label
                            style={{
                                color: text,
                                fontSize: "16px",
                                cursor: "pointer",
                            }}
                        >
                            <input
                                type="checkbox"
                                checked={form.data.is_active}
                                onChange={(event) =>
                                    form.setData(
                                        "is_active",
                                        event.target.checked,
                                    )
                                }
                                style={{ marginRight: "8px" }}
                            />
                            Active
                        </label>
                    )}

                    <button
                        type="submit"
                        disabled={form.processing}
                        style={{
                            ...buttonStyle,
                            opacity: form.processing ? 0.7 : 1,
                        }}
                    >
                        {editing === null ? "Add unit" : "Save unit"}
                    </button>
                </form>
            )}

            <div
                className="overflow-x-auto"
                style={{ background: surface, border: `1px solid ${border}` }}
            >
                <table className="min-w-full" style={{ fontSize: "18px" }}>
                    <thead>
                        <tr style={{ background: headerBg }}>
                            <th className="text-left px-4 py-3" style={thStyle}>
                                Name
                            </th>
                            <th className="text-left px-4 py-3" style={thStyle}>
                                Farms
                            </th>
                            <th className="text-left px-4 py-3" style={thStyle}>
                                Where
                            </th>
                            <th className="text-left px-4 py-3" style={thStyle}>
                                Holds
                            </th>
                            <th className="text-left px-4 py-3" style={thStyle}>
                                Checked
                            </th>
                            {(permissions.update || permissions.approve) && (
                                <th
                                    className="text-left px-4 py-3"
                                    style={thStyle}
                                >
                                    Actions
                                </th>
                            )}
                        </tr>
                    </thead>
                    <tbody>
                        {units.length === 0 && (
                            <tr>
                                <td
                                    colSpan={columnCount}
                                    className="px-4 py-6 text-center"
                                    style={{ color: textSecondary }}
                                >
                                    No units yet. Add the first pen or plot
                                    above.
                                </td>
                            </tr>
                        )}

                        {units.map((unit) => (
                            <tr
                                key={unit.id}
                                style={{ borderTop: `1px solid ${border}` }}
                            >
                                <td
                                    className="px-4 py-3"
                                    style={{
                                        color: unit.is_active
                                            ? text
                                            : "#B45309",
                                    }}
                                >
                                    {unit.name}
                                    {!unit.is_active && (
                                        <span
                                            style={{
                                                fontSize: "15px",
                                                display: "block",
                                            }}
                                        >
                                            On hold
                                        </span>
                                    )}
                                </td>
                                <td
                                    className="px-4 py-3"
                                    style={{ color: text }}
                                >
                                    {unit.farm_type}
                                </td>
                                <td
                                    className="px-4 py-3"
                                    style={{ color: text }}
                                >
                                    {unit.community}
                                </td>
                                <td
                                    className="px-4 py-3"
                                    style={{ color: text }}
                                >
                                    {unit.capacity
                                        ? `${unit.capacity} ${unit.capacity_unit ?? ""}`
                                        : "—"}
                                </td>
                                <td
                                    className="px-4 py-3"
                                    style={{
                                        color: unit.is_approved
                                            ? headerText
                                            : "#B45309",
                                    }}
                                >
                                    {unit.is_approved
                                        ? `Yes, by ${unit.approved_by}`
                                        : "Not yet"}
                                </td>
                                {(permissions.update ||
                                    permissions.approve) && (
                                    <td className="px-4 py-3">
                                        <div className="flex gap-4">
                                            {permissions.update && (
                                                <button
                                                    onClick={() =>
                                                        startEdit(unit)
                                                    }
                                                    style={linkStyle}
                                                >
                                                    Edit
                                                </button>
                                            )}
                                            {permissions.approve &&
                                                !unit.is_approved &&
                                                unit.can_approve && (
                                                    <button
                                                        onClick={() =>
                                                            approve(unit)
                                                        }
                                                        style={linkStyle}
                                                    >
                                                        Approve
                                                    </button>
                                                )}
                                            <button
                                                onClick={() =>
                                                    router.visit(
                                                        `${basePath}/${farmer.id}/units/${unit.id}/stocks`,
                                                    )
                                                }
                                                style={linkStyle}
                                            >
                                                What is in it
                                            </button>
                                        </div>
                                    </td>
                                )}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <p style={{ color: textSecondary, fontSize: "15px" }}>
                A unit that is not checked still works, but nothing recorded
                against it counts toward credit.
            </p>
        </div>
    );
}
