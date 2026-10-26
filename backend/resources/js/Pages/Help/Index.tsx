import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import Tour from "@/Components/Tour";
import { tourFor } from "@/lib/tourSteps";
import { usePage } from "@inertiajs/react";
import { useState } from "react";

const TOPICS: { title: string; steps: string[] }[] = [
    {
        title: "Add a farm",
        steps: [
            "Tap My Farm.",
            "Tap the button to add a farm.",
            "Choose the type and give it a name.",
            "Save.",
        ],
    },
    {
        title: "Add a farm unit",
        steps: [
            "Open your farm.",
            "Tap the button to add a unit (for example a poultry house, a field or a pond).",
            "Say how many or how big.",
            "Save. Your agent may need to approve it.",
        ],
    },
    {
        title: "Record money or stock",
        steps: [
            "Tap Record.",
            "Pick what happened (sold, bought, lost, other).",
            "Fill in the amount and the date (DD/MM/YYYY).",
            "Save. You'll see it in My Money.",
        ],
    },
    {
        title: "Loans",
        steps: [
            "Tap Credit.",
            "See each loan, what it was for and what is still owed.",
            'Mark a purchase "Paid from a loan" when you record it.',
            "Print the loan statement.",
        ],
    },
    {
        title: "PIN and password",
        steps: [
            "The PIN is the 4 digits for USSD, and the password is for the website.",
            "Forgot your PIN: enter 0 at the USSD PIN prompt and your agent sets a temporary one.",
            'Forgot your password: tap "Forgot password" on the sign-in page.',
            "Never tell anyone either.",
        ],
    },
    {
        title: "Reports",
        steps: [
            "Tap Reports.",
            "Choose the dates.",
            "Print it or show it to your agent or lender.",
        ],
    },
];

export default function Index({ supportPhone }: { supportPhone: string | null }) {
    return (
        <AuthenticatedLayout title="Help">
            <IndexContent supportPhone={supportPhone} />
        </AuthenticatedLayout>
    );
}

function IndexContent({ supportPhone }: { supportPhone: string | null }) {
    const { dark } = useTheme();

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";

    const { auth } = usePage().props as unknown as { auth?: { user?: { roles?: string[] } } };
    const steps = tourFor(auth?.user?.roles?.[0] ?? "farmer");
    const [touring, setTouring] = useState(false);
    const topics = supportPhone
        ? [...TOPICS, { title: "Need a person?", steps: [`Call or WhatsApp ${supportPhone}, 24 hours.`] }]
        : TOPICS;

    return (
        <div className="p-6">
            <p style={{ fontSize: "1.625rem", fontWeight: 700, color: text, marginBottom: "16px" }}>
                Help
            </p>
            {steps.length > 0 && (
                <button
                    onClick={() => setTouring(true)}
                    style={{
                        padding: "12px 20px",
                        fontSize: "1.125rem",
                        fontWeight: 600,
                        color: "#FFFFFF",
                        background: "#1D9E75",
                        border: "1px solid #1D9E75",
                        cursor: "pointer",
                        marginBottom: "16px",
                    }}
                >
                    Take the tour
                </button>
            )}
            {touring && <Tour steps={steps} onClose={() => setTouring(false)} />}
            {topics.map((topic) => (
                <details
                    key={topic.title}
                    style={{ background: surface, border: `1px solid ${border}`, marginBottom: "8px" }}
                >
                    <summary
                        style={{ padding: "16px 24px", fontSize: "1.125rem", fontWeight: 600, color: text, cursor: "pointer" }}
                    >
                        {topic.title}
                    </summary>
                    <ol style={{ padding: "0 24px 16px 44px", color: textSecondary, fontSize: "1.0625rem", listStyle: "decimal" }}>
                        {topic.steps.map((step) => (
                            <li key={step} style={{ marginBottom: "4px" }}>{step}</li>
                        ))}
                    </ol>
                </details>
            ))}
        </div>
    );
}
