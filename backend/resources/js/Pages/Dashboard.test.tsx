// @vitest-environment jsdom
import { act, ComponentProps } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import Dashboard from "./Dashboard";

const visit = vi.fn();

vi.mock("@/Layouts/AuthenticatedLayout", () => ({
    default: ({ children }: { children: unknown }) => children,
    useTheme: () => ({ dark: false }),
}));
vi.mock("@/hooks/useAuthGuard", () => ({ default: () => {} }));
vi.mock("@/Components/GreetingHeader", () => ({ default: () => null }));
vi.mock("@/Components/UnsentRecordsCount", () => ({ default: () => null }));

vi.mock("@inertiajs/react", async () => {
    const React = await import("react");

    return {
        Head: () => null,
        Link: ({ href, children }: { href: string; children: React.ReactNode }) => React.createElement("a", { href }, children),
        router: { visit: (...args: unknown[]) => visit(...args) },
        usePage: () => ({ props: { auth: { user: {} }, errors: {}, flash: {} } }),
    };
});

const trend = { direction: "flat", percent: null, good: true };

const props = {
    summary: {
        total_income: 100000,
        total_expense: 40000,
        net: 60000,
        cash_collected: 100000,
        cash_paid_out: 40000,
        total_owed: 115000,
        trends: { income: trend, expense: trend, net: trend },
    },
    breakdown: { income_rows: [], expense_rows: [], loss_rows: [] },
    recent_transactions: [],
    farm_produce: { items: [], more_count: 0 },
    weather: [],
    filters: { from: "2026-10-01", to: "2026-10-31" },
};

let root: Root;
let container: HTMLElement;

beforeEach(() => {
    (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    visit.mockClear();
    container = document.createElement("div");
    document.body.appendChild(container);
    root = createRoot(container);
});

afterEach(async () => {
    await act(async () => root.unmount());
    container.remove();
});

describe("The Total owed on loans card", () => {
    const card = () => Array.from(container.querySelectorAll("p")).find((p) => p.textContent === "Total owed on loans")!.parentElement!;

    it("shows the principal and interest still owed", async () => {
        await act(async () => root.render(<Dashboard {...(props as unknown as ComponentProps<typeof Dashboard>)} />));

        expect(card().textContent).toContain("GHS 1,150.00");
    });

    it("takes the farmer to the Loans page when clicked", async () => {
        await act(async () => root.render(<Dashboard {...(props as unknown as ComponentProps<typeof Dashboard>)} />));

        await act(async () => card().click());

        expect(visit).toHaveBeenCalledWith("/loans");
    });
});
