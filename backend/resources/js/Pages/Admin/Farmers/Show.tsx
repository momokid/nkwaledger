import AdminLayout from "@/Layouts/AdminLayout";
import { useTheme } from "@/Layouts/AuthenticatedLayout";
import { router, useForm, usePage } from "@inertiajs/react";
import { PageProps } from "@/types";
import { FormEvent } from "react";

interface Option {
    id: number;
    name: string;
}

interface FarmerData {
    id: number;
    name: string;
    phone: string | null;
    phone_verified: boolean;
    gender: string | null;
    date_of_birth: string | null;
    community_id: number;
    community: string | null;
    farmer_group_id: number | null;
    farm_type_ids: number[];
    farm_types: string[];
    identity_type: string | null;
    identity_type_label: string | null;
    has_identity: boolean;
    identity_verified_at: string | null;
    identity_verified_by: string | null;
    registered_by: string | null;
    is_active: boolean;
}

interface Props extends PageProps {
    farmer: FarmerData;
    communities: Option[];
    farmerGroups: Option[];
    farmTypes: Option[];
    permissions: { update: boolean; verify: boolean };
}

export default function Show(props: Props) {
    return (
        <AdminLayout title={props.farmer.name}>
            <ShowContent {...props} />
        </AdminLayout>
    );
}

type ContentProps = Pick<
    Props,
    "farmer" | "communities" | "farmerGroups" | "farmTypes" | "permissions"
>;

