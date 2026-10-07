// @vitest-environment jsdom
import { beforeEach, describe, expect, it, vi } from "vitest";
import "fake-indexeddb/auto";
import { enqueue, listMediaIds, listPending, listStuck, queueCounts, readItem, recordFailedAttempt } from "./offlineStore";
import { runSync } from "./offlineSync";
import { buildHealthReport } from "./healthReport";
import { setDataCostAsker } from "./dataCostGate";
import { DATA_COST_TEXT } from "./dataCostText";
import { QueuedHealthReport } from "@/types/offlineQueue";

const USER = "7";
const FARMER = "5b0f9f4e-0c3e-4a67-9a52-1e0f3f2a1111";
const CHUNK = 4;

const asked: string[] = [];
const answer = (reply: "send" | "wait" | "dismissed") => setDataCostAsker(async (text) => (asked.push(text), reply));

beforeEach(() => {
    asked.length = 0;
    answer("send");
    indexedDB = new IDBFactory();
    document.head.innerHTML = '<meta name="csrf-token" content="t">';
});

const bytesOf = (...values: number[]) => new File([new Uint8Array(values)], "x.bin", { type: "image/png" });
const PHOTO = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
const AUDIO = [11, 12, 13, 14, 15];

const report = (uuid: string, withAudio = true) =>
    buildHealthReport({
        farmer: FARMER,
        farmUnitId: 4,
        description: "Weak birds",
        photo: bytesOf(...PHOTO),
        audio: withAudio ? new File([new Uint8Array(AUDIO)], "voice-note.webm", { type: "audio/webm" }) : null,
        uuid,
    });

interface Sent {
    method: string;
    url: string;
    offset?: number;
}

// a stand-in for the server: it alone knows how much of each file it holds
function server(opts: { preheld?: { key: string; bytes: number[]; size: number }; dropAfterChunks?: number; textStatus?: string; failWith?: number; gone?: boolean; sessionEnded?: boolean; wrongOffsetOnce?: boolean } = {}) {
    const held: Record<string, number[]> = {};
    const sessions: Record<string, { size: number }> = {};
    const log: Sent[] = [];
    let chunks = 0;
    let wrongSent = false;
    const state: { dropAfterChunks?: number } = { dropAfterChunks: opts.dropAfterChunks };

    if (opts.preheld) {
        held[opts.preheld.key] = opts.preheld.bytes;
        sessions[opts.preheld.key] = { size: opts.preheld.size };
    }

    const json = (status: number, body: unknown) => new Response(JSON.stringify(body), { status });

    vi.stubGlobal(
        "fetch",
        vi.fn(async (url: string, init: RequestInit) => {
            const method = init.method ?? "GET";
            const headers = (init.headers ?? {}) as Record<string, string>;
            const offset = headers["X-Upload-Offset"] !== undefined ? Number(headers["X-Upload-Offset"]) : undefined;
            log.push({ method, url, offset });

            if (url === "/sync/submissions") {
                const { records } = JSON.parse(init.body as string) as { records: Array<{ uuid: string }> };

                return json(200, { results: records.map((r) => ({ uuid: r.uuid, status: opts.textStatus ?? "accepted" })) });
            }

            if (opts.sessionEnded) {
                return json(401, {});
            }

            if (opts.gone) {
                return json(404, {});
            }

            const key = url.replace("/sync/health-reports/", "");
            const complete = (held[key]?.length ?? 0) === sessions[key]?.size && sessions[key] !== undefined;

            if (method === "GET") {
                if (complete) return json(200, { state: "complete", offset: 0, chunk_size: CHUNK });

                return json(200, { state: sessions[key] ? "open" : "none", offset: held[key]?.length ?? 0, chunk_size: CHUNK });
            }

            if (method === "POST") {
                const body = JSON.parse(init.body as string) as { size: number; sha256: string };
                expect(body.sha256).toMatch(/^[0-9a-f]{64}$/);
                sessions[key] ??= { size: body.size };
                held[key] ??= [];

                return json(200, { state: "open", offset: held[key].length, chunk_size: CHUNK });
            }

            if (opts.failWith) {
                return json(opts.failWith, {});
            }

            if (state.dropAfterChunks !== undefined && chunks >= state.dropAfterChunks) {
                throw new TypeError("connection lost");
            }

            if (opts.wrongOffsetOnce && !wrongSent) {
                wrongSent = true;

                return json(409, { error: "wrong_offset", offset: held[key].length });
            }

            const have = held[key].length;

            if (offset !== have) {
                return json(409, { error: "wrong_offset", offset: have });
            }

            held[key].push(...new Uint8Array(await new Response(init.body as Blob).arrayBuffer()));
            chunks++;
            const done = held[key].length === sessions[key].size;

            return json(200, { state: done ? "complete" : "open", offset: held[key].length, chunk_size: CHUNK });
        }),
    );

    return { held, log, state, mediaRequests: () => log.filter((l) => l.url.startsWith("/sync/health-reports/")) };
}

