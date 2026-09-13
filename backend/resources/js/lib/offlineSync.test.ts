// @vitest-environment jsdom
import { beforeEach, describe, expect, it, vi } from "vitest";
import "fake-indexeddb/auto";
import { enqueue } from "./offlineStore";
import { runSync } from "./offlineSync";

beforeEach(() => {
    indexedDB = new IDBFactory();

    document.head.innerHTML = '<meta name="csrf-token" content="test-token">';
});

function jsonResponse(status: number, body: unknown): Response {
    return new Response(JSON.stringify(body), { status });
}

function opaqueRedirectResponse(): Response {
    return { type: "opaqueredirect", ok: false, status: 0 } as Response;
}

describe("runSync", () => {
    it("posts every pending item and clears the queue on success", async () => {
        await enqueue({ url: "/my-records", data: { amount: "10" } });
        await enqueue({ url: "/my-records", data: { amount: "20" } });

        const fetchMock = vi
            .fn()
            .mockResolvedValue(jsonResponse(200, { reference: "TXN-1" }));
        vi.stubGlobal("fetch", fetchMock);

        const outcome = await runSync();

        expect(fetchMock).toHaveBeenCalledTimes(2);
        expect(outcome.synced).toHaveLength(2);
        expect(outcome.authExpired).toBe(false);
        expect(outcome.needsAttention).toEqual([]);

        vi.unstubAllGlobals();
    });

    it("posts items in the order they were recorded", async () => {
        await enqueue({ url: "/my-records", data: { amount: "first" } });
        await enqueue({ url: "/my-records", data: { amount: "second" } });

        const calls: unknown[] = [];
        const fetchMock = vi.fn().mockImplementation((_url, init) => {
            calls.push(JSON.parse(init.body as string));

            return Promise.resolve(jsonResponse(200, { reference: "TXN" }));
        });
        vi.stubGlobal("fetch", fetchMock);

        await runSync();

        expect(calls).toEqual([{ amount: "first" }, { amount: "second" }]);

        vi.unstubAllGlobals();
    });

    it("sends the csrf token and asks for a JSON response", async () => {
        await enqueue({ url: "/my-records", data: { amount: "10" } });

        const fetchMock = vi
            .fn()
            .mockResolvedValue(jsonResponse(200, { reference: "TXN-1" }));
        vi.stubGlobal("fetch", fetchMock);

        await runSync();

        const [, init] = fetchMock.mock.calls[0];
        expect(init.headers["X-CSRF-TOKEN"]).toBe("test-token");
        expect(init.headers["Accept"]).toBe("application/json");
        expect(init.redirect).toBe("manual");

        vi.unstubAllGlobals();
    });

    it("stops syncing and leaves everything queued on a 419", async () => {
        await enqueue({ url: "/my-records", data: { amount: "10" } });
        await enqueue({ url: "/my-records", data: { amount: "20" } });

        const fetchMock = vi.fn().mockResolvedValue(jsonResponse(419, {}));
        vi.stubGlobal("fetch", fetchMock);

        const outcome = await runSync();

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(outcome.authExpired).toBe(true);
        expect(outcome.synced).toEqual([]);

        vi.stubGlobal("fetch", vi.fn().mockResolvedValue(jsonResponse(200, { reference: "TXN" })));

        const secondOutcome = await runSync();
        expect(secondOutcome.synced).toHaveLength(2);

        vi.unstubAllGlobals();
    });

    it("treats a redirected response the same as an expired session", async () => {
        await enqueue({ url: "/my-records", data: { amount: "10" } });

        const fetchMock = vi.fn().mockResolvedValue(opaqueRedirectResponse());
        vi.stubGlobal("fetch", fetchMock);

        const outcome = await runSync();

        expect(outcome.authExpired).toBe(true);
        expect(outcome.synced).toEqual([]);

        vi.unstubAllGlobals();
    });

    it("removes a validation failure from the queue and surfaces it for review", async () => {
        const id = await enqueue({ url: "/my-records", data: { amount: "999999" } });

        const fetchMock = vi
            .fn()
            .mockResolvedValue(jsonResponse(422, { message: "That is more than the farm has on record." }));
        vi.stubGlobal("fetch", fetchMock);

        const outcome = await runSync();

        expect(outcome.needsAttention).toEqual([
            { id, message: "That is more than the farm has on record." },
        ]);

        const { listPending, listNeedsAttention } = await import("./offlineStore");
        expect(await listPending()).toEqual([]);
        expect(await listNeedsAttention()).toEqual([
            {
                id,
                payload: { url: "/my-records", data: { amount: "999999" } },
                message: "That is more than the farm has on record.",
            },
        ]);

        vi.unstubAllGlobals();
    });

    it("keeps an item queued to retry after a generic server failure", async () => {
        await enqueue({ url: "/my-records", data: { amount: "10" } });

        const fetchMock = vi.fn().mockResolvedValue(jsonResponse(500, {}));
        vi.stubGlobal("fetch", fetchMock);

        const outcome = await runSync();

        expect(outcome.synced).toEqual([]);
        expect(outcome.needsAttention).toEqual([]);
        expect(outcome.authExpired).toBe(false);

        const { listPending } = await import("./offlineStore");
        expect(await listPending()).toHaveLength(1);

        vi.unstubAllGlobals();
    });

    it("keeps an item queued to retry after a network-level failure", async () => {
        await enqueue({ url: "/my-records", data: { amount: "10" } });

        const fetchMock = vi.fn().mockRejectedValue(new TypeError("Failed to fetch"));
        vi.stubGlobal("fetch", fetchMock);

        const outcome = await runSync();

        expect(outcome.synced).toEqual([]);

        const { listPending } = await import("./offlineStore");
        expect(await listPending()).toHaveLength(1);

        vi.unstubAllGlobals();
    });

    it("continues to the next item after one fails with a generic error", async () => {
        await enqueue({ url: "/my-records", data: { amount: "bad" } });
        await enqueue({ url: "/my-records", data: { amount: "good" } });

        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce(jsonResponse(500, {}))
            .mockResolvedValueOnce(jsonResponse(200, { reference: "TXN" }));
        vi.stubGlobal("fetch", fetchMock);

        const outcome = await runSync();

        expect(outcome.synced).toHaveLength(1);

        const { listPending } = await import("./offlineStore");
        expect(await listPending()).toHaveLength(1);

        vi.unstubAllGlobals();
    });
});
