import AdminLayout from "@/Layouts/AdminLayout";
import { useTheme } from "@/Layouts/AuthenticatedLayout";
import { router, useForm, usePage } from "@inertiajs/react";
import { PageProps } from "@/types";
import { FormEvent, useEffect, useState } from "react";

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

interface Account {
    id: number;
    surname: string;
    first_name: string;
    other_name: string | null;
    phone: string | null;
    phone_verified: boolean;
}

interface Props extends PageProps {
    account: Account;
    communities: Option[];
    farmerGroups: GroupOption[];
    farmTypes: Option[];
    agents: AgentOption[];
    permissions: { assign: boolean };
}

export default function Complete(props: Props) {
    return (
        <AdminLayout title="Complete a farmer profile">
            <CompleteContent {...props} />
        </AdminLayout>
    );
}

type ContentProps = Pick<
    Props,
    | "account"
    | "communities"
    | "farmerGroups"
    | "farmTypes"
    | "agents"
    | "permissions"
>;

function CompleteContent({
    account,
    communities,
    farmerGroups,
    farmTypes,
    agents,
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
    const headerText = "#1D9E75";

    const form = useForm({
        gender: "",
        date_of_birth: "",
        home_address: "",
        community_id: "",
        farmer_group_id: "",
        assigned_agent_id: "",
        farm_type_ids: [] as number[],
    });

    const [groupOptions, setGroupOptions] = useState<Option[]>([]);

    // a group belongs to one community, so the list only makes sense once a community is chosen
    useEffect(() => {
        if (!form.data.community_id) {
            setGroupOptions([]);
            return;
        }

        setGroupOptions(
            farmerGroups.filter(
                (group) =>
                    String(group.community_id) === form.data.community_id,
            ),
        );
    }, [form.data.community_id, farmerGroups]);

    const toggleFarmType = (id: number) => {
        const chosen = form.data.farm_type_ids.includes(id)
            ? form.data.farm_type_ids.filter((value) => value !== id)
            : [...form.data.farm_type_ids, id];

        form.setData("farm_type_ids", chosen);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(`/admin/farmers/pending/${account.id}`);
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
    const readOnlyStyle = {
        ...fieldStyle,
        background: readOnlyBg,
        color: textSecondary,
    };
    const errorStyle = { color: "#DC2626", fontSize: "15px", marginTop: "4px" };
    const cardStyle = {
        background: surface,
        border: `1px solid ${border}`,
        padding: "20px",
    };

    return (
        <div className="space-y-6">
            <button
                onClick={() => router.visit("/admin/farmers")}
                style={{
                    background: "none",
                    border: "none",
                    color: headerText,
                    fontSize: "17px",
                    fontWeight: 600,
                    cursor: "pointer",
                    padding: 0,
                }}
            >
                Back to farmers
            </button>

            <div style={cardStyle} className="space-y-3">
                <p style={{ color: text, fontSize: "19px", fontWeight: 700 }}>
                    The account
                </p>
                <p style={{ color: textSecondary, fontSize: "16px" }}>
                    This farmer signed up already. These details belong to their
                    account, so they cannot be changed here.
                </p>

                <div className="grid gap-4 md:grid-cols-2">
                    <div>
                        <label style={labelStyle}>Surname</label>
                        <input
                            value={account.surname}
                            readOnly
                            style={readOnlyStyle}
                        />
                    </div>

                    <div>
                        <label style={labelStyle}>First name</label>
                        <input
                            value={account.first_name}
                            readOnly
                            style={readOnlyStyle}
                        />
                    </div>

                    <div>
                        <label style={labelStyle}>Other name</label>
                        <input
                            value={account.other_name ?? ""}
                            readOnly
                            style={readOnlyStyle}
                        />
                    </div>

                    <div>
                        <label style={labelStyle}>Phone</label>
                        <input
                            value={account.phone ?? ""}
                            readOnly
                            style={readOnlyStyle}
                        />
                        {!account.phone_verified && (
                            <p
                                style={{
                                    color: "#B45309",
                                    fontSize: "15px",
                                    marginTop: "4px",
                                }}
                            >
                                This number is not confirmed yet.
                            </p>
                        )}
                    </div>
                </div>
            </div>

            <form onSubmit={submit} style={cardStyle} className="space-y-4">
                <p style={{ color: text, fontSize: "19px", fontWeight: 700 }}>
                    Farm profile
                </p>

                <div className="grid gap-4 md:grid-cols-2">
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
                                form.setData("home_address", event.target.value)
                            }
                            placeholder="Where the farmer lives"
                            style={fieldStyle}
                        />
                        {(form.errors.home_address || errors.home_address) && (
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
                                <option key={community.id} value={community.id}>
                                    {community.name}
                                </option>
                            ))}
                        </select>
                        {(form.errors.community_id || errors.community_id) && (
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
                                    fontSize: "16px",
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
                                    onChange={() => toggleFarmType(farmType.id)}
                                    style={{ marginRight: "8px" }}
                                />
                                {farmType.name}
                            </label>
                        ))}
                    </div>
                    {(form.errors.farm_type_ids || errors.farm_type_ids) && (
                        <p style={errorStyle}>
                            {form.errors.farm_type_ids || errors.farm_type_ids}
                        </p>
                    )}
                </div>

                <button
                    type="submit"
                    disabled={form.processing}
                    style={{
                        background: "#1D9E75",
                        color: "#FFFFFF",
                        border: "none",
                        padding: "10px 20px",
                        fontSize: "17px",
                        fontWeight: 600,
                        cursor: form.processing ? "not-allowed" : "pointer",
                        opacity: form.processing ? 0.7 : 1,
                    }}
                >
                    Save profile
                </button>
            </form>
        </div>
    );
}
