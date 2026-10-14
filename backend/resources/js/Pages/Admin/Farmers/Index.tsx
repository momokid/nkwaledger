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

interface GroupOption extends Option {
    community_id: number;
}

interface AgentOption {
    id: number;
    surname: string;
    first_name: string;
}

interface FarmerRow {
    id: string;
    name: string;
    phone: string | null;
    phone_verified: boolean;
    has_login: boolean;
    locked: boolean;
    community: string | null;
    agent: string | null;
    identity_verified: boolean;
    is_active: boolean;
}

interface PendingRow {
    id: number;
    name: string;
    phone: string | null;
    phone_verified: boolean;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props extends PageProps {
    farmers: { data: FarmerRow[]; links: PaginationLink[] };
    pending: PendingRow[];
    communities: Option[];
    farmerGroups: GroupOption[];
    farmTypes: Option[];
    agents: AgentOption[];
    layout: "admin" | "agent";
    basePath: string;
    permissions: {
        create: boolean;
        update: boolean;
        verify: boolean;
        assign: boolean;
        force_logout: boolean;
        lock: boolean;
        unlock: boolean;
    };
}

// the same page wears whichever frame the current route group belongs to
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
        <Frame layout={props.layout} title="Farmers">
            <IndexContent {...props} />
        </Frame>
    );
}

type ContentProps = Pick<
    Props,
    | "farmers"
    | "pending"
    | "communities"
    | "farmerGroups"
    | "farmTypes"
    | "agents"
    | "basePath"
    | "permissions"
>;

