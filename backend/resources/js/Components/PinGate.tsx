import { IconArrowRight, IconLogout } from "@tabler/icons-react";
import { FormEvent, ReactNode, useEffect, useState } from "react";
import LogoutBlockedBanner from "@/Components/LogoutBlockedBanner";
import useSafeLogout from "@/hooks/useSafeLogout";
import { getPinRecord, isPinAllowed, isUnlocked, markUnlocked, savePin, verifyPin } from "@/lib/pin";
import { PIN_TEXT } from "@/lib/pinText";

type Status = "loading" | "setup" | "enter" | "locked";

const card = {
    maxWidth: "360px",
    margin: "20vh auto 0",
    padding: "24px",
    background: "#FFFFFF",
    border: "1px solid #E5E7EB",
    color: "#111827",
    fontFamily: "'Inter', system-ui, sans-serif",
} as const;

const iconButton = {
    background: "#1D9E75",
    color: "#FFFFFF",
    border: "none",
    padding: "10px 14px",
    cursor: "pointer",
} as const;

// everything a signed-in page shows waits behind this: while locked its children are not rendered,
// so nothing under them (layouts, sync, the queue) runs
export default function PinGate({ user, children }: { user: { id: number } | null | undefined; children: ReactNode }) {
    const userId = user ? String(user.id) : null;
    const [unlocked, setUnlocked] = useState(() => userId !== null && isUnlocked(userId));
    const [status, setStatus] = useState<Status>("loading");

    useEffect(() => {
        if (userId !== null) {
            setUnlocked(isUnlocked(userId));
        }
    }, [userId]);

    useEffect(() => {
        if (userId === null || unlocked) {
            return;
        }

        let current = true;

        void getPinRecord(userId).then((record) => {
            if (current) {
                setStatus(!record ? "setup" : record.locked ? "locked" : "enter");
            }
        });

        return () => {
            current = false;
        };
    }, [userId, unlocked]);

    if (userId === null || unlocked) {
        return <>{children}</>;
    }

    if (status === "loading") {
        return null;
    }

    if (status === "locked") {
        return <LockedScreen userId={userId} />;
    }

    return (
        <PinScreen
            userId={userId}
            setup={status === "setup"}
            onUnlocked={() => {
                markUnlocked(userId);
                setUnlocked(true);
            }}
            onLocked={() => setStatus("locked")}
            onMissing={() => setStatus("setup")}
        />
    );
}

function PinScreen({
    userId,
    setup,
    onUnlocked,
    onLocked,
    onMissing,
}: {
    userId: string;
    setup: boolean;
    onUnlocked: () => void;
    onLocked: () => void;
    onMissing: () => void;
}) {
    const [value, setValue] = useState("");
    const [first, setFirst] = useState<string | null>(null);
    const [message, setMessage] = useState<string | null>(null);

    const submit = async (event: FormEvent) => {
        event.preventDefault();

        const entered = value;
        setValue("");

        if (setup) {
            if (first === null) {
                if (!isPinAllowed(entered)) {
                    setMessage(PIN_TEXT.weak);

                    return;
                }

                setFirst(entered);
                setMessage(null);

                return;
            }

            if (entered !== first) {
                setFirst(null);
                setMessage(PIN_TEXT.mismatch);

                return;
            }

            await savePin(userId, entered);
            onUnlocked();

            return;
        }

        const check = await verifyPin(userId, entered);

        if (check === "ok") {
            onUnlocked();
        } else if (check === "locked") {
            onLocked();
        } else if (check === "none") {
            onMissing();
        } else {
            setMessage(PIN_TEXT.wrong);
        }
    };

    const label = !setup ? PIN_TEXT.enter : first === null ? PIN_TEXT.create : PIN_TEXT.confirm;

    return (
        <form onSubmit={submit} style={card}>
            <label htmlFor="nkwa-pin" style={{ display: "block", fontSize: "1.125rem", fontWeight: 600, marginBottom: "8px" }}>
                {label}
            </label>
            <div style={{ display: "flex", gap: "8px" }}>
                <input
                    id="nkwa-pin"
                    type="password"
                    inputMode="numeric"
                    autoComplete="off"
                    maxLength={4}
                    autoFocus
                    value={value}
                    onChange={(event) => setValue(event.target.value)}
                    style={{ flex: 1, padding: "10px 12px", border: "1px solid #9CA3AF", fontSize: "1.25rem", letterSpacing: "0.4em" }}
                />
                <button type="submit" style={iconButton}>
                    <IconArrowRight size={22} />
                </button>
            </div>
            {message && (
                <p role="alert" style={{ color: "#B91C1C", marginTop: "10px" }}>
                    {message}
                </p>
            )}
        </form>
    );
}

function LockedScreen({ userId }: { userId: string }) {
    const { logout, blocked } = useSafeLogout(userId);

    return (
        <div style={card}>
            <LogoutBlockedBanner show={blocked} />
            <p role="alert" style={{ color: "#B91C1C", fontSize: "1.125rem", fontWeight: 600 }}>
                {PIN_TEXT.locked}
            </p>
            <p style={{ marginTop: "8px" }}>{PIN_TEXT.safe}</p>
            <button type="button" onClick={logout} style={{ ...iconButton, marginTop: "16px" }}>
                <IconLogout size={22} />
            </button>
        </div>
    );
}
