// @vitest-environment jsdom
import { act } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import "fake-indexeddb/auto";
import { enqueue, recordFailedAttempt } from "@/lib/offlineStore";
import { buildBatchRecord } from "@/lib/batchRecord";
import { buildHealthReport } from "@/lib/healthReport";
import { RECORDS_TEXT } from "@/lib/recordsText";
import { withQueueLock } from "@/lib/queueLock";
import RecordsAttention from "./RecordsAttention";

const h = vi.hoisted(() => ({ full: false, dark: false }));

vi.mock("@/Layouts/AuthenticatedLayout", () => ({ useTheme: () => ({ dark: h.dark }) }));
vi.mock("@inertiajs/react", () => ({ usePage: () => ({ props: { auth: { user: { id: 7 } } } }) }));
vi.mock("@/lib/offlineStore", async (importOriginal) => {
    const real = await importOriginal<typeof import("@/lib/offlineStore")>();
    const full = () => Promise.reject(new DOMException("full", "QuotaExceededError"));

    return {
        ...real,
        retryStuck: (id: string) => (h.full ? full() : real.retryStuck(id)),
        deleteStuck: (id: string) => (h.full ? full() : real.deleteStuck(id)),
    };
});

const FARMER = "5b0f9f4e-0c3e-4a67-9a52-1e0f3f2a1111";
const GENERIC = "Something went wrong. Please try again.";

let root: Root;
let container: HTMLElement;

const flaggedRow = (over: Record<string, unknown> = {}) => ({
    uuid: crypto.randomUUID(),
    kind: "record",
    status: "needs_fixing",
    reason: "The amount is too high.",
    event_date: "2026-03-01",
    template: "I sold crops",
    amount: "120.50",
    description: null,
    ...over,
});

const show = async (flagged: unknown[] = []) => {
    await act(async () => root.render(<RecordsAttention flagged={flagged as never} farmerId={FARMER} />));
    // the phone's storage answers a moment after the page draws
    await act(async () => new Promise((resolve) => setTimeout(resolve, 80)));
};

const text = () => container.textContent ?? "";
const buttons = () => Array.from(container.querySelectorAll("button")).map((b) => b.textContent);
const press = (label: string, within: ParentNode = container) =>
    act(async () => {
        Array.from(within.querySelectorAll("button")).find((b) => b.textContent === label)!.click();
    });

async function stick(owner: string, amount = "250") {
    const id = await enqueue(buildBatchRecord(FARMER, { transaction_template_id: "1", amount, transaction_date: "2026-03-01" }, "", crypto.randomUUID()), owner);

    for (let i = 0; i < 5; i++) {
        await recordFailedAttempt(id);
    }

    return id;
}

