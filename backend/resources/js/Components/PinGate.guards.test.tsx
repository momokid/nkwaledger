// @vitest-environment jsdom
import { act } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import "fake-indexeddb/auto";
import useOfflineSync from "@/hooks/useOfflineSync";
import { enqueue, listPending } from "@/lib/offlineStore";
import { savePin } from "@/lib/pin";
import { PIN_TEXT } from "@/lib/pinText";
import { checkGuards, getContact, recordContact, resetContactTrackingForTests } from "@/lib/serverContact";
import PinGate from "./PinGate";

const h = vi.hoisted(() => ({ syncOnce: vi.fn(async () => null) }));

vi.mock("@inertiajs/react", () => ({
    router: { post: vi.fn() },
    usePage: () => ({ props: { auth: { user: { id: 7 } } } }),
}));

vi.mock("@/lib/offlineSync", async (original) => ({
    ...(await original<typeof import("@/lib/offlineSync")>()),
    syncOnce: h.syncOnce,
}));

const until = (check: () => unknown) => vi.waitFor(check, { timeout: 8000 });

const DAY = 24 * 60 * 60 * 1000;
const HOUR = 60 * 60 * 1000;
const MINUTE = 60 * 1000;
const T0 = Date.UTC(2026, 2, 1, 8, 0, 0);

let clock = T0;
let root: Root;
let container: HTMLElement;

const text = () => container.textContent ?? "";
const flag = () => sessionStorage.getItem("nkwa_pin_unlocked");

function App() {
    useOfflineSync();

    return <p>inside-the-gate</p>;
}

const render = async () => {
    root = createRoot(container);
    await act(async () => root.render(<PinGate user={{ id: 7 }}><App /></PinGate>));
    await act(async () => new Promise((resolve) => setTimeout(resolve, 40)));
};

const unmount = () => act(async () => root.unmount());
const setVisibility = (state: "hidden" | "visible") =>
    act(async () => {
        Object.defineProperty(document, "visibilityState", { value: state, configurable: true });
        document.dispatchEvent(new Event("visibilitychange"));
    });

const answer = (status: number) => new Response("{}", { status, headers: { Date: new Date(clock).toUTCString() } });

const retry = () =>
    act(async () => {
        container.querySelector<HTMLButtonElement>('[data-testid="retry"]')!.click();
    });

