import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import useAuthGuard from "@/hooks/useAuthGuard";
import { Head } from "@inertiajs/react";
import { cedis as formatMoney } from "@/lib/format";
import { IconPrinter } from "@tabler/icons-react";

interface Identity {
    type: string | null;
    has_document: boolean;
    verified: boolean;
    verified_by: string | null;
}

interface Farmer {
    id: string;
    name: string;
    phone: string | null;
    gender: string | null;
    date_of_birth: string | null;
    home_address: string | null;
    community: string | null;
    farmer_group: string | null;
    is_active: boolean;
    onboarded_at: string | null;
    registered_by: string | null;
    farm_types: string[];
    identity: Identity;
}

interface FarmUnit {
    name: string;
    farm_type: string | null;
    capacity: string | null;
    capacity_unit: string | null;
    is_approved: boolean;
}

interface Summary {
    total_income: number;
    total_expense: number;
    net: number;
}

interface Props {
    farmer: Farmer;
    farm_units: FarmUnit[];
    summary: Summary;
    credit_score: string;
    filters: { from: string; to: string };
}

export default function FarmerProfile({
    farmer,
    farm_units,
    summary,
    credit_score,
    filters,
}: Props) {
    useAuthGuard();

    return (
        <AuthenticatedLayout title="Farmer Profile">
            <Head title={`${farmer.name} — Profile`} />
            <FarmerProfileContent
                farmer={farmer}
                farmUnits={farm_units}
                summary={summary}
                creditScore={credit_score}
                filters={filters}
            />
        </AuthenticatedLayout>
    );
}

function Field({
    label,
    value,
    text,
    textSecondary,
}: {
    label: string;
    value: string;
    text: string;
    textSecondary: string;
}) {
    return (
        <div style={{ marginBottom: "10px" }}>
            <p style={{ fontSize: "0.875rem", color: textSecondary }}>{label}</p>
            <p style={{ fontSize: "1.0625rem", color: text, fontWeight: 600 }}>
                {value}
            </p>
        </div>
    );
}

