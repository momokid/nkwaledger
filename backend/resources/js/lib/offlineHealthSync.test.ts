// @vitest-environment jsdom
import { beforeEach, describe, expect, it, vi } from "vitest";
import "fake-indexeddb/auto";
import { enqueue, listPending, listStuck, queueCounts } from "./offlineStore";
import { runSync } from "./offlineSync";
import { buildBatchRecord } from "./batchRecord";
import { buildHealthReport } from "./healthReport";

const USER = "7";
const FARMER = "5b0f9f4e-0c3e-4a67-9a52-1e0f3f2a1111";

beforeEach(() => {
    indexedDB = new IDBFactory();
    document.head.innerHTML = '<meta name="csrf-token" content="t">';
});

const report = (uuid: string) =>
    buildHealthReport({
        farmer: FARMER,
        farmUnitId: 4,
        description: "Weak birds",
        photo: new File([new Uint8Array([9, 9])], "leaf.png", { type: "image/png" }),
        audio: new File([new Uint8Array([1])], "voice-note.webm", { type: "audio/webm" }),
        uuid,
    });

function serve(status: (uuid: string) => string | null = () => "accepted") {
    const sent: Array<Record<string, unknown>> = [];

    vi.stubGlobal(
        "fetch",
        vi.fn(async (_url: string, init: RequestInit) => {
            const { records } = JSON.parse(init.body as string) as { records: Array<{ uuid: string }> };
            sent.push(...records);

            return new Response(JSON.stringify({ results: records.map((r) => ({ uuid: r.uuid, status: status(r.uuid) })) }), { status: 200 });
        }),
    );

    return sent;
}

describe("health reports in the offline queue", () => {
    it("keeps the photo and voice note with the queued report", async () => {
        await enqueue(await report("a"), USER);

        const [item] = await listPending<Awaited<ReturnType<typeof report>>>(USER);

        expect(item.payload.media.photo.type).toBe("image/png");
        expect(item.payload.media.audio?.type).toBe("audio/webm");
    });

    it("sends the text in saved order with the other records and never the media", async () => {
        await enqueue(buildBatchRecord(FARMER, { transaction_template_id: "1", amount: "10", transaction_date: "2026-03-01" }, "", "r-1"), USER);
        await enqueue(await report("h-1"), USER);
        await enqueue(buildBatchRecord(FARMER, { transaction_template_id: "1", amount: "20", transaction_date: "2026-03-01" }, "", "r-2"), USER);
        const sent = serve();

        const outcome = await runSync(USER);

        expect(sent.map((r) => r.uuid)).toEqual(["r-1", "h-1", "r-2"]);
        expect(sent[1]).toMatchObject({ type: "health_report", farm_unit_id: 4, description: "Weak birds" });
        expect(sent[1]).not.toHaveProperty("media");
        expect(sent[1]).not.toHaveProperty("shape");
        expect(outcome.synced).toHaveLength(3);
        expect(await listPending(USER)).toEqual([]);
    });

    it("sends a report the server refused to needs-fixing out of the queue like a record", async () => {
        await enqueue(await report("h-1"), USER);
        serve(() => "needs_fixing");

        const outcome = await runSync(USER);

        expect(outcome.synced).toHaveLength(1);
        expect(await queueCounts(USER)).toEqual({ own: 0, total: 0 });
    });

    it("parks a report as stuck after five failed sends, keeps it with its media, and never sends it again", async () => {
        await enqueue(await report("h-1"), USER);
        const sent = serve(() => "error");

        for (let run = 0; run < 6; run++) {
            await runSync(USER);
        }

        expect(sent).toHaveLength(5);
        const [stuck] = await listStuck<Awaited<ReturnType<typeof report>>>(USER);
        expect(stuck.payload.media.photo.data).not.toBe("");
        expect(await queueCounts(USER)).toEqual({ own: 0, total: 1 });
    });

    it("counts as unsent for sign-out, and another user's sync neither sends nor deletes it", async () => {
        await enqueue(await report("h-1"), USER);
        const sent = serve();

        expect(await queueCounts(USER)).toEqual({ own: 1, total: 1 });

        await runSync("8");

        expect(sent).toEqual([]);
        expect(await queueCounts(USER)).toEqual({ own: 1, total: 1 });
        expect(await queueCounts("8")).toEqual({ own: 0, total: 1 });
    });
});