const readIds = () => listMediaIds(USER).then((rows) => rows.map((row) => row.id));

const stored = (s: ReturnType<typeof server>, uuid: string, kind: string) => s.held[`${uuid}/${kind}`];

describe("a health report's photo and voice note", () => {
    it("keeps the row and its media after the text is accepted, and deletes it only when every file is confirmed", async () => {
        await enqueue(await report("h-1"), USER);
        const s = server({ dropAfterChunks: 0 });

        await runSync(USER);

        // text accepted, the connection died on the first chunk: still on the phone, with its media
        expect(await queueCounts(USER)).toEqual({ own: 1, total: 1 });
        expect(await listPending(USER)).toEqual([]);

        s.state.dropAfterChunks = undefined;
        const outcome = await runSync(USER);

        expect(stored(s, "h-1", "photo")).toEqual(PHOTO);
        expect(stored(s, "h-1", "audio")).toEqual(AUDIO);
        expect(outcome.synced).toHaveLength(1);
        expect(await queueCounts(USER)).toEqual({ own: 0, total: 0 });
    });

    it("sends only the photo when there is no voice note", async () => {
        await enqueue(await report("h-1", false), USER);
        const s = server();

        await runSync(USER);

        expect(stored(s, "h-1", "photo")).toEqual(PHOTO);
        expect(s.mediaRequests().some((r) => r.url.endsWith("/audio"))).toBe(false);
        expect(await queueCounts(USER)).toEqual({ own: 0, total: 0 });
    });

    it("resumes from the server's offset after the connection drops mid-upload", async () => {
        await enqueue(await report("h-1"), USER);
        const s = server({ dropAfterChunks: 2 });

        await runSync(USER);

        expect(stored(s, "h-1", "photo")).toEqual(PHOTO.slice(0, 8));
        expect(await queueCounts(USER)).toEqual({ own: 1, total: 1 });

        s.state.dropAfterChunks = undefined;
        const before = s.log.length;
        await runSync(USER);

        const resumed = s.log.slice(before).filter((l) => l.method === "PUT");
        expect(resumed[0].offset).toBe(8);
        expect(stored(s, "h-1", "photo")).toEqual(PHOTO);
        expect(await queueCounts(USER)).toEqual({ own: 0, total: 0 });
    });

    it("takes the server's word when it refuses an offset", async () => {
        await enqueue(await report("h-1", false), USER);
        const s = server({ wrongOffsetOnce: true });

        await runSync(USER);

        expect(stored(s, "h-1", "photo")).toEqual(PHOTO);
        expect(await queueCounts(USER)).toEqual({ own: 0, total: 0 });
    });

    it("keeps the row when the voice note fails after the photo is done, and sends only what is missing next time", async () => {
        await enqueue(await report("h-1"), USER);
        const s = server({ dropAfterChunks: 3 });

        await runSync(USER);
        expect(stored(s, "h-1", "photo")).toEqual(PHOTO);
        expect(await queueCounts(USER)).toEqual({ own: 1, total: 1 });

        s.state.dropAfterChunks = undefined;
        const before = s.log.length;
        await runSync(USER);

        const puts = s.log.slice(before).filter((l) => l.method === "PUT");
        expect(puts.every((p) => p.url.endsWith("/audio"))).toBe(true);
        expect(await queueCounts(USER)).toEqual({ own: 0, total: 0 });
    });

    it("keeps the data and asks to sign in again when the session has ended", async () => {
        await enqueue(await report("h-1"), USER);
        const s = server({ sessionEnded: true });

        const outcome = await runSync(USER);

        expect(outcome.authExpired).toBe(true);
        expect(await queueCounts(USER)).toEqual({ own: 1, total: 1 });
        expect(await listStuck(USER)).toEqual([]);
        // one look at where the upload stands, one try to begin; both end at the 401
        expect(s.mediaRequests()).toHaveLength(2);
    });

    it("marks the row stuck, never deletes it, when the server says the report is gone", async () => {
        await enqueue(await report("h-1"), USER);
        server({ gone: true });

        await runSync(USER);

        expect(await listStuck(USER)).toHaveLength(1);
        expect(await queueCounts(USER)).toEqual({ own: 0, total: 1 });
        expect((await listStuck<{ media: unknown }>(USER))[0].payload.media).toBeDefined();
    });

    it("keeps the row after any other error, and parks it as stuck after five, still with its media", async () => {
        await enqueue(await report("h-1"), USER);
        server({ failWith: 500 });

        for (let run = 0; run < 5; run++) {
            await runSync(USER);
        }

        expect(await listStuck(USER)).toHaveLength(1);
        expect(await queueCounts(USER)).toEqual({ own: 0, total: 1 });
    });

    it("does not count a lost connection against the row", async () => {
        await enqueue(await report("h-1"), USER);
        server({ dropAfterChunks: 0 });

        for (let run = 0; run < 7; run++) {
            await runSync(USER);
        }

        expect(await listStuck(USER)).toEqual([]);
        expect(await queueCounts(USER)).toEqual({ own: 1, total: 1 });
    });

    it("keeps a report the server refused to accept, stuck, with its media", async () => {
        await enqueue(await report("h-1"), USER);
        const s = server({ textStatus: "needs_fixing" });

        await runSync(USER);

        expect(s.mediaRequests()).toEqual([]);
        expect(await listStuck(USER)).toHaveLength(1);
        expect(await queueCounts(USER)).toEqual({ own: 0, total: 1 });
    });

    it("opens one queue item at a time", async () => {
        await enqueue(await report("h-1"), USER);
        await enqueue(await report("h-2"), USER);
        await enqueue(await report("h-3"), USER);
        const s = server({ dropAfterChunks: 0 });
        await runSync(USER);
        s.state.dropAfterChunks = undefined;

        const decrypt = vi.spyOn(crypto.subtle, "decrypt");
        const decryptedWhenFirstFileMoves: number[] = [];
        const real = fetch;
        vi.stubGlobal("fetch", (url: string, init: RequestInit) => {
            if (init.method === "PUT" && decryptedWhenFirstFileMoves.length === 0) {
                decryptedWhenFirstFileMoves.push(decrypt.mock.calls.length);
            }

            return real(url, init);
        });

        await runSync(USER);

        expect(decryptedWhenFirstFileMoves).toEqual([1]);
        expect(await queueCounts(USER)).toEqual({ own: 0, total: 0 });
    });

    it("leaves another user's report alone", async () => {
        await enqueue(await report("h-1"), "8");
        const s = server();

        await runSync(USER);

        expect(s.log).toEqual([]);
        expect(await queueCounts("8")).toEqual({ own: 1, total: 1 });
    });

    it("does not touch a stuck report's media", async () => {
        const id = await enqueue(await report("h-1"), USER);
        for (let i = 0; i < 5; i++) {
            await recordFailedAttempt(id);
        }
        const s = server();

        await runSync(USER);

        expect(s.log).toEqual([]);
        expect(await listStuck(USER)).toHaveLength(1);
    });
});

