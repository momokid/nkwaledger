// @vitest-environment jsdom
import { act } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import "fake-indexeddb/auto";
import { getPinRecord, savePin, verifyPin } from "@/lib/pin";
import { PIN_TEXT } from "@/lib/pinText";
import PinGate from "./PinGate";

const h = vi.hoisted(() => ({ syncMounts: 0 }));

vi.mock("@inertiajs/react", () => ({ router: { post: vi.fn() } }));

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
    await settle();
}

const settle = () => act(async () => new Promise((resolve) => setTimeout(resolve, 30)));

async function enter(pin: string) {
    await vi.waitFor(() => expect(container.querySelector("input")).not.toBeNull());
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

const showsApp = () => vi.waitFor(() => expect(text()).toContain("the app"));

async function setUp(pin: string) {
    await enter(pin);
    await enter(pin);
    await showsApp();
}

// each wrong try is counted in the store before the next one is typed
async function wrongTries(count: number) {
    for (let tried = 1; tried <= count; tried++) {
        await enter("1357");
        await vi.waitFor(async () => expect((await getPinRecord("7"))!.attempts).toBe(tried));
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

        await vi.waitFor(() => expect(text()).toContain(PIN_TEXT.wrong));
        expect(text()).not.toContain("the app");
        expect(flag()).toBeNull();
    });

    it("does not let another user's PIN unlock this user", async () => {
        await savePin("8", "2580");
        sessionStorage.setItem("nkwa_pin_unlocked", "8");

        await show({ id: 7 });

        expect(text()).toContain(PIN_TEXT.enter);

        await enter("2580");

        await vi.waitFor(() => expect(text()).toContain(PIN_TEXT.wrong));
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

        await vi.waitFor(() => expect(text()).toContain(PIN_TEXT.locked));
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

        await vi.waitFor(() => expect(text()).toContain(PIN_TEXT.locked));
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
