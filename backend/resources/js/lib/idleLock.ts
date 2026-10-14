// How long the app may sit hidden before the PIN is asked for again.
export const LOCK_AFTER_HIDDEN_MS = 60_000;

const HIDDEN_KEY = "nkwa_pin_hidden_at";

export interface Clocks {
    wall: number;
    mono: number;
    origin: number;
}

// two clocks on purpose: the wall clock can be changed by the person holding the phone, the
// monotonic one cannot (but it may stand still while the phone sleeps, and restarts on a reload)
export function readClocks(): Clocks {
    return { wall: Date.now(), mono: performance.now(), origin: performance.timeOrigin };
}

export function shouldLock(hiddenAt: Clocks, now: Clocks): boolean {
    const wall = now.wall - hiddenAt.wall;

    if (wall < 0) {
        return true;
    }

    const mono = now.origin === hiddenAt.origin ? now.mono - hiddenAt.mono : 0;

    return Math.max(wall, mono) >= LOCK_AFTER_HIDDEN_MS;
}

export function markHidden(): void {
    try {
        sessionStorage.setItem(HIDDEN_KEY, JSON.stringify(readClocks()));
    } catch {
        // without session storage the app cannot tell how long it was hidden, and stays as it is
    }
}

// the stamp left by the last time the page went hidden, removed so it is only judged once
export function takeHiddenStamp(): Clocks | null {
    try {
        const raw = sessionStorage.getItem(HIDDEN_KEY);
        sessionStorage.removeItem(HIDDEN_KEY);

        return raw ? (JSON.parse(raw) as Clocks) : null;
    } catch {
        return null;
    }
}