function IndexContent({
    farmers,
    pending,
    communities,
    farmerGroups,
    farmTypes,
    agents,
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
    const pendingBg = dark ? "rgba(180,83,9,0.15)" : "#FEF6E7";
    const headerText = "#1D9E75";

    const [showForm, setShowForm] = useState(false);
    const [navLoading, setNavLoading] = useState(false);
    const [forcing, setForcing] = useState<string | null>(null);
    const [signedOut, setSignedOut] = useState<string[]>([]);
    const [confirming, setConfirming] = useState<FarmerRow | null>(null);
    const [rowErrors, setRowErrors] = useState<Record<string, string>>({});
    const [lockState, setLockState] = useState<Record<string, boolean>>({});
    const [lockNotes, setLockNotes] = useState<Record<string, string>>({});
    const [lockConfirm, setLockConfirm] = useState<{
        farmer: FarmerRow;
        lock: boolean;
    } | null>(null);

    useEffect(() => {
        const start = router.on("start", () => setNavLoading(true));
        const finish = router.on("finish", () => setNavLoading(false));
        return () => {
            start();
            finish();
        };
    }, []);

    const form = useForm({
        surname: "",
        first_name: "",
        other_name: "",
        phone: "",
        gender: "",
        date_of_birth: "",
        home_address: "",
        community_id: "",
        farmer_group_id: "",
        assigned_agent_id: "",
        farm_type_ids: [] as number[],
    });

    // a group belongs to one community, so the list only makes sense once a community is chosen
    const groupOptions = useMemo(
        () =>
            farmerGroups.filter(
                (group) =>
                    String(group.community_id) === form.data.community_id,
            ),
        [farmerGroups, form.data.community_id],
    );

    const toggleFarmType = (id: number) => {
        const chosen = form.data.farm_type_ids.includes(id)
            ? form.data.farm_type_ids.filter((value) => value !== id)
            : [...form.data.farm_type_ids, id];

        form.setData("farm_type_ids", chosen);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(basePath, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setShowForm(false);
            },
        });
    };

    const thStyle = { color: headerText, fontWeight: 700 };
    const labelStyle = {
        color: text,
        fontSize: "1rem",
        fontWeight: 600,
        display: "block",
        marginBottom: "4px",
    };
    const fieldStyle = {
        width: "100%",
        padding: "10px",
        fontSize: "1.0625rem",
        background: inputBg,
        color: text,
        border: `1px solid ${inputBorder}`,
    };
    const errorStyle = { color: "#DC2626", fontSize: "0.9375rem", marginTop: "4px" };
    const buttonStyle = {
        background: "#1D9E75",
        color: "#FFFFFF",
        border: "none",
        padding: "10px 20px",
        fontSize: "1.0625rem",
        fontWeight: 600,
        cursor: "pointer",
    };

    const showActions =
        permissions.update ||
        permissions.force_logout ||
        permissions.lock ||
        permissions.unlock;
    const isLocked = (farmer: FarmerRow) =>
        lockState[farmer.id] ?? farmer.locked;
    const columnCount = showActions ? 7 : 6;

    // a background request: the row turns into placeholders while it runs, then says what happened
    const forceLogout = async (farmer: FarmerRow) => {
        setConfirming(null);
        setForcing(farmer.id);
        setRowErrors(({ [farmer.id]: _cleared, ...rest }) => rest);

        try {
            await window.axios.post(
                route("admin.farmers.force-logout", farmer.id),
            );
            setSignedOut((done) => [...done, farmer.id]);
        } catch (error) {
            const message = (
                error as { response?: { data?: { message?: string } } }
            ).response?.data?.message;

            if (message) {
                setRowErrors((all) => ({ ...all, [farmer.id]: message }));
            }
        } finally {
            setForcing(null);
        }
    };

    // same pattern as force logout: the row is a placeholder while it runs, then shows the new state
    const changeLock = async (farmer: FarmerRow, lock: boolean) => {
        setLockConfirm(null);
        setForcing(farmer.id);
        setRowErrors(({ [farmer.id]: _cleared, ...rest }) => rest);
        setLockNotes(({ [farmer.id]: _cleared, ...rest }) => rest);

        try {
            await window.axios.post(
                route(
                    lock ? "admin.farmers.lock" : "admin.farmers.unlock",
                    farmer.id,
                ),
            );
            setLockState((all) => ({ ...all, [farmer.id]: lock }));
            setLockNotes((all) => ({
                ...all,
                [farmer.id]: lock ? "Account locked." : "Account unlocked.",
            }));
        } catch (error) {
            const message = (
                error as { response?: { data?: { message?: string } } }
            ).response?.data?.message;

            if (message) {
                setRowErrors((all) => ({ ...all, [farmer.id]: message }));
            }
        } finally {
            setForcing(null);
        }
    };

    return (
        <div className="space-y-6">
            {pending.length > 0 && permissions.create && (
                <div
                    style={{
                        background: surface,
                        border: `1px solid ${border}`,
                    }}
                >
                    <div
                        style={{ background: pendingBg, padding: "12px 16px" }}
                    >
                        <p
                            style={{
                                color: text,
                                fontSize: "1.125rem",
                                fontWeight: 700,
                            }}
                        >
                            Waiting for a farm profile
                        </p>
                        <p style={{ color: textSecondary, fontSize: "0.9375rem" }}>
                            These farmers signed up on their own. They cannot
                            record anything until their profile is filled in.
                        </p>
                    </div>

                    <table className="min-w-full" style={{ fontSize: "1.125rem" }}>
                        <tbody>
                            {pending.map((row) => (
                                <tr
                                    key={row.id}
                                    style={{ borderTop: `1px solid ${border}` }}
                                >
                                    <td
                                        className="px-4 py-3"
                                        style={{ color: text }}
                                    >
                                        {row.name}
                                    </td>
                                    <td
                                        className="px-4 py-3"
                                        style={{ color: text }}
                                    >
                                        {row.phone}
                                        {!row.phone_verified && (
                                            <span
                                                style={{
                                                    color: "#B45309",
                                                    fontSize: "0.9375rem",
                                                    display: "block",
                                                }}
                                            >
                                                Not confirmed yet
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <button
                                            onClick={() =>
                                                router.visit(
                                                    `${basePath}/pending/${row.id}`,
                                                )
                                            }
                                            style={{
                                                background: "none",
                                                border: "none",
                                                color: headerText,
                                                fontSize: "1.0625rem",
                                                fontWeight: 600,
                                                cursor: "pointer",
                                                padding: 0,
                                            }}
                                        >
                                            Fill in profile
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {permissions.create && (
                <button
                    onClick={() => setShowForm(!showForm)}
                    style={buttonStyle}
                >
                    {showForm ? "Close" : "Register a farmer"}
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
                    <div className="grid gap-4 md:grid-cols-3">
                        <div>
                            <label style={labelStyle}>Surname</label>
                            <input
                                value={form.data.surname}
                                onChange={(event) =>
                                    form.setData("surname", event.target.value)
                                }
                                style={fieldStyle}
                            />
                            {(form.errors.surname || errors.surname) && (
                                <p style={errorStyle}>
                                    {form.errors.surname || errors.surname}
                                </p>
                            )}
                        </div>

                        <div>
                            <label style={labelStyle}>First name</label>
                            <input
                                value={form.data.first_name}
                                onChange={(event) =>
                                    form.setData(
                                        "first_name",
                                        event.target.value,
                                    )
                                }
                                style={fieldStyle}
                            />
                            {(form.errors.first_name || errors.first_name) && (
                                <p style={errorStyle}>
                                    {form.errors.first_name ||
                                        errors.first_name}
                                </p>
                            )}
                        </div>

                        <div>
                            <label style={labelStyle}>Other name</label>
                            <input
                                value={form.data.other_name}
                                onChange={(event) =>
                                    form.setData(
                                        "other_name",
                                        event.target.value,
                                    )
                                }
                                style={fieldStyle}
                            />
                            {(form.errors.other_name || errors.other_name) && (
                                <p style={errorStyle}>
                                    {form.errors.other_name ||
                                        errors.other_name}
                                </p>
                            )}
                        </div>

                        <div>
                            <label style={labelStyle}>Phone</label>
                            <input
                                value={form.data.phone}
                                onChange={(event) =>
                                    form.setData("phone", event.target.value)
                                }
                                placeholder="0244445566"
                                style={fieldStyle}
                            />
                            {(form.errors.phone || errors.phone) && (
                                <p style={errorStyle}>
                                    {form.errors.phone || errors.phone}
                                </p>
                            )}
                        </div>

                        <div>
                            <label style={labelStyle}>Gender</label>
                            <select
                                value={form.data.gender}
                                onChange={(event) =>
                                    form.setData("gender", event.target.value)
                                }
                                style={fieldStyle}
                            >
                                <option value="">Not stated</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                            </select>
                            {(form.errors.gender || errors.gender) && (
                                <p style={errorStyle}>
                                    {form.errors.gender || errors.gender}
                                </p>
                            )}
                        </div>

                        <div>
                            <label style={labelStyle}>Date of birth</label>
                            <input
                                type="date"
                                value={form.data.date_of_birth}
                                onChange={(event) =>
                                    form.setData(
                                        "date_of_birth",
                                        event.target.value,
                                    )
                                }
                                style={fieldStyle}
                            />
                            {(form.errors.date_of_birth ||
                                errors.date_of_birth) && (
                                <p style={errorStyle}>
                                    {form.errors.date_of_birth ||
                                        errors.date_of_birth}
                                </p>
                            )}
                        </div>

                        <div>
                            <label style={labelStyle}>Home address</label>
                            <input
                                value={form.data.home_address}
                                onChange={(event) =>
                                    form.setData(
                                        "home_address",
                                        event.target.value,
                                    )
                                }
                                placeholder="Where the farmer lives"
                                style={fieldStyle}
                            />
                            {(form.errors.home_address ||
                                errors.home_address) && (
                                <p style={errorStyle}>
                                    {form.errors.home_address ||
                                        errors.home_address}
                                </p>
                            )}
                        </div>

                        <div>
                            <label style={labelStyle}>
                                Community the farm is in
                            </label>
                            <select
                                value={form.data.community_id}
                                onChange={(event) => {
                                    form.setData(
                                        "community_id",
                                        event.target.value,
                                    );
                                    form.setData("farmer_group_id", "");
                                }}
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
                            <label style={labelStyle}>Farmer group</label>
                            <select
                                value={form.data.farmer_group_id}
                                onChange={(event) =>
                                    form.setData(
                                        "farmer_group_id",
                                        event.target.value,
                                    )
                                }
                                disabled={!form.data.community_id}
                                style={fieldStyle}
                            >
                                <option value="">
                                    {form.data.community_id
                                        ? "None"
                                        : "Choose a community first"}
                                </option>
                                {groupOptions.map((group) => (
                                    <option key={group.id} value={group.id}>
                                        {group.name}
                                    </option>
                                ))}
                            </select>
                            {(form.errors.farmer_group_id ||
                                errors.farmer_group_id) && (
                                <p style={errorStyle}>
                                    {form.errors.farmer_group_id ||
                                        errors.farmer_group_id}
                                </p>
                            )}
                        </div>

                        {permissions.assign && (
                            <div>
                                <label style={labelStyle}>Agent</label>
                                <select
                                    value={form.data.assigned_agent_id}
                                    onChange={(event) =>
                                        form.setData(
                                            "assigned_agent_id",
                                            event.target.value,
                                        )
                                    }
                                    style={fieldStyle}
                                >
                                    <option value="">Not assigned yet</option>
                                    {agents.map((agent) => (
                                        <option key={agent.id} value={agent.id}>
                                            {agent.surname} {agent.first_name}
                                        </option>
                                    ))}
                                </select>
                                {(form.errors.assigned_agent_id ||
                                    errors.assigned_agent_id) && (
                                    <p style={errorStyle}>
                                        {form.errors.assigned_agent_id ||
                                            errors.assigned_agent_id}
                                    </p>
                                )}
                            </div>
                        )}
                    </div>

                    <div>
                        <label style={labelStyle}>
                            What does this farmer produce?
                        </label>
                        <div className="flex flex-wrap gap-3">
                            {farmTypes.map((farmType) => (
                                <label
                                    key={farmType.id}
                                    style={{
                                        color: text,
                                        fontSize: "1rem",
                                        border: `1px solid ${inputBorder}`,
                                        padding: "8px 12px",
                                        cursor: "pointer",
                                    }}
                                >
                                    <input
                                        type="checkbox"
                                        checked={form.data.farm_type_ids.includes(
                                            farmType.id,
                                        )}
                                        onChange={() =>
                                            toggleFarmType(farmType.id)
                                        }
                                        style={{ marginRight: "8px" }}
                                    />
                                    {farmType.name}
                                </label>
                            ))}
                        </div>
                        {(form.errors.farm_type_ids ||
                            errors.farm_type_ids) && (
                            <p style={errorStyle}>
                                {form.errors.farm_type_ids ||
                                    errors.farm_type_ids}
                            </p>
                        )}
                    </div>

                    <p style={{ color: textSecondary, fontSize: "0.9375rem" }}>
                        We will send a code to this phone so the farmer can
                        confirm the number.
                    </p>

                    <button
                        type="submit"
                        disabled={form.processing}
                        style={{
                            ...buttonStyle,
                            opacity: form.processing ? 0.7 : 1,
                        }}
                    >
                        Register farmer
                    </button>
                </form>
            )}

            <div
                className="overflow-x-auto"
                style={{ background: surface, border: `1px solid ${border}` }}
            >
                <table className="min-w-full" style={{ fontSize: "1.125rem" }}>
                    <thead>
                        <tr style={{ background: headerBg }}>
                            <th className="text-left px-4 py-3" style={thStyle}>
                                Name
                            </th>
                            <th className="text-left px-4 py-3" style={thStyle}>
                                Phone
                            </th>
                            <th className="text-left px-4 py-3" style={thStyle}>
                                Community
                            </th>
                            <th className="text-left px-4 py-3" style={thStyle}>
                                Agent
                            </th>
                            <th className="text-left px-4 py-3" style={thStyle}>
                                Document
                            </th>
                            <th className="text-left px-4 py-3" style={thStyle}>
                                Status
                            </th>
                            {showActions && (
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
                        {navLoading && (
                            <TableSkeletonRows rows={5} columns={columnCount} />
                        )}

                        {!navLoading && farmers.data.length === 0 && (
                            <tr>
                                <td
                                    colSpan={columnCount}
                                    className="px-4 py-6 text-center"
                                    style={{ color: textSecondary }}
                                >
                                    No farmers yet. Register the first one
                                    above.
                                </td>
                            </tr>
                        )}

                        {!navLoading &&
                            farmers.data.map((farmer) =>
                                forcing === farmer.id ? (
                                    <TableSkeletonRows
                                        key={farmer.id}
                                        rows={1}
                                        columns={columnCount}
                                    />
                                ) : (
                                <tr
                                    key={farmer.id}
                                    style={{ borderTop: `1px solid ${border}` }}
                                >
                                    <td
                                        className="px-4 py-3"
                                        style={{ color: text }}
                                    >
                                        {farmer.name}
                                    </td>
                                    <td
                                        className="px-4 py-3"
                                        style={{ color: text }}
                                    >
                                        {farmer.phone}
                                        {!farmer.phone_verified && (
                                            <span
                                                style={{
                                                    color: "#B45309",
                                                    fontSize: "0.9375rem",
                                                    display: "block",
                                                }}
                                            >
                                                Not confirmed yet
                                            </span>
                                        )}
                                    </td>
                                    <td
                                        className="px-4 py-3"
                                        style={{ color: text }}
                                    >
                                        {farmer.community}
                                    </td>
                                    <td
                                        className="px-4 py-3"
                                        style={{
                                            color: farmer.agent
                                                ? text
                                                : "#B45309",
                                        }}
                                    >
                                        {farmer.agent ?? "Not assigned"}
                                    </td>
                                    <td
                                        className="px-4 py-3"
                                        style={{
                                            color: farmer.identity_verified
                                                ? headerText
                                                : textSecondary,
                                        }}
                                    >
                                        {farmer.identity_verified
                                            ? "Verified"
                                            : "Not verified"}
                                    </td>
                                    <td
                                        className="px-4 py-3"
                                        style={{
                                            color: farmer.is_active
                                                ? text
                                                : "#B45309",
                                        }}
                                    >
                                        {farmer.is_active
                                            ? "Active"
                                            : "On hold"}
                                        {isLocked(farmer) && (
                                            <span
                                                style={{
                                                    color: "#B91C1C",
                                                    fontWeight: 600,
                                                    marginLeft: "8px",
                                                }}
                                            >
                                                Locked
                                            </span>
                                        )}
                                    </td>
                                    {showActions && (
                                        <td className="px-4 py-3">
                                            <div
                                                style={{
                                                    display: "flex",
                                                    gap: "14px",
                                                    flexWrap: "wrap",
                                                }}
                                            >
                                                {permissions.update && (
                                                    <button
                                                        onClick={() =>
                                                            router.visit(
                                                                `${basePath}/${farmer.id}`,
                                                            )
                                                        }
                                                        style={{
                                                            background: "none",
                                                            border: "none",
                                                            color: headerText,
                                                            fontSize:
                                                                "1.0625rem",
                                                            fontWeight: 600,
                                                            cursor: "pointer",
                                                            padding: 0,
                                                        }}
                                                    >
                                                        Open
                                                    </button>
                                                )}

                                                {permissions.force_logout &&
                                                    farmer.has_login &&
                                                    !isLocked(farmer) &&
                                                    (signedOut.includes(
                                                        farmer.id,
                                                    ) ? (
                                                        <span
                                                            style={{
                                                                color: headerText,
                                                                fontSize:
                                                                    "1.0625rem",
                                                                fontWeight: 600,
                                                            }}
                                                        >
                                                            User signed out.
                                                        </span>
                                                    ) : (
                                                        <button
                                                            onClick={() =>
                                                                setConfirming(
                                                                    farmer,
                                                                )
                                                            }
                                                            style={{
                                                                background:
                                                                    "none",
                                                                border: "none",
                                                                color: "#DC2626",
                                                                fontSize:
                                                                    "1.0625rem",
                                                                fontWeight: 600,
                                                                cursor: "pointer",
                                                                padding: 0,
                                                            }}
                                                        >
                                                            Force logout
                                                        </button>
                                                    ))}

                                                {farmer.has_login &&
                                                    (isLocked(farmer)
                                                        ? permissions.unlock
                                                        : permissions.lock) && (
                                                        <button
                                                            onClick={() =>
                                                                setLockConfirm({
                                                                    farmer,
                                                                    lock: !isLocked(
                                                                        farmer,
                                                                    ),
                                                                })
                                                            }
                                                            style={{
                                                                background:
                                                                    "none",
                                                                border: "none",
                                                                color: isLocked(
                                                                    farmer,
                                                                )
                                                                    ? headerText
                                                                    : "#DC2626",
                                                                fontSize:
                                                                    "1.0625rem",
                                                                fontWeight: 600,
                                                                cursor: "pointer",
                                                                padding: 0,
                                                            }}
                                                        >
                                                            {isLocked(farmer)
                                                                ? "Unlock account"
                                                                : "Lock account"}
                                                        </button>
                                                    )}

                                                {lockNotes[farmer.id] && (
                                                    <span
                                                        style={{
                                                            color: headerText,
                                                            fontSize:
                                                                "1.0625rem",
                                                            fontWeight: 600,
                                                        }}
                                                    >
                                                        {lockNotes[farmer.id]}
                                                    </span>
                                                )}

                                                {rowErrors[farmer.id] && (
                                                    <span
                                                        role="alert"
                                                        style={{
                                                            color: "#DC2626",
                                                            fontSize:
                                                                "1.0625rem",
                                                        }}
                                                    >
                                                        {rowErrors[farmer.id]}
                                                    </span>
                                                )}
                                            </div>
                                        </td>
                                    )}
                                </tr>
                                ),
                            )}
                    </tbody>
                </table>
            </div>

            <div className="flex flex-wrap gap-2">
                {farmers.links.map((link, index) => (
                    <button
                        key={index}
                        disabled={!link.url}
                        onClick={() => link.url && router.visit(link.url)}
                        style={{
                            padding: "6px 12px",
                            fontSize: "1rem",
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

            {confirming && (
                <div
                    onClick={() => setConfirming(null)}
                    style={{
                        position: "fixed",
                        inset: 0,
                        background: "rgba(17,24,39,0.55)",
                        display: "flex",
                        alignItems: "flex-start",
                        justifyContent: "center",
                        padding: "40px 20px",
                        zIndex: 70,
                    }}
                >
                    <div
                        role="dialog"
                        onClick={(event) => event.stopPropagation()}
                        style={{
                            background: surface,
                            border: `1px solid ${border}`,
                            width: "100%",
                            maxWidth: "420px",
                            padding: "28px",
                        }}
                    >
                        <p
                            style={{
                                margin: "0 0 20px",
                                fontSize: "1.0625rem",
                                color: text,
                            }}
                        >
                            Sign this user out of all devices now?
                        </p>
                        <div style={{ display: "flex", gap: "12px" }}>
                            <button
                                onClick={() => forceLogout(confirming)}
                                style={{
                                    background: "#DC2626",
                                    color: "#FFFFFF",
                                    border: "none",
                                    padding: "12px 20px",
                                    fontWeight: 600,
                                    fontSize: "1.0625rem",
                                    cursor: "pointer",
                                    fontFamily: "inherit",
                                }}
                            >
                                Sign out
                            </button>
                            <button
                                onClick={() => setConfirming(null)}
                                style={{
                                    background: "transparent",
                                    color: text,
                                    border: `1px solid ${inputBorder}`,
                                    padding: "12px 20px",
                                    fontWeight: 600,
                                    fontSize: "1.0625rem",
                                    cursor: "pointer",
                                    fontFamily: "inherit",
                                }}
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {lockConfirm && (
                <div
                    onClick={() => setLockConfirm(null)}
                    style={{
                        position: "fixed",
                        inset: 0,
                        background: "rgba(17,24,39,0.55)",
                        display: "flex",
                        alignItems: "flex-start",
                        justifyContent: "center",
                        padding: "40px 20px",
                        zIndex: 70,
                    }}
                >
                    <div
                        role="dialog"
                        onClick={(event) => event.stopPropagation()}
                        style={{
                            background: surface,
                            border: `1px solid ${border}`,
                            width: "100%",
                            maxWidth: "420px",
                            padding: "28px",
                        }}
                    >
                        <p
                            style={{
                                margin: "0 0 20px",
                                fontSize: "1.0625rem",
                                color: text,
                            }}
                        >
                            {lockConfirm.lock
                                ? "Lock this account? The farmer will be signed out and cannot sign in until you unlock it."
                                : "Unlock this account?"}
                        </p>
                        <div style={{ display: "flex", gap: "12px" }}>
                            <button
                                onClick={() =>
                                    changeLock(
                                        lockConfirm.farmer,
                                        lockConfirm.lock,
                                    )
                                }
                                style={{
                                    background: lockConfirm.lock
                                        ? "#DC2626"
                                        : "#1D9E75",
                                    color: "#FFFFFF",
                                    border: "none",
                                    padding: "12px 20px",
                                    fontWeight: 600,
                                    fontSize: "1.0625rem",
                                    cursor: "pointer",
                                    fontFamily: "inherit",
                                }}
                            >
                                {lockConfirm.lock ? "Lock" : "Unlock"}
                            </button>
                            <button
                                onClick={() => setLockConfirm(null)}
                                style={{
                                    background: "transparent",
                                    color: text,
                                    border: `1px solid ${inputBorder}`,
                                    padding: "12px 20px",
                                    fontWeight: 600,
                                    fontSize: "1.0625rem",
                                    cursor: "pointer",
                                    fontFamily: "inherit",
                                }}
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
