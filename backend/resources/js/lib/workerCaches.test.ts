// @vitest-environment jsdom
import { beforeEach, describe, expect, it, vi } from "vitest";
import "fake-indexeddb/auto";
import { enqueue, listPending, recordFailedAttempt } from "./offlineStore";
import { savePin, getPinRecord } from "./pin";
import { clearWorkerCaches, noteSignedInUser } from "./workerCaches";

let names: string[];
let deleted: string[];

beforeEach(() => {
    indexedDB = new IDBFactory();
    localStorage.clear();
    names = ["nkwa-shell-v1", "something-else"];
    deleted = [];
    vi.stubGlobal("caches", {
        keys: async () => names,
        delete: async (name: string) => {
            deleted.push(name);

            return true;
        },
        open: async () => ({ add: async () => {} }),
    });
});

const record = () => ({
    shape: 2 as const,
    uuid: crypto.randomUUID(),
    template: 1,
    farmer: "5b0f9f4e-0c3e-4a67-9a52-1e0f3f2a1111",
    amount: "100",
    event_date: "2026-03-01",
    device_created_at: "2026-03-01T08:00:00.000Z",
});

describe("clearing the worker's caches", () => {
    it("deletes every cache", async () => {
        await clearWorkerCaches();

        expect(deleted).toEqual(["nkwa-shell-v1", "something-else"]);
    });

    it("puts the offline page back, so it is still there if the phone goes offline next", async () => {
        const add = vi.fn(async () => {});
        const open = vi.fn(async () => ({ add }));
        vi.stubGlobal("caches", { keys: async () => [], delete: async () => true, open });

        await clearWorkerCaches();

        expect(open).toHaveBeenCalledWith("nkwa-shell-v1");
        expect(add).toHaveBeenCalledWith("/offline.html");
    });

    it("does nothing, and does not fail, where there is no cache storage", async () => {
        vi.stubGlobal("caches", undefined);

        await expect(clearWorkerCaches()).resolves.toBeUndefined();
    });

    it("leaves the record queue, stuck records and the PIN data alone", async () => {
        const pending = await enqueue(record(), "7");
        const stuck = await enqueue(record(), "7");
        for (let i = 0; i < 5; i++) {
            await recordFailedAttempt(stuck);
        }
        await savePin("7", "4826");

        await clearWorkerCaches();

        expect((await listPending("7")).map((item) => item.id)).toEqual([pending]);
        expect(await getPinRecord("7")).toMatchObject({ userId: "7", attempts: 0, locked: false });

        const db = await new Promise<IDBDatabase>((resolve, reject) => {
            const request = indexedDB.open("nkwa-offline-store");
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
        const row = await new Promise<{ stuck?: boolean } | undefined>((resolve, reject) => {
            const request = db.transaction("queue", "readonly").objectStore("queue").get(stuck);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
        db.close();

        expect(row).toMatchObject({ stuck: true });
    });
});

describe("noticing a different user", () => {
    it("clears nothing the first time a user is seen, and remembers them", async () => {
        expect(await noteSignedInUser("7")).toBe("first");

        expect(deleted).toEqual([]);
        expect(localStorage.getItem("nkwa_last_user")).toBe("7");
    });

    it("clears nothing when the same user returns", async () => {
        await noteSignedInUser("7");

        expect(await noteSignedInUser("7")).toBe("same");
        expect(deleted).toEqual([]);
    });

    it("clears the caches when a different user signs in on the same phone, and remembers the new one", async () => {
        await noteSignedInUser("7");

        expect(await noteSignedInUser("8")).toBe("switched");
        expect(deleted).toEqual(["nkwa-shell-v1", "something-else"]);
        expect(localStorage.getItem("nkwa_last_user")).toBe("8");
    });
});
