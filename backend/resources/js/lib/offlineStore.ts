// Encrypted local storage for offline-first recording. Everything queued here is
// AES-GCM encrypted with a device key that never leaves IndexedDB and is never
// exportable — losing the key (see deleteDeviceKey, called on logout) is what
// makes previously queued data permanently unreadable.

import { buildQueueRow, ownedBy } from "./queueOwner";

const DB_NAME = "nkwa-offline-store";
const DB_VERSION = 1;
const KEY_STORE = "device-key";
const QUEUE_STORE = "queue";
const DEVICE_KEY_ID = "device-key";

export interface EncryptedEnvelope {
    iv: Uint8Array<ArrayBuffer>;
    ciphertext: ArrayBuffer;
}

export interface QueueItem<T = unknown> {
    id: string;
    payload: T;
    createdAt: string;
}

export interface NeedsAttentionItem<T = unknown> {
    id: string;
    payload: T;
    message: string;
}

interface QueueRow {
    id: string;
    envelope: EncryptedEnvelope;
    createdAt: string;
    seq?: number;
    synced: boolean;
    owner?: string;
    needsAttention?: string;
    attempts?: number;
    stuck?: boolean;
}

function openDatabase(): Promise<IDBDatabase> {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);

        request.onupgradeneeded = () => {
            const db = request.result;

            if (!db.objectStoreNames.contains(KEY_STORE)) {
                db.createObjectStore(KEY_STORE);
            }

            if (!db.objectStoreNames.contains(QUEUE_STORE)) {
                db.createObjectStore(QUEUE_STORE, { keyPath: "id" });
            }
        };

        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

