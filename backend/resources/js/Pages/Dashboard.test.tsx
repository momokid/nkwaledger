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
    loan_alerts: [],
    credit_score: { text: "Not yet available", lines: [], note: null },
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

describe("The credit score card", () => {
    it("shows the score, the breakdown and the note, and no made-up number", async () => {
        const credit_score = {
            text: "72 out of 100 (Good)",
            lines: ["Farm purchases: 50 out of 50. Recorded in 12 of the last 12 months.", "Credit and loans: not counted yet."],
            note: "This score is a guide only. It does not decide a loan.",
        };

        await act(async () => root.render(<Dashboard {...({ ...props, credit_score } as unknown as ComponentProps<typeof Dashboard>)} />));

        const text = container.textContent ?? "";

        expect(text).toContain("72 out of 100 (Good)");
        expect(text).toContain("Credit and loans: not counted yet.");
        expect(text).toContain("This score is a guide only. It does not decide a loan.");
        expect(text).not.toContain("720");
        expect(text).not.toContain("(Soon)");
    });

    it("says Not yet available when there is no score", async () => {
        await act(async () => root.render(<Dashboard {...(props as unknown as ComponentProps<typeof Dashboard>)} />));

        expect(container.textContent).toContain("Not yet available");
    });
});

describe("The loan use banner", () => {
    it("shows one banner for each loan with money not yet accounted for, with the approved words", async () => {
        const loan_alerts = [{ uuid: "l-1", unaccounted: 25000 }, { uuid: "l-2", unaccounted: 5000 }];

        await act(async () => root.render(<Dashboard {...({ ...props, loan_alerts } as unknown as ComponentProps<typeof Dashboard>)} />));

        const text = container.textContent ?? "";

        expect(text).toContain("You have not yet recorded how GHS 250.00 of your loan was used.");
        expect(text).toContain("You have not yet recorded how GHS 50.00 of your loan was used.");
        expect(container.textContent?.split("Record how I used it").length).toBe(3);
    });

    it("shows none when nothing is left to account for", async () => {
        await act(async () => root.render(<Dashboard {...(props as unknown as ComponentProps<typeof Dashboard>)} />));

        expect(container.textContent).not.toContain("You have not yet recorded how");
    });
});

