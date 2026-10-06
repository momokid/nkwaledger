import { useTheme } from "@/Layouts/AuthenticatedLayout";

export default function LogoutBlockedBanner({ show }: { show: boolean }) {
    const { dark } = useTheme();

    if (!show) return null;

    return (
        <div
            role="alert"
            style={{
                marginBottom: "16px",
                padding: "12px 14px",
                background: dark ? "rgba(186,117,23,0.15)" : "#FEF3C7",
                borderLeft: "4px solid #BA7517",
                fontSize: "1rem",
                color: dark ? "#FCD34D" : "#92400E",
            }}
        >
            You have records not sent yet. Connect to the internet and wait for
            them to send before you sign out.
        </div>
    );
}
