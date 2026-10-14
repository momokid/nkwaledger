// @vitest-environment jsdom
import { act } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import "fake-indexeddb/auto";
import { enqueue, listPending } from "@/lib/offlineStore";
import { runSync } from "@/lib/offlineSync";
import { getPinRecord, savePin, verifyPin } from "@/lib/pin";
import { PIN_TEXT } from "@/lib/pinText";
import useOfflineSync from "@/hooks/useOfflineSync";
import PinGate from "./PinGate";

// the PIN hash is deliberately slow, so outcomes get longer than the default one second
const until = (check: () => unknown) => vi.waitFor(check, { timeout: 8000 });

const h = vi.hoisted(() => ({ syncMounts: 0, syncOnce: vi.fn(async () => null) }));

vi.mock("@inertiajs/react", () => ({
    router: { post: vi.fn() },
    usePage: () => ({ props: { auth: { user: { id: 7 } } } }),
}));

vi.mock("@/lib/offlineSync", async (original) => ({
    ...(await original<typeof import("@/lib/offlineSync")>()),
    syncOnce: h.syncOnce,
}));

function Child() {
    // stands in for a layout: a layout is what mounts useOfflineSync
    h.syncMounts += 1;

    return <p>the app</p>;
}

let root: Root;
let container: HTMLElement;

const text = () => container.textContent ?? "";
const flag = () => sessionStorage.getItem("nkwa_pin_unlocked");

async function show(user: { id: number } | null) {
    await act(async () => root.render(<PinGate user={user}><Child /></PinGate>));
    // every state the gate can be in puts something on the screen once its checks are done
    await until(() => expect(text()).not.toBe(""));
    await settle();
}

const settle = () => act(async () => new Promise((resolve) => setTimeout(resolve, 30)));

async function enter(pin: string) {
    await until(() => expect(container.querySelector("input")).not.toBeNull());
    const input = container.querySelector("input")!;
    await act(async () => {
        Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, "value")!.set!.call(input, pin);
        input.dispatchEvent(new Event("input", { bubbles: true }));
    });
    await act(async () => {
        container.querySelector("form")!.dispatchEvent(new Event("submit", { bubbles: true, cancelable: true }));
    });
    await settle();
}

const showsApp = () => until(() => expect(text()).toContain("the app"));

async function setUp(pin: string) {
    await enter(pin);
    await enter(pin);
    await showsApp();
}

// each wrong try is counted in the store before the next one is typed
async function wrongTries(count: number) {
    for (let tried = 1; tried <= count; tried++) {
        await enter("1357");
        await until(async () => expect((await getPinRecord("7"))!.attempts).toBe(tried));
    }
}

