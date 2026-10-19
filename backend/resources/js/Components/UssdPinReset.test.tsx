// @vitest-environment jsdom
import { act } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import UssdPinReset from "./UssdPinReset";

const fetchMock = vi.fn();

vi.mock("@/Layouts/AuthenticatedLayout", () => ({
    useTheme: () => ({ dark: false }),
}));

let root: Root;
let container: HTMLElement;

const button = (label: string) => Array.from(container.querySelectorAll("button")).find((item) => item.textContent === label);
const click = async (label: string) => act(async () => button(label)!.click());

beforeEach(async () => {
    (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    fetchMock.mockReset();
    vi.stubGlobal("fetch", fetchMock);
    container = document.createElement("div");
    document.body.appendChild(container);
    root = createRoot(container);
    await act(async () => root.render(<UssdPinReset url="/agent/farmers/f-1/ussd-pin" />));
});

afterEach(async () => {
    await act(async () => root.unmount());
    container.remove();
});

describe("The agent's USSD PIN reset", () => {
    it("asks the agent to be sure before anything is sent", async () => {
        await click("Set a new USSD PIN");

        expect(container.textContent).toContain("Have you checked that you are speaking to the farmer?");
        expect(fetchMock).not.toHaveBeenCalled();

        await click("Cancel");
        expect(button("Set a new USSD PIN")).toBeTruthy();
    });

    it("posts for this farmer and shows the temporary PIN in place", async () => {
        fetchMock.mockResolvedValue({ ok: true, json: async () => ({ pin: "7352" }) });

        await click("Set a new USSD PIN");
        await click("Yes, set it");

        expect(fetchMock.mock.calls[0][0]).toBe("/agent/farmers/f-1/ussd-pin");
        expect(fetchMock.mock.calls[0][1].method).toBe("POST");
        expect(container.textContent).toContain("Temporary PIN: 7352");
    });

    it("names the person it is for", async () => {
        await act(async () => root.render(<UssdPinReset url="/admin/staff/a-1/ussd-pin" person="agent" />));
        await click("Set a new USSD PIN");

        expect(container.textContent).toContain("speaking to the agent?");
    });

    it("says so when it could not be set, and shows no PIN", async () => {
        fetchMock.mockResolvedValue({ ok: false, json: async () => ({}) });

        await click("Set a new USSD PIN");
        await click("Yes, set it");

        expect(container.textContent).toContain("The PIN could not be set. Please try again.");
        expect(container.textContent).not.toContain("Temporary PIN");
    });
});
