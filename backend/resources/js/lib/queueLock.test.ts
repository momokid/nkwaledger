// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import "fake-indexeddb/auto";
import { deleteDeviceKey, enqueue, getOrCreateDeviceKey } from "./offlineStore";
import { withQueueLock } from "./queueLock";

const FARMER = "5b0f9f4e-0c3e-4a67-9a52-1e0f3f2a1111";

const record = () => ({
    shape: 2 as const,
    uuid: crypto.randomUUID(),
    template: 1,
    farmer: FARMER,
    amount: "100",
    event_date: "2026-03-01",
    device_created_at: "2026-03-01T08:00:00.000Z",
});

function openDb(): Promise<IDBDatabase> {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open("nkwa-offline-store");
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

async function rowCount(): Promise<number> {
    const db = await openDb();
    const count = await new Promise<number>((resolve, reject) => {
        const request = db.transaction("queue", "readonly").objectStore("queue").count();
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
    db.close();

    return count;
}

// what another tab's logout does to the stored key, behind this tab's back
async function deleteKeyBehindTheCache(): Promise<void> {
    const db = await openDb();
    await new Promise<void>((resolve, reject) => {
        const tx = db.transaction("device-key", "readwrite");
        tx.objectStore("device-key").delete("device-key");
        tx.oncomplete = () => resolve();
        tx.onerror = () => reject(tx.error);
    });
    db.close();
}

const pause = (ms = 40) => new Promise((resolve) => setTimeout(resolve, ms));

beforeEach(async () => {
    indexedDB = new IDBFactory();
    await deleteDeviceKey();
    await getOrCreateDeviceKey();
});

afterEach(() => vi.unstubAllGlobals());

describe.each([
    ["Web Locks", true],
    ["the fallback", false],
])("the queue lock with %s", (_label, withLocks) => {
    beforeEach(() => {
        if (withLocks) {
            let tail: Promise<unknown> = Promise.resolve();
            vi.stubGlobal("navigator", {
                ...navigator,
                locks: {
                    request: (_name: string, _options: object, callback: () => Promise<unknown>) => {
                        const run = tail.then(callback);
                        tail = run.catch(() => {});

                        return run;
                    },
                },
            });
        } else {
            vi.stubGlobal("navigator", { ...navigator, locks: undefined });
        }
    });

    it("makes a record saved while the lock is held wait for it", async () => {
        let release!: () => void;
        const held = withQueueLock(() => new Promise<void>((resolve) => (release = resolve)));

        const saving = enqueue(record(), "7");
        await pause();

        expect(await rowCount()).toBe(0);

        release();
        await held;
        await saving;

        expect(await rowCount()).toBe(1);
    });

    it("never lets a record be encrypted with a key that was deleted while it waited", async () => {
        let release!: () => void;
        const held = withQueueLock(
            () =>
                new Promise<void>((resolve) => {
                    release = resolve;
                }),
        );

        const saving = enqueue(record(), "7");
        await pause();
        await deleteKeyBehindTheCache();
        release();
        await held;
        await saving;

        vi.resetModules();
        const reloaded = await import("./offlineStore");

        expect(await reloaded.listPending("7")).toHaveLength(1);
    });

    it("is released after a failure, so the next holder is not stuck", async () => {
        await expect(
            withQueueLock(async () => {
                throw new Error("boom");
            }),
        ).rejects.toThrow("boom");

        await expect(enqueue(record(), "7")).resolves.toEqual(expect.any(String));
        expect(await rowCount()).toBe(1);
    });
});