describe("the data-cost warning", () => {
    const putsOf = (s: ReturnType<typeof server>) => s.mediaRequests().filter((l) => l.method === "PUT" || l.method === "POST");

    it("asks, with the approved words, before the first upload of a report with media", async () => {
        await enqueue(await report("h-1"), USER);
        const s = server();

        await runSync(USER);

        expect(asked).toEqual([DATA_COST_TEXT.warning]);
        expect(stored(s, "h-1", "photo")).toEqual(PHOTO);
    });

    it("asks before any file moves", async () => {
        await enqueue(await report("h-1"), USER);
        const s = server();
        const order: string[] = [];
        setDataCostAsker(async () => (order.push("asked"), "send"));
        const real = fetch;
        vi.stubGlobal("fetch", (url: string, init: RequestInit) => {
            if (url.startsWith("/sync/health-reports/") && (init.method === "POST" || init.method === "PUT")) order.push("sent");

            return real(url, init);
        });

        await runSync(USER);

        expect(order[0]).toBe("asked");
        expect(stored(s, "h-1", "photo")).toEqual(PHOTO);
    });

    it("does not ask again for that report after Send now, even after a dropped connection and a resume", async () => {
        await enqueue(await report("h-1"), USER);
        const s = server({ dropAfterChunks: 2 });

        await runSync(USER);
        s.state.dropAfterChunks = undefined;
        await runSync(USER);
        await runSync(USER);

        expect(asked).toHaveLength(1);
        expect(stored(s, "h-1", "photo")).toEqual(PHOTO);
        expect(await queueCounts(USER)).toEqual({ own: 0, total: 0 });
    });

    it("asks once for each report", async () => {
        await enqueue(await report("h-1"), USER);
        await enqueue(await report("h-2"), USER);
        server();

        await runSync(USER);

        expect(asked).toHaveLength(2);
    });

    it("sends nothing on Wait, keeps the row and its media untouched, and counts no failure", async () => {
        await enqueue(await report("h-1"), USER);
        answer("wait");
        const s = server();

        for (let run = 0; run < 7; run++) {
            await runSync(USER);
            // the farmer coming back to the app is what asks again
            document.dispatchEvent(new Event("visibilitychange"));
        }

        expect(putsOf(s)).toEqual([]);
        expect(await listStuck(USER)).toEqual([]);
        expect(await queueCounts(USER)).toEqual({ own: 1, total: 1 });
        const [item] = await Promise.all((await readIds()).map((id) => readItem<QueuedHealthReport>(id)));
        expect(item?.media.photo.data).not.toBe("");
    });

    it("does not ask again until the farmer returns to the app", async () => {
        await enqueue(await report("h-1"), USER);
        answer("wait");
        server();

        await runSync(USER);
        await runSync(USER);
        expect(asked).toHaveLength(1);

        Object.defineProperty(document, "visibilityState", { value: "visible", configurable: true });
        document.dispatchEvent(new Event("visibilitychange"));
        await runSync(USER);

        expect(asked).toHaveLength(2);
    });

    it("keeps asking on the next run when the dialog was closed without an answer", async () => {
        await enqueue(await report("h-1"), USER);
        answer("dismissed");
        server();

        await runSync(USER);
        await runSync(USER);

        expect(asked).toHaveLength(2);
    });

    it("sends the files once Send now is chosen after an earlier Wait", async () => {
        await enqueue(await report("h-1"), USER);
        answer("wait");
        const s = server();
        await runSync(USER);

        answer("send");
        await runSync(USER);

        expect(stored(s, "h-1", "photo")).toEqual(PHOTO);
        expect(await queueCounts(USER)).toEqual({ own: 0, total: 0 });
    });

    it("never asks about a report with no photo or voice note", async () => {
        const text = { ...(await report("h-1")), media: {} } as unknown as QueuedHealthReport;
        await enqueue(text, USER);
        const s = server();

        await runSync(USER);

        expect(asked).toEqual([]);
        expect(putsOf(s)).toEqual([]);
        expect(await queueCounts(USER)).toEqual({ own: 0, total: 0 });
    });

    it("does not ask about an upload that had already started", async () => {
        await enqueue(await report("h-1"), USER);
        const s = server({ preheld: { key: "h-1/photo", bytes: PHOTO.slice(0, 4), size: PHOTO.length } });

        await runSync(USER);

        expect(asked).toEqual([]);
        expect(stored(s, "h-1", "photo")).toEqual(PHOTO);
    });

    it("sends nothing, and keeps everything, when nothing is there to ask with", async () => {
        await enqueue(await report("h-1"), USER);
        setDataCostAsker(null);
        const s = server();

        await runSync(USER);

        expect(putsOf(s)).toEqual([]);
        expect(await queueCounts(USER)).toEqual({ own: 1, total: 1 });
    });
});