beforeEach(() => {
    (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    indexedDB = new IDBFactory();
    sessionStorage.clear();
    localStorage.clear();
    h.syncMounts = 0;
    container = document.createElement("div");
    document.body.appendChild(container);
    root = createRoot(container);
});

afterEach(async () => {
    await act(async () => root.unmount());
    container.remove();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

describe("setting up a PIN", () => {
    it("asks a signed-in user with no PIN to create one, and shows nothing of the app", async () => {
        await show({ id: 7 });

        expect(text()).toContain(PIN_TEXT.create);
        expect(text()).not.toContain("the app");
        expect(h.syncMounts).toBe(0);
    });

    it.each(["1111", "1234", "4321", "0123", "9876"])("refuses the easy PIN %s", async (pin) => {
        await show({ id: 7 });
        await enter(pin);

        expect(text()).toContain(PIN_TEXT.weak);
        expect(text()).toContain(PIN_TEXT.create);
        expect(await getPinRecord("7")).toBeUndefined();
    });

    it("refuses two PINs that do not match and starts again", async () => {
        await show({ id: 7 });
        await enter("4826");

        expect(text()).toContain(PIN_TEXT.confirm);

        await enter("4827");

        expect(text()).toContain(PIN_TEXT.mismatch);
        expect(text()).toContain(PIN_TEXT.create);
        expect(await getPinRecord("7")).toBeUndefined();
        expect(flag()).toBeNull();
    });

    it("saves a matching PIN, unlocks, and sets the session flag for this user", async () => {
        await show({ id: 7 });
        await setUp("4826");

        expect(text()).toContain("the app");
        expect(flag()).toBe("7");
        expect(await getPinRecord("7")).toMatchObject({ attempts: 0, locked: false });
    });

    it("never keeps the PIN in storage or logs in plain text", async () => {
        const logs = [vi.spyOn(console, "log"), vi.spyOn(console, "warn"), vi.spyOn(console, "error"), vi.spyOn(console, "info")];

        await show({ id: 7 });
        await setUp("4826");

        const record = await getPinRecord("7");

        expect(Object.values(record!).some((value) => value === "4826" || value === 4826)).toBe(false);
        expect(Object.values(sessionStorage).concat(Object.values(localStorage)).some((value) => String(value).includes("4826"))).toBe(false);
        expect(logs.some((log) => JSON.stringify(log.mock.calls).includes("4826"))).toBe(false);
    });
});

describe("unlocking", () => {
    beforeEach(async () => {
        await savePin("7", "4826");
    });

    it("stays unlocked on a reload in the same session", async () => {
        await show({ id: 7 });
        await enter("4826");
        await showsApp();

        await act(async () => root.unmount());
        root = createRoot(container);
        await show({ id: 7 });

        expect(text()).toContain("the app");
        expect(text()).not.toContain(PIN_TEXT.enter);
    });

    it("asks for the PIN in a new session", async () => {
        await show({ id: 7 });

        expect(text()).toContain(PIN_TEXT.enter);
        expect(text()).not.toContain("the app");
    });

    it("says the PIN is wrong and stays locked out of the app", async () => {
        await show({ id: 7 });
        await enter("1357");

        await until(() => expect(text()).toContain(PIN_TEXT.wrong));
        expect(text()).not.toContain("the app");
        expect(flag()).toBeNull();
    });

    it("does not let another user's PIN unlock this user", async () => {
        await savePin("8", "2580");
        sessionStorage.setItem("nkwa_pin_unlocked", "8");

        await show({ id: 7 });

        expect(text()).toContain(PIN_TEXT.enter);

        await enter("2580");

        await until(() => expect(text()).toContain(PIN_TEXT.wrong));
        expect(text()).not.toContain("the app");
    });

    it("does not mount the app, and does not touch the queue database, while locked", async () => {
        const open = vi.spyOn(indexedDB, "open");

        await show({ id: 7 });

        expect(h.syncMounts).toBe(0);
        expect(open.mock.calls.some(([name]) => name === "nkwa-offline-store")).toBe(false);
    });
});

describe("too many wrong tries", () => {
    beforeEach(async () => {
        await savePin("7", "4826");
    });

    it("locks on the fifth wrong try, shows the locked wording, and stays locked after a reload", async () => {
        await show({ id: 7 });
        await wrongTries(5);

        await until(() => expect(text()).toContain(PIN_TEXT.locked));
        expect(text()).toContain(PIN_TEXT.safe);
        expect(container.querySelector("input")).toBeNull();

        await act(async () => root.unmount());
        root = createRoot(container);
        await show({ id: 7 });

        expect(text()).toContain(PIN_TEXT.locked);
        expect(container.querySelector("input")).toBeNull();
        expect(text()).not.toContain("the app");
    });

    it("no longer accepts the correct PIN once locked", async () => {
        await show({ id: 7 });
        await wrongTries(5);

        expect(await verifyPin("7", "4826")).toBe("locked");
        expect(flag()).toBeNull();
    });

    it("offers a sign-out button while locked", async () => {
        await show({ id: 7 });
        await wrongTries(5);

        await until(() => expect(text()).toContain(PIN_TEXT.locked));
        expect(container.querySelectorAll("button").length).toBeGreaterThan(0);
    });
});

describe("guest pages", () => {
    it("are not locked, and need no PIN", async () => {
        const open = vi.spyOn(indexedDB, "open");

        await show(null);

        expect(text()).toContain("the app");
        expect(open).not.toHaveBeenCalled();
    });
});

describe("resetting a locked PIN by SMS", () => {
    const answer = (status: number, body: object = {}) => new Response(JSON.stringify(body), { status });

    const click = (label: string) =>
        act(async () => {
            Array.from(container.querySelectorAll("button")).find((button) => button.textContent === label)!.click();
        });

    const stubFetch = (handler: (url: string) => Response | Promise<Response>) => {
        const fetchMock = vi.fn(async (url: string) => handler(url));
        vi.stubGlobal("fetch", fetchMock);

        return fetchMock;
    };

    beforeEach(async () => {
        document.head.innerHTML = '<meta name="csrf-token" content="t">';
        await savePin("7", "4826");
    });

    async function lockIt() {
        await show({ id: 7 });
        await wrongTries(5);
        await until(() => expect(text()).toContain(PIN_TEXT.locked));
    }

    async function askForCode() {
        await click(PIN_TEXT.forgot);
        await click(PIN_TEXT.sendCode);
        await until(() => expect(text()).toContain(PIN_TEXT.enterCode));
    }

    it("is offered on the locked screen and on the enter screen", async () => {
        await show({ id: 7 });

        expect(text()).toContain(PIN_TEXT.forgot);

        await wrongTries(5);
        await until(() => expect(text()).toContain(PIN_TEXT.locked));

        expect(text()).toContain(PIN_TEXT.forgot);
    });

    it("sends the code, and a right code leads to setup, with the old PIN no longer working", async () => {
        const fetchMock = stubFetch((url) => (url === "/pin-reset/send" ? answer(200, { status: "sent" }) : answer(200, { status: "ok" })));
        await lockIt();

        await askForCode();
        await enter("123456");

        await until(() => expect(text()).toContain(PIN_TEXT.create));
        expect(fetchMock.mock.calls.map(([url]) => url)).toEqual(["/pin-reset/send", "/pin-reset/confirm"]);
        expect(await getPinRecord("7")).toBeUndefined();
        expect(await verifyPin("7", "4826")).toBe("none");
        expect(text()).not.toContain("the app");
    });

    it("applies the same easy-PIN rules to the new PIN", async () => {
        stubFetch((url) => (url === "/pin-reset/send" ? answer(200, { status: "sent" }) : answer(200, { status: "ok" })));
        await lockIt();
        await askForCode();
        await enter("123456");
        await until(() => expect(text()).toContain(PIN_TEXT.create));

        await enter("1234");

        expect(text()).toContain(PIN_TEXT.weak);
    });

    it("shows the wrong-code text for a wrong code", async () => {
        stubFetch((url) => (url === "/pin-reset/send" ? answer(200, { status: "sent" }) : answer(422, { status: "wrong" })));
        await lockIt();
        await askForCode();

        await enter("123456");

        await until(() => expect(text()).toContain(PIN_TEXT.wrongCode));
        expect(await getPinRecord("7")).toMatchObject({ locked: true });
    });

    it("shows the expired text for an expired or used code", async () => {
        stubFetch((url) => (url === "/pin-reset/send" ? answer(200, { status: "sent" }) : answer(422, { status: "expired" })));
        await lockIt();
        await askForCode();

        await enter("123456");

        await until(() => expect(text()).toContain(PIN_TEXT.expiredCode));
    });

    it("shows the too-many-codes text once the server says so", async () => {
        stubFetch((url) => (url === "/pin-reset/send" ? answer(200, { status: "sent" }) : answer(422, { status: "too_many" })));
        await lockIt();
        await askForCode();

        await enter("123456");

        await until(() => expect(text()).toContain(PIN_TEXT.tooManyCodes));
    });

    it("says the code could not be sent when offline, and stays locked", async () => {
        stubFetch(() => {
            throw new TypeError("offline");
        });
        await lockIt();

        await click(PIN_TEXT.forgot);
        await click(PIN_TEXT.sendCode);

        await until(() => expect(text()).toContain(PIN_TEXT.couldNotSend));
        expect(await getPinRecord("7")).toMatchObject({ locked: true });
        expect(text()).not.toContain("the app");
    });

    it("leaves the queue intact, syncs nothing until the new PIN is set, then syncs", async () => {
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

        const fetchMock = stubFetch((url) => {
            if (url === "/pin-reset/send") return answer(200, { status: "sent" });
            if (url === "/pin-reset/confirm") return answer(200, { status: "ok" });

            return answer(200, { results: [{ uuid: record.uuid, status: "accepted" }] });
        });

        await lockIt();
        await askForCode();
        await enter("123456");
        await until(() => expect(text()).toContain(PIN_TEXT.create));

        expect(await listPending("7")).toHaveLength(1);
        expect(h.syncMounts).toBe(0);
        expect(fetchMock.mock.calls.some(([url]) => url === "/sync/submissions")).toBe(false);

        await setUp("2580");

        expect(h.syncMounts).toBeGreaterThan(0);

        await runSync("7");

        expect(fetchMock.mock.calls.some(([url]) => url === "/sync/submissions")).toBe(true);
        expect(await listPending("7")).toHaveLength(0);
    });
});

describe("locking after time in the background", () => {
    // fake clocks start from the real time, so the offline and clock locks see a believable phone
    const BASE = Date.now();

    function SyncChild() {
        useOfflineSync();

        return <p>the app</p>;
    }

    const setClocks = (wall: number, mono: number) => {
        vi.spyOn(Date, "now").mockReturnValue(wall);
        vi.spyOn(performance, "now").mockReturnValue(mono);
    };

    const setVisibility = (state: "hidden" | "visible") =>
        act(async () => {
            Object.defineProperty(document, "visibilityState", { value: state, configurable: true });
            document.dispatchEvent(new Event("visibilitychange"));
        });

    async function unlockedApp(child = <SyncChild />) {
        await savePin("7", "4826");
        await act(async () => root.render(<PinGate user={{ id: 7 }}>{child}</PinGate>));
        await settle();
        await enter("4826");
        await showsApp();
        h.syncOnce.mockClear();
    }

    afterEach(() => {
        Object.defineProperty(document, "visibilityState", { value: "visible", configurable: true });
    });

    it("stays unlocked after 59 seconds in the background", async () => {
        await unlockedApp();

        setClocks(BASE, 5_000);
        await setVisibility("hidden");
        setClocks(BASE + 59_000, 64_000);
        await setVisibility("visible");

        expect(text()).toContain("the app");
        expect(flag()).toBe("7");
    });

    it("locks after 60 seconds in the background and asks for the PIN", async () => {
        await unlockedApp();

        setClocks(BASE, 5_000);
        await setVisibility("hidden");
        setClocks(BASE + 60_000, 65_000);
        await setVisibility("visible");

        await until(() => expect(text()).toContain(PIN_TEXT.enter));
        expect(text()).not.toContain("the app");
        expect(flag()).toBeNull();
    });

    it("locks when the phone clock was moved backward", async () => {
        await unlockedApp();

        setClocks(BASE, 5_000);
        await setVisibility("hidden");
        setClocks(BASE - 100_000, 15_000);
        await setVisibility("visible");

        await until(() => expect(text()).toContain(PIN_TEXT.enter));
        expect(text()).not.toContain("the app");
    });

    it("locks when the wall clock was frozen but the monotonic clock ran on", async () => {
        await unlockedApp();

        setClocks(BASE, 5_000);
        await setVisibility("hidden");
        setClocks(BASE, 66_000);
        await setVisibility("visible");

        await until(() => expect(text()).toContain(PIN_TEXT.enter));
    });

    it("still locks when the page was closed in the background and opened again later", async () => {
        await unlockedApp();

        setClocks(BASE, 5_000);
        await setVisibility("hidden");
        await act(async () => root.unmount());
        root = createRoot(container);
        setClocks(BASE + 90_000, 3_000);
        await setVisibility("visible");
        await act(async () => root.render(<PinGate user={{ id: 7 }}><SyncChild /></PinGate>));
        await settle();

        await until(() => expect(text()).toContain(PIN_TEXT.enter));
        expect(text()).not.toContain("the app");
    });

    it("runs no sync once the screen is locked, not even from the same wake-up", async () => {
        await unlockedApp();
        expect(h.syncOnce).not.toHaveBeenCalled();

        setClocks(BASE, 5_000);
        await setVisibility("hidden");
        setClocks(BASE + 120_000, 125_000);
        await setVisibility("visible");
        await until(() => expect(text()).toContain(PIN_TEXT.enter));

        await act(async () => {
            window.dispatchEvent(new Event("online"));
        });

        expect(h.syncOnce).not.toHaveBeenCalled();
    });
});
