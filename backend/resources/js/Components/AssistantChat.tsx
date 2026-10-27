import axios from "axios";
import { Fragment, ReactNode, useState } from "react";

interface Line {
    from: "user" | "bot";
    text: string;
}

const GREETING = "Hello! Ask me how to use NkwaLedger. For example: How do I add a farm?";
const LIMIT_WORDS = "You have used your 50 questions for today.";
const FAILED_WORDS = "Sorry, I cannot answer right now.";
const MAX_LENGTH = 500;
const PAGE_LINK = /(^|\s)(\/[a-z][a-z0-9-]*(?:\/[a-z0-9-]+)*)/g;

const callUs = (phone: string | null) => (phone ? ` Please call or WhatsApp ${phone}, 24 hours.` : "");

// only paths written like /my-farm become links, so the bot cannot send people to another website
const withLinks = (text: string): ReactNode[] => {
    const parts: ReactNode[] = [];
    let last = 0;

    for (const match of text.matchAll(PAGE_LINK)) {
        const start = (match.index ?? 0) + match[1].length;
        parts.push(text.slice(last, start));
        parts.push(
            <a key={start} href={match[2]} style={{ color: "#0F6E56", textDecoration: "underline" }}>
                {match[2]}
            </a>,
        );
        last = start + match[2].length;
    }
    parts.push(text.slice(last));

    return parts.map((part, i) => <Fragment key={i}>{part}</Fragment>);
};

export default function AssistantChat() {
    const [open, setOpen] = useState(false);
    const [lines, setLines] = useState<Line[]>([{ from: "bot", text: GREETING }]);
    const [draft, setDraft] = useState("");
    const [waiting, setWaiting] = useState(false);

    const canSend = !waiting && draft.trim().length > 0 && draft.length <= MAX_LENGTH;

    const send = async () => {
        const message = draft.trim();
        setDraft("");
        setWaiting(true);
        setLines((current) => [...current, { from: "user", text: message }]);

        try {
            const { data } = await axios.post("/assistant", { message });
            setLines((current) => [...current, { from: "bot", text: data.reply }]);
        } catch (error) {
            const response = (error as { response?: { status?: number; data?: { support_phone?: string | null } } }).response;
            const phone = response?.data?.support_phone ?? null;
            const words = response?.status === 429 ? LIMIT_WORDS : FAILED_WORDS;
            setLines((current) => [...current, { from: "bot", text: words + callUs(phone) }]);
        } finally {
            setWaiting(false);
        }
    };

    const base = { fontSize: "1.0625rem", fontFamily: "'Inter', system-ui, sans-serif" };

    if (!open) {
        return (
            <button
                onClick={() => setOpen(true)}
                style={{
                    ...base,
                    position: "fixed",
                    right: "16px",
                    bottom: "16px",
                    zIndex: 90,
                    padding: "12px 18px",
                    fontWeight: 600,
                    color: "#FFFFFF",
                    background: "#1D9E75",
                    border: "1px solid #0F6E56",
                    cursor: "pointer",
                }}
            >
                Ask for help
            </button>
        );
    }

    return (
        <div
            role="dialog"
            style={{
                ...base,
                position: "fixed",
                right: "16px",
                bottom: "16px",
                zIndex: 90,
                width: "min(92vw, 380px)",
                maxHeight: "80vh",
                display: "flex",
                flexDirection: "column",
                background: "#FFFFFF",
                color: "#111827",
                border: "1px solid #D1D5DB",
                boxShadow: "0 8px 24px rgba(0,0,0,0.25)",
            }}
        >
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", padding: "12px 16px", borderBottom: "1px solid #E5E7EB" }}>
                <strong>Help assistant</strong>
                <button onClick={() => setOpen(false)} style={{ ...base, cursor: "pointer", background: "transparent", border: "none", color: "#111827" }}>
                    Close
                </button>
            </div>
            <div style={{ padding: "12px 16px", overflowY: "auto", flex: 1 }}>
                {lines.map((line, i) => (
                    <p
                        key={i}
                        style={{
                            marginBottom: "10px",
                            padding: "8px 12px",
                            whiteSpace: "pre-wrap",
                            marginLeft: line.from === "user" ? "32px" : 0,
                            marginRight: line.from === "bot" ? "32px" : 0,
                            background: line.from === "user" ? "#EAF5F0" : "#F3F4F6",
                        }}
                    >
                        {line.from === "bot" ? withLinks(line.text) : line.text}
                    </p>
                ))}
                {waiting && <p style={{ color: "#6B7280" }}>Thinking...</p>}
            </div>
            <div style={{ padding: "12px 16px", borderTop: "1px solid #E5E7EB" }}>
                <textarea
                    value={draft}
                    onChange={(e) => setDraft(e.target.value)}
                    placeholder="Type your question"
                    rows={2}
                    style={{ ...base, width: "100%", padding: "8px", border: "1px solid #D1D5DB", color: "#111827", background: "#FFFFFF" }}
                />
                <p style={{ fontSize: "0.875rem", color: "#6B7280", margin: "6px 0 8px" }}>
                    Answers come from an AI. Your question is sent to an AI provider. Never type your PIN or password.
                </p>
                <button
                    onClick={send}
                    disabled={!canSend}
                    style={{
                        ...base,
                        padding: "10px 18px",
                        fontWeight: 600,
                        color: "#FFFFFF",
                        background: canSend ? "#1D9E75" : "#9CA3AF",
                        border: "none",
                        cursor: canSend ? "pointer" : "not-allowed",
                    }}
                >
                    Send
                </button>
            </div>
        </div>
    );
}
