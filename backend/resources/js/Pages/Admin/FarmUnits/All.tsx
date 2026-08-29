import AdminLayout from "@/Layouts/AdminLayout";
import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import TableSkeletonRows from "@/Components/Admin/TableSkeletonRows";
import { router, useForm, usePage } from "@inertiajs/react";
import { PageProps } from "@/types";
import { FormEvent, ReactNode, useEffect, useMemo, useState } from "react";

interface Option {
    id: number;
    name: string;
}

interface FarmerOption {
    uuid: string;
    name: string;
    phone: string | null;
    community_id: number;
}

interface UnitRow {
    id: number;
    name: string;
    farmer: string;
    farmer_uuid: string;
    farm_type: string | null;
    community: string | null;
    capacity: string | null;
    capacity_unit: string | null;
    is_approved: boolean;
    approved_by: string | null;
    can_approve: boolean;
    is_active: boolean;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props extends PageProps {
    units: { data: UnitRow[]; links: PaginationLink[] };
    farmers: FarmerOption[];
    communities: Option[];
    farmTypes: Option[];
    filters: { farmer: string | null };
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

export default function All(props: Props) {
    return (
        <Frame layout={props.layout} title="Farm Units">
            <AllContent {...props} />
        </Frame>
    );
}

type ContentProps = Pick<
    Props,
    | "units"
    | "farmers"
    | "communities"
    | "farmTypes"
    | "filters"
    | "basePath"
    | "permissions"
>;

function AllContent({
    units,
    farmers,
    communities,
    farmTypes,
    filters,
    basePath,
    permissions,
}: ContentProps) {
    const { errors } = usePage<Props>().props;
    const { dark } = useTheme();

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const inputBorder = dark ? "#4B5563" : "#9CA3AF";
    const inputBg = dark ? "#111827" : "#FFFFFF";
    const readOnlyBg = dark ? "#0B1220" : "#F3F4F6";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const headerBg = dark ? "rgba(29,158,117,0.15)" : "#EAF5F0";
    const headerText = "#1D9E75";

    const [showForm, setShowForm] = useState(false);
    const [navLoading, setNavLoading] = useState(false);
    const [farmerSearch, setFarmerSearch] = useState("");
    const [filterSearch, setFilterSearch] = useState("");

    useEffect(() => {
        const start = router.on("start", () => setNavLoading(true));
        const finish = router.on("finish", () => setNavLoading(false));
        return () => {
            start();
            finish();
        };
    }, []);

    // when the list is already showing one farmer, that is who a new unit belongs to
    const chosenFarmer =
        farmers.find((row) => row.uuid === filters.farmer) ?? null;

    const form = useForm({
        farmer_uuid: chosenFarmer?.uuid ?? "",
        farm_type_id: "",
        community_id: chosenFarmer ? String(chosenFarmer.community_id) : "",
        name: "",
        capacity: "",
        capacity_unit: "",
    });

    const matches = (farmer: FarmerOption, term: string) => {
        const needle = term.trim().toLowerCase();

        if (needle === "") {
            return true;
        }

        return (
            farmer.name.toLowerCase().includes(needle) ||
            (farmer.phone ?? "").includes(needle)
        );
    };

    const searchResults = useMemo(
        () =>
            farmers
                .filter((farmer) => matches(farmer, farmerSearch))
                .slice(0, 8),
        [farmers, farmerSearch],
    );

    const filterResults = useMemo(
        () =>
            farmers
                .filter((farmer) => matches(farmer, filterSearch))
                .slice(0, 8),
        [farmers, filterSearch],
    );

    // the unit usually sits where the farmer works, so choosing one fills the other
    const chooseFarmer = (farmer: FarmerOption) => {
        form.setData("farmer_uuid", farmer.uuid);
        form.setData("community_id", String(farmer.community_id));
        setFarmerSearch("");
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(basePath, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                form.setData("farmer_uuid", chosenFarmer?.uuid ?? "");
                setShowForm(false);
            },
        });
    };

    const filterBy = (uuid: string) => {
        setFilterSearch("");
        router.visit(uuid ? `${basePath}?farmer=${uuid}` : basePath);
    };