function requestResult<T>(request: IDBRequest<T>): Promise<T> {
    return new Promise((resolve, reject) => {
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

function whenDone(transaction: IDBTransaction): Promise<void> {
    return new Promise((resolve, reject) => {
        transaction.oncomplete = () => resolve();
        transaction.onerror = () => reject(transaction.error);
        transaction.onabort = () => reject(transaction.error);
    });
}

// generating a key is not instant, and two near-simultaneous first calls must
// not race each other into writing two different keys
let deviceKeyPromise: Promise<CryptoKey> | null = null;

export function getOrCreateDeviceKey(): Promise<CryptoKey> {
    if (!deviceKeyPromise) {
        deviceKeyPromise = loadOrGenerateDeviceKey();
    }

    return deviceKeyPromise;
}

async function loadOrGenerateDeviceKey(): Promise<CryptoKey> {
    const db = await openDatabase();

    const readTx = db.transaction(KEY_STORE, "readonly");
    const existing = await requestResult(readTx.objectStore(KEY_STORE).get(DEVICE_KEY_ID));

    if (existing) {
        db.close();

        return existing as CryptoKey;
    }

    const key = await crypto.subtle.generateKey({ name: "AES-GCM", length: 256 }, false, [
        "encrypt",
        "decrypt",
    ]);

    const writeTx = db.transaction(KEY_STORE, "readwrite");
    writeTx.objectStore(KEY_STORE).put(key, DEVICE_KEY_ID);
    await whenDone(writeTx);
    db.close();

    return key;
}

export async function deleteDeviceKey(): Promise<void> {
    deviceKeyPromise = null;

    const db = await openDatabase();
    const tx = db.transaction(KEY_STORE, "readwrite");
    tx.objectStore(KEY_STORE).delete(DEVICE_KEY_ID);
    await whenDone(tx);
    db.close();
}

export async function encrypt(key: CryptoKey, data: unknown): Promise<EncryptedEnvelope> {
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const plaintext = new TextEncoder().encode(JSON.stringify(data));
    const ciphertext = await crypto.subtle.encrypt({ name: "AES-GCM", iv }, key, plaintext);

    return { iv, ciphertext };
}

export async function decrypt<T = unknown>(key: CryptoKey, envelope: EncryptedEnvelope): Promise<T> {
    const plaintext = await crypto.subtle.decrypt(
        { name: "AES-GCM", iv: envelope.iv },
        key,
        envelope.ciphertext,
    );

    return JSON.parse(new TextDecoder().decode(plaintext)) as T;
}

export async function enqueue(payload: unknown, owner: string | null = null): Promise<string> {
    const key = await getOrCreateDeviceKey();
    const envelope = await encrypt(key, payload);
    const row: QueueRow = buildQueueRow(
        { id: crypto.randomUUID(), envelope, createdAt: new Date().toISOString() },
        owner,
    );

    const db = await openDatabase();
    const tx = db.transaction(QUEUE_STORE, "readwrite");
    const store = tx.objectStore(QUEUE_STORE);
    const existing = (await requestResult(store.getAll())) as QueueRow[];
    const seq = Math.max(0, ...existing.map((item) => item.seq ?? 0)) + 1;
    store.put({ ...row, seq });
    await whenDone(tx);
    db.close();

    return row.id;
}

const bySavedOrder = (a: QueueRow, b: QueueRow) => (a.seq ?? 0) - (b.seq ?? 0) || a.createdAt.localeCompare(b.createdAt);

// saved order (seq), not phone-clock order, so a partial sync retries in the order the farmer actually recorded things
export async function listPending<T = unknown>(currentUser: string | null): Promise<QueueItem<T>[]> {
    const key = await getOrCreateDeviceKey();

    const db = await openDatabase();
    const tx = db.transaction(QUEUE_STORE, "readonly");
    const rows = (await requestResult(tx.objectStore(QUEUE_STORE).getAll())) as QueueRow[];
    db.close();

    const pending = ownedBy(rows, currentUser)
        .filter((row) => !row.synced && !row.needsAttention && !row.stuck)
        .sort(bySavedOrder);

    return Promise.all(
        pending.map(async (row) => ({
            id: row.id,
            payload: await decrypt<T>(key, row.envelope),
            createdAt: row.createdAt,
        })),
    );
}

// stops an item from being auto-retried while keeping it around, since the
// farmer still needs to see why it failed and decide whether to fix or drop it
export async function markNeedsAttention(id: string, message: string): Promise<void> {
    const db = await openDatabase();
    const tx = db.transaction(QUEUE_STORE, "readwrite");
    const store = tx.objectStore(QUEUE_STORE);
    const row = (await requestResult(store.get(id))) as QueueRow | undefined;

    if (row) {
        store.put({ ...row, needsAttention: message });
    }

    await whenDone(tx);
    db.close();
}

export const MAX_FAILED_ATTEMPTS = 5;

// the fifth failure parks the item as stuck: kept on the device, never retried automatically
export async function recordFailedAttempt(id: string): Promise<void> {
    const db = await openDatabase();
    const tx = db.transaction(QUEUE_STORE, "readwrite");
    const store = tx.objectStore(QUEUE_STORE);
    const row = (await requestResult(store.get(id))) as QueueRow | undefined;

    if (row) {
        const attempts = (row.attempts ?? 0) + 1;
        store.put({ ...row, attempts, ...(attempts >= MAX_FAILED_ATTEMPTS ? { stuck: true } : {}) });
    }

    await whenDone(tx);
    db.close();
}

// items that failed five times: kept on the device, shown to their owner, never sent again by themselves
export async function listStuck<T = unknown>(currentUser: string | null): Promise<QueueItem<T>[]> {
    const key = await getOrCreateDeviceKey();

    const db = await openDatabase();
    const tx = db.transaction(QUEUE_STORE, "readonly");
    const rows = (await requestResult(tx.objectStore(QUEUE_STORE).getAll())) as QueueRow[];
    db.close();

    const stuck = ownedBy(rows, currentUser)
        .filter((row) => row.stuck && !row.synced)
        .sort(bySavedOrder);

    return Promise.all(
        stuck.map(async (row) => ({
            id: row.id,
            payload: await decrypt<T>(key, row.envelope),
            createdAt: row.createdAt,
        })),
    );
}

export async function listNeedsAttention<T = unknown>(currentUser: string | null): Promise<NeedsAttentionItem<T>[]> {
    const key = await getOrCreateDeviceKey();

    const db = await openDatabase();
    const tx = db.transaction(QUEUE_STORE, "readonly");
    const rows = (await requestResult(tx.objectStore(QUEUE_STORE).getAll())) as QueueRow[];
    db.close();

    const flagged = ownedBy(rows, currentUser).filter((row) => !!row.needsAttention);

    return Promise.all(
        flagged.map(async (row) => ({
            id: row.id,
            payload: await decrypt<T>(key, row.envelope),
            message: row.needsAttention as string,
        })),
    );
}

export async function markSynced(id: string): Promise<void> {
    const db = await openDatabase();
    const tx = db.transaction(QUEUE_STORE, "readwrite");
    const store = tx.objectStore(QUEUE_STORE);
    const row = (await requestResult(store.get(id))) as QueueRow | undefined;

    if (row) {
        store.put({ ...row, synced: true });
    }

    await whenDone(tx);
    db.close();
}

export async function remove(id: string): Promise<void> {
    const db = await openDatabase();
    const tx = db.transaction(QUEUE_STORE, "readwrite");
    tx.objectStore(QUEUE_STORE).delete(id);
    await whenDone(tx);
    db.close();
}
