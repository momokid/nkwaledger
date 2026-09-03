import { router, usePage } from "@inertiajs/react";
import { IconBell } from "@tabler/icons-react";
import { useEffect, useRef, useState } from "react";

interface Note {
    uuid: string;
    kind: string;
    message: string;
    link: string | null;
    is_read: boolean;
    created_at: string;
}

interface Props {
    dark: boolean;
}

const when = (iso: string) => {
    const minutes = Math.floor((Date.now() - new Date(iso).getTime()) / 60000);

    if (minutes < 1) return "just now";
    if (minutes < 60) return `${minutes} min ago`;

    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours} hour${hours === 1 ? "" : "s"} ago`;

    const days = Math.floor(hours / 24);
    return `${days} day${days === 1 ? "" : "s"} ago`;
};

export default function NotificationBell({ dark }: Props) {
    const { auth } = usePage().props as {
        auth?: { unreadNotifications?: number };
    };
    const unread = auth?.unreadNotifications ?? 0;

    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [notes, setNotes] = useState<Note[]>([]);

    const boxRef = useRef<HTMLDivElement>(null);

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const unreadBg = dark ? "rgba(29,158,117,0.12)" : "#EAF5F0";
    const brand = "#1D9E75";
    const gold = "#BA7517";

    useEffect(() => {
        const away = (event: MouseEvent) => {
            if (
                boxRef.current &&
                !boxRef.current.contains(event.target as Node)
            ) {
                setOpen(false);
            }
        };

        document.addEventListener("mousedown", away);

        return () => document.removeEventListener("mousedown", away);
    }, []);

    const load = async () => {
        setLoading(true);

        try {
            const response = await fetch("/notifications", {
                headers: { Accept: "application/json" },
            });

            const body = await response.json();

            setNotes(body.data ?? []);
        } finally {
            setLoading(false);
        }
    };

    const toggle = () => {
        const next = !open;

        setOpen(next);

        if (next) load();
    };

    const patch = (url: string) =>
        fetch(url, {
            method: "PATCH",
            headers: {
                Accept: "application/json",
                "X-CSRF-TOKEN":
                    document.querySelector<HTMLMetaElement>(
                        'meta[name="csrf-token"]',
                    )?.content ?? "",
            },
        });

    const openOne = async (note: Note) => {
        await patch(`/notifications/${note.uuid}/read`);

        setOpen(false);

        if (note.link) {
            router.visit(note.link);
        } else {
            router.reload({ only: ["auth"] });
        }
    };

    const readAll = async () => {
        await patch("/notifications/read-all");

        await load();

        router.reload({ only: ["auth"] });
    };

    return (
        <div ref={boxRef} style={{ position: "relative" }}>
            <button
                onClick={toggle}
                aria-label="Notifications"
                style={{
                    background: "transparent",
                    border: "none",
                    cursor: "pointer",
                    padding: "6px",
                    display: "flex",
                    position: "relative",
                }}
            >
                <IconBell size={24} stroke={1.6} color={textSecondary} />

                {unread > 0 && (
                    <span
                        style={{
                            position: "absolute",
                            top: 0,
                            right: 0,
                            background: gold,
                            color: "#FFFFFF",
                            fontSize: "12px",
                            fontWeight: 700,
                            padding: "0 5px",
                            minWidth: "18px",
                            textAlign: "center",
                        }}
                    >
                        {unread > 9 ? "9+" : unread}
                    </span>
                )}
            </button>

            {open && (
                <div
                    style={{
                        position: "absolute",
                        top: "40px",
                        right: 0,
                        width: "340px",
                        maxHeight: "420px",
                        overflowY: "auto",
                        background: surface,
                        border: `1px solid ${border}`,
                        boxShadow: "0 6px 20px rgba(17, 24, 39, 0.15)",
                        zIndex: 70,
                        fontFamily: "'Inter', system-ui, sans-serif",
                    }}
                >
                    <div
                        className="flex items-center justify-between"
                        style={{
                            padding: "12px 14px",
                            borderBottom: `1px solid ${border}`,
                        }}
                    >
                        <span
                            style={{
                                fontSize: "17px",
                                fontWeight: 700,
                                color: text,
                            }}
                        >
                            Notifications
                        </span>

                        {unread > 0 && (
                            <button
                                onClick={readAll}
                                style={{
                                    background: "transparent",
                                    border: "none",
                                    color: brand,
                                    fontSize: "15px",
                                    cursor: "pointer",
                                    fontFamily: "inherit",
                                }}
                            >
                                Mark all read
                            </button>
                        )}
                    </div>

                    {loading && (
                        <p
                            style={{
                                padding: "18px 14px",
                                fontSize: "16px",
                                color: textSecondary,
                                margin: 0,
                            }}
                        >
                            Loading...
                        </p>
                    )}

                    {!loading && notes.length === 0 && (
                        <p
                            style={{
                                padding: "18px 14px",
                                fontSize: "16px",
                                color: textSecondary,
                                margin: 0,
                            }}
                        >
                            Nothing here yet.
                        </p>
                    )}

                    {!loading &&
                        notes.map((note) => (
                            <button
                                key={note.uuid}
                                onClick={() => openOne(note)}
                                style={{
                                    display: "block",
                                    width: "100%",
                                    textAlign: "left",
                                    padding: "12px 14px",
                                    borderTop: `1px solid ${border}`,
                                    borderLeft: "none",
                                    borderRight: "none",
                                    borderBottom: "none",
                                    background: note.is_read
                                        ? "transparent"
                                        : unreadBg,
                                    cursor: "pointer",
                                    fontFamily: "inherit",
                                }}
                            >
                                <span
                                    style={{
                                        display: "block",
                                        fontSize: "16px",
                                        lineHeight: 1.4,
                                        color: text,
                                        fontWeight: note.is_read ? 400 : 600,
                                    }}
                                >
                                    {note.message}
                                </span>
                                <span
                                    style={{
                                        display: "block",
                                        fontSize: "14px",
                                        color: textSecondary,
                                        marginTop: "3px",
                                    }}
                                >
                                    {when(note.created_at)}
                                </span>
                            </button>
                        ))}
                </div>
            )}
        </div>
    );
}
