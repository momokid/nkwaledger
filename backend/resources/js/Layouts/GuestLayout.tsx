import { IconCheck, IconPlant } from "@tabler/icons-react";
import { PropsWithChildren } from "react";

export default function GuestLayout({ children }: PropsWithChildren) {
    return (
        <div
            className="min-h-screen flex items-stretch bg-gray-100"
            style={{ fontFamily: "'Inter', system-ui, sans-serif" }}
        >
            <div
                className="hidden lg:flex lg:w-[238px] xl:w-[280px] flex-shrink-0 flex-col"
                style={{ background: "#0F6E56" }}
            >
                <div className="flex flex-col h-full p-8">
                    <div className="flex items-center gap-3 mb-7">
                        <div
                            className="w-9 h-9 flex items-center justify-center flex-shrink-0"
                            style={{ background: "#BA7517" }}
                        >
                            <IconPlant size={20} color="#fff" />
                        </div>
                        <div>
                            <div className="text-white font-bold text-base tracking-tight">
                                NkwaLedger
                            </div>
                            <div
                                className="text-xs font-semibold uppercase tracking-widest"
                                style={{
                                    color: "rgba(255,255,255,0.45)",
                                    fontSize: "10px",
                                }}
                            >
                                Farm Finance Platform
                            </div>
                        </div>
                    </div>

                    <p
                        className="font-bold text-lg leading-snug mb-2 tracking-tight"
                        style={{ color: "#fff" }}
                    >
                        Your farm's financial story, in one place.
                    </p>
                    <p
                        className="text-sm leading-relaxed mb-8"
                        style={{ color: "rgba(255,255,255,0.62)" }}
                    >
                        Track income, crops, and livestock. Build a bankable
                        credit profile for your farm.
                    </p>

                    <div className="mt-auto flex flex-col gap-3">
                        {[
                            "MTN MoMo payments integrated",
                            "AI-powered VetAI and CropAI",
                            "Trusted by farmers across Ghana",
                        ].map((text) => (
                            <div key={text} className="flex items-center gap-3">
                                <div
                                    className="w-[18px] h-[18px] flex items-center justify-center flex-shrink-0"
                                    style={{
                                        background: "rgba(255,255,255,0.12)",
                                    }}
                                >
                                    <IconCheck size={11} color="#fff" />
                                </div>
                                <span
                                    className="text-xs"
                                    style={{ color: "rgba(255,255,255,0.75)" }}
                                >
                                    {text}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>
            </div>

            <div className="flex-1 flex flex-col justify-center px-6 py-12 sm:px-10 bg-white">
                <div className="lg:hidden flex items-center gap-3 mb-8">
                    <div
                        className="w-8 h-8 flex items-center justify-center flex-shrink-0"
                        style={{ background: "#BA7517" }}
                    >
                        <IconPlant size={16} color="#fff" />
                    </div>
                    <div>
                        <div
                            className="font-bold text-sm tracking-tight"
                            style={{ color: "#0F6E56" }}
                        >
                            NkwaLedger
                        </div>
                        <div
                            className="font-semibold uppercase tracking-widest"
                            style={{
                                color: "#0F6E56",
                                fontSize: "9px",
                                opacity: 0.6,
                            }}
                        >
                            Farm Finance Platform
                        </div>
                    </div>
                </div>

                <div className="w-full max-w-md mx-auto">{children}</div>
            </div>
        </div>
    );
}
