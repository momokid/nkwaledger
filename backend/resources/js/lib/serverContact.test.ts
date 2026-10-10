// @vitest-environment jsdom
import { beforeEach, describe, expect, it, vi } from "vitest";
import "fake-indexeddb/auto";
import {
    checkGuards,
    CLOCK_TOLERANCE_MS,
    getContact,
    getHighWater,
    installContactTracking,
    noteContact,
    OFFLINE_LIMIT_MS,
    recordContact,
    resetContactTrackingForTests,
    retryContact,
    setContactUser,
} from "./serverContact";
import { enqueue } from "./offlineStore";
import { runSync } from "./offlineSync";

const DAY = 24 * 60 * 60 * 1000;
const HOUR = 60 * 60 * 1000;
const MINUTE = 60 * 1000;
const T0 = Date.UTC(2026, 2, 1, 8, 0, 0);

beforeEach(() => {
    indexedDB = new IDBFactory();
    resetContactTrackingForTests();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

async function contactAt(time: number) {
    vi.spyOn(Date, "now").mockReturnValue(time);
    await recordContact("7", time);
}

describe("the limits", () => {
    it("are named: seven days and five minutes", () => {
        expect(OFFLINE_LIMIT_MS).toBe(7 * DAY);
        expect(CLOCK_TOLERANCE_MS).toBe(5 * MINUTE);
    });
});

describe("the 7-day lock", () => {
    it("does not lock at 6 days 23 hours", async () => {
        await contactAt(T0);

        expect(await checkGuards("7", T0 + 6 * DAY + 23 * HOUR)).toBe("ok");
    });

    it("locks at 7 days", async () => {
        await contactAt(T0);

        expect(await checkGuards("7", T0 + 7 * DAY)).toBe("offline_too_long");
    });

    it("counts a clock set forward toward the 7 days, and does not call it a wrong clock", async () => {
        await contactAt(T0);

        expect(await checkGuards("7", T0 + 8 * DAY)).toBe("offline_too_long");
    });

    it("on first use sets last contact now and does not lock", async () => {
        expect(await getContact("7")).toBeUndefined();

        expect(await checkGuards("7", T0)).toBe("ok");
        expect(await getContact("7")).toEqual({ serverTime: T0, wallAt: T0 });
    });
});

describe("the clock lock", () => {
    it("locks when the phone clock reads more than 5 minutes earlier than ever seen", async () => {
        await checkGuards("7", T0);

        expect(await checkGuards("7", T0 - 6 * MINUTE)).toBe("clock_wrong");
    });

    it("does not lock for 4 minutes earlier", async () => {
        await checkGuards("7", T0);

        expect(await checkGuards("7", T0 - 4 * MINUTE)).toBe("ok");
    });

    it("does not lock for a clock moved forward", async () => {
        await checkGuards("7", T0);

        expect(await checkGuards("7", T0 + 3 * DAY)).toBe("ok");
    });

    it("locks when the clock is set back to stretch the 7 days", async () => {
        await contactAt(T0);
        expect(await checkGuards("7", T0 + 6 * DAY)).toBe("ok");

        expect(await checkGuards("7", T0 + 1 * DAY)).toBe("clock_wrong");
    });

    it("does not lower the high-water mark when it locks", async () => {
        await checkGuards("7", T0);
        await checkGuards("7", T0 - DAY);

        expect(await getHighWater()).toBe(T0);
    });
});

describe("last contact", () => {
    it("is set from the server's own time and the phone's clock at that moment", async () => {
        vi.spyOn(Date, "now").mockReturnValue(T0 + 123);

        await recordContact("7", T0);

        expect(await getContact("7")).toEqual({ serverTime: T0, wallAt: T0 + 123 });
    });

    it("a retry that works reads the Date header, updates contact and the high-water mark", async () => {
        vi.spyOn(Date, "now").mockReturnValue(T0 + 5 * MINUTE);
        vi.stubGlobal("fetch", vi.fn().mockResolvedValue(new Response("{}", { status: 200, headers: { Date: new Date(T0).toUTCString() } })));
        await checkGuards("7", T0 + 2 * DAY);

        expect(await retryContact("7")).toBe("ok");
        expect(await getContact("7")).toEqual({ serverTime: T0, wallAt: T0 + 5 * MINUTE });
        expect(await getHighWater()).toBe(T0);
    });

    it("a retry offline fails and changes nothing", async () => {
        vi.stubGlobal("fetch", vi.fn().mockRejectedValue(new TypeError("offline")));
        await checkGuards("7", T0);

        expect(await retryContact("7")).toBe("failed");
        expect(await getContact("7")).toEqual({ serverTime: T0, wallAt: T0 });
    });

    it.each([401, 419])("a retry answered with %i says the session has ended", async (status) => {
        vi.stubGlobal("fetch", vi.fn().mockResolvedValue(new Response("{}", { status })));

        expect(await retryContact("7")).toBe("session_ended");
    });

    it("a retry that lands on a redirect says the session has ended", async () => {
        vi.stubGlobal("fetch", vi.fn().mockResolvedValue({ type: "opaqueredirect", ok: false, status: 0 } as Response));

        expect(await retryContact("7")).toBe("session_ended");
    });

    it("a retry reads no queue", async () => {
        vi.stubGlobal("fetch", vi.fn().mockResolvedValue(new Response("{}", { status: 200, headers: { Date: new Date(T0).toUTCString() } })));
        const open = vi.spyOn(indexedDB, "open");

        await retryContact("7");

        expect(open.mock.calls.some(([name]) => name === "nkwa-offline-store")).toBe(false);
    });

    it("is updated by a successful sync run", async () => {
        const answer = new Response(JSON.stringify({ results: [] }), { status: 200, headers: { Date: new Date(T0).toUTCString() } });
        vi.stubGlobal("fetch", vi.fn().mockResolvedValue(answer));
        installContactTracking();
        setContactUser("7");
        document.head.innerHTML = '<meta name="csrf-token" content="t">';
        await enqueue(
            {
                shape: 2,
                uuid: crypto.randomUUID(),
                template: 1,
                farmer: "5b0f9f4e-0c3e-4a67-9a52-1e0f3f2a1111",
                amount: "100",
                event_date: "2026-03-01",
                device_created_at: "2026-03-01T08:00:00.000Z",
            },
            "7",
        );

        await runSync("7");
        await vi.waitFor(async () => expect((await getContact("7"))?.serverTime).toBe(T0));
    });

    it("is not updated by an answer with no server date", () => {
        setContactUser("7");

        noteContact(null);
        noteContact("not a date");

        return expect(getContact("7")).resolves.toBeUndefined();
    });
});
