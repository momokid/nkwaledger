import Button from "@/Components/Button";
import { useTheme } from "@/Layouts/AuthenticatedLayout";

export default function SessionEndedBanner({ show }: { show: boolean }) {
    const { dark } = useTheme();

    if (!show) return null;

    return (
        <div
            role="alert"
            style={{
                marginBottom: "16px",
                padding: "12px 14px",
                display: "flex",
                flexWrap: "wrap",
                alignItems: "center",
                gap: "12px",
                background: dark ? "rgba(186,117,23,0.15)" : "#FEF3C7",
                borderLeft: "4px solid #BA7517",
                fontSize: "1rem",
                color: dark ? "#FCD34D" : "#92400E",
            }}
        >
            <span style={{ flex: 1 }}>
                Your session has ended. Your saved records are safe on this
                device. Sign in to send them.
            </span>
            <Button
                type="button"
                onClick={() => window.location.assign("/login")}
            >
                Sign in
            </Button>
        </div>
    );
}
