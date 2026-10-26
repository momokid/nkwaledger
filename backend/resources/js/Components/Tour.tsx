import { useEffect, useState } from "react";
import { TourStep } from "@/lib/tourSteps";

interface Props {
    steps: TourStep[];
    onClose: () => void;
}

const OUTLINE = "3px solid #1D9E75";

export default function Tour({ steps, onClose }: Props) {
    const [index, setIndex] = useState(0);
    const step = steps[index];
    const last = index === steps.length - 1;

    useEffect(() => {
        const items = Array.from(document.querySelectorAll<HTMLElement>(`[data-tour="${step.target}"]`));
        items.forEach((el) => {
            el.style.outline = OUTLINE;
            el.scrollIntoView?.({ block: "nearest" });
        });
        return () => items.forEach((el) => (el.style.outline = ""));
    }, [step.target]);

    const button = (label: string, onClick: () => void, primary = false) => (
        <button
            onClick={onClick}
            style={{
                padding: "10px 16px",
                fontSize: "1.0625rem",
                fontWeight: 600,
                cursor: "pointer",
                color: primary ? "#FFFFFF" : "#111827",
                background: primary ? "#1D9E75" : "#FFFFFF",
                border: `1px solid ${primary ? "#1D9E75" : "#D1D5DB"}`,
            }}
        >
            {label}
        </button>
    );

    return (
        <div
            role="dialog"
            style={{
                position: "fixed",
                left: "50%",
                bottom: "24px",
                transform: "translateX(-50%)",
                width: "min(92vw, 420px)",
                zIndex: 100,
                background: "#FFFFFF",
                color: "#111827",
                border: "1px solid #D1D5DB",
                boxShadow: "0 8px 24px rgba(0,0,0,0.25)",
                padding: "20px",
            }}
        >
            <p style={{ fontSize: "0.9375rem", color: "#6B7280", marginBottom: "4px" }}>
                {index + 1} / {steps.length}
            </p>
            <p style={{ fontSize: "1.125rem", fontWeight: 600, marginBottom: "16px" }}>{step.text}</p>
            <div style={{ display: "flex", gap: "8px", justifyContent: "space-between" }}>
                {button("Skip tour", onClose)}
                <div style={{ display: "flex", gap: "8px" }}>
                    {index > 0 && button("Back", () => setIndex(index - 1))}
                    {last ? button("Done", onClose, true) : button("Next", () => setIndex(index + 1), true)}
                </div>
            </div>
        </div>
    );
}
