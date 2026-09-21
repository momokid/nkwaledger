import { useTheme } from "@/Layouts/AuthenticatedLayout";
import { IconX } from "@tabler/icons-react";
import { PropsWithChildren } from "react";

interface Props {
    open: boolean;
    title: string;
    onClose: () => void;
    width?: string;
}

export default function Drawer({
    open,
    title,
    onClose,
    width = "480px",
    children,
}: PropsWithChildren<Props>) {
    const { dark } = useTheme();

    const overlay = "rgba(0,0,0,0.5)";
    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";

    if (!open) return null;

    return (
        <div
            onClick={onClose}
            style={{
                position: "fixed",
                inset: 0,
                background: overlay,
                zIndex: 100,
                display: "flex",
                justifyContent: "flex-end",
            }}
        >
            <div
                onClick={(event) => event.stopPropagation()}
                style={{
                    background: surface,
                    borderLeft: `1px solid ${border}`,
                    width,
                    maxWidth: "100%",
                    height: "100%",
                    display: "flex",
                    flexDirection: "column",
                    animation: "drawer-slide-in 0.2s ease-out",
                }}
            >
                <div
                    style={{
                        padding: "16px 20px",
                        borderBottom: `1px solid ${border}`,
                        display: "flex",
                        justifyContent: "space-between",
                        alignItems: "center",
                    }}
                >
                    <p
                        style={{
                            fontSize: "1.25rem",
                            fontWeight: 700,
                            color: text,
                            margin: 0,
                        }}
                    >
                        {title}
                    </p>
                    <button
                        onClick={onClose}
                        aria-label="Close"
                        style={{
                            background: "transparent",
                            border: "none",
                            color: textSecondary,
                            cursor: "pointer",
                            display: "flex",
                            padding: "4px",
                        }}
                    >
                        <IconX size={22} stroke={1.6} />
                    </button>
                </div>

                <div style={{ overflowY: "auto", flex: 1, padding: "20px" }}>
                    {children}
                </div>
            </div>
            <style>{`
                @keyframes drawer-slide-in {
                    from { transform: translateX(100%); }
                    to { transform: translateX(0); }
                }
            `}</style>
        </div>
    );
}
