// A PIN gate for this phone, per signed-in user. It only decides whether the app shows; it does not
// encrypt anything. The PIN itself is never stored: only a salted PBKDF2 hash, a try counter and a lock flag.

const DB_NAME = "nkwa-pin";
const STORE = "pins";
const ITERATIONS = 210000;
const MAX_TRIES = 5;

export const UNLOCK_KEY = "nkwa_pin_unlocked";

export interface PinRecord {
    userId: string;
    salt: Uint8Array;
    hash: Uint8Array;
    iterations: number;
    attempts: number;
    locked: boolean;
}

export type PinCheck = "ok" | "wrong" | "locked" | "none";

// four digits, not all the same, not a straight run up or down
export function isPinAllowed(pin: string): boolean {
    if (!/^\d{4}$/.test(pin)) {
        return false;
    }

    const digits = Array.from(pin, Number);
    const steps = digits.slice(1).map((digit, index) => digit - digits[index]);

    return !(steps.every((step) => step === 0) || steps.every((step) => step === 1) || steps.every((step) => step === -1));
}

function openDatabase(): Promise<IDBDatabase> {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, 1);

        request.onupgradeneeded = () => {
            if (!request.result.objectStoreNames.contains(STORE)) {
                request.result.createObjectStore(STORE, { keyPath: "userId" });
            }
        };
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

function result<T>(request: IDBRequest<T>): Promise<T> {
    return new Promise((resolve, reject) => {
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

function done(tx: IDBTransaction): Promise<void> {
    return new Promise((resolve, reject) => {
        tx.oncomplete = () => resolve();
        tx.onerror = () => reject(tx.error);
        tx.onabort = () => reject(tx.error);
    });
}

async function derive(pin: string, salt: Uint8Array, iterations: number): Promise<Uint8Array> {
    const material = await crypto.subtle.importKey("raw", new TextEncoder().encode(pin), "PBKDF2", false, ["deriveBits"]);
    const bits = await crypto.subtle.deriveBits({ name: "PBKDF2", hash: "SHA-256", salt: salt as BufferSource, iterations }, material, 256);

    return new Uint8Array(bits);
}

// looks at every byte whatever the answer, so the time taken says nothing about how close a guess was
function sameBytes(a: Uint8Array, b: Uint8Array): boolean {
    let difference = a.length ^ b.length;

    for (let index = 0; index < Math.max(a.length, b.length); index++) {
        difference |= (a[index] ?? 0) ^ (b[index] ?? 0);
    }

    return difference === 0;
}

export async function getPinRecord(userId: string): Promise<PinRecord | undefined> {
    const db = await openDatabase();
    const record = (await result(db.transaction(STORE, "readonly").objectStore(STORE).get(userId))) as PinRecord | undefined;
    db.close();

    return record;
}

export async function savePin(userId: string, pin: string): Promise<void> {
    const salt = crypto.getRandomValues(new Uint8Array(16));
    const record: PinRecord = {
        userId,
        salt,
        hash: await derive(pin, salt, ITERATIONS),
        iterations: ITERATIONS,
        attempts: 0,
        locked: false,
    };

    const db = await openDatabase();
    const tx = db.transaction(STORE, "readwrite");
    tx.objectStore(STORE).put(record);
    await done(tx);
    db.close();
}

export async function verifyPin(userId: string, pin: string): Promise<PinCheck> {
    const record = await getPinRecord(userId);

    if (!record) {
        return "none";
    }

    if (record.locked) {
        return "locked";
    }

    const right = sameBytes(await derive(pin, record.salt, record.iterations), record.hash);

    // counted against the freshest copy, so two tabs cannot both spend the same try
    const db = await openDatabase();
    const tx = db.transaction(STORE, "readwrite");
    const store = tx.objectStore(STORE);
    const fresh = (await result(store.get(userId))) as PinRecord | undefined;

    let outcome: PinCheck = "none";

    if (fresh) {
        if (fresh.locked) {
            outcome = "locked";
        } else if (right) {
            store.put({ ...fresh, attempts: 0 });
            outcome = "ok";
        } else {
            const attempts = fresh.attempts + 1;
            const locked = attempts >= MAX_TRIES;
            store.put({ ...fresh, attempts, locked });
            outcome = locked ? "locked" : "wrong";
        }
    }

    await done(tx);
    db.close();

    return outcome;
}

export function isUnlocked(userId: string): boolean {
    try {
        return sessionStorage.getItem(UNLOCK_KEY) === userId;
    } catch {
        return false;
    }
}

export function markUnlocked(userId: string): void {
    try {
        sessionStorage.setItem(UNLOCK_KEY, userId);
    } catch {
        // without session storage the gate simply asks again on the next load
    }
}

export function clearUnlocked(): void {
    try {
        sessionStorage.removeItem(UNLOCK_KEY);
    } catch {
        // nothing to clear
    }
}