    const pickedInForm =
        farmers.find((row) => row.uuid === form.data.farmer_uuid) ?? null;

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
    const readOnlyStyle = {
        ...fieldStyle,
        background: readOnlyBg,
        color: textSecondary,
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
    const resultStyle = {
        display: "block",
        width: "100%",
        textAlign: "left" as const,
        padding: "10px",
        fontSize: "17px",
        background: surface,
        color: text,
        border: "none",
        borderTop: `1px solid ${border}`,
        cursor: "pointer",
    };

    const columnCount = 7;

    return (
        <div className="space-y-6">
            <div
                style={{
                    background: surface,
                    border: `1px solid ${border}`,
                    padding: "16px",
                }}
                className="space-y-3"
            >
                <label style={labelStyle}>Show one farmer</label>

                {chosenFarmer ? (
                    <div className="flex flex-wrap items-center gap-4">
                        <p
                            style={{
                                color: text,
                                fontSize: "18px",
                                fontWeight: 600,
                            }}
                        >
                            {chosenFarmer.name} · {chosenFarmer.phone}
                        </p>
                        <button onClick={() => filterBy("")} style={linkStyle}>
                            Show all farmers
                        </button>
                    </div>
                ) : (
                    <div>
                        <input
                            value={filterSearch}
                            onChange={(event) =>
                                setFilterSearch(event.target.value)
                            }
                            placeholder="Type a name or phone number"
                            style={fieldStyle}
                        />

                        {filterSearch.trim() !== "" && (
                            <div
                                style={{
                                    border: `1px solid ${inputBorder}`,
                                    borderTop: "none",
                                }}
                            >
                                {filterResults.length === 0 && (
                                    <p
                                        style={{
                                            color: textSecondary,
                                            fontSize: "16px",
                                            padding: "10px",
                                        }}
                                    >
                                        Nobody matches that.
                                    </p>
                                )}

                                {filterResults.map((farmer) => (
                                    <button
                                        key={farmer.uuid}
                                        onClick={() => filterBy(farmer.uuid)}
                                        style={resultStyle}
                                    >
                                        {farmer.name}
                                        <span
                                            style={{
                                                color: textSecondary,
                                                marginLeft: "8px",
                                            }}
                                        >
                                            {farmer.phone}
                                        </span>
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>
                )}
            </div>

            {permissions.create && (
                <button
                    onClick={() => setShowForm(!showForm)}
                    style={buttonStyle}
                >
                    {showForm ? "Close" : "Add a unit"}
                </button>
            )}

            {showForm && permissions.create && (
                <form
                    onSubmit={submit}
                    className="space-y-4"
                    style={{
                        background: surface,
                        border: `1px solid ${border}`,
                        padding: "20px",
                    }}
                >
                    <div>
                        <label style={labelStyle}>Whose farm</label>

                        {chosenFarmer ? (
                            <input
                                value={`${chosenFarmer.name} · ${chosenFarmer.phone}`}
                                readOnly
                                style={readOnlyStyle}
                            />
                        ) : pickedInForm ? (
                            <div className="flex flex-wrap items-center gap-4">
                                <p
                                    style={{
                                        color: text,
                                        fontSize: "18px",
                                        fontWeight: 600,
                                    }}
                                >
                                    {pickedInForm.name} · {pickedInForm.phone}
                                </p>
                                <button
                                    type="button"
                                    onClick={() =>
                                        form.setData("farmer_uuid", "")
                                    }
                                    style={linkStyle}
                                >
                                    Choose someone else
                                </button>
                            </div>
                        ) : (
                            <div>
                                <input
                                    value={farmerSearch}
                                    onChange={(event) =>
                                        setFarmerSearch(event.target.value)
                                    }
                                    placeholder="Type a name or phone number"
                                    style={fieldStyle}
                                />

                                {farmerSearch.trim() !== "" && (
                                    <div
                                        style={{
                                            border: `1px solid ${inputBorder}`,
                                            borderTop: "none",
                                        }}
                                    >
                                        {searchResults.length === 0 && (
                                            <p
                                                style={{
                                                    color: textSecondary,
                                                    fontSize: "16px",
                                                    padding: "10px",
                                                }}
                                            >
                                                Nobody matches that.
                                            </p>
                                        )}

                                        {searchResults.map((farmer) => (
                                            <button
                                                key={farmer.uuid}
                                                type="button"
                                                onClick={() =>
                                                    chooseFarmer(farmer)
                                                }
                                                style={resultStyle}
                                            >
                                                {farmer.name}
                                                <span
                                                    style={{
                                                        color: textSecondary,
                                                        marginLeft: "8px",
                                                    }}
                                                >
                                                    {farmer.phone}
                                                </span>
                                            </button>
                                        ))}
                                    </div>
                                )}
                            </div>
                        )}

                        {(form.errors.farmer_uuid || errors.farmer_uuid) && (
                            <p style={errorStyle}>
                                {form.errors.farmer_uuid || errors.farmer_uuid}
                            </p>
                        )}
                    </div>

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
                        </div>
                    </div>

                    <button
                        type="submit"
                        disabled={form.processing}
                        style={{
                            ...buttonStyle,
                            opacity: form.processing ? 0.7 : 1,
                        }}
                    >
                        Add unit
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
                                Unit
                            </th>
                            <th className="text-left px-4 py-3" style={thStyle}>
                                Farmer
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
                            <th className="text-left px-4 py-3" style={thStyle}>
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {navLoading && (
                            <TableSkeletonRows rows={5} columns={columnCount} />
                        )}

                        {!navLoading && units.data.length === 0 && (
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

                        {!navLoading &&
                            units.data.map((unit) => (
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
                                        {unit.farmer}
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
                                    <td className="px-4 py-3">
                                        <button
                                            onClick={() =>
                                                router.visit(
                                                    `${basePath.replace("/farm-units", "/farmers")}/${unit.farmer_uuid}/units/${unit.id}/stocks`,
                                                )
                                            }
                                            style={linkStyle}
                                        >
                                            What is in it
                                        </button>
                                    </td>
                                </tr>
                            ))}
                    </tbody>
                </table>
            </div>

            <div className="flex flex-wrap gap-2">
                {units.links.map((link, index) => (
                    <button
                        key={index}
                        disabled={!link.url}
                        onClick={() => link.url && router.visit(link.url)}
                        style={{
                            padding: "6px 12px",
                            fontSize: "16px",
                            border: `1px solid ${border}`,
                            background: link.active ? "#1D9E75" : surface,
                            color: link.active ? "#FFFFFF" : text,
                            cursor: link.url ? "pointer" : "not-allowed",
                            opacity: link.url ? 1 : 0.5,
                        }}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ))}
            </div>
        </div>
    );
}
