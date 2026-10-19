// @vitest-environment jsdom
import { act, ComponentProps } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import Show from "./Show";

vi.mock("@/Layouts/AuthenticatedLayout", () => ({
    default: ({ children }: { children: unknown }) => children,
    useTheme: () => ({ dark: false }),
}));
vi.mock("@inertiajs/react", async () => {
    const React = await import("react");

    return { Link: ({ href, children }: { href: string; children: React.ReactNode }) => React.createElement("a", { href }, children), router: { visit: vi.fn() } };
});

const base = {
    loan: { uuid: "l-1", lender: "Sika Microfinance", amount: 100000, balance: 60000, status: "active", check: { status: "confirmed", note: "Spoke to the manager", date: "2026-10-16" } },
    farmer: "Mensah Kofi",
    repayments: [{ uuid: "r1", date: "2026-10-10", amount: 60000, principal: 50000, interest: 10000, balance: 60000, cancelled: false }],
    rejected: [{ uuid: "x1", date: "2026-10-09", amount: 5000 }],
    checks: [{ uuid: "c1", status: "confirmed", note: "Spoke to the manager", date: "2026-10-16", by: "Admin One" }],
};

const scrolled = vi.fn();
let root: Root;
let container: HTMLElement;

const open = async (search: string) => {
    window.history.replaceState(null, "", `/agent/loans/l-1${search}`);
    await act(async () => root.render(<Show {...(base as unknown as ComponentProps<typeof Show>)} />));
};

const flashing = () => Array.from(container.querySelectorAll(".flash-focus")).map((element) => element.getAttribute("data-focus"));

beforeEach(() => {
    (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    scrolled.mockReset();
    Element.prototype.scrollIntoView = function (this: Element) {
        scrolled(this.getAttribute("data-focus"));
    };
    container = document.createElement("div");
    document.body.appendChild(container);
    root = createRoot(container);
});

afterEach(async () => {
    await act(async () => root.unmount());
    container.remove();
    window.history.replaceState(null, "", "/");
});

describe("The agent's loan page, opened from a notification", () => {
    it.each([
        ["a check", "c1"],
        ["a repayment", "r1"],
        ["a rejected repayment", "x1"],
        ["the loan itself", "l-1"],
    ])("scrolls to %s and flashes only that", async (_name, id) => {
        await open(`?focus=${id}`);

        expect(flashing()).toEqual([id]);
        expect(scrolled).toHaveBeenCalledTimes(1);
        expect(scrolled).toHaveBeenCalledWith(id);
    });

    it("flashes nothing when it is opened on its own", async () => {
        await open("");

        expect(flashing()).toEqual([]);
        expect(scrolled).not.toHaveBeenCalled();
    });

    it("ignores a focus that is not on the page", async () => {
        await open("?focus=nothing-here");

        expect(flashing()).toEqual([]);
        expect(container.textContent).toContain("Sika Microfinance");
    });

    it("lets the flash go after a moment, so the page is left as it was", async () => {
        vi.useFakeTimers();
        await open("?focus=r1");
        await act(async () => {
            vi.advanceTimersByTime(3500);
        });
        vi.useRealTimers();

        expect(flashing()).toEqual([]);
    });
});