function ShowContent({
    farmer,
    communities,
    farmerGroups,
    farmTypes,
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
    const headerText = "#1D9E75";

    const details = useForm({
        gender: farmer.gender ?? "",
        date_of_birth: farmer.date_of_birth
            ? farmer.date_of_birth.slice(0, 10)
            : "",
        community_id: String(farmer.community_id),
        farmer_group_id: farmer.farmer_group_id
            ? String(farmer.farmer_group_id)
            : "",
        farm_type_ids: farmer.farm_type_ids,
        is_active: farmer.is_active,
    });

    const identity = useForm({
        identity_type: farmer.identity_type ?? "",
        identity_number: "",
    });

    const toggleFarmType = (id: number) => {
        const chosen = details.data.farm_type_ids.includes(id)
            ? details.data.farm_type_ids.filter((value) => value !== id)
            : [...details.data.farm_type_ids, id];

        details.setData("farm_type_ids", chosen);
    };

    const saveDetails = (event: FormEvent) => {
        event.preventDefault();
        details.put(`/admin/farmers/${farmer.id}`, { preserveScroll: true });
    };

    const saveIdentity = (event: FormEvent) => {
        event.preventDefault();
        identity.post(`/admin/farmers/${farmer.id}/identity`, {
            preserveScroll: true,
            onSuccess: () => identity.setData("identity_number", ""),
        });
    };

    const verify = () => {
        router.patch(
            `/admin/farmers/${farmer.id}/identity/verify`,
            {},
            { preserveScroll: true },
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

    // the person who registered a farmer may not vouch for the document, so the button is not offered to them
    const canVerifyNow =
        permissions.verify &&
        farmer.has_identity &&
        !farmer.identity_verified_at;

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

            <div style={cardStyle} className="space-y-2">
                <p style={{ color: text, fontSize: "22px", fontWeight: 700 }}>
                    {farmer.name}
                </p>
                <p style={{ color: text, fontSize: "17px" }}>
                    {farmer.phone}
                    {!farmer.phone_verified && (
                        <span style={{ color: "#B45309" }}>
                            {" "}
                            — number not confirmed yet
                        </span>
                    )}
                </p>
                <p style={{ color: textSecondary, fontSize: "16px" }}>
                    {farmer.community} · Registered by{" "}
                    {farmer.registered_by ?? "themselves"}
                </p>
                <p style={{ color: textSecondary, fontSize: "16px" }}>
                    Produces: {farmer.farm_types.join(", ") || "not yet stated"}
                </p>
            </div>

            <form
                onSubmit={saveDetails}
                style={cardStyle}
                className="space-y-4"
            >
                <p style={{ color: text, fontSize: "19px", fontWeight: 700 }}>
                    Details
                </p>

                <div className="grid gap-4 md:grid-cols-2">
                    <div>
                        <label style={labelStyle}>Gender</label>
                        <select
                            value={details.data.gender}
                            onChange={(event) =>
                                details.setData("gender", event.target.value)
                            }
                            disabled={!permissions.update}
                            style={fieldStyle}
                        >
                            <option value="">Not stated</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                        </select>
                        {(details.errors.gender || errors.gender) && (
                            <p style={errorStyle}>
                                {details.errors.gender || errors.gender}
                            </p>
                        )}
                    </div>

                    <div>
                        <label style={labelStyle}>Date of birth</label>
                        <input
                            type="date"
                            value={details.data.date_of_birth}
                            onChange={(event) =>
                                details.setData(
                                    "date_of_birth",
                                    event.target.value,
                                )
                            }
                            disabled={!permissions.update}
                            style={fieldStyle}
                        />
                        {(details.errors.date_of_birth ||
                            errors.date_of_birth) && (
                            <p style={errorStyle}>
                                {details.errors.date_of_birth ||
                                    errors.date_of_birth}
                            </p>
                        )}
                    </div>

                    <div>
                        <label style={labelStyle}>Home community</label>
                        <select
                            value={details.data.community_id}
                            onChange={(event) =>
                                details.setData(
                                    "community_id",
                                    event.target.value,
                                )
                            }
                            disabled={!permissions.update}
                            style={fieldStyle}
                        >
                            {communities.map((community) => (
                                <option key={community.id} value={community.id}>
                                    {community.name}
                                </option>
                            ))}
                        </select>
                        {(details.errors.community_id ||
                            errors.community_id) && (
                            <p style={errorStyle}>
                                {details.errors.community_id ||
                                    errors.community_id}
                            </p>
                        )}
                    </div>

                    <div>
                        <label style={labelStyle}>Farmer group</label>
                        <select
                            value={details.data.farmer_group_id}
                            onChange={(event) =>
                                details.setData(
                                    "farmer_group_id",
                                    event.target.value,
                                )
                            }
                            disabled={!permissions.update}
                            style={fieldStyle}
                        >
                            <option value="">None</option>
                            {farmerGroups.map((group) => (
                                <option key={group.id} value={group.id}>
                                    {group.name}
                                </option>
                            ))}
                        </select>
                        {(details.errors.farmer_group_id ||
                            errors.farmer_group_id) && (
                            <p style={errorStyle}>
                                {details.errors.farmer_group_id ||
                                    errors.farmer_group_id}
                            </p>
                        )}
                    </div>
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
                                    checked={details.data.farm_type_ids.includes(
                                        farmType.id,
                                    )}
                                    onChange={() => toggleFarmType(farmType.id)}
                                    disabled={!permissions.update}
                                    style={{ marginRight: "8px" }}
                                />
                                {farmType.name}
                            </label>
                        ))}
                    </div>
                    {(details.errors.farm_type_ids || errors.farm_type_ids) && (
                        <p style={errorStyle}>
                            {details.errors.farm_type_ids ||
                                errors.farm_type_ids}
                        </p>
                    )}
                </div>

                <label
                    style={{ color: text, fontSize: "16px", cursor: "pointer" }}
                >
                    <input
                        type="checkbox"
                        checked={details.data.is_active}
                        onChange={(event) =>
                            details.setData("is_active", event.target.checked)
                        }
                        disabled={!permissions.update}
                        style={{ marginRight: "8px" }}
                    />
                    Active
                </label>

                {permissions.update && (
                    <button
                        type="submit"
                        disabled={details.processing}
                        style={{
                            ...buttonStyle,
                            opacity: details.processing ? 0.7 : 1,
                        }}
                    >
                        Save details
                    </button>
                )}
            </form>

            <div style={cardStyle} className="space-y-4">
                <p style={{ color: text, fontSize: "19px", fontWeight: 700 }}>
                    Identity document
                </p>

                {farmer.identity_verified_at ? (
                    <p style={{ color: headerText, fontSize: "17px" }}>
                        {farmer.identity_type_label} verified by{" "}
                        {farmer.identity_verified_by}
                    </p>
                ) : (
                    <p style={{ color: textSecondary, fontSize: "16px" }}>
                        {farmer.has_identity
                            ? "A document is on file and is waiting to be verified."
                            : "No document yet. The farmer can still record transactions, but credit reports need one."}
                    </p>
                )}

                {permissions.update && (
                    <form onSubmit={saveIdentity} className="space-y-4">
                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label style={labelStyle}>Document</label>
                                <select
                                    value={identity.data.identity_type}
                                    onChange={(event) =>
                                        identity.setData(
                                            "identity_type",
                                            event.target.value,
                                        )
                                    }
                                    style={fieldStyle}
                                >
                                    <option value="">Choose a document</option>
                                    <option value="ghana_card">
                                        Ghana Card
                                    </option>
                                    <option value="passport">Passport</option>
                                    <option value="voter_id">Voter ID</option>
                                </select>
                                {(identity.errors.identity_type ||
                                    errors.identity_type) && (
                                    <p style={errorStyle}>
                                        {identity.errors.identity_type ||
                                            errors.identity_type}
                                    </p>
                                )}
                            </div>

                            <div>
                                <label style={labelStyle}>Number</label>
                                <input
                                    value={identity.data.identity_number}
                                    onChange={(event) =>
                                        identity.setData(
                                            "identity_number",
                                            event.target.value,
                                        )
                                    }
                                    style={fieldStyle}
                                />
                                {(identity.errors.identity_number ||
                                    errors.identity_number) && (
                                    <p style={errorStyle}>
                                        {identity.errors.identity_number ||
                                            errors.identity_number}
                                    </p>
                                )}
                            </div>
                        </div>

                        <p style={{ color: textSecondary, fontSize: "15px" }}>
                            We store this number in a scrambled form, so it can
                            never be read back.
                        </p>

                        <button
                            type="submit"
                            disabled={identity.processing}
                            style={{
                                ...buttonStyle,
                                opacity: identity.processing ? 0.7 : 1,
                            }}
                        >
                            Save document
                        </button>
                    </form>
                )}

                {canVerifyNow && (
                    <button onClick={verify} style={buttonStyle}>
                        Verify this document
                    </button>
                )}
            </div>
        </div>
    );
}
