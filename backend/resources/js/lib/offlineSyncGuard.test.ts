// @vitest-environment jsdom
import { readFileSync } from "node:fs";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import "fake-indexeddb/auto";
import { enqueue } from "./offlineStore";
import { syncOnce } from "./offlineSync";

const USER = "7";

function stubLocks(held: boolean) {
    let busy = held;
    const request = vi.fn(
        async (_name: string, _opts: unknown, cb: (lock: object | null) => Promise<unknown>) => {
            if (busy) {
                return cb(null);
            }
            busy = true;
            try {
                return await cb({});
            } finally {
                busy = false;
            }
        },
    );
    vi.stubGlobal("navigator", { ...navigator, locks: { request } });

    return request;
}

function okFetch() {
    const fetchMock = vi.fn().mockResolvedValue(new Response("{}", { status: 200 }));
    vi.stubGlobal("fetch", fetchMock);

    return fetchMock;
}

beforeEach(async () => {
    indexedDB = new IDBFactory();
    document.head.innerHTML = '<meta name="csrf-token" content="t">';
    await enqueue({ url: "/my-records", data: { amount: "10" } });
});

afterEach(() => vi.unstubAllGlobals());

describe.each([
    ["with Web Locks", true],
    ["without Web Locks (flag fallback)", false],
])("syncOnce %s", (_label, useLocks) => {
    beforeEach(() => {
        if (useLocks) {
            stubLocks(false);
        }
    });

    it("sends one request for two simultaneous triggers", async () => {
        const fetchMock = okFetch();

        const [a, b] = await Promise.all([syncOnce(USER), syncOnce(USER)]);

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect([a, b].filter((o) => o === null)).toHaveLength(1);
    });

    it("runs again after a failed run", async () => {
        vi.stubGlobal("fetch", vi.fn().mockRejectedValue(new Error("offline")));
        await syncOnce(USER);

        const fetchMock = okFetch();
        const outcome = await syncOnce(USER);

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(outcome?.synced).toHaveLength(1);
    });

    it("runs again after a thrown error", async () => {
        indexedDB = undefined as unknown as IDBFactory;
        await expect(syncOnce(USER)).rejects.toThrow();

        indexedDB = new IDBFactory();
        await enqueue({ url: "/my-records", data: { amount: "5" } });
        const fetchMock = okFetch();
        await syncOnce(USER);

        expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    it("runs again after a successful run", async () => {
        okFetch();
        await syncOnce(USER);

        await enqueue({ url: "/my-records", data: { amount: "20" } });
        const fetchMock = okFetch();
        await syncOnce(USER);

        expect(fetchMock).toHaveBeenCalledTimes(1);
    });
});

describe("syncOnce lock held by another tab", () => {
    it("skips without error and sends nothing", async () => {
        stubLocks(true);
        const fetchMock = okFetch();

        await expect(syncOnce(USER)).resolves.toBeNull();
        expect(fetchMock).not.toHaveBeenCalled();
    });
});

describe("single entry point", () => {
    const read = (p: string) => readFileSync(`resources/js/${p}`, "utf8");

    it("both layouts trigger sync through useOfflineSync, which calls syncOnce", () => {
        expect(read("Layouts/AdminLayout.tsx")).toContain("useOfflineSync()");
        expect(read("Layouts/AuthenticatedLayout.tsx")).toContain("useOfflineSync()");
        expect(read("hooks/useOfflineSync.ts")).toMatch(/syncOnce\(/);
        expect(read("hooks/useOfflineSync.ts")).not.toMatch(/\brunSync\(/);
    });
});
