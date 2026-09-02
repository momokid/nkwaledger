import { useTheme } from "@/Layouts/AuthenticatedLayout";
import { ButtonHTMLAttributes, ReactNode } from "react";

type Look = "main" | "primary" | "secondary" | "danger";
type Size = "normal" | "small";

interface Props extends ButtonHTMLAttributes<HTMLButtonElement> {
    look?: Look;
    size?: Size;
    // shown instead of the label while something is happening
    busy?: boolean;
    busyLabel?: string;
    children: ReactNode;
}

export default function Button({
    look = "main",
    size = "normal",
    busy = false,
    busyLabel = "Saving...",
    disabled,
    children,
    style,
    ...rest
}: Props) {
    const { dark } = useTheme();

    const brand = "#1D9E75";
    const danger = "#DC2626";
    const text = dark ? "#F9FAFB" : "#111827";

    // every action looks pressable, so nothing is mistaken for a label
    const base = {
        display: "inline-flex",
        alignItems: "center",
        justifyContent: "center",
        gap: "8px",
        border: "1px solid transparent",
        fontFamily: "inherit",
        fontWeight: 600,
        cursor: disabled || busy ? "not-allowed" : "pointer",
        opacity: disabled || busy ? 0.55 : 1,
        whiteSpace: "nowrap" as const,
    };

    const sizes: Record<Size, { padding: string; fontSize: string }> = {
        normal: { padding: "11px 22px", fontSize: "18px" },
        small: { padding: "7px 14px", fontSize: "16px" },
    };

        const looks: Record<Look, { background: string; color: string; borderColor: string }> = {
        main: { background: brand, color: "#FFFFFF", borderColor: brand },
        primary: { background: brand, color: "#FFFFFF", borderColor: brand },
        secondary: {
            background: "transparent",
            color: brand,
            borderColor: brand,
        },
        danger: {
            background: "transparent",
            color: danger,
            borderColor: danger,
        },
    };
    return (
        <button
            {...rest}
            disabled={disabled || busy}
            style={{
                ...base,
                ...sizes[size],
                background: looks[look].background,
                color: looks[look].color,
                borderColor: looks[look].borderColor,
                ...style,
            }}
        >
            {busy ? busyLabel : children}
        </button>
    );
}
