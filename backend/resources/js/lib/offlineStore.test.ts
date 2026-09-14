import { beforeEach, describe, expect, it } from "vitest";
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

        const pending = await listPending();

        expect(pending.map((item) => item.id)).toEqual([first, second]);
        expect(pending.map((item) => item.payload)).toEqual([
            { narration: "first" },
            { narration: "second" },
        ]);
    });

    it("stops returning an item once it has been marked synced", async () => {
        const id = await enqueue({ narration: "will sync" });

        await markSynced(id);

        expect(await listPending()).toEqual([]);
    });

    it("removes an item from the queue entirely", async () => {
        const id = await enqueue({ narration: "will be discarded" });

        await remove(id);

        expect(await listPending()).toEqual([]);
    });
});

describe("needs-attention items", () => {
    it("stops auto-syncing an item once marked as needing attention, but keeps it for review", async () => {
        const id = await enqueue({ narration: "oversold" });

        await markNeedsAttention(id, "That is more than the farm has on record.");

        expect(await listPending()).toEqual([]);

        const attention = await listNeedsAttention();
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

        expect(await listNeedsAttention()).toEqual([]);
    });
});
