// @vitest-environment jsdom
import { act, ComponentProps } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import Index from "./Index";

const fetchMock = vi.fn();

vi.mock("@/Layouts/AuthenticatedLayout", () => ({
    default: ({ children }: { children: unknown }) => children,
    useTheme: () => ({ dark: false }),
}));
vi.mock("@inertiajs/react", async () => {
    const React = await import("react");

    return { Link: ({ href, children }: { href: string; children: React.ReactNode }) => React.createElement("a", { href }, children) };
});

const props = {
    rows: [{ row: "loan", uuid: "l-1", farmer: "Mensah Kofi", lender: "Sika Microfinance", amount: 100000, balance: 120000, status: "active" }],
    pagination: { page: 1, last: 1 },
    total_owed: 120000,
    filters: { search: "", status: "all" },
};

let root: Root;
let container: HTMLElement;

beforeEach(async () => {
    (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    fetchMock.mockReset();
    vi.stubGlobal("fetch", fetchMock);
    container = document.createElement("div");
    document.body.appendChild(container);
    root = createRoot(container);
    await act(async () => root.render(<Index {...(props as unknown as ComponentProps<typeof Index>)} />));
});

afterEach(async () => {
    await act(async () => root.unmount());
    container.remove();
    vi.unstubAllGlobals();
});

describe("The agent's Loans list", () => {
    it("shows the loans and the total owed, and opens a loan under the agent's own address", () => {
        expect(container.textContent).toContain("Mensah Kofi");
        expect(container.textContent).toContain("GHS 1,200.00");
        expect(container.querySelector("a")!.getAttribute("href")).toBe("/agent/loans/l-1");
    });

    it("fetches a new filter in place from the agent's own address", async () => {
        fetchMock.mockResolvedValue({ ok: true, json: async () => ({ rows: [], pagination: { page: 1, last: 1 }, total_owed: 0 }) });

        const select = container.querySelector("select")!;
        await act(async () => {
            Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, "value")!.set!.call(select, "repaid");
            select.dispatchEvent(new Event("change", { bubbles: true }));
        });
        await act(async () => {
            await new Promise((resolve) => setTimeout(resolve, 300));
        });

        expect(String(fetchMock.mock.calls[0][0])).toBe("/agent/loans?search=&status=repaid&page=1");
        expect(container.textContent).toContain("No loans yet");
    });

    it("links nowhere but to the agent's own loans", () => {
        const links = Array.from(container.querySelectorAll("a")).map((link) => link.getAttribute("href"));

        expect(links.every((href) => href!.startsWith("/agent/loans/"))).toBe(true);
    });
});
