import { usePage } from "@inertiajs/react";
import {
    IconAlertTriangle,
    IconCircleCheck,
    IconInfoCircle,
    IconX,
} from "@tabler/icons-react";
import { useEffect, useState } from "react";

interface Flash {
    success?: string | null;
    error?: string | null;
    status?: string | null;
}

type Tone = "success" | "error" | "status";

const TONES = {
    success: { accent: "#1D9E75", icon: IconCircleCheck },
    error: { accent: "#DC2626", icon: IconAlertTriangle },
    status: { accent: "#BA7517", icon: IconInfoCircle },
};

// an error outranks the rest, since it is the one a person must not miss
const ORDER: Tone[] = ["error", "success", "status"];

export default function FlashMessages() {
    const { flash } = usePage().props as { flash?: Flash };

    const tone = ORDER.find((key) => flash?.[key]) ?? null;
    const message = tone ? flash?.[tone] : null;

    const [visible, setVisible] = useState(false);

    // a fresh message restarts the timer, so two in a row are both readable
    useEffect(() => {
        if (!message) {
            setVisible(false);
            return;
        }

        setVisible(true);

        const timer = setTimeout(() => setVisible(false), 6000);

        return () => clearTimeout(timer);
    }, [message, tone]);

    if (!message || !tone || !visible) {
        return null;
    }

    const { accent, icon: Icon } = TONES[tone];

    return (
        <div
            role="status"
            aria-live="polite"
            style={{
                position: "fixed",
                top: "20px",
                right: "20px",
                left: "20px",
                maxWidth: "420px",
                marginLeft: "auto",
                zIndex: 80,
                display: "flex",
                alignItems: "flex-start",
                gap: "12px",
                background: "#FFFFFF",
                border: "1px solid #E5E7EB",
                borderLeft: `4px solid ${accent}`,
                padding: "14px 16px",
                boxShadow: "0 4px 16px rgba(17, 24, 39, 0.12)",
                fontFamily: "'Inter', system-ui, sans-serif",
            }}
        >
            <Icon
                size={22}
                color={accent}
                stroke={1.8}
                style={{ flexShrink: 0 }}
            />

            <p
                style={{
                    margin: 0,
                    flex: 1,
                    fontSize: "16px",
                    lineHeight: 1.45,
                    color: "#111827",
                }}
            >
                {message}
            </p>

            <button
                type="button"
                onClick={() => setVisible(false)}
                aria-label="Dismiss"
                style={{
                    background: "none",
                    border: "none",
                    cursor: "pointer",
                    padding: 0,
                    display: "flex",
                    flexShrink: 0,
                }}
            >
                <IconX size={18} color="#9CA3AF" />
            </button>
        </div>
    );
}
