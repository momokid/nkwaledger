import { useEffect, useRef, useState } from "react";
import Button from "@/Components/Button";
import { useTheme } from "@/Layouts/AuthenticatedLayout";
import { DataCostAnswer, setDataCostAsker } from "@/lib/dataCostGate";
import { DATA_COST_TEXT } from "@/lib/dataCostText";

// a plain dialog with nothing from the farmer's records in it, so it is safe to show anywhere, offline too
export default function DataCostDialog() {
    const { dark } = useTheme();
    const [text, setText] = useState<string | null>(null);
    const pending = useRef<((answer: DataCostAnswer) => void) | null>(null);

    useEffect(() => {
        setDataCostAsker(
            (warning) =>
                new Promise<DataCostAnswer>((resolve) => {
                    pending.current = resolve;
                    setText(warning);
                }),
        );

        return () => {
            setDataCostAsker(null);
            pending.current?.("dismissed");
            pending.current = null;
        };
    }, []);

    if (text === null) return null;

    const answer = (choice: DataCostAnswer) => {
        pending.current?.(choice);
        pending.current = null;
        setText(null);
    };

    return (
        <div
            role="dialog"
            aria-modal="true"
            style={{
                position: "fixed",
                inset: 0,
                zIndex: 60,
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
                padding: "16px",
                background: "rgba(17,24,39,0.6)",
            }}
        >
            <div
                style={{
                    width: "100%",
                    maxWidth: "420px",
                    padding: "24px",
                    background: dark ? "#1F2937" : "#FFFFFF",
                    border: `1px solid ${dark ? "#374151" : "#E5E7EB"}`,
                }}
            >
                <p style={{ fontSize: "1.125rem", color: dark ? "#F9FAFB" : "#111827", margin: 0 }}>{text}</p>
                <div className="flex mt-5" style={{ gap: "12px", flexWrap: "wrap" }}>
                    <Button type="button" onClick={() => answer("send")}>
                        {DATA_COST_TEXT.send}
                    </Button>
                    <Button type="button" look="secondary" onClick={() => answer("wait")}>
                        {DATA_COST_TEXT.wait}
                    </Button>
                </div>
            </div>
        </div>
    );
}
