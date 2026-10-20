// @vitest-environment jsdom
import { act, ComponentProps } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import Index from "./Index";

const fetchMock = vi.fn();
const visit = vi.fn();

vi.mock("@/Layouts/AdminLayout", () => ({ default: ({ children }: { children: unknown }) => children }));
vi.mock("@/Layouts/AuthenticatedLayout", () => ({
    default: ({ children }: { children: unknown }) => children,
    useTheme: () => ({ dark: false }),
}));
vi.mock("@inertiajs/react", async () => {
    const React = await import("react");

    return {
        Link: ({ href, children }: { href: string; children: React.ReactNode }) => React.createElement("a", { href }, children),
        router: { visit: (...args: unknown[]) => visit(...args), get: (...args: unknown[]) => visit(...args) },
    };
});

const loan = (over: Record<string, unknown> = {}) => ({
    row: "loan",
    uuid: "l-1",
    farmer: "Mensah Kofi",
    lender: "Sika Microfinance",
    amount: 100000,
    balance: 120000,
    status: "active",
    ...over,
});

const props = (over: Record<string, unknown> = {}) => ({
    rows: [loan(), loan({ uuid: "l-2", farmer: "Boateng Ama", lender: "Asante Rural Bank", amount: 50000, balance: 0, status: "repaid" })],
    pagination: { page: 1, last: 1 },
    total_owed: 120000,
    filters: { search: "", status: "all" },
    ...over,
});

let root: Root;
let container: HTMLElement;

const show = async (over: Record<string, unknown> = {}) =>
    act(async () => root.render(<Index {...(props(over) as unknown as ComponentProps<typeof Index>)} />));

const settle = async () =>
    act(async () => {
        await new Promise((resolve) => setTimeout(resolve, 320));
    });

const typeInto = async (value: string) => {
    const input = container.querySelector<HTMLInputElement>("input")!;
    const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, "value")!.set!;

    await act(async () => {
        setter.call(input, value);
        input.dispatchEvent(new Event("input", { bubbles: true }));
    });
};

const reply = (rows: unknown[], pagination = { page: 1, last: 1 }, total_owed = 0) => ({ ok: true, json: async () => ({ rows, pagination, total_owed }) });

beforeEach(() => {
    (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    fetchMock.mockReset();
    visit.mockClear();
    vi.stubGlobal("fetch", fetchMock);
    container = document.createElement("div");
    document.body.appendChild(container);
    root = createRoot(container);
});

afterEach(async () => {
    await act(async () => root.unmount());
    container.remove();
    vi.unstubAllGlobals();
});

describe("The admin Loans list", () => {
    it("shows the total owed, the approved column names and each loan with its balance and status", async () => {
        await show();

        const headers = Array.from(container.querySelectorAll("th")).map((cell) => cell.textContent);
        const text = container.textContent ?? "";

        expect(headers).toEqual(["Farmer", "Lender", "Amount", "Loan Balance", "Status"]);
        expect(text).toContain("Total owed on loans");
        expect(text).toContain("GHS 1,200.00");
        expect(text).toContain("Mensah Kofi");
        expect(text).toContain("Active");
        expect(text).toContain("Repaid");
        expect(text).not.toMatch(/\b(repayment|lender)\.[a-z_]+/);
    });

    it("shows Overdue and Default as the status", async () => {
        await show({ rows: [loan({ status: "overdue" }), loan({ uuid: "l-3", status: "default" })] });

        expect(container.textContent).toContain("Overdue");
        expect(container.textContent).toContain("Default");
    });

    it("links a loan to its admin page and nothing to any farmer page", async () => {
        await show();

        const hrefs = Array.from(container.querySelectorAll("a")).map((link) => link.getAttribute("href"));

        expect(hrefs).toEqual(["/admin/loans/l-1", "/admin/loans/l-2"]);
    });

    it("says there are no loans when the list is empty", async () => {
        await show({ rows: [], total_owed: 0 });

        expect(container.textContent).toContain("No loans yet");
    });

    it("searches in place: it fetches the list and swaps the rows with no page visit", async () => {
        fetchMock.mockResolvedValue(reply([loan({ uuid: "l-9", lender: "Found Bank" })], { page: 1, last: 1 }, 5000));
        await show();
        await typeInto("found");
        await settle();

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(String(fetchMock.mock.calls[0][0])).toBe("/admin/loans?search=found&status=all&page=1");
        expect(container.textContent).toContain("Found Bank");
        expect(container.textContent).not.toContain("Sika Microfinance");
        expect(container.textContent).toContain("GHS 50.00");
        expect(visit).not.toHaveBeenCalled();
    });

    it("shows placeholder rows while the list is being fetched", async () => {
        let release: (value: unknown) => void = () => {};
        fetchMock.mockReturnValue(new Promise((resolve) => (release = resolve)));
        await show();
        await typeInto("x");
        await settle();

        expect(container.querySelectorAll(".animate-pulse").length).toBeGreaterThan(0);

        await act(async () => release(reply([])));

        expect(container.querySelectorAll(".animate-pulse").length).toBe(0);
    });

    it("filters by status in place, and the waiting entries show as waiting with no link", async () => {
        fetchMock.mockResolvedValue(
            reply([{ row: "entry", uuid: "e-1", kind: "repayment", farmer: "Mensah Kofi", lender: "Sika Microfinance", amount: 60000, balance: null, status: "pending" }]),
        );
        await show();

        const select = container.querySelector<HTMLSelectElement>("select")!;
        await act(async () => {
            select.value = "waiting";
            select.dispatchEvent(new Event("change", { bubbles: true }));
        });
        await settle();

        expect(String(fetchMock.mock.calls[0][0])).toContain("status=waiting");
        expect(container.textContent).toContain("Waiting for the farmer to accept");
        expect(container.textContent).toContain("Sika Microfinance - Repayment");
        expect(container.querySelectorAll("a")).toHaveLength(0);
    });

    it("pages with the arrows, fetched in place", async () => {
        fetchMock.mockResolvedValue(reply([loan({ uuid: "l-30", lender: "Page Two Bank" })], { page: 2, last: 2 }));
        await show({ pagination: { page: 1, last: 2 } });

        const next = Array.from(container.querySelectorAll("button")).find((button) => button.textContent === "→")!;
        await act(async () => next.click());
        await settle();

        expect(String(fetchMock.mock.calls[0][0])).toContain("page=2");
        expect(container.textContent).toContain("Page Two Bank");
        expect(visit).not.toHaveBeenCalled();
    });
});
