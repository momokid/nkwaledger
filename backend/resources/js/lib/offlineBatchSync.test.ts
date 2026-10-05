// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import "fake-indexeddb/auto";
import { enqueue, listPending } from "./offlineStore";
import { runSync } from "./offlineSync";
import { QueuedBatchRecord } from "@/types/offlineQueue";

const USER = "7";
const FARMER = "5b0f9f4e-0c3e-4a67-9a52-1e0f3f2a1111";

function record(n: number): QueuedBatchRecord {
    return {
        shape: 2,
        uuid: crypto.randomUUID(),
        template: 1,
        farmer: FARMER,
        amount: String(n),
        event_date: "2026-03-01",
        device_created_at: "2026-03-01T08:00:00.000Z",
    };
}

async function queue(count: number, owner: string | null = USER): Promise<QueuedBatchRecord[]> {
    const made: QueuedBatchRecord[] = [];

    for (let n = 1; n <= count; n++) {
        made.push(record(n));
        await enqueue(made[n - 1], owner);
    }

    return made;
}

interface Call {
    url: string;
    records: Array<{ uuid: string; amount: string }>;
}

function serve(answer: (call: Call, index: number) => Response | Promise<Response>) {
    const calls: Call[] = [];
    vi.stubGlobal(
        "fetch",
        vi.fn(async (url: string, init: RequestInit) => {
            const body = JSON.parse(init.body as string);
            const call = { url, records: body.records ?? [] };
            calls.push(call);

            return answer(call, calls.length - 1);
        }),
    );

    return calls;
}

const offline = () => {
    throw new TypeError("offline");
};

const results = (status: string | ((r: { uuid: string }, i: number) => string | null)) => (call: Call) =>
    new Response(
        JSON.stringify({
            results: call.records
                .map((r, i) => ({ uuid: r.uuid, status: typeof status === "string" ? status : status(r, i) }))
                .filter((r) => r.status !== null),
        }),
        { status: 200 },
    );

interface Row {
    id: string;
    attempts?: number;
    stuck?: boolean;
}

