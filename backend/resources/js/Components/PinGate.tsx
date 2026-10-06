import { IconArrowRight, IconLogout, IconRefresh } from "@tabler/icons-react";
import { FormEvent, ReactNode, useEffect, useState } from "react";
import LogoutBlockedBanner from "@/Components/LogoutBlockedBanner";
import SessionEndedBanner from "@/Components/SessionEndedBanner";
import useSafeLogout from "@/hooks/useSafeLogout";
import { markHidden, readClocks, shouldLock, takeHiddenStamp } from "@/lib/idleLock";
import { clearPin, clearUnlocked, getPinRecord, isPinAllowed, isUnlocked, markUnlocked, savePin, verifyPin } from "@/lib/pin";
import { confirmResetCode, requestResetCode } from "@/lib/pinReset";
import { checkGuards, GuardState, peekGuard, retryContact, setContactUser } from "@/lib/serverContact";
import { PIN_TEXT } from "@/lib/pinText";

type Status = "loading" | "setup" | "enter" | "locked" | "reset";

const card = {
    maxWidth: "360px",
    margin: "20vh auto 0",
    padding: "24px",
    background: "#FFFFFF",
    border: "1px solid #E5E7EB",
    color: "#111827",
    fontFamily: "'Inter', system-ui, sans-serif",
} as const;

const linkButton = {
    display: "block",
    background: "transparent",
    border: "none",
    color: "#0F6E56",
    textDecoration: "underline",
    cursor: "pointer",
    fontSize: "1rem",
    padding: 0,
    marginTop: "12px",
} as const;

const iconButton = {
    background: "#1D9E75",
    color: "#FFFFFF",
    border: "none",
    padding: "10px 14px",
    cursor: "pointer",
} as const;

// unlocked for this session, unless the page was left hidden too long before it was closed
function stillUnlocked(userId: string): boolean {
    if (!isUnlocked(userId)) {
        return false;
    }

    const hiddenAt = takeHiddenStamp();

    if (hiddenAt && shouldLock(hiddenAt, readClocks())) {
        clearUnlocked();

        return false;
    }

    return true;
}

// everything a signed-in page shows waits behind this: while locked its children are not rendered,
// so nothing under them (layouts, sync, the queue) runs
export default function PinGate({ user, children }: { user: { id: number } | null | undefined; children: ReactNode }) {
    const userId = user ? String(user.id) : null;
    const [unlocked, setUnlocked] = useState(() => userId !== null && stillUnlocked(userId));
    const [status, setStatus] = useState<Status>("loading");
    const [guard, setGuard] = useState<GuardState | "checking">("checking");

    useEffect(() => {
        if (userId !== null) {
            setUnlocked(stillUnlocked(userId));
        }
    }, [userId]);

    // the offline and clock locks: checked when the app opens and every time the page shows or hides.
    // They sit above the PIN, so while one is up nothing below it (layouts, sync, the queue) is mounted.
    const checkAgain = async () => {
        if (userId === null) {
            return;
        }

        const state = await checkGuards(userId);

        if (state !== "ok") {
            clearUnlocked();
            setUnlocked(false);
        }

        setGuard(state);
    };

    useEffect(() => {
        if (userId === null) {
            return;
        }

        setContactUser(userId);
        void checkAgain();

        // first in line on a wake-up: if what was last read already says "locked", no other
        // listener, such as a sync trigger, gets to act on the same event
        const onChange = (event: Event) => {
            if (peekGuard() !== "ok") {
                event.stopImmediatePropagation();
                clearUnlocked();
                setUnlocked(false);
            }

            void checkAgain();
        };

        document.addEventListener("visibilitychange", onChange, true);

        return () => {
            document.removeEventListener("visibilitychange", onChange, true);
            setContactUser(null);
        };
    }, [userId]);

    // hidden for too long: the next time the page shows, the PIN is asked for again. This listener
    // goes first (capture) and stops the event there, so no other listener, such as a sync trigger,
    // acts on the same wake-up before the app has been taken off the screen.
    useEffect(() => {
        if (userId === null || !unlocked) {
            return;
        }

        const onChange = (event: Event) => {
            if (document.visibilityState === "hidden") {
                markHidden();

                return;
            }

            const hiddenAt = takeHiddenStamp();

            if (hiddenAt && shouldLock(hiddenAt, readClocks())) {
                event.stopImmediatePropagation();
                clearUnlocked();
                setStatus("loading");
                setUnlocked(false);
            }
        };

        document.addEventListener("visibilitychange", onChange, true);

        return () => document.removeEventListener("visibilitychange", onChange, true);
    }, [userId, unlocked]);

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

    if (userId === null) {
        return <>{children}</>;
    }

    if (guard === "checking") {
        return null;
    }

    if (guard !== "ok") {
        return <GuardScreen userId={userId} state={guard} onRetried={() => void checkAgain()} />;
    }

    if (unlocked) {
        return <>{children}</>;
    }

    if (status === "loading") {
        return null;
    }

    if (status === "reset") {
        return <ResetScreen userId={userId} onDone={() => setStatus("setup")} />;
    }

    if (status === "locked") {
        return <LockedScreen userId={userId} onForgot={() => setStatus("reset")} />;
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
            onForgot={() => setStatus("reset")}
        />
    );
}

