import { useTheme } from "@/Layouts/AuthenticatedLayout";
import { ButtonHTMLAttributes, ReactNode } from "react";

type Look = "primary" | "secondary" | "danger";
type Size = "normal" | "small";

interface Props extends ButtonHTMLAttributes<HTMLButtonElement> {
    look?: Look;
    size?: Size;
    // shown in place of the label while the action is running
    busy?: boolean;
    busyLabel?: string;
    children: ReactNode;
}

export default function Button({
    look = "primary",
    size = "normal",
    busy = false,
    busyLabel = "Working...",
    disabled,
    children,
    style,
    ...rest
}: Props) {
    const { dark } = useTheme();

    const brand = "#1D9E75";
    const danger = "#DC2626";
    const text = dark ? "#F9FAFB" : "#111827";

    const looks: Record<
        Look,
        { background: string; color: string; border: string }
    > = {
        primary: {
            background: brand,
            color: "#FFFFFF",
            border: `1px solid ${brand}`,
        },
        secondary: {
            background: "transparent",
            color: text,
            border: `1px solid ${dark ? "#4B5563" : "#9CA3AF"}`,
        },
        danger: {
            background: "transparent",
            color: danger,
            border: `1px solid ${danger}`,
        },
    };

    const sizes: Record<Size, { padding: string; fontSize: string }> = {
        normal: { padding: "11px 22px", fontSize: "18px" },
        small: { padding: "7px 14px", fontSize: "16px" },
    };

    const off = busy || disabled;

    return (
        <button
            {...rest}
            disabled={off}
            style={{
                ...looks[look],
                ...sizes[size],
                fontWeight: 600,
                fontFamily: "inherit",
                cursor: off ? "default" : "pointer",
                opacity: off ? 0.6 : 1,
                whiteSpace: "nowrap",
                ...style,
            }}
        >
            {busy ? busyLabel : children}
        </button>
    );
}