async function rows(): Promise<Row[]> {
    const db = await new Promise<IDBDatabase>((resolve, reject) => {
        const request = indexedDB.open("nkwa-offline-store");
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
    const all = await new Promise<Row[]>((resolve, reject) => {
        const request = db.transaction("queue", "readonly").objectStore("queue").getAll();
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
    db.close();

    return all;
}

const remaining = async () => (await listPending<QueuedBatchRecord>(USER)).map((item) => item.payload.uuid);

beforeEach(() => {
    indexedDB = new IDBFactory();
    document.head.innerHTML = '<meta name="csrf-token" content="t">';
});

afterEach(() => vi.unstubAllGlobals());

describe("batches", () => {
    it("sends 45 items as batches of 20, 20 and 5 in saved order", async () => {
        const made = await queue(45);
        const calls = serve(results("accepted"));

        await runSync(USER);

        expect(calls.map((c) => c.records.length)).toEqual([20, 20, 5]);
        expect(calls.every((c) => c.url === "/sync/submissions")).toBe(true);
        expect(calls.flatMap((c) => c.records.map((r) => r.uuid))).toEqual(made.map((m) => m.uuid));
        expect(await remaining()).toEqual([]);
    });

    it("does not send the shape marker to the server", async () => {
        await queue(1);
        const calls = serve(results("accepted"));

        await runSync(USER);

        expect(calls[0].records[0]).not.toHaveProperty("shape");
    });

    it.each(["accepted", "needs_fixing", "held", "rejected", "superseded"])("removes a %s record", async (status) => {
        await queue(1);
        serve(results(status));

        await runSync(USER);

        expect(await remaining()).toEqual([]);
    });

    it("keeps an error record and a record missing from the response", async () => {
        const made = await queue(3);
        serve(results((_r, i) => (i === 0 ? "accepted" : i === 1 ? "error" : null)));

        await runSync(USER);

        expect(await remaining()).toEqual([made[1].uuid, made[2].uuid]);
    });

    it("never sends another user's items", async () => {
        await queue(2, "8");
        const mine = await queue(1);
        const calls = serve(results("accepted"));

        await runSync(USER);

        expect(calls.flatMap((c) => c.records.map((r) => r.uuid))).toEqual([mine[0].uuid]);
    });

    it("resends the same uuid with the same details on a retry", async () => {
        await queue(2);
        const calls = serve(offline);

        await runSync(USER);
        await runSync(USER);

        expect(calls).toHaveLength(2);
        expect(calls[1].records).toEqual(calls[0].records);
    });

    it("keeps the uuid stored on the item after a failed run", async () => {
        const made = await queue(1);
        serve(offline);

        await runSync(USER);

        expect(await remaining()).toEqual([made[0].uuid]);
    });
});

describe("what counts as a failed attempt", () => {
    const counters = async () => (await rows()).map((r) => r.attempts ?? 0);

    it("fetch throwing leaves everything queued and the counter alone", async () => {
        await queue(3);
        serve(offline);

        await runSync(USER);

        expect(await remaining()).toHaveLength(3);
        expect(await counters()).toEqual([0, 0, 0]);
    });

    it.each([401, 419])("a %i leaves everything queued, the counter alone, and flags the session", async (status) => {
        await queue(3);
        serve(() => new Response("{}", { status }));

        const outcome = await runSync(USER);

        expect(outcome.authExpired).toBe(true);
        expect(await remaining()).toHaveLength(3);
        expect(await counters()).toEqual([0, 0, 0]);
    });

    it("a 5xx raises every counter in the batch", async () => {
        await queue(2);
        serve(() => new Response("{}", { status: 500 }));

        await runSync(USER);

        expect(await remaining()).toHaveLength(2);
        expect(await counters()).toEqual([1, 1]);
    });

    it("a per-record error raises only that record's counter", async () => {
        await queue(2);
        serve(results((_r, i) => (i === 0 ? "error" : "accepted")));

        await runSync(USER);

        expect((await rows()).map((r) => r.attempts ?? 0)).toEqual([1]);
    });

    it("the fifth failure sets stuck; a stuck item is never sent again or deleted", async () => {
        await queue(1);
        const calls = serve(() => new Response("{}", { status: 500 }));

        for (let i = 0; i < 5; i++) {
            await runSync(USER);
        }

        expect(calls).toHaveLength(5);
        expect((await rows())[0]).toMatchObject({ attempts: 5, stuck: true });

        await runSync(USER);

        expect(calls).toHaveLength(5);
        expect(await rows()).toHaveLength(1);
    });

    it("a stuck item does not block the items behind it", async () => {
        const made = await queue(1);
        serve(results("error"));

        for (let i = 0; i < 5; i++) {
            await runSync(USER);
        }

        const later = record(2);
        await enqueue(later, USER);
        const calls = serve(results("accepted"));

        await runSync(USER);

        expect(calls.flatMap((c) => c.records.map((r) => r.uuid))).toEqual([later.uuid]);
        expect((await rows()).map((r) => r.stuck)).toEqual([true]);
        expect((await rows())[0].id).toBeDefined();
        expect(made).toHaveLength(1);
    });
});

describe("a batch refused with 422", () => {
    it("is resent one record at a time: good ones removed, the bad one's counter rises", async () => {
        const made = await queue(3);
        const calls = serve((call) => {
            if (call.records.length > 1) {
                return new Response("{}", { status: 422 });
            }

            return call.records[0].uuid === made[1].uuid
                ? new Response("{}", { status: 422 })
                : results("accepted")(call);
        });

        await runSync(USER);

        expect(calls.map((c) => c.records.length)).toEqual([3, 1, 1, 1]);
        expect(calls.slice(1).map((c) => c.records[0].uuid)).toEqual(made.map((m) => m.uuid));
        expect(await remaining()).toEqual([made[1].uuid]);
        expect((await rows()).map((r) => r.attempts ?? 0)).toEqual([1]);
    });
});

describe("old-shape items", () => {
    it("still go one by one to their own url and never into a batch", async () => {
        await enqueue({ url: "/my-records", data: { amount: "10" } }, USER);
        await queue(1);
        const urls: string[] = [];
        const bodies: unknown[] = [];
        vi.stubGlobal(
            "fetch",
            vi.fn(async (url: string, init: RequestInit) => {
                urls.push(url);
                const body = JSON.parse(init.body as string);
                bodies.push(body);

                return url === "/sync/submissions"
                    ? results("accepted")({ url, records: body.records })
                    : new Response("{}", { status: 200 });
            }),
        );

        await runSync(USER);

        expect(urls).toEqual(["/my-records", "/sync/submissions"]);
        expect(bodies[0]).toEqual({ amount: "10" });
    });
});