function PinScreen({
    userId,
    setup,
    onUnlocked,
    onLocked,
    onMissing,
    onForgot,
}: {
    userId: string;
    setup: boolean;
    onUnlocked: () => void;
    onLocked: () => void;
    onMissing: () => void;
    onForgot: () => void;
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
            {!setup && <ForgotButton onClick={onForgot} />}
        </form>
    );
}

function ForgotButton({ onClick }: { onClick: () => void }) {
    return (
        <button type="button" onClick={onClick} style={linkButton}>
            {PIN_TEXT.forgot}
        </button>
    );
}

// the phone has been out of touch too long, or its date looks wrong: one check with the server frees it
function GuardScreen({ userId, state, onRetried }: { userId: string; state: GuardState; onRetried: () => void }) {
    const { logout, blocked } = useSafeLogout(userId);
    const [busy, setBusy] = useState(false);
    const [sessionEnded, setSessionEnded] = useState(false);

    const retry = async () => {
        setBusy(true);
        const outcome = await retryContact(userId);
        setBusy(false);

        if (outcome === "session_ended") {
            setSessionEnded(true);
        } else if (outcome === "ok") {
            setSessionEnded(false);
            onRetried();
        }
    };

    return (
        <div style={card}>
            <SessionEndedBanner show={sessionEnded} />
            <LogoutBlockedBanner show={blocked} />
            <p role="alert" style={{ color: "#B91C1C", fontSize: "1.125rem", fontWeight: 600 }}>
                {state === "clock_wrong" ? PIN_TEXT.clockWrong : PIN_TEXT.offlineTooLong}
            </p>
            <p style={{ marginTop: "8px" }}>{PIN_TEXT.safe}</p>
            <div style={{ display: "flex", gap: "8px", marginTop: "16px" }}>
                <button type="button" data-testid="retry" onClick={retry} disabled={busy} style={iconButton}>
                    <IconRefresh size={22} />
                </button>
                <button type="button" data-testid="sign-out" onClick={logout} style={iconButton}>
                    <IconLogout size={22} />
                </button>
            </div>
        </div>
    );
}

function LockedScreen({ userId, onForgot }: { userId: string; onForgot: () => void }) {
    const { logout, blocked } = useSafeLogout(userId);

    return (
        <div style={card}>
            <LogoutBlockedBanner show={blocked} />
            <p role="alert" style={{ color: "#B91C1C", fontSize: "1.125rem", fontWeight: 600 }}>
                {PIN_TEXT.locked}
            </p>
            <p style={{ marginTop: "8px" }}>{PIN_TEXT.safe}</p>
            <ForgotButton onClick={onForgot} />
            <button type="button" onClick={logout} style={{ ...iconButton, marginTop: "16px" }}>
                <IconLogout size={22} />
            </button>
        </div>
    );
}

// the code goes to the number on record; a right code forgets the old PIN, nothing else
function ResetScreen({ userId, onDone }: { userId: string; onDone: () => void }) {
    const [codeSent, setCodeSent] = useState(false);
    const [value, setValue] = useState("");
    const [message, setMessage] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

    const send = async () => {
        setBusy(true);
        const sent = await requestResetCode();
        setBusy(false);

        if (sent === "sent") {
            setCodeSent(true);
            setMessage(null);
        } else {
            setMessage(PIN_TEXT.couldNotSend);
        }
    };

    const confirm = async (event: FormEvent) => {
        event.preventDefault();

        const entered = value;
        setValue("");
        setBusy(true);
        const result = await confirmResetCode(entered);
        setBusy(false);

        if (result === "ok") {
            await clearPin(userId);
            onDone();

            return;
        }

        setMessage(
            result === "wrong"
                ? PIN_TEXT.wrongCode
                : result === "expired"
                  ? PIN_TEXT.expiredCode
                  : result === "too_many"
                    ? PIN_TEXT.tooManyCodes
                    : PIN_TEXT.couldNotSend,
        );
    };

    return (
        <form onSubmit={confirm} style={card}>
            {codeSent && (
                <>
                    <label htmlFor="nkwa-pin-code" style={{ display: "block", fontSize: "1.125rem", fontWeight: 600, marginBottom: "8px" }}>
                        {PIN_TEXT.enterCode}
                    </label>
                    <div style={{ display: "flex", gap: "8px" }}>
                        <input
                            id="nkwa-pin-code"
                            type="password"
                            inputMode="numeric"
                            autoComplete="off"
                            maxLength={6}
                            autoFocus
                            value={value}
                            onChange={(event) => setValue(event.target.value)}
                            style={{ flex: 1, padding: "10px 12px", border: "1px solid #9CA3AF", fontSize: "1.25rem", letterSpacing: "0.3em" }}
                        />
                        <button type="submit" disabled={busy} style={iconButton}>
                            <IconArrowRight size={22} />
                        </button>
                    </div>
                </>
            )}
            {message && (
                <p role="alert" style={{ color: "#B91C1C", marginTop: "10px" }}>
                    {message}
                </p>
            )}
            <button type="button" onClick={send} disabled={busy} style={{ ...linkButton, marginTop: "12px" }}>
                {PIN_TEXT.sendCode}
            </button>
        </form>
    );
}