beforeEach(async () => {
    (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    indexedDB = new IDBFactory();
    sessionStorage.clear();
    resetContactTrackingForTests();
    clock = T0;
    vi.spyOn(Date, "now").mockImplementation(() => clock);
    h.syncOnce.mockClear();
    container = document.createElement("div");
    document.body.appendChild(container);

    await savePin("7", "4826");
    await recordContact("7", T0);
    sessionStorage.setItem("nkwa_pin_unlocked", "7");
});

afterEach(async () => {
    await unmount();
    container.remove();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
    Object.defineProperty(document, "visibilityState", { value: "visible", configurable: true });
});

describe("the 7-day lock", () => {
    it("lets the app open after 6 days 23 hours", async () => {
        clock = T0 + 6 * DAY + 23 * HOUR;

        await render();

        expect(text()).toContain("inside-the-gate");
    });

    it("shows the offline lock at 7 days, clears the unlock flag and shows nothing of the app", async () => {
        clock = T0 + 7 * DAY;

        await render();

        await until(() => expect(text()).toContain(PIN_TEXT.offlineTooLong));
        expect(text()).toContain(PIN_TEXT.safe);
        expect(text()).not.toContain("inside-the-gate");
        expect(flag()).toBeNull();
    });

    it("locks when the page shows again after the days have passed, before any sync can start", async () => {
        clock = T0 + DAY;
        await render();
        expect(text()).toContain("inside-the-gate");
        h.syncOnce.mockClear();

        await setVisibility("hidden");
        clock = T0 + 7 * DAY;
        await setVisibility("visible");

        await until(() => expect(text()).toContain(PIN_TEXT.offlineTooLong));
        expect(h.syncOnce).not.toHaveBeenCalled();
    });
});

describe("the clock lock", () => {
    it("shows the clock lock when the phone clock was set back more than 5 minutes", async () => {
        await render();
        expect(text()).toContain("inside-the-gate");
        await unmount();

        clock = T0 - 6 * MINUTE;
        await render();

        await until(() => expect(text()).toContain(PIN_TEXT.clockWrong));
        expect(text()).toContain(PIN_TEXT.safe);
        expect(text()).not.toContain("inside-the-gate");
    });

    it("does not lock for a clock set back 4 minutes", async () => {
        await render();
        await unmount();

        clock = T0 - 4 * MINUTE;
        await render();

        expect(text()).toContain("inside-the-gate");
    });

    it("does not show the clock lock for a clock set forward", async () => {
        await render();
        await unmount();

        clock = T0 + 3 * DAY;
        await render();

        expect(text()).toContain("inside-the-gate");
        expect(text()).not.toContain(PIN_TEXT.clockWrong);
    });

    it("locks when the clock is set back to stretch the 7 days", async () => {
        clock = T0 + 6 * DAY;
        await render();
        expect(text()).toContain("inside-the-gate");
        await unmount();

        clock = T0 + DAY;
        await render();

        await until(() => expect(text()).toContain(PIN_TEXT.clockWrong));
    });
});

describe("while a lock is showing", () => {
    it("mounts no layout, runs no sync and does not open the queue", async () => {
        const open = vi.spyOn(indexedDB, "open");
        clock = T0 + 7 * DAY;

        await render();
        await until(() => expect(text()).toContain(PIN_TEXT.offlineTooLong));
        await act(async () => {
            window.dispatchEvent(new Event("online"));
        });

        expect(text()).not.toContain("inside-the-gate");
        expect(h.syncOnce).not.toHaveBeenCalled();
        expect(open.mock.calls.some(([name]) => name === "nkwa-offline-store")).toBe(false);
    });

    it("offers a way to sign out", async () => {
        clock = T0 + 7 * DAY;

        await render();
        await until(() => expect(text()).toContain(PIN_TEXT.offlineTooLong));

        expect(container.querySelector('[data-testid="sign-out"]')).not.toBeNull();
    });
});

describe("unlocking with a check on the server", () => {
    async function lockedByTime() {
        const record = {
            shape: 2 as const,
            uuid: crypto.randomUUID(),
            template: 1,
            farmer: "5b0f9f4e-0c3e-4a67-9a52-1e0f3f2a1111",
            amount: "100",
            event_date: "2026-03-01",
            device_created_at: "2026-03-01T08:00:00.000Z",
        };
        await enqueue(record, "7");
        clock = T0 + 7 * DAY;
        await render();
        await until(() => expect(text()).toContain(PIN_TEXT.offlineTooLong));
    }

    it("stays locked when the retry cannot reach the server", async () => {
        vi.stubGlobal("fetch", vi.fn().mockRejectedValue(new TypeError("offline")));
        await lockedByTime();

        await retry();

        expect(text()).toContain(PIN_TEXT.offlineTooLong);
        expect(text()).not.toContain("inside-the-gate");
    });

    it("goes to the PIN screen when the retry works, with the queue intact and the unlock flag still cleared", async () => {
        const fetchMock = vi.fn(async () => answer(200));
        vi.stubGlobal("fetch", fetchMock);
        await lockedByTime();

        await retry();

        await until(() => expect(text()).toContain(PIN_TEXT.enter));
        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(flag()).toBeNull();
        expect(text()).not.toContain("inside-the-gate");
        expect(await listPending("7")).toHaveLength(1);
        expect((await getContact("7"))?.serverTime).toBe(clock);
    });

    it("keeps the lock when the phone clock is still wrong after a good retry", async () => {
        await render();
        await unmount();
        clock = T0 - 10 * MINUTE;
        vi.stubGlobal("fetch", vi.fn(async () => new Response("{}", { status: 200, headers: { Date: new Date(T0).toUTCString() } })));
        await render();
        await until(() => expect(text()).toContain(PIN_TEXT.clockWrong));

        await retry();

        await until(() => expect(text()).toContain(PIN_TEXT.clockWrong));
    });

    it("shows the session-ended notice when the session has ended", async () => {
        vi.stubGlobal("fetch", vi.fn(async () => answer(401)));
        await lockedByTime();

        await retry();

        await until(() => expect(text()).toContain("Your session has ended"));
        expect(text()).toContain(PIN_TEXT.offlineTooLong);
    });
});

describe("first use", () => {
    it("sets last contact now and does not lock", async () => {
        indexedDB = new IDBFactory();
        await savePin("7", "4826");
        expect(await getContact("7")).toBeUndefined();

        await render();

        expect(text()).toContain("inside-the-gate");
        expect(await getContact("7")).toEqual({ serverTime: T0, wallAt: T0 });
        expect(await checkGuards("7", T0)).toBe("ok");
    });
});

describe("the worker's caches and a change of user", () => {
    const stubCaches = () => {
        const deleted: string[] = [];
        vi.stubGlobal("caches", {
            keys: async () => ["nkwa-shell-v1"],
            delete: async (name: string) => {
                deleted.push(name);

                return true;
            },
            open: async () => ({ add: async () => {} }),
        });

        return deleted;
    };

    beforeEach(() => localStorage.clear());

    it("are cleared when a different user is signed in on the same phone", async () => {
        const deleted = stubCaches();
        localStorage.setItem("nkwa_last_user", "8");

        await render();

        await until(() => expect(deleted).toEqual(["nkwa-shell-v1"]));
        expect(localStorage.getItem("nkwa_last_user")).toBe("7");
        expect(text()).toContain("inside-the-gate");
    });

    it("are kept when the same user comes back", async () => {
        const deleted = stubCaches();
        localStorage.setItem("nkwa_last_user", "7");

        await render();
        await act(async () => new Promise((resolve) => setTimeout(resolve, 60)));

        expect(deleted).toEqual([]);
    });

    it("are kept the first time any user is seen on the phone", async () => {
        const deleted = stubCaches();

        await render();
        await act(async () => new Promise((resolve) => setTimeout(resolve, 60)));

        expect(deleted).toEqual([]);
        expect(localStorage.getItem("nkwa_last_user")).toBe("7");
    });
});
