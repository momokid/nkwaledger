import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";

type Size = "normal" | "large" | "extra-large";

const SIZES: Size[] = ["normal", "large", "extra-large"];

export default function Index() {
    return (
        <AuthenticatedLayout title="Settings">
            <IndexContent />
        </AuthenticatedLayout>
    );
}

function IndexContent() {
    const { dark, textSize, setTextSize } = useTheme();

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";

    return (
        <div className="p-6">
            <p style={{ fontSize: "1.625rem", fontWeight: 700, color: text, marginBottom: "16px" }}>
                Settings
            </p>
            <div style={{ background: surface, border: `1px solid ${border}`, padding: "16px 24px" }}>
                <p style={{ fontSize: "1.125rem", fontWeight: 600, color: text, marginBottom: "12px" }}>
                    Text size
                </p>
                <div style={{ display: "flex", gap: "8px" }}>
                    {SIZES.map((size, index) => (
                        <button
                            key={size}
                            onClick={() => setTextSize(size)}
                            aria-pressed={textSize === size}
                            title={size.replace("-", " ").replace(/^\w/, (c) => c.toUpperCase())}
                            style={{
                                flex: 1,
                                padding: "12px",
                                fontSize: `${1 + index * 0.25}rem`,
                                fontWeight: 700,
                                color: textSize === size ? "#FFFFFF" : text,
                                background: textSize === size ? "#1D9E75" : "transparent",
                                border: `1px solid ${textSize === size ? "#1D9E75" : border}`,
                                cursor: "pointer",
                            }}
                        >
                            A
                        </button>
                    ))}
                </div>
            </div>
        </div>
    );
}
