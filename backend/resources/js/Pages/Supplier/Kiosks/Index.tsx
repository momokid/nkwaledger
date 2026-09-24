import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import BackLink from "@/Components/BackLink";
import { Link, useForm } from "@inertiajs/react";
import { FormEvent, useState } from "react";

interface KioskData {
    uuid: string;
    kiosk_number: string | null;
    name: string;
    status: string;
    confirmed_at: string | null;
    requires_admin_approval: boolean;
    admin_approved_at: string | null;
    is_visible: boolean;
}

interface Option {
    id: number;
    name: string;
    region_id?: number;
}

interface Props {
    kiosks: KioskData[];
    regions: Option[];
    districts: Option[];
}

export default function Index({ kiosks, regions, districts }: Props) {
    return (
        <AuthenticatedLayout title="My Kiosks">
            <BackLink fallbackHref="/supplier/dashboard" />
            <IndexContent kiosks={kiosks} regions={regions} districts={districts} />
        </AuthenticatedLayout>
    );
}

type ContentProps = Pick<Props, "kiosks" | "regions" | "districts">;

function IndexContent({ kiosks, regions, districts }: ContentProps) {
    const { dark } = useTheme();
    const [confirmingUuid, setConfirmingUuid] = useState<string | null>(null);

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const inputBorder = dark ? "#4B5563" : "#9CA3AF";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const primary = "#1D9E75";
    const amber = "#B45309";

    const inputStyle = {
        border: `1px solid ${inputBorder}`,
        background: dark ? "#111827" : "#FFFFFF",
        color: text,
        padding: "8px 10px",
        fontSize: "1.0625rem",
        outline: "none",
    };

    const createForm = useForm({
        name: "",
        region_id: "",
        district_id: "",
        contact_phone: "",
        latitude: "",
        longitude: "",
    });

    const submitCreate = (event: FormEvent) => {
        event.preventDefault();
        createForm.post(route("supplier.kiosks.store"), {
            preserveScroll: true,
            onSuccess: () => createForm.reset(),
        });
    };

    const codeForm = useForm({ code: "" });

    const submitConfirm = (event: FormEvent, uuid: string) => {
        event.preventDefault();
        codeForm.post(route("supplier.kiosks.confirm", uuid), {
            preserveScroll: true,
            onSuccess: () => {
                codeForm.reset("code");
                setConfirmingUuid(null);
            },
        });
    };

    const statusLabel: Record<string, string> = {
        pending_confirmation: "Pending confirmation",
        active: "Active",
        suspended: "Suspended",
    };

    return (
        <>
            <form
                onSubmit={submitCreate}
                className="mb-6"
                style={{
                    background: surface,
                    border: `1px solid ${border}`,
                    padding: "20px",
                    display: "flex",
                    gap: "12px",
                    alignItems: "flex-end",
                    flexWrap: "wrap",
                }}
            >
                <div>
                    <label
                        style={{
                            display: "block",
                            fontSize: "1rem",
                            fontWeight: 600,
                            color: text,
                            marginBottom: "4px",
                        }}
                    >
                        Kiosk name
                    </label>
                    <input
                        type="text"
                        maxLength={60}
                        value={createForm.data.name}
                        onChange={(event) =>
                            createForm.setData("name", event.target.value)
                        }
                        style={{ ...inputStyle, width: "200px" }}
                    />
                    {createForm.errors.name && (
                        <p style={{ color: "#DC2626", fontSize: "0.9375rem" }}>
                            {createForm.errors.name}
                        </p>
                    )}
                </div>

                <div>
                    <label
                        style={{
                            display: "block",
                            fontSize: "1rem",
                            fontWeight: 600,
                            color: text,
                            marginBottom: "4px",
                        }}
                    >
                        Region
                    </label>
                    <select
                        value={createForm.data.region_id}
                        onChange={(event) =>
                            createForm.setData("region_id", event.target.value)
                        }
                        style={{ ...inputStyle, width: "160px" }}
                    >
                        <option value="">Select</option>
                        {regions.map((region) => (
                            <option key={region.id} value={region.id}>
                                {region.name}
                            </option>
                        ))}
                    </select>
                    {createForm.errors.region_id && (
                        <p style={{ color: "#DC2626", fontSize: "0.9375rem" }}>
                            {createForm.errors.region_id}
                        </p>
                    )}
                </div>

                <div>
                    <label
                        style={{
                            display: "block",
                            fontSize: "1rem",
                            fontWeight: 600,
                            color: text,
                            marginBottom: "4px",
                        }}
                    >
                        District
                    </label>
                    <select
                        value={createForm.data.district_id}
                        onChange={(event) =>
                            createForm.setData("district_id", event.target.value)
                        }
                        style={{ ...inputStyle, width: "160px" }}
                    >
                        <option value="">Select</option>
                        {districts
                            .filter(
                                (district) =>
                                    !createForm.data.region_id ||
                                    String(district.region_id) ===
                                        createForm.data.region_id,
                            )
                            .map((district) => (
                                <option key={district.id} value={district.id}>
                                    {district.name}
                                </option>
                            ))}
                    </select>
                    {createForm.errors.district_id && (
                        <p style={{ color: "#DC2626", fontSize: "0.9375rem" }}>
                            {createForm.errors.district_id}
                        </p>
                    )}
                </div>

                <div>
                    <label
                        style={{
                            display: "block",
                            fontSize: "1rem",
                            fontWeight: 600,
                            color: text,
                            marginBottom: "4px",
                        }}
                    >
                        Contact phone
                    </label>
                    <input
                        type="text"
                        value={createForm.data.contact_phone}
                        onChange={(event) =>
                            createForm.setData("contact_phone", event.target.value)
                        }
                        style={{ ...inputStyle, width: "160px" }}
                    />
                    {createForm.errors.contact_phone && (
                        <p style={{ color: "#DC2626", fontSize: "0.9375rem" }}>
                            {createForm.errors.contact_phone}
                        </p>
                    )}
                </div>

                <button
                    type="submit"
                    disabled={createForm.processing}
                    style={{
                        background: primary,
                        color: "#FFFFFF",
                        border: "none",
                        padding: "9px 18px",
                        fontSize: "1.0625rem",
                        fontWeight: 600,
                        cursor: createForm.processing ? "not-allowed" : "pointer",
                    }}
                >
                    Register kiosk
                </button>
            </form>

            <div
                className="overflow-x-auto"
                style={{ background: surface, border: `1px solid ${border}` }}
            >
                <table className="min-w-full" style={{ fontSize: "1.0625rem" }}>
                    <thead>
                        <tr style={{ background: dark ? "rgba(29,158,117,0.15)" : "#EAF5F0" }}>
                            <th className="text-left px-4 py-3" style={{ color: primary, fontWeight: 700 }}>
                                Number
                            </th>
                            <th className="text-left px-4 py-3" style={{ color: primary, fontWeight: 700 }}>
                                Name
                            </th>
                            <th className="text-left px-4 py-3" style={{ color: primary, fontWeight: 700 }}>
                                Status
                            </th>
                            <th className="text-left px-4 py-3" style={{ color: primary, fontWeight: 700 }}>
                                Action
                            </th>
                            <th className="text-left px-4 py-3" style={{ color: primary, fontWeight: 700 }}>
                                Products
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {kiosks.length === 0 && (
                            <tr>
                                <td colSpan={5} className="px-4 py-6 text-center" style={{ color: textSecondary }}>
                                    No kiosks registered yet.
                                </td>
                            </tr>
                        )}
                        {kiosks.map((kiosk) => (
                            <tr key={kiosk.uuid} style={{ borderTop: `1px solid ${border}` }}>
                                <td className="px-4 py-3" style={{ color: text }}>
                                    {kiosk.kiosk_number}
                                </td>
                                <td className="px-4 py-3" style={{ color: text }}>
                                    {kiosk.name}
                                </td>
                                <td className="px-4 py-3" style={{ color: text }}>
                                    {statusLabel[kiosk.status]}
                                    {kiosk.requires_admin_approval && !kiosk.admin_approved_at && (
                                        <span style={{ color: amber, marginLeft: "8px", fontSize: "0.9375rem" }}>
                                            (needs admin approval)
                                        </span>
                                    )}
                                </td>
                                <td className="px-4 py-3">
                                    {!kiosk.confirmed_at ? (
                                        confirmingUuid === kiosk.uuid ? (
                                            <form
                                                onSubmit={(event) => submitConfirm(event, kiosk.uuid)}
                                                style={{ display: "flex", gap: "8px" }}
                                            >
                                                <input
                                                    type="text"
                                                    value={codeForm.data.code}
                                                    onChange={(event) =>
                                                        codeForm.setData("code", event.target.value)
                                                    }
                                                    placeholder="Code"
                                                    style={{ ...inputStyle, width: "100px" }}
                                                />
                                                <button
                                                    type="submit"
                                                    disabled={codeForm.processing}
                                                    style={{
                                                        background: primary,
                                                        color: "#FFFFFF",
                                                        border: "none",
                                                        padding: "6px 12px",
                                                        fontSize: "0.9375rem",
                                                        fontWeight: 600,
                                                        cursor: "pointer",
                                                    }}
                                                >
                                                    Confirm
                                                </button>
                                            </form>
                                        ) : (
                                            <button
                                                onClick={() => setConfirmingUuid(kiosk.uuid)}
                                                style={{
                                                    color: primary,
                                                    background: "transparent",
                                                    border: "none",
                                                    fontWeight: 600,
                                                    cursor: "pointer",
                                                }}
                                            >
                                                Enter confirmation code
                                            </button>
                                        )
                                    ) : (
                                        <span style={{ color: textSecondary }}>
                                            {kiosk.is_visible ? "Live" : "Confirmed"}
                                        </span>
                                    )}
                                </td>
                                <td className="px-4 py-3">
                                    <Link
                                        href={route("supplier.kiosks.products.index", kiosk.uuid)}
                                        style={{ color: primary, fontWeight: 600 }}
                                    >
                                        Manage products
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </>
    );
}
