import AdminLayout from "@/Layouts/AdminLayout";
import { useTheme } from "@/Layouts/AuthenticatedLayout";
import { router, usePage } from "@inertiajs/react";
import { PageProps } from "@/types";
import { useState } from "react";
import TableSkeletonRows from "@/Components/Admin/TableSkeletonRows";
import QuickAddModal, { QuickAddItem } from "@/Components/Admin/QuickAddModal";

interface RefItem {
    id: number;
    name: string;
}

interface FarmerGroupData {
    id: number;
    name: string;
    description: string | null;
    is_shared_liability: boolean;
    is_active: boolean;
    group_type_id: number | null;
    region_id: number | null;
    district_id: number | null;
    community_id: number | null;
    group_type: RefItem | null;
    region: RefItem | null;
    district: RefItem | null;
    community: RefItem | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props extends PageProps {
    farmerGroups: {
        data: FarmerGroupData[];
        links: PaginationLink[];
    };
    groupTypes: RefItem[];
    regions: RefItem[];
    permissions: {
        create: boolean;
        update: boolean;
        delete: boolean;
    };
}

const emptyForm = {
    id: null as number | null,
    name: "",
    group_type_id: "",
    region_id: "",
    district_id: "",
    community_id: "",
    description: "",
    is_shared_liability: false,
    is_active: true,
};

export default function Index({
    farmerGroups,
    groupTypes,
    regions,
    permissions,
}: Props) {
    return (
        <AdminLayout title="Farmer Groups">
            <IndexContent
                farmerGroups={farmerGroups}
                groupTypes={groupTypes}
                regions={regions}
                permissions={permissions}
            />
        </AdminLayout>
    );
}

async function fetchScoped(url: string): Promise<QuickAddItem[]> {
    const response = await fetch(url, {
        headers: { Accept: "application/json" },
    });
    if (!response.ok) return [];
    return response.json();
}

function IndexContent({
    farmerGroups,
    groupTypes,
    regions,
    permissions,
}: Props) {
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
    const rowAlt = dark ? "#111827" : "#F9FAFB";

    const [form, setForm] = useState(emptyForm);
    const [submitting, setSubmitting] = useState(false);
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [submitError, setSubmitError] = useState<string | null>(null);

    const [districts, setDistricts] = useState<RefItem[]>([]);
    const [communities, setCommunities] = useState<RefItem[]>([]);
    const [districtsLoading, setDistrictsLoading] = useState(false);
    const [communitiesLoading, setCommunitiesLoading] = useState(false);

    const [navLoading, setNavLoading] = useState(false);
    const [deletingId, setDeletingId] = useState<number | null>(null);

    const [openModal, setOpenModal] = useState<
        null | "groupType" | "region" | "district" | "community"
    >(null);
    const [districtModalRegionId, setDistrictModalRegionId] = useState("");
    const [districtModalItems, setDistrictModalItems] = useState<
        QuickAddItem[] | null
    >(null);
    const [districtModalLoading, setDistrictModalLoading] = useState(false);
    const [communityModalDistrictId, setCommunityModalDistrictId] =
        useState("");
    const [communityModalItems, setCommunityModalItems] = useState<
        QuickAddItem[] | null
    >(null);
    const [communityModalLoading, setCommunityModalLoading] = useState(false);

    const inputStyle = {
        border: `1px solid ${inputBorder}`,
        background: inputBg,
        color: text,
        padding: "10px 12px",
        fontSize: "17px",
        outline: "none",
        fontFamily: "inherit",
        width: "100%",
    };

    const labelStyle = {
        display: "flex",
        alignItems: "center",
        gap: "6px",
        fontSize: "15px",
        fontWeight: 600,
        color: text,
        marginBottom: "6px",
    };

    const plusButtonStyle = {
        color: "#1D9E75",
        background: "transparent",
        border: `1px solid #1D9E75`,
        borderRadius: "999px",
        width: "18px",
        height: "18px",
        lineHeight: "16px",
        fontSize: "13px",
        fontWeight: 700,
        cursor: "pointer",
        padding: 0,
    };

    const errorTextStyle = {
        color: "#DC2626",
        fontSize: "14px",
        marginTop: "4px",
    };

    const loadDistricts = async (regionId: string, keepSelection: boolean) => {
        setDistrictsLoading(true);
        const items = await fetchScoped(
            `/admin/districts?region_id=${regionId}`,
        );
        setDistricts(items);
        setDistrictsLoading(false);
        if (!keepSelection) {
            setForm((current) => ({
                ...current,
                district_id: "",
                community_id: "",
            }));
            setCommunities([]);
        }
    };

    const loadCommunities = async (
        districtId: string,
        keepSelection: boolean,
    ) => {
        setCommunitiesLoading(true);
        const items = await fetchScoped(
            `/admin/communities?district_id=${districtId}`,
        );
        setCommunities(items);
        setCommunitiesLoading(false);
        if (!keepSelection) {
            setForm((current) => ({ ...current, community_id: "" }));
        }
    };

    const onRegionChange = (value: string) => {
        setForm((current) => ({ ...current, region_id: value }));
        if (value) {
            loadDistricts(value, false);
        } else {
            setDistricts([]);
            setCommunities([]);
            setForm((current) => ({
                ...current,
                district_id: "",
                community_id: "",
            }));
        }
    };

    const onDistrictChange = (value: string) => {
        setForm((current) => ({ ...current, district_id: value }));
        if (value) {
            loadCommunities(value, false);
        } else {
            setCommunities([]);
            setForm((current) => ({ ...current, community_id: "" }));
        }
    };

    const startEdit = (group: FarmerGroupData) => {
        setForm({
            id: group.id,
            name: group.name,
            group_type_id: group.group_type_id
                ? String(group.group_type_id)
                : "",
            region_id: group.region_id ? String(group.region_id) : "",
            district_id: group.district_id ? String(group.district_id) : "",
            community_id: group.community_id ? String(group.community_id) : "",
            description: group.description ?? "",
            is_shared_liability: group.is_shared_liability,
            is_active: group.is_active,
        });
        setFormErrors({});
        setSubmitError(null);
        if (group.region_id) loadDistricts(String(group.region_id), true);
        if (group.district_id) loadCommunities(String(group.district_id), true);
        window.scrollTo({ top: 0, behavior: "smooth" });
    };

    const cancelEdit = () => {
        setForm(emptyForm);
        setDistricts([]);
        setCommunities([]);
        setFormErrors({});
        setSubmitError(null);
    };

    const submitForm = () => {
        if (!form.name.trim()) {
            setSubmitError("Name is required.");
            return;
        }
        setSubmitError(null);

        const payload = {
            name: form.name,
            group_type_id: form.group_type_id || null,
            region_id: form.region_id || null,
            district_id: form.district_id || null,
            community_id: form.community_id || null,
            description: form.description || null,
            is_shared_liability: form.is_shared_liability,
            is_active: form.is_active,
        };

        setSubmitting(true);

        const options = {
            preserveScroll: true,
            onError: (errs: Record<string, string>) => setFormErrors(errs),
            onSuccess: () => {
                cancelEdit();
            },
            onFinish: () => setSubmitting(false),
        };

        if (form.id) {
            router.put(
                route("admin.farmer-groups.update", form.id),
                payload,
                options,
            );
        } else {
            router.post(route("admin.farmer-groups.store"), payload, options);
        }
    };

    const destroy = (id: number, name: string) => {
        if (
            !window.confirm(
                `Delete "${name}"? This cannot be undone from here.`,
            )
        )
            return;
        setDeletingId(id);
        router.delete(route("admin.farmer-groups.destroy", id), {
            preserveScroll: true,
            onFinish: () => setDeletingId(null),
        });
    };

    const goToPage = (url: string) => {
        router.get(
            url,
            {},
            {
                preserveScroll: true,
                onStart: () => setNavLoading(true),
                onFinish: () => setNavLoading(false),
            },
        );
    };

    const openGroupTypeModal = () => setOpenModal("groupType");
    const openRegionModal = () => setOpenModal("region");

    const openDistrictModal = async () => {
        const regionId =
            form.region_id || (regions[0] ? String(regions[0].id) : "");
        setDistrictModalRegionId(regionId);
        setOpenModal("district");
        if (regionId) {
            setDistrictModalLoading(true);
            setDistrictModalItems(
                await fetchScoped(`/admin/districts?region_id=${regionId}`),
            );
            setDistrictModalLoading(false);
        } else {
            setDistrictModalItems([]);
        }
    };

    const refreshDistrictModal = async (regionId: string) => {
        setDistrictModalLoading(true);
        setDistrictModalItems(
            await fetchScoped(`/admin/districts?region_id=${regionId}`),
        );
        setDistrictModalLoading(false);
        if (form.region_id === regionId) loadDistricts(regionId, true);
    };

    const openCommunityModal = async () => {
        const districtId =
            form.district_id || (districts[0] ? String(districts[0].id) : "");
        setCommunityModalDistrictId(districtId);
        setOpenModal("community");
        if (districtId) {
            setCommunityModalLoading(true);
            setCommunityModalItems(
                await fetchScoped(
                    `/admin/communities?district_id=${districtId}`,
                ),
            );
            setCommunityModalLoading(false);
        } else {
            setCommunityModalItems([]);
        }
    };

    const refreshCommunityModal = async (districtId: string) => {
        setCommunityModalLoading(true);
        setCommunityModalItems(
            await fetchScoped(`/admin/communities?district_id=${districtId}`),
        );
        setCommunityModalLoading(false);
        if (form.district_id === districtId) loadCommunities(districtId, true);
    };

    const closeModal = () => setOpenModal(null);

    return (
        <>
            {permissions.create || form.id ? (
                <div
                    className="mb-6"
                    style={{
                        background: surface,
                        border: `1px solid ${border}`,
                        padding: "20px",
                    }}
                >
                    <h3
                        style={{
                            fontSize: "18px",
                            fontWeight: 700,
                            color: text,
                            marginBottom: "16px",
                        }}
                    >
                        {form.id ? "Edit farmer group" : "New farmer group"}
                    </h3>

                    <div className="mb-4">
                        <label style={labelStyle}>Name</label>
                        <input
                            type="text"
                            value={form.name}
                            onChange={(event) =>
                                setForm((c) => ({
                                    ...c,
                                    name: event.target.value,
                                }))
                            }
                            style={inputStyle}
                        />
                        {(errors?.name || formErrors.name) && (
                            <p style={errorTextStyle}>
                                {errors?.name ?? formErrors.name}
                            </p>
                        )}
                    </div>

                    <div className="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label style={labelStyle}>
                                Group type
                                {permissions.create && (
                                    <button
                                        type="button"
                                        onClick={openGroupTypeModal}
                                        style={plusButtonStyle}
                                    >
                                        +
                                    </button>
                                )}
                            </label>
                            <select
                                value={form.group_type_id}
                                onChange={(event) =>
                                    setForm((c) => ({
                                        ...c,
                                        group_type_id: event.target.value,
                                    }))
                                }
                                style={inputStyle}
                            >
                                <option value="">None</option>
                                {groupTypes.map((type) => (
                                    <option key={type.id} value={type.id}>
                                        {type.name}
                                    </option>
                                ))}
                            </select>
                            {(errors?.group_type_id ||
                                formErrors.group_type_id) && (
                                <p style={errorTextStyle}>
                                    {errors?.group_type_id ??
                                        formErrors.group_type_id}
                                </p>
                            )}
                        </div>

                        <div>
                            <label style={labelStyle}>
                                Region
                                {permissions.create && (
                                    <button
                                        type="button"
                                        onClick={openRegionModal}
                                        style={plusButtonStyle}
                                    >
                                        +
                                    </button>
                                )}
                            </label>
                            <select
                                value={form.region_id}
                                onChange={(event) =>
                                    onRegionChange(event.target.value)
                                }
                                style={inputStyle}
                            >
                                <option value="">None</option>
                                {regions.map((region) => (
                                    <option key={region.id} value={region.id}>
                                        {region.name}
                                    </option>
                                ))}
                            </select>
                            {(errors?.region_id || formErrors.region_id) && (
                                <p style={errorTextStyle}>
                                    {errors?.region_id ?? formErrors.region_id}
                                </p>
                            )}
                        </div>

                        <div>
                            <label style={labelStyle}>
                                District
                                {permissions.create && (
                                    <button
                                        type="button"
                                        onClick={openDistrictModal}
                                        style={plusButtonStyle}
                                    >
                                        +
                                    </button>
                                )}
                            </label>
                            <select
                                value={form.district_id}
                                onChange={(event) =>
                                    onDistrictChange(event.target.value)
                                }
                                disabled={!form.region_id}
                                style={{
                                    ...inputStyle,
                                    opacity: form.region_id ? 1 : 0.6,
                                }}
                            >
                                <option value="">
                                    {districtsLoading ? "Loading…" : "None"}
                                </option>
                                {districts.map((district) => (
                                    <option
                                        key={district.id}
                                        value={district.id}
                                    >
                                        {district.name}
                                    </option>
                                ))}
                            </select>
                            {(errors?.district_id ||
                                formErrors.district_id) && (
                                <p style={errorTextStyle}>
                                    {errors?.district_id ??
                                        formErrors.district_id}
                                </p>
                            )}
                        </div>

                        <div>
                            <label style={labelStyle}>
                                Community
                                {permissions.create && (
                                    <button
                                        type="button"
                                        onClick={openCommunityModal}
                                        style={plusButtonStyle}
                                    >
                                        +
                                    </button>
                                )}
                            </label>
                            <select
                                value={form.community_id}
                                onChange={(event) =>
                                    setForm((c) => ({
                                        ...c,
                                        community_id: event.target.value,
                                    }))
                                }
                                disabled={!form.district_id}
                                style={{
                                    ...inputStyle,
                                    opacity: form.district_id ? 1 : 0.6,
                                }}
                            >
                                <option value="">
                                    {communitiesLoading ? "Loading…" : "None"}
                                </option>
                                {communities.map((community) => (
                                    <option
                                        key={community.id}
                                        value={community.id}
                                    >
                                        {community.name}
                                    </option>
                                ))}
                            </select>
                            {(errors?.community_id ||
                                formErrors.community_id) && (
                                <p style={errorTextStyle}>
                                    {errors?.community_id ??
                                        formErrors.community_id}
                                </p>
                            )}
                        </div>
                    </div>

                    <div className="mb-4">
                        <label style={labelStyle}>Description</label>
                        <textarea
                            value={form.description}
                            onChange={(event) =>
                                setForm((c) => ({
                                    ...c,
                                    description: event.target.value,
                                }))
                            }
                            rows={3}
                            style={{ ...inputStyle, resize: "vertical" }}
                        />
                        {(errors?.description || formErrors.description) && (
                            <p style={errorTextStyle}>
                                {errors?.description ?? formErrors.description}
                            </p>
                        )}
                    </div>

                    <div className="flex gap-6 mb-6">
                        <label
                            style={{
                                display: "flex",
                                alignItems: "center",
                                gap: "8px",
                                color: text,
                                fontSize: "15px",
                            }}
                        >
                            <input
                                type="checkbox"
                                checked={form.is_shared_liability}
                                onChange={(event) =>
                                    setForm((c) => ({
                                        ...c,
                                        is_shared_liability:
                                            event.target.checked,
                                    }))
                                }
                            />
                            Shared liability
                        </label>
                        <label
                            style={{
                                display: "flex",
                                alignItems: "center",
                                gap: "8px",
                                color: text,
                                fontSize: "15px",
                            }}
                        >
                            <input
                                type="checkbox"
                                checked={form.is_active}
                                onChange={(event) =>
                                    setForm((c) => ({
                                        ...c,
                                        is_active: event.target.checked,
                                    }))
                                }
                            />
                            Active
                        </label>
                    </div>

                    {submitError && (
                        <p style={{ ...errorTextStyle, marginBottom: "12px" }}>
                            {submitError}
                        </p>
                    )}

                    <div style={{ display: "flex", gap: "12px" }}>
                        <button
                            onClick={submitForm}
                            disabled={submitting}
                            style={{
                                background: "#1D9E75",
                                color: "#FFFFFF",
                                border: "none",
                                padding: "10px 20px",
                                fontSize: "17px",
                                fontWeight: 600,
                                cursor: submitting ? "not-allowed" : "pointer",
                                opacity: submitting ? 0.7 : 1,
                            }}
                        >
                            {form.id ? "Save changes" : "Add farmer group"}
                        </button>
                        {form.id && (
                            <button
                                onClick={cancelEdit}
                                style={{
                                    background: "transparent",
                                    color: textSecondary,
                                    border: `1px solid ${border}`,
                                    padding: "10px 20px",
                                    fontSize: "17px",
                                    cursor: "pointer",
                                }}
                            >
                                Cancel
                            </button>
                        )}
                    </div>
                </div>
            ) : null}

            <div
                className="overflow-x-auto"
                style={{ background: surface, border: `1px solid ${border}` }}
            >
                <table className="min-w-full" style={{ fontSize: "17px" }}>
                    <thead>
                        <tr style={{ background: headerBg }}>
                            <th
                                className="text-left px-4 py-3"
                                style={{ color: headerText, fontWeight: 700 }}
                            >
                                Name
                            </th>
                            <th
                                className="text-left px-4 py-3"
                                style={{ color: headerText, fontWeight: 700 }}
                            >
                                Type
                            </th>
                            <th
                                className="text-left px-4 py-3"
                                style={{ color: headerText, fontWeight: 700 }}
                            >
                                Location
                            </th>
                            <th
                                className="text-left px-4 py-3"
                                style={{ color: headerText, fontWeight: 700 }}
                            >
                                Status
                            </th>
                            {(permissions.update || permissions.delete) && (
                                <th
                                    className="text-left px-4 py-3"
                                    style={{
                                        color: headerText,
                                        fontWeight: 700,
                                    }}
                                >
                                    Actions
                                </th>
                            )}
                        </tr>
                    </thead>
                    <tbody>
                        {navLoading && (
                            <TableSkeletonRows rows={5} columns={5} />
                        )}

                        {!navLoading && farmerGroups.data.length === 0 && (
                            <tr>
                                <td
                                    colSpan={5}
                                    className="px-4 py-6 text-center"
                                    style={{ color: textSecondary }}
                                >
                                    No farmer groups yet.
                                </td>
                            </tr>
                        )}

                        {!navLoading &&
                            farmerGroups.data.map((group, index) =>
                                deletingId === group.id ? (
                                    <TableSkeletonRows
                                        key={group.id}
                                        rows={1}
                                        columns={5}
                                    />
                                ) : (
                                    <tr
                                        key={group.id}
                                        style={{
                                            borderTop: `1px solid ${border}`,
                                            background:
                                                index % 2 === 1
                                                    ? rowAlt
                                                    : "transparent",
                                        }}
                                    >
                                        <td
                                            className="px-4 py-3"
                                            style={{ color: text }}
                                        >
                                            {group.name}
                                        </td>
                                        <td
                                            className="px-4 py-3"
                                            style={{ color: text }}
                                        >
                                            {group.group_type?.name ?? (
                                                <span
                                                    style={{
                                                        color: textSecondary,
                                                    }}
                                                >
                                                    None
                                                </span>
                                            )}
                                        </td>
                                        <td
                                            className="px-4 py-3"
                                            style={{ color: text }}
                                        >
                                            {[
                                                group.region?.name,
                                                group.district?.name,
                                                group.community?.name,
                                            ]
                                                .filter(Boolean)
                                                .join(" › ") || (
                                                <span
                                                    style={{
                                                        color: textSecondary,
                                                    }}
                                                >
                                                    None
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span
                                                style={{
                                                    color: group.is_active
                                                        ? "#1D9E75"
                                                        : textSecondary,
                                                    fontWeight: 600,
                                                }}
                                            >
                                                {group.is_active
                                                    ? "Active"
                                                    : "Inactive"}
                                            </span>
                                        </td>
                                        {(permissions.update ||
                                            permissions.delete) && (
                                            <td className="px-4 py-3">
                                                <div
                                                    style={{
                                                        display: "flex",
                                                        gap: "12px",
                                                    }}
                                                >
                                                    {permissions.update && (
                                                        <button
                                                            onClick={() =>
                                                                startEdit(group)
                                                            }
                                                            style={{
                                                                color: "#1D9E75",
                                                                background:
                                                                    "transparent",
                                                                border: "none",
                                                                fontWeight: 600,
                                                                cursor: "pointer",
                                                                fontSize:
                                                                    "15px",
                                                            }}
                                                        >
                                                            Edit
                                                        </button>
                                                    )}
                                                    {permissions.delete && (
                                                        <button
                                                            onClick={() =>
                                                                destroy(
                                                                    group.id,
                                                                    group.name,
                                                                )
                                                            }
                                                            style={{
                                                                color: "#DC2626",
                                                                background:
                                                                    "transparent",
                                                                border: "none",
                                                                cursor: "pointer",
                                                                fontSize:
                                                                    "15px",
                                                            }}
                                                        >
                                                            Delete
                                                        </button>
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

            {farmerGroups.links.length > 3 && (
                <div className="flex gap-2 mt-4 flex-wrap">
                    {farmerGroups.links.map((link, index) => (
                        <button
                            key={index}
                            disabled={!link.url}
                            onClick={() => link.url && goToPage(link.url)}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                            style={{
                                padding: "6px 12px",
                                fontSize: "14px",
                                border: `1px solid ${border}`,
                                background: link.active ? "#1D9E75" : surface,
                                color: link.active ? "#FFFFFF" : text,
                                cursor: link.url ? "pointer" : "default",
                                opacity: link.url ? 1 : 0.5,
                            }}
                        />
                    ))}
                </div>
            )}

            {openModal === "groupType" && (
                <QuickAddModal
                    title="Group Types"
                    items={groupTypes}
                    loading={false}
                    permissions={permissions}
                    onClose={closeModal}
                    onCreate={(name) =>
                        new Promise<void>((resolve) => {
                            router.post(
                                route("admin.farmer-group-types.store"),
                                { name },
                                {
                                    preserveScroll: true,
                                    onFinish: () => resolve(),
                                },
                            );
                        })
                    }
                    onUpdate={(id, name) =>
                        new Promise<void>((resolve) => {
                            router.put(
                                route("admin.farmer-group-types.update", id),
                                { name },
                                {
                                    preserveScroll: true,
                                    onFinish: () => resolve(),
                                },
                            );
                        })
                    }
                    onDelete={(id) =>
                        new Promise<void>((resolve) => {
                            router.delete(
                                route("admin.farmer-group-types.destroy", id),
                                {
                                    preserveScroll: true,
                                    onFinish: () => resolve(),
                                },
                            );
                        })
                    }
                />
            )}

            {openModal === "region" && (
                <QuickAddModal
                    title="Regions"
                    items={regions}
                    loading={false}
                    permissions={permissions}
                    onClose={closeModal}
                    onCreate={(name) =>
                        new Promise<void>((resolve) => {
                            router.post(
                                route("admin.regions.store"),
                                { name },
                                {
                                    preserveScroll: true,
                                    onFinish: () => resolve(),
                                },
                            );
                        })
                    }
                    onUpdate={(id, name) =>
                        new Promise<void>((resolve) => {
                            router.put(
                                route("admin.regions.update", id),
                                { name },
                                {
                                    preserveScroll: true,
                                    onFinish: () => resolve(),
                                },
                            );
                        })
                    }
                    onDelete={(id) =>
                        new Promise<void>((resolve) => {
                            router.delete(route("admin.regions.destroy", id), {
                                preserveScroll: true,
                                onFinish: () => resolve(),
                            });
                        })
                    }
                />
            )}

            {openModal === "district" && (
                <QuickAddModal
                    title="Districts"
                    items={districtModalItems}
                    loading={districtModalLoading}
                    permissions={permissions}
                    onClose={closeModal}
                    extraFieldRequired
                    extraFieldDefault={districtModalRegionId}
                    extraField={(value, onChange) => (
                        <select
                            value={value || districtModalRegionId}
                            onChange={(event) => {
                                onChange(event.target.value);
                                setDistrictModalRegionId(event.target.value);
                                refreshDistrictModal(event.target.value);
                            }}
                            style={inputStyle}
                        >
                            <option value="">Select a region</option>
                            {regions.map((region) => (
                                <option key={region.id} value={region.id}>
                                    {region.name}
                                </option>
                            ))}
                        </select>
                    )}
                    onCreate={(name, regionId) =>
                        new Promise<void>((resolve) => {
                            const targetRegion =
                                regionId || districtModalRegionId;
                            router.post(
                                route("admin.districts.store"),
                                { name, region_id: targetRegion },
                                {
                                    preserveScroll: true,
                                    onSuccess: () =>
                                        refreshDistrictModal(targetRegion),
                                    onFinish: () => resolve(),
                                },
                            );
                        })
                    }
                    onUpdate={(id, name, regionId) =>
                        new Promise<void>((resolve) => {
                            const targetRegion =
                                regionId || districtModalRegionId;
                            router.put(
                                route("admin.districts.update", id),
                                { name, region_id: targetRegion },
                                {
                                    preserveScroll: true,
                                    onSuccess: () =>
                                        refreshDistrictModal(targetRegion),
                                    onFinish: () => resolve(),
                                },
                            );
                        })
                    }
                    onDelete={(id) =>
                        new Promise<void>((resolve) => {
                            router.delete(
                                route("admin.districts.destroy", id),
                                {
                                    preserveScroll: true,
                                    onSuccess: () =>
                                        refreshDistrictModal(
                                            districtModalRegionId,
                                        ),
                                    onFinish: () => resolve(),
                                },
                            );
                        })
                    }
                />
            )}

            {openModal === "community" && (
                <QuickAddModal
                    title="Communities"
                    items={communityModalItems}
                    loading={communityModalLoading}
                    permissions={permissions}
                    onClose={closeModal}
                    extraFieldRequired
                    extraFieldDefault={communityModalDistrictId}
                    extraField={(value, onChange) => (
                        <select
                            value={value || communityModalDistrictId}
                            onChange={(event) => {
                                onChange(event.target.value);
                                setCommunityModalDistrictId(event.target.value);
                                refreshCommunityModal(event.target.value);
                            }}
                            style={inputStyle}
                        >
                            <option value="">Select a district</option>
                            {districts.map((district) => (
                                <option key={district.id} value={district.id}>
                                    {district.name}
                                </option>
                            ))}
                        </select>
                    )}
                    onCreate={(name, districtId) =>
                        new Promise<void>((resolve) => {
                            const targetDistrict =
                                districtId || communityModalDistrictId;
                            router.post(
                                route("admin.communities.store"),
                                { name, district_id: targetDistrict },
                                {
                                    preserveScroll: true,
                                    onSuccess: () =>
                                        refreshCommunityModal(targetDistrict),
                                    onFinish: () => resolve(),
                                },
                            );
                        })
                    }
                    onUpdate={(id, name, districtId) =>
                        new Promise<void>((resolve) => {
                            const targetDistrict =
                                districtId || communityModalDistrictId;
                            router.put(
                                route("admin.communities.update", id),
                                { name, district_id: targetDistrict },
                                {
                                    preserveScroll: true,
                                    onSuccess: () =>
                                        refreshCommunityModal(targetDistrict),
                                    onFinish: () => resolve(),
                                },
                            );
                        })
                    }
                    onDelete={(id) =>
                        new Promise<void>((resolve) => {
                            router.delete(
                                route("admin.communities.destroy", id),
                                {
                                    preserveScroll: true,
                                    onSuccess: () =>
                                        refreshCommunityModal(
                                            communityModalDistrictId,
                                        ),
                                    onFinish: () => resolve(),
                                },
                            );
                        })
                    }
                />
            )}
        </>
    );
}
