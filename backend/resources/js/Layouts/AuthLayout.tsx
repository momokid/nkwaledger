import { IconCheck, IconPlant } from "@tabler/icons-react";
import { PropsWithChildren } from "react";

interface Props extends PropsWithChildren {
    showBrandPanel?: boolean;
    brandPanelSlot?: React.ReactNode;
}

export default function AuthLayout({
    children,
    showBrandPanel = true,
    brandPanelSlot,
}: Props) {
    return (
        <div
            className="min-h-screen flex bg-gray-50"
            style={{
                fontFamily:
                    "'Inter', 'Segoe UI Variable', 'Segoe UI', system-ui, sans-serif",
            }}
        >
            <div className="w-full">
                <div className="flex overflow-hidden border min-h-[calc(100vh-3rem)]">
                    {showBrandPanel && (
                        <div
                            className="hidden lg:flex flex-col flex-shrink-0 p-8"
                            style={{ width: "238px", background: "#0F6E56" }}
                        >
                            <div className="flex items-center gap-3 mb-7">
                                <div
                                    className="flex items-center justify-center flex-shrink-0"
                                    style={{
                                        width: "36px",
                                        height: "36px",
                                        background: "#BA7517",
                                    }}
                                >
                                    <IconPlant size={20} color="#fff" />
                                </div>
                                <div>
                                    <div
                                        style={{
                                            fontSize: "16px",
                                            fontWeight: 700,
                                            color: "#fff",
                                            letterSpacing: "-0.2px",
                                        }}
                                    >
                                        NkwaLedger
                                    </div>
                                    <div
                                        style={{
                                            fontSize: "10px",
                                            fontWeight: 600,
                                            color: "rgba(255,255,255,0.45)",
                                            letterSpacing: "0.8px",
                                            textTransform: "uppercase",
                                        }}
                                    >
                                        Farm Finance Platform
                                    </div>
                                </div>
                            </div>

                            <p
                                style={{
                                    margin: "0 0 8px",
                                    fontSize: "18px",
                                    fontWeight: 700,
                                    color: "#fff",
                                    lineHeight: 1.4,
                                    letterSpacing: "-0.3px",
                                }}
                            >
                                Your farm's financial story, in one place.
                            </p>
                            <p
                                style={{
                                    margin: "0 0 1.5rem",
                                    fontSize: "13px",
                                    color: "rgba(255,255,255,0.62)",
                                    lineHeight: 1.65,
                                }}
                            >
                                Track income, crops, and livestock. Build a
                                bankable credit profile for your farm.
                            </p>

                            {brandPanelSlot && (
                                <div className="mb-6">{brandPanelSlot}</div>
                            )}

                            <div className="mt-auto flex flex-col gap-3">
                                {[
                                    "MTN MoMo payments integrated",
                                    "AI-powered VetAI and CropAI",
                                    "Trusted by farmers across Ghana",
                                ].map((text) => (
                                    <div
                                        key={text}
                                        className="flex items-center gap-3"
                                    >
                                        <div
                                            className="flex items-center justify-center flex-shrink-0"
                                            style={{
                                                width: "18px",
                                                height: "18px",
                                                background:
                                                    "rgba(255,255,255,0.12)",
                                            }}
                                        >
                                            <IconCheck size={11} color="#fff" />
                                        </div>
                                        <span
                                            style={{
                                                fontSize: "12px",
                                                color: "rgba(255,255,255,0.75)",
                                            }}
                                        >
                                            {text}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    <div className="flex-1 bg-white">
                        <div className="lg:hidden flex items-center gap-3 px-9 pt-9">
                            <div
                                className="flex items-center justify-center flex-shrink-0"
                                style={{
                                    width: "30px",
                                    height: "30px",
                                    background: "#BA7517",
                                }}
                            >
                                <IconPlant size={15} color="#fff" />
                            </div>
                            <div>
                                <div
                                    style={{
                                        fontSize: "15px",
                                        fontWeight: 700,
                                        color: "#0F6E56",
                                        letterSpacing: "-0.2px",
                                    }}
                                >
                                    NkwaLedger
                                </div>
                                <div
                                    style={{
                                        fontSize: "9px",
                                        fontWeight: 600,
                                        color: "#0F6E56",
                                        opacity: 0.6,
                                        letterSpacing: "0.8px",
                                        textTransform: "uppercase",
                                    }}
                                >
                                    Farm Finance Platform
                                </div>
                            </div>
                        </div>

                        <div className="p-9">{children}</div>
                    </div>
                </div>
            </div>
        </div>
    );
}
