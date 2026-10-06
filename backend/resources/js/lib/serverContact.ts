// Two locks that need no PIN to be wrong about: the phone has been out of touch with our server for
// too long, or its clock reads earlier than it already has. Neither deletes anything; both only keep
// the app closed until the phone has talked to the server again.

import { isSessionEnded } from "./offlineSync";

export const OFFLINE_LIMIT_MS = 7 * 24 * 60 * 60 * 1000;
export const CLOCK_TOLERANCE_MS = 5 * 60 * 1000;

const DB_NAME = "nkwa-contact";
const STORE = "contact";
const PHONE_KEY = "phone";
const WRITE_EVERY_MS = 5000;

export type GuardState = "ok" | "offline_too_long" | "clock_wrong";
export type Retry = "ok" | "failed" | "session_ended";

interface Row {
    key: string;
    serverTime?: number;
    wallAt?: number;
    highWater?: number;
}

export interface Contact {
    serverTime: number;
    wallAt: number;
}

const userKey = (userId: string) => `user:${userId}`;

function openDatabase(): Promise<IDBDatabase> {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, 1);

        request.onupgradeneeded = () => {
            if (!request.result.objectStoreNames.contains(STORE)) {
                request.result.createObjectStore(STORE, { keyPath: "key" });
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

// the last thing read, so a page that is about to wake up can be judged on the spot
let cache: { highWater?: number; contact?: Contact } = {};

function judge(highWater: number | undefined, contact: Contact | undefined, now: number): GuardState {
    if (highWater !== undefined && now < highWater - CLOCK_TOLERANCE_MS) {
        return "clock_wrong";
    }

    if (!contact) {
        return "ok";
    }

    // a clock set back a little cannot buy time, and the estimate is never less than the last contact
    const elapsed = Math.max(0, Math.max(now, highWater ?? now) - contact.wallAt);

    return elapsed >= OFFLINE_LIMIT_MS ? "offline_too_long" : "ok";
}

// same answer as checkGuards, from what was last read, writing nothing
export function peekGuard(now = Date.now()): GuardState {
    return judge(cache.highWater, cache.contact, now);
}

// run on every app open and every time the page shows or hides
export async function checkGuards(userId: string, now = Date.now()): Promise<GuardState> {
    const db = await openDatabase();
    const tx = db.transaction(STORE, "readwrite");
    const store = tx.objectStore(STORE);
    const phone = (await result(store.get(PHONE_KEY))) as Row | undefined;
    let contact = (await result(store.get(userKey(userId)))) as Row | undefined;

    const state = judge(phone?.highWater, contact?.serverTime !== undefined ? (contact as Contact) : undefined, now);

    if (state !== "clock_wrong" && (phone?.highWater === undefined || now > phone.highWater)) {
        store.put({ key: PHONE_KEY, highWater: now });
    }

    // first use on this phone: start the clock now rather than lock a brand new user
    if (!contact) {
        contact = { key: userKey(userId), serverTime: now, wallAt: now };
        store.put(contact);
    }

    await done(tx);
    db.close();

    cache = {
        highWater: state === "clock_wrong" ? phone?.highWater : Math.max(phone?.highWater ?? now, now),
        contact: { serverTime: contact.serverTime!, wallAt: contact.wallAt! },
    };

    return state;
}

export async function getContact(userId: string): Promise<Contact | undefined> {
    const db = await openDatabase();
    const row = (await result(db.transaction(STORE, "readonly").objectStore(STORE).get(userKey(userId)))) as Row | undefined;
    db.close();

    return row?.serverTime !== undefined ? { serverTime: row.serverTime, wallAt: row.wallAt! } : undefined;
}

export async function getHighWater(): Promise<number | undefined> {
    const db = await openDatabase();
    const row = (await result(db.transaction(STORE, "readonly").objectStore(STORE).get(PHONE_KEY))) as Row | undefined;
    db.close();

    return row?.highWater;
}

// the server's own clock, read from a real answer, next to what this phone's clock said at that moment
export async function recordContact(userId: string, serverTime: number, resetHighWater = false): Promise<void> {
    const wallAt = Date.now();
    const db = await openDatabase();
    const tx = db.transaction(STORE, "readwrite");
    const store = tx.objectStore(STORE);

    store.put({ key: userKey(userId), serverTime, wallAt });

    // after a check with the server the high-water mark is the server's time, which also frees a
    // phone whose clock was once set far ahead and has since been put right
    if (resetHighWater) {
        store.put({ key: PHONE_KEY, highWater: serverTime });
    }

    await done(tx);
    db.close();

    cache = {
        highWater: resetHighWater ? serverTime : cache.highWater,
        contact: { serverTime, wallAt },
    };
}

// one authenticated round trip to our own server. It touches no queue.
export async function retryContact(userId: string): Promise<Retry> {
    try {
        const response = await fetch("/auth/check", {
            cache: "no-store",
            redirect: "manual",
            credentials: "same-origin",
            headers: { Accept: "application/json" },
        });

        if (isSessionEnded(response)) {
            return "session_ended";
        }

        const serverTime = Date.parse(response.headers.get("Date") ?? "");

        if (!response.ok || Number.isNaN(serverTime)) {
            return "failed";
        }

        await recordContact(userId, serverTime, true);

        return "ok";
    } catch {
        return "failed";
    }
}

let contactUser: string | null = null;
let lastWrite = 0;

export function setContactUser(userId: string | null): void {
    contactUser = userId;
}

// any successful answer from our own server proves contact; writes are spaced out so a busy page
// does not hammer the disk
export function noteContact(dateHeader: string | null | undefined): void {
    const serverTime = Date.parse(dateHeader ?? "");

    if (contactUser === null || Number.isNaN(serverTime) || Date.now() - lastWrite < WRITE_EVERY_MS) {
        return;
    }

    lastWrite = Date.now();
    void recordContact(contactUser, serverTime).catch(() => {});
}

export function installContactTracking(): void {
    const original = window.fetch.bind(window);

    window.fetch = async (...args: Parameters<typeof fetch>) => {
        const response = await original(...args);

        if (response.ok && response.type !== "opaque" && response.type !== "opaqueredirect") {
            noteContact(response.headers.get("Date"));
        }

        return response;
    };
}

export function resetContactTrackingForTests(): void {
    cache = {};
    contactUser = null;
    lastWrite = 0;
}
