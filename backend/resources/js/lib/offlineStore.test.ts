import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import "fake-indexeddb/auto";
import {
    decrypt,
    deleteDeviceKey,
    enqueue,
    encrypt,
    getOrCreateDeviceKey,
    listNeedsAttention,
    listPending,
    markNeedsAttention,
    markSynced,
    remove,
} from "./offlineStore";

const USER = "7";

beforeEach(async () => {
    indexedDB = new IDBFactory();
});

describe("getOrCreateDeviceKey", () => {
    it("generates a non-extractable AES-GCM key", async () => {
        const key = await getOrCreateDeviceKey();

        expect(key.type).toBe("secret");
        expect(key.extractable).toBe(false);
        expect(key.algorithm).toMatchObject({ name: "AES-GCM" });
    });

    it("returns the same key on a later call instead of generating a new one", async () => {
        const first = await getOrCreateDeviceKey();
        const second = await getOrCreateDeviceKey();

        const message = { hello: "farmer" };
        const envelope = await encrypt(first, message);

        await expect(decrypt(second, envelope)).resolves.toEqual(message);
    });
});

describe("encrypt / decrypt", () => {
    it("round-trips a JSON-serialisable object", async () => {
        const key = await getOrCreateDeviceKey();
        const payload = { amount: "250.75", narration: "Sold maize", quantity_sold: "12" };

        const envelope = await encrypt(key, payload);

        expect(envelope.ciphertext).not.toEqual(payload);
        await expect(decrypt(key, envelope)).resolves.toEqual(payload);
    });

    it("uses a different random IV for every encryption", async () => {
        const key = await getOrCreateDeviceKey();

        const first = await encrypt(key, { a: 1 });
        const second = await encrypt(key, { a: 1 });

        expect(Array.from(first.iv)).not.toEqual(Array.from(second.iv));
    });
});

describe("deleteDeviceKey", () => {
    it("makes previously queued data permanently unreadable", async () => {
        const key = await getOrCreateDeviceKey();
        const envelope = await encrypt(key, { secret: "farm data" });

        await deleteDeviceKey();

        const newKey = await getOrCreateDeviceKey();

        await expect(decrypt(newKey, envelope)).rejects.toThrow();
    });
});

describe("queue", () => {
    it("keeps enqueued items independent and returns them in the order they were recorded", async () => {
        const first = await enqueue({ narration: "first" });
        const second = await enqueue({ narration: "second" });

        const pending = await listPending(USER);

        expect(pending.map((item) => item.id)).toEqual([first, second]);
        expect(pending.map((item) => item.payload)).toEqual([
            { narration: "first" },
            { narration: "second" },
        ]);
    });

    it("stops returning an item once it has been marked synced", async () => {
        const id = await enqueue({ narration: "will sync" });

        await markSynced(id);

        expect(await listPending(USER)).toEqual([]);
    });

    it("removes an item from the queue entirely", async () => {
        const id = await enqueue({ narration: "will be discarded" });

        await remove(id);

        expect(await listPending(USER)).toEqual([]);
    });
});

describe("needs-attention items", () => {
    it("stops auto-syncing an item once marked as needing attention, but keeps it for review", async () => {
        const id = await enqueue({ narration: "oversold" });

        await markNeedsAttention(id, "That is more than the farm has on record.");

        expect(await listPending(USER)).toEqual([]);

        const attention = await listNeedsAttention(USER);
        expect(attention).toEqual([
            {
                id,
                payload: { narration: "oversold" },
                message: "That is more than the farm has on record.",
            },
        ]);
    });

    it("lets a needs-attention item be discarded like any other", async () => {
        const id = await enqueue({ narration: "oversold" });
        await markNeedsAttention(id, "That is more than the farm has on record.");

        await remove(id);

        expect(await listNeedsAttention(USER)).toEqual([]);
    });
});

describe("owners", () => {
    it("lists only the current user's items and ownerless ones, and leaves the rest stored", async () => {
        await enqueue({ n: "mine" }, USER);
        await enqueue({ n: "theirs" }, "8");
        await enqueue({ n: "old" });

        const mine = await listPending<{ n: string }>(USER);

        expect(mine.map((item) => item.payload.n)).toEqual(["mine", "old"]);
        expect(await listPending("8")).toHaveLength(2);
        expect(await listPending(null)).toEqual([]);
    });

    it("shows another user's needs-attention items to nobody but them", async () => {
        const theirs = await enqueue({ n: "theirs" }, "8");
        await markNeedsAttention(theirs, "Check this");

        expect(await listNeedsAttention(USER)).toEqual([]);
        expect(await listNeedsAttention("8")).toHaveLength(1);
    });
});

describe("enqueue order within the same millisecond", () => {
    const SAME_MOMENT = "2026-01-01T00:00:00.000Z";

    beforeEach(() => {
        vi.spyOn(Date.prototype, "toISOString").mockReturnValue(SAME_MOMENT);
    });

    afterEach(() => vi.restoreAllMocks());

    async function rawRows(): Promise<Array<{ id: string; seq?: number }>> {
        const db = await new Promise<IDBDatabase>((resolve, reject) => {
            const request = indexedDB.open("nkwa-offline-store");
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });

        const rows = await new Promise<Array<{ id: string; seq?: number }>>((resolve, reject) => {
            const request = db.transaction("queue", "readonly").objectStore("queue").getAll();
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
        db.close();

        return rows;
    }

    it("returns two items in the order they were made", async () => {
        for (const amount of ["first", "second"]) {
            await enqueue({ amount }, USER);
        }

        const items = await listPending<{ amount: string }>(USER);

        expect(items.map((item) => item.payload.amount)).toEqual(["first", "second"]);
    });

    it("returns five items in the order they were made", async () => {
        const made = ["a", "b", "c", "d", "e"];

        for (const amount of made) {
            await enqueue({ amount }, USER);
        }

        const items = await listPending<{ amount: string }>(USER);

        expect(items.map((item) => item.payload.amount)).toEqual(made);
    });

    it("gives two enqueues started at once different seq values", async () => {
        await Promise.all([enqueue({ amount: "x" }, USER), enqueue({ amount: "y" }, USER)]);

        const seqs = (await rawRows()).map((row) => row.seq);

        expect(seqs.every((seq) => typeof seq === "number")).toBe(true);
        expect(new Set(seqs).size).toBe(2);
    });

    it("sorts an old item stored without a seq before newer ones", async () => {
        await remove(await enqueue({ amount: "warm-up" }, USER));
        const key = await getOrCreateDeviceKey();
        const envelope = await encrypt(key, { amount: "old" });
        const db = await new Promise<IDBDatabase>((resolve, reject) => {
            const request = indexedDB.open("nkwa-offline-store");
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
        const tx = db.transaction("queue", "readwrite");
        tx.objectStore("queue").put({ id: "zzz-old", envelope, createdAt: SAME_MOMENT, synced: false, owner: USER });
        await new Promise((resolve) => (tx.oncomplete = resolve));
        db.close();

        await enqueue({ amount: "new1" }, USER);
        await enqueue({ amount: "new2" }, USER);

        const items = await listPending<{ amount: string }>(USER);

        expect(items.map((item) => item.payload.amount)).toEqual(["old", "new1", "new2"]);
    });
});