const rowOf = async (id: string) => {
    const db = await new Promise<IDBDatabase>((resolve, reject) => {
        const request = indexedDB.open("nkwa-offline-store");
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
    const row = await new Promise<{ stuck?: boolean; attempts?: number } | undefined>((resolve, reject) => {
        const request = db.transaction("queue", "readonly").objectStore("queue").get(id);
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
    db.close();

    return row;
};

beforeEach(() => {
    (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    indexedDB = new IDBFactory();
    document.head.innerHTML = '<meta name="csrf-token" content="t">';
    h.full = false;
    h.dark = false;
    container = document.createElement("div");
    document.body.appendChild(container);
    root = createRoot(container);
});

afterEach(async () => {
    await withQueueLock(async () => {});
    await act(async () => root.unmount());
    container.remove();
    vi.unstubAllGlobals();
});

describe("the three groups", () => {
    it("shows nothing at all when no record is held up", async () => {
        await show();

        expect(text()).toBe("");
    });

    it("shows a stuck record under its heading, with the approved wording and its details", async () => {
        await stick("7", "250");
        await show();

        expect(text()).toContain(RECORDS_TEXT.stuckHeading);
        expect(text()).toContain(RECORDS_TEXT.stuck);
        expect(text()).toContain("250");
    });

    it("shows a record that needs a fix with its wording and the server's reason", async () => {
        await show([flaggedRow()]);

        expect(text()).toContain(RECORDS_TEXT.fixHeading);
        expect(text()).toContain(RECORDS_TEXT.fix);
        expect(text()).toContain("The amount is too high.");
        expect(text()).toContain("I sold crops");
    });

    it("shows a rejected record with its wording and the reason as plain text", async () => {
        await show([flaggedRow({ status: "rejected", reason: "<b>Not a sale.</b>" })]);

        expect(text()).toContain(RECORDS_TEXT.rejectedHeading);
        expect(text()).toContain(RECORDS_TEXT.rejected);
        expect(text()).toContain("<b>Not a sale.</b>");
        expect(container.querySelector("b")).toBeNull();
    });

    it("shows a held record with its wording and no reason", async () => {
        await show([flaggedRow({ status: "held", reason: null })]);

        expect(text()).toContain(RECORDS_TEXT.heldHeading);
        expect(text()).toContain(RECORDS_TEXT.held);
        expect(text()).not.toContain(RECORDS_TEXT.fix);
    });

    it("shows the reason as plain text, never as markup", async () => {
        await show([flaggedRow({ reason: "<b>bold</b><img src=x onerror=alert(1)>" })]);

        expect(container.querySelector("b")).toBeNull();
        expect(container.querySelector("img")).toBeNull();
        expect(text()).toContain("<b>bold</b>");
    });

    it("shows a health report that needs a fix, read-only", async () => {
        await show([flaggedRow({ kind: "health_report", template: null, amount: null, description: "Some birds look weak", reason: "A record could not be saved." })]);

        expect(text()).toContain(RECORDS_TEXT.fix);
        expect(text()).toContain("Some birds look weak");
        expect(buttons()).toEqual([]);
    });

    it("offers no Retry or Delete on a record that needs a fix or waits for a check", async () => {
        await show([flaggedRow(), flaggedRow({ status: "held", reason: null })]);

        expect(buttons()).toEqual([]);
        expect(container.querySelector("a, input, textarea")).toBeNull();
    });

    it("never shows another user's stuck record", async () => {
        await stick("8");
        await show();

        expect(text()).toBe("");
    });

    it("lists a stuck health report from its summary, without opening its media", async () => {
        const report = await buildHealthReport({
            farmer: FARMER,
            farmUnitId: 4,
            description: "Some birds look weak",
            photo: new File([new Uint8Array([1])], "p.png", { type: "image/png" }),
            audio: null,
            uuid: crypto.randomUUID(),
        });
        const id = await enqueue(report, "7");
        for (let i = 0; i < 5; i++) await recordFailedAttempt(id);
        const decrypt = vi.spyOn(crypto.subtle, "decrypt");
        const before = decrypt.mock.calls.length;

        await show();

        expect(text()).toContain("Some birds look weak");
        // one small summary opened, not the report with its photo
        expect(decrypt.mock.calls.length - before).toBe(1);
    });
});

describe("Retry", () => {
    it("sends the record now and resets its failure count", async () => {
        const id = await stick("7");
        const seen: string[] = [];
        vi.stubGlobal(
            "fetch",
            vi.fn(async (url: string) => {
                seen.push(url);
                throw new TypeError("offline");
            }),
        );
        await show();

        await press(RECORDS_TEXT.retry);

        await vi.waitFor(() => expect(seen).toContain("/sync/submissions"));
        expect(await rowOf(id)).toMatchObject({ stuck: false, attempts: 0 });
        expect(text()).not.toContain(RECORDS_TEXT.stuck);
    });

    it("lets the record go once the server takes it", async () => {
        const id = await stick("7");
        vi.stubGlobal(
            "fetch",
            vi.fn(async (_url: string, init: RequestInit) => {
                const { records } = JSON.parse(init.body as string) as { records: Array<{ uuid: string }> };

                return new Response(JSON.stringify({ results: records.map((r) => ({ uuid: r.uuid, status: "accepted" })) }), { status: 200 });
            }),
        );
        await show();

        await press(RECORDS_TEXT.retry);

        await vi.waitFor(async () => expect(await rowOf(id)).toBeUndefined());
    });

    it("shows the approved text and keeps the data when the phone is full", async () => {
        const id = await stick("7");
        h.full = true;
        await show();

        await press(RECORDS_TEXT.retry);

        expect(text()).toContain(GENERIC);
        expect(await rowOf(id)).toMatchObject({ stuck: true });
    });
});

describe("Delete", () => {
    const dialog = () => container.querySelector<HTMLElement>('[role="dialog"]');

    it("asks first, with the approved words and two buttons, and deletes nothing yet", async () => {
        const id = await stick("7");
        await show();

        await press(RECORDS_TEXT.delete);

        expect(dialog()).not.toBeNull();
        expect(dialog()!.textContent).toBe(`${RECORDS_TEXT.confirmDelete}${RECORDS_TEXT.delete}${RECORDS_TEXT.keep}`);
        expect(await rowOf(id)).toMatchObject({ stuck: true });
    });

    it("changes nothing on Keep", async () => {
        const id = await stick("7");
        await show();
        await press(RECORDS_TEXT.delete);

        await press(RECORDS_TEXT.keep, dialog()!);

        expect(dialog()).toBeNull();
        expect(await rowOf(id)).toMatchObject({ stuck: true, attempts: 5 });
        expect(text()).toContain(RECORDS_TEXT.stuck);
    });

    it("removes the row and its media only after Delete is confirmed", async () => {
        const id = await stick("7");
        await show();
        await press(RECORDS_TEXT.delete);

        await press(RECORDS_TEXT.delete, dialog()!);

        expect(await rowOf(id)).toBeUndefined();
        await vi.waitFor(() => expect(text()).not.toContain(RECORDS_TEXT.stuck));
    });

    it("shows the approved text and keeps the data when the phone is full", async () => {
        const id = await stick("7");
        await show();
        await press(RECORDS_TEXT.delete);
        h.full = true;

        await press(RECORDS_TEXT.delete, dialog()!);

        expect(text()).toContain(GENERIC);
        expect(await rowOf(id)).toMatchObject({ stuck: true });
    });
});

describe("Dismiss", () => {
    const online = (value: boolean) => Object.defineProperty(navigator, "onLine", { value, configurable: true });
    const rejected = (over: Record<string, unknown> = {}) => flaggedRow({ status: "rejected", reason: "Not a sale.", ...over });
    const dismissButton = () => Array.from(container.querySelectorAll("button")).find((b) => b.textContent === RECORDS_TEXT.dismiss)!;

    afterEach(() => online(true));

    it("is on rejected records only", async () => {
        await show([flaggedRow(), flaggedRow({ status: "held", reason: null }), rejected()]);

        expect(buttons()).toEqual([RECORDS_TEXT.dismiss]);
    });

    it("asks the server, and the record leaves the list at once with no confirmation", async () => {
        const row = rejected();
        const calls: Array<{ url: string; method: string | undefined }> = [];
        vi.stubGlobal(
            "fetch",
            vi.fn(async (url: string, init: RequestInit) => {
                calls.push({ url, method: init.method });

                return new Response(JSON.stringify({ dismissed: true }), { status: 200 });
            }),
        );
        await show([row]);

        await press(RECORDS_TEXT.dismiss);

        await vi.waitFor(() => expect(text()).not.toContain(RECORDS_TEXT.rejected));
        expect(calls).toEqual([{ url: `/my-records/rejected/${row.uuid}/dismiss`, method: "POST" }]);
        expect(container.querySelector('[role="dialog"]')).toBeNull();
    });

    it("keeps the record and shows the approved text when the server cannot be reached", async () => {
        vi.stubGlobal(
            "fetch",
            vi.fn(async () => {
                throw new TypeError("offline");
            }),
        );
        await show([rejected()]);

        await press(RECORDS_TEXT.dismiss);

        await vi.waitFor(() => expect(text()).toContain(GENERIC));
        expect(text()).toContain(RECORDS_TEXT.rejected);
    });

    it("is disabled with no connection, adds no text, and sends nothing", async () => {
        const fetchMock = vi.fn();
        vi.stubGlobal("fetch", fetchMock);
        online(false);
        await show([rejected()]);
        const before = text();

        await act(async () => dismissButton().click());

        expect(dismissButton().disabled).toBe(true);
        expect(fetchMock).not.toHaveBeenCalled();
        expect(text()).toBe(before);
    });

    it("turns on again when the connection comes back", async () => {
        online(false);
        await show([rejected()]);
        expect(dismissButton().disabled).toBe(true);

        online(true);
        await act(async () => window.dispatchEvent(new Event("online")));

        expect(dismissButton().disabled).toBe(false);
    });
});

describe("the two themes", () => {
    it("draws a group on a light surface, or a dark one", async () => {
        await show([flaggedRow()]);
        const light = container.querySelector<HTMLElement>("section")!.style.background;

        h.dark = true;
        await show([flaggedRow()]);

        expect(container.querySelector<HTMLElement>("section")!.style.background).not.toBe(light);
    });
});