function FarmerProfileContent({
    farmer,
    farmUnits,
    summary,
    creditScore,
    filters,
}: {
    farmer: Farmer;
    farmUnits: FarmUnit[];
    summary: Summary;
    creditScore: string;
    filters: { from: string; to: string };
}) {
    const { dark } = useTheme();

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const primary = "#1D9E75";

    return (
        <div className="p-6 space-y-6">
            <div
                style={{
                    display: "flex",
                    justifyContent: "space-between",
                    alignItems: "flex-start",
                }}
            >
                <div>
                    <p
                        style={{
                            fontSize: "1.5rem",
                            fontWeight: 700,
                            color: text,
                            marginBottom: "4px",
                        }}
                    >
                        {farmer.name}
                    </p>
                    <p style={{ fontSize: "1.0625rem", color: textSecondary }}>
                        Financial period: {filters.from} to {filters.to}
                    </p>
                </div>

                <a
                    href={`/agent/farmers/${farmer.id}/profile-report/print`}
                    rel="noopener noreferrer"
                    style={{
                        display: "flex",
                        alignItems: "center",
                        gap: "8px",
                        border: `1px solid ${border}`,
                        background: surface,
                        color: text,
                        padding: "10px 16px",
                        fontSize: "1.0625rem",
                        textDecoration: "none",
                    }}
                >
                    <IconPrinter size={18} />
                    Print
                </a>
            </div>

            <div
                style={{
                    background: surface,
                    border: `1px solid ${border}`,
                    padding: "20px",
                }}
            >
                <p
                    style={{
                        fontSize: "1.125rem",
                        fontWeight: 600,
                        color: text,
                        marginBottom: "12px",
                    }}
                >
                    Personal &amp; registration
                </p>
                <div
                    style={{
                        display: "grid",
                        gridTemplateColumns:
                            "repeat(auto-fit, minmax(200px, 1fr))",
                    }}
                >
                    <Field
                        label="Phone"
                        value={farmer.phone ?? "—"}
                        text={text}
                        textSecondary={textSecondary}
                    />
                    <Field
                        label="Gender"
                        value={farmer.gender ?? "—"}
                        text={text}
                        textSecondary={textSecondary}
                    />
                    <Field
                        label="Date of birth"
                        value={farmer.date_of_birth ?? "—"}
                        text={text}
                        textSecondary={textSecondary}
                    />
                    <Field
                        label="Community"
                        value={farmer.community ?? "—"}
                        text={text}
                        textSecondary={textSecondary}
                    />
                    <Field
                        label="Farmer group"
                        value={farmer.farmer_group ?? "—"}
                        text={text}
                        textSecondary={textSecondary}
                    />
                    <Field
                        label="Status"
                        value={farmer.is_active ? "Active" : "On hold"}
                        text={text}
                        textSecondary={textSecondary}
                    />
                    <Field
                        label="Home address"
                        value={farmer.home_address ?? "—"}
                        text={text}
                        textSecondary={textSecondary}
                    />
                    <Field
                        label="Onboarded"
                        value={farmer.onboarded_at ?? "—"}
                        text={text}
                        textSecondary={textSecondary}
                    />
                </div>
            </div>

            <div
                style={{
                    background: surface,
                    border: `1px solid ${border}`,
                    padding: "20px",
                }}
            >
                <p
                    style={{
                        fontSize: "1.125rem",
                        fontWeight: 600,
                        color: text,
                        marginBottom: "12px",
                    }}
                >
                    Identity
                </p>
                <Field
                    label="Document"
                    value={farmer.identity.type ?? "None on file"}
                    text={text}
                    textSecondary={textSecondary}
                />
                <Field
                    label="Verification"
                    value={
                        farmer.identity.verified
                            ? `Verified by ${farmer.identity.verified_by}`
                            : "Not verified"
                    }
                    text={text}
                    textSecondary={textSecondary}
                />
            </div>

            <div
                style={{
                    background: surface,
                    border: `1px solid ${border}`,
                    padding: "20px",
                }}
            >
                <p
                    style={{
                        fontSize: "1.125rem",
                        fontWeight: 600,
                        color: text,
                        marginBottom: "12px",
                    }}
                >
                    Farm types
                </p>
                <p style={{ fontSize: "1.0625rem", color: text }}>
                    {farmer.farm_types.length === 0
                        ? "None recorded."
                        : farmer.farm_types.join(", ")}
                </p>
            </div>

            <div
                style={{
                    background: surface,
                    border: `1px solid ${border}`,
                    padding: "20px",
                }}
            >
                <p
                    style={{
                        fontSize: "1.125rem",
                        fontWeight: 600,
                        color: text,
                        marginBottom: "12px",
                    }}
                >
                    Farm units
                </p>
                {farmUnits.length === 0 ? (
                    <p style={{ fontSize: "1.0625rem", color: textSecondary }}>
                        No farm units recorded.
                    </p>
                ) : (
                    <table
                        style={{
                            width: "100%",
                            borderCollapse: "collapse",
                            fontSize: "1rem",
                        }}
                    >
                        <thead>
                            <tr
                                style={{
                                    borderBottom: `1px solid ${border}`,
                                    textAlign: "left",
                                }}
                            >
                                <th
                                    style={{
                                        padding: "10px 8px",
                                        color: textSecondary,
                                    }}
                                >
                                    Name
                                </th>
                                <th
                                    style={{
                                        padding: "10px 8px",
                                        color: textSecondary,
                                    }}
                                >
                                    Type
                                </th>
                                <th
                                    style={{
                                        padding: "10px 8px",
                                        color: textSecondary,
                                    }}
                                >
                                    Capacity
                                </th>
                                <th
                                    style={{
                                        padding: "10px 8px",
                                        color: textSecondary,
                                    }}
                                >
                                    Approved
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {farmUnits.map((unit) => (
                                <tr
                                    key={unit.name}
                                    style={{
                                        borderBottom: `1px solid ${border}`,
                                    }}
                                >
                                    <td
                                        style={{
                                            padding: "12px 8px",
                                            color: text,
                                            fontWeight: 600,
                                        }}
                                    >
                                        {unit.name}
                                    </td>
                                    <td
                                        style={{
                                            padding: "12px 8px",
                                            color: textSecondary,
                                        }}
                                    >
                                        {unit.farm_type ?? "—"}
                                    </td>
                                    <td
                                        style={{
                                            padding: "12px 8px",
                                            color: textSecondary,
                                        }}
                                    >
                                        {unit.capacity
                                            ? `${unit.capacity} ${unit.capacity_unit ?? ""}`
                                            : "—"}
                                    </td>
                                    <td
                                        style={{
                                            padding: "12px 8px",
                                            color: unit.is_approved
                                                ? primary
                                                : textSecondary,
                                        }}
                                    >
                                        {unit.is_approved ? "Yes" : "Pending"}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>

            <div
                style={{
                    background: surface,
                    border: `1px solid ${border}`,
                    padding: "20px",
                }}
            >
                <p
                    style={{
                        fontSize: "1.125rem",
                        fontWeight: 600,
                        color: text,
                        marginBottom: "12px",
                    }}
                >
                    Financial summary
                </p>
                <div
                    style={{
                        display: "grid",
                        gridTemplateColumns:
                            "repeat(auto-fit, minmax(180px, 1fr))",
                        gap: "16px",
                    }}
                >
                    <Field
                        label="Total income"
                        value={`GHS ${formatMoney(summary.total_income)}`}
                        text={primary}
                        textSecondary={textSecondary}
                    />
                    <Field
                        label="Total expense"
                        value={`GHS ${formatMoney(summary.total_expense)}`}
                        text={text}
                        textSecondary={textSecondary}
                    />
                    <Field
                        label="Net"
                        value={`GHS ${formatMoney(summary.net)}`}
                        text={text}
                        textSecondary={textSecondary}
                    />
                </div>
            </div>

            <div
                style={{
                    background: surface,
                    border: `1px solid ${border}`,
                    padding: "20px",
                }}
            >
                <p
                    style={{
                        fontSize: "1.125rem",
                        fontWeight: 600,
                        color: text,
                        marginBottom: "8px",
                    }}
                >
                    Credit score
                </p>
                <p style={{ fontSize: "1.0625rem", color: textSecondary }}>
                    {creditScore}
                </p>
            </div>
        </div>
    );
}
