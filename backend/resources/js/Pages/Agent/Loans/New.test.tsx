// @vitest-environment jsdom
import { act } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import New from "./New";

const fetchMock = vi.fn();
const visit = vi.fn();

vi.mock("@/Layouts/AuthenticatedLayout", () => ({
    default: ({ children }: { children: unknown }) => children,
    useTheme: () => ({ dark: false }),
}));
vi.mock("@inertiajs/react", () => ({ router: { visit: (...args: unknown[]) => visit(...args) }, usePage: () => ({ props: {} }) }));

const farmers = [{ id: "f-1", name: "Mensah Kofi", community: "Kuapa" }];

let root: Root;
let container: HTMLElement;

const type = async (value: string) => {
    const input = container.querySelector("input") as HTMLInputElement;
    await act(async () => {
        Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, "value")!.set!.call(input, value);
        input.dispatchEvent(new Event("input", { bubbles: true }));
    });
    await act(async () => {
        await vi.advanceTimersByTimeAsync(300);
    });
};

beforeEach(async () => {
    (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    vi.useFakeTimers();
    fetchMock.mockReset();
    visit.mockReset();
    vi.stubGlobal("fetch", fetchMock);
    container = document.createElement("div");
    document.body.appendChild(container);
    root = createRoot(container);
    await act(async () => root.render(<New />));
});

afterEach(async () => {
    await act(async () => root.unmount());
    container.remove();
    vi.useRealTimers();
});

describe("agent New Loan page", () => {
    it("searches only after two letters, through a POST", async () => {
        fetchMock.mockResolvedValue({ ok: true, json: async () => farmers });

        await type("m");
        expect(fetchMock).not.toHaveBeenCalled();

        await type("me");
        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(fetchMock.mock.calls[0][0]).toBe("/agent/loans/farmer-search");
        expect(fetchMock.mock.calls[0][1].method).toBe("POST");
        expect(container.textContent).toContain("Mensah Kofi");
    });

    it("says so when no farmer is found", async () => {
        fetchMock.mockResolvedValue({ ok: true, json: async () => [] });

        await type("zz");

        expect(container.textContent).toContain("No farmer found");
    });

    it("opens the two usual loan forms for the picked farmer", async () => {
        fetchMock.mockResolvedValue({ ok: true, json: async () => farmers });
        await type("me");

        await act(async () => (container.querySelector('[role="option"]') as HTMLElement).click());

        const buttons = [...container.querySelectorAll("button")].filter((b) => b.textContent?.startsWith("Add"));
        expect(buttons.map((b) => b.textContent)).toEqual(["Add a loan", "Add an existing loan for the farmer"]);

        await act(async () => buttons[0].click());
        expect(visit).toHaveBeenCalledWith("/agent/farmers/f-1/loans/create");

        await act(async () => buttons[1].click());
        expect(visit).toHaveBeenCalledWith("/agent/farmers/f-1/loans/existing/create");
    });
});
