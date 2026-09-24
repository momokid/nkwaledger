import AdminLayout from "@/Layouts/AdminLayout";
import { useTheme } from "@/Layouts/AuthenticatedLayout";
import { router } from "@inertiajs/react";
import { useState } from "react";

interface SettingRow {
    key: string;
    value: string | null;
    default: string | null;
}

interface Props {
    settings: SettingRow[];
}

const settingLabel: Record<string, string> = {
    "marketplace.supplier_report_response_days": "Days a supplier has to answer a report",
    "marketplace.admin_intervention_days": "Days admin has to contact a silent supplier",
    "marketplace.buyer_confirmation_days": "Days before an unconfirmed order closes",
    "marketplace.crop_listing_reminder_days": "Days before a crop listing expiry reminder",
    "marketplace.animal_listing_prompt_days": "Days between animal listing availability prompts",
    "marketplace.product_expiry_alert_days": "Days before a product expiry alert",
    "marketplace.price_stale_days": "Days before a price is considered stale",
    "marketplace.kiosks_per_email_cap": "Kiosks one verified identity can own without approval",
    "marketplace.central_contact_number": "NkwaLedger support number shown on listings",
};

export default function Index({ settings }: Props) {
    return (
        <AdminLayout title="Marketplace Settings">
            <IndexContent settings={settings} />
        </AdminLayout>
    );
}

function IndexContent({ settings }: Props) {
    const { dark } = useTheme();
    const [drafts, setDrafts] = useState<Record<string, string>>(
        Object.fromEntries(settings.map((s) => [s.key, s.value ?? ""])),
    );

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const inputBorder = dark ? "#4B5563" : "#9CA3AF";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const primary = "#1D9E75";

    const save = (key: string) => {
        router.put(
            route("admin.marketplace.settings.update", key),
            { value: drafts[key] },
            { preserveScroll: true },
        );
    };

    return (
        <div className="overflow-x-auto" style={{ background: surface, border: `1px solid ${border}` }}>
            <table className="min-w-full" style={{ fontSize: "1.0625rem" }}>
                <thead>
                    <tr style={{ background: dark ? "rgba(29,158,117,0.15)" : "#EAF5F0" }}>
                        <th className="text-left px-4 py-3" style={{ color: primary, fontWeight: 700 }}>Setting</th>
                        <th className="text-left px-4 py-3" style={{ color: primary, fontWeight: 700 }}>Value</th>
                        <th className="text-left px-4 py-3" style={{ color: primary, fontWeight: 700 }}>Default</th>
                        <th className="text-left px-4 py-3" style={{ color: primary, fontWeight: 700 }}>Action</th>
                    </tr>
                </thead>
                <tbody>
                    {settings.map((setting, index) => (
                        <tr
                            key={setting.key}
                            style={{
                                borderTop: `1px solid ${border}`,
                                background: index % 2 === 1 ? (dark ? "#111827" : "#F9FAFB") : "transparent",
                            }}
                        >
                            <td className="px-4 py-3" style={{ color: text }}>
                                {settingLabel[setting.key] ?? setting.key}
                            </td>
                            <td className="px-4 py-3">
                                <input
                                    type="text"
                                    value={drafts[setting.key]}
                                    onChange={(event) =>
                                        setDrafts((prev) => ({ ...prev, [setting.key]: event.target.value }))
                                    }
                                    style={{
                                        border: `1px solid ${inputBorder}`,
                                        background: dark ? "#111827" : "#FFFFFF",
                                        color: text,
                                        padding: "6px 10px",
                                        fontSize: "1rem",
                                        width: "140px",
                                    }}
                                />
                            </td>
                            <td className="px-4 py-3" style={{ color: textSecondary }}>
                                {setting.default ?? "—"}
                            </td>
                            <td className="px-4 py-3">
                                <button
                                    onClick={() => save(setting.key)}
                                    style={{
                                        color: primary,
                                        background: "transparent",
                                        border: "none",
                                        fontWeight: 600,
                                        cursor: "pointer",
                                    }}
                                >
                                    Save
                                </button>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
