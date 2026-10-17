// @vitest-environment jsdom
import { act, ComponentProps } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import Index from "./Index";

const fetchMock = vi.fn();

vi.mock("@/Layouts/AdminLayout", () => ({ default: ({ children }: { children: unknown }) => children }));
vi.mock("@/Layouts/AuthenticatedLayout", () => ({
    default: ({ children }: { children: unknown }) => children,
    useTheme: () => ({ dark: false }),
}));

const figures = (over: Record<string, unknown> = {}) => ({ loans: 2, amount: 150000, interest: 25000, repaid: 115000, balance: 60000, repaid_percent: 66, ...over });

const props = (over: Record<string, unknown> = {}) => ({
    rows: [{ label: "North", ...figures() }, { label: "South", ...figures({ loans: 1, amount: 30000, interest: 3000, repaid: 0, balance: 33000, repaid_percent: 0 }) }],
    totals: figures({ loans: 3, amount: 180000, interest: 28000, repaid: 115000, balance: 93000, repaid_percent: 55 }),
    group: "region",
    from: "",
    to: "",
    ...over,
});

let root: Root;
let container: HTMLElement;

const show = async (over: Record<string, unknown> = {}) => act(async () => root.render(<Index key={JSON.stringify(over)} {...(props(over) as unknown as ComponentProps<typeof Index>)} />));

const change = async (selector: string, value: string) => {
    const element = container.querySelector<HTMLSelectElement | HTMLInputElement>(selector)!;
    const prototype = element instanceof HTMLSelectElement ? HTMLSelectElement.prototype : HTMLInputElement.prototype;

    await act(async () => {
        Object.getOwnPropertyDescriptor(prototype, "value")!.set!.call(element, value);
        element.dispatchEvent(new Event(element instanceof HTMLSelectElement ? "change" : "input", { bubbles: true }));
    });
};

const reply = (rows: unknown[], totals = figures()) => ({ ok: true, json: async () => ({ rows, totals }) });

beforeEach(() => {
    (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    fetchMock.mockReset();
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

describe("The admin's Loan Analysis", () => {
    it("shows the approved columns, each group's figures, the total row and a bar for the share repaid", async () => {
        await show();

        const headers = Array.from(container.querySelectorAll("th")).map((cell) => cell.textContent);
        const text = container.textContent ?? "";

        expect(headers).toEqual(["Region", "Loans", "Amount", "Interest", "Repaid", "Loan Balance", "Repaid %"]);
        expect(text).toContain("North");
        expect(text).toContain("GHS 1,500.00");
        expect(text).toContain("Total");
        expect(text).toContain("GHS 930.00");

        const bars = Array.from(container.querySelectorAll("[role='progressbar']")).map((bar) => bar.getAttribute("aria-valuenow"));

        expect(bars).toEqual(["66", "0", "55"]);
        expect((container.querySelector("[role='progressbar'] div") as HTMLElement).style.width).toBe("66%");
    });

    it("offers the ten groupings and the date range under their approved names", async () => {
        await show();

        const options = Array.from(container.querySelectorAll("select option")).map((option) => option.textContent);
        const labels = Array.from(container.querySelectorAll("label")).map((label) => label.textContent);

        expect(options).toEqual(["Region", "District", "Farm Type", "Lender Type", "Lender", "Farmer Group", "Agent", "Month", "Status", "Checked with the lender"]);
        expect(labels).toEqual(["Group by", "From", "To"]);
    });

    it("regroups in place: it fetches the new grouping and swaps the table", async () => {
        fetchMock.mockResolvedValue(reply([{ label: "Tamale", ...figures() }]));
        await show();
        await change("select", "district");

        expect(String(fetchMock.mock.calls[0][0])).toBe("/admin/loan-analysis?group=district");
        expect(container.textContent).toContain("Tamale");
        expect(container.textContent).not.toContain("North");
        expect(container.querySelector("th")!.textContent).toBe("District");
    });

    it("sends the dates with the grouping when they are set", async () => {
        fetchMock.mockResolvedValue(reply([]));
        await show();
        await change("#analysis-from", "2026-10-01");
        await change("#analysis-to", "2026-10-31");

        expect(String(fetchMock.mock.calls.at(-1)![0])).toBe("/admin/loan-analysis?group=region&from=2026-10-01&to=2026-10-31");
        expect(container.textContent).toContain("No loans yet");
    });

    it("shows placeholder rows while it fetches", async () => {
        let release: (value: unknown) => void = () => {};
        fetchMock.mockReturnValue(new Promise((resolve) => (release = resolve)));
        await show();
        await change("select", "lender");

        expect(container.querySelectorAll(".animate-pulse").length).toBeGreaterThan(0);

        await act(async () => release(reply([])));

        expect(container.querySelectorAll(".animate-pulse").length).toBe(0);
    });

    it("explains the farm type counting, and only for that grouping", async () => {
        const note = "A farmer with more than one farm type is counted under each of them";
        await show();

        expect(container.textContent).not.toContain(note);

        await show({ group: "farm_type" });

        expect(container.textContent).toContain(note);
    });

    it("links nowhere: it is a page to read", async () => {
        await show();

        expect(container.querySelectorAll("a, button")).toHaveLength(0);
    });
});
