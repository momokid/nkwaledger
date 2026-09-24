import { Link } from "@inertiajs/react";
import { useTheme } from "@/Layouts/AuthenticatedLayout";

interface Props {
    fallbackHref: string;
}

// there is nothing to go back to once this is the first entry in the tab's own
// history (e.g. opened directly, or reloaded) - render a real link instead
export function shouldRenderFallbackLink(historyLength: number): boolean {
    return historyLength <= 1;
}

export function handleBackClick(historyLength: number, goBack: () => void): void {
    if (historyLength > 1) {
        goBack();
    }
}

export default function BackLink({ fallbackHref }: Props) {
    const { dark } = useTheme();
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";

    const style = {
        display: "inline-flex",
        alignItems: "center",
        gap: "6px",
        marginBottom: "12px",
        fontSize: "1rem",
        color: textSecondary,
        textDecoration: "none",
        background: "none",
        border: "none",
        padding: 0,
        cursor: "pointer",
        fontFamily: "'Inter', system-ui, sans-serif",
    };

    if (shouldRenderFallbackLink(window.history.length)) {
        return (
            <Link href={fallbackHref} style={style}>
                ← Back
            </Link>
        );
    }

    return (
        <button
            type="button"
            onClick={() => handleBackClick(window.history.length, () => window.history.back())}
            style={style}
        >
            ← Back
        </button>
    );
}
