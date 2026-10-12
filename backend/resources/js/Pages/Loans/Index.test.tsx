// @vitest-environment jsdom
import { act, ComponentProps } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import Index from "./Index";

const visit = vi.fn();
const post = vi.fn();

vi.mock("@/Layouts/AuthenticatedLayout", () => ({
    default: ({ children }: { children: unknown }) => children,
    useTheme: () => ({ dark: false }),
}));

vi.mock("@inertiajs/react", () => ({
    usePage: () => ({ props: { errors: {}, flash: {} } }),
    Link: ({ children }: { children: unknown }) => children,
    router: { visit: (...args: unknown[]) => visit(...args), post: (...args: unknown[]) => post(...args) },
}));

const loan = (over: Record<string, unknown> = {}) => ({
    uuid: "9d2f0c0e-0000-4000-8000-000000000001",
    lender: "Sika Microfinance",
    amount: 50000,
    balance: 50000,
    status: "active",
    ...over,
});

let root: Root;
let container: HTMLElement;

const card = (over: Record<string, unknown> = {}) => ({
    uuid: "5a1c0c0e-0000-4000-8000-000000000009",
    lender: "Sika Microfinance",
    amount: 50000,
    date: "2026-10-08",
    agent: "Mensah Kofi",
    ...over,
});

async function show(loans: unknown[], pending: unknown[] = []) {
    await act(async () => root.render(<Index {...({ loans, pending } as unknown as ComponentProps<typeof Index>)} />));
}

const buttons = () => Array.from(container.querySelectorAll("button")).map((button) => button.textContent);

beforeEach(() => {
    (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    visit.mockClear();
    post.mockClear();
    container = document.createElement("div");
    document.body.appendChild(container);
    root = createRoot(container);
});

afterEach(async () => {
    await act(async () => root.unmount());
    container.remove();
});

describe("Pending entries from an agent", () => {
    const click = async (label: string) => {
        const button = Array.from(container.querySelectorAll("button")).find((item) => item.textContent === label);
        await act(async () => button!.click());
    };

    it("shows each as a card with the lender, amount, date, the agent, the notice and the two buttons", async () => {
        await show([], [card()]);

        const text = container.textContent ?? "";

        expect(text).toContain("Sika Microfinance");
        expect(text).toContain("500.00");
        expect(text).toContain("2026-10-08");
        expect(text).toContain("Recorded by Mensah Kofi");
        expect(text).toContain("Your agent recorded a loan for you. It is not counted until you accept it.");
        expect(buttons()).toContain("Accept");
        expect(buttons()).toContain("Decline");
    });

    it("sits above the list of loans", async () => {
        await show([loan({ lender: "Listed Lender" })], [card()]);

        const text = container.textContent ?? "";

        expect(text.indexOf("Recorded by")).toBeLessThan(text.indexOf("Listed Lender"));
    });

    it("shows no cards when nothing is waiting", async () => {
        await show([], []);

        expect(container.textContent).not.toContain("Recorded by");
        expect(buttons()).not.toContain("Accept");
    });

    it("accepts straight away", async () => {
        await show([], [card()]);
        await click("Accept");

        expect(post).toHaveBeenCalledWith("/loans/entries/5a1c0c0e-0000-4000-8000-000000000009/accept");
    });

    it("asks before declining, and Keep puts the question away without saving", async () => {
        await show([], [card()]);
        await click("Decline");

        expect(container.textContent).toContain("Decline this loan?");
        expect(post).not.toHaveBeenCalled();

        await click("Keep");

        expect(container.textContent).not.toContain("Decline this loan?");
        expect(post).not.toHaveBeenCalled();
    });

    it("declines only after the question is answered with Decline", async () => {
        await show([], [card()]);
        await click("Decline");

        const buttonsNow = Array.from(container.querySelectorAll("button")).filter((item) => item.textContent === "Decline");
        await act(async () => buttonsNow[buttonsNow.length - 1].click());

        expect(post).toHaveBeenCalledWith("/loans/entries/5a1c0c0e-0000-4000-8000-000000000009/decline");
    });
});

describe("The loans page", () => {
    it("has the two buttons and the notice about self-reported loans", async () => {
        await show([]);

        expect(buttons()).toContain("Add a loan");
        expect(buttons()).toContain("Add a loan I already had");
        expect(container.textContent).toContain("You entered this loan yourself. It has not been checked with the lender.");
    });

    it("says there are no loans when there are none", async () => {
        await show([]);

        expect(container.textContent).toContain("You have no loans recorded.");
        expect(container.querySelector("table")).toBeNull();
    });

    it("lists lender, amount, loan balance and status under the approved column names", async () => {
        await show([loan()]);

        const headers = Array.from(container.querySelectorAll("th")).map((cell) => cell.textContent);
        const cells = Array.from(container.querySelectorAll("tbody td")).map((cell) => cell.textContent);

        expect(headers).toEqual(["Lender", "Amount", "Loan Balance", "Status"]);
        expect(cells[0]).toBe("Sika Microfinance");
        expect(cells[1]).toContain("500.00");
        expect(cells[2]).toContain("500.00");
        expect(cells[3]).toBe("Active");
        expect(container.textContent).not.toContain("You have no loans recorded.");
    });

    it("shows Repaid for a repaid loan", async () => {
        await show([loan({ balance: 0, status: "repaid" })]);

        expect(container.querySelector("tbody")?.textContent).toContain("Repaid");
    });

    it("shows a cancelled loan with the Cancelled tag and a balance of zero", async () => {
        await show([loan({ balance: 0, status: "cancelled" })]);

        const cells = Array.from(container.querySelectorAll("tbody td")).map((cell) => cell.textContent);

        expect(cells[3]).toBe("Cancelled");
        expect(cells[2]).toContain("0.00");
    });

    it("opens the right form from each button", async () => {
        await show([]);

        const click = async (label: string) => {
            const button = Array.from(container.querySelectorAll("button")).find((item) => item.textContent === label);
            await act(async () => button!.click());
        };

        await click("Add a loan");
        await click("Add a loan I already had");

        expect(visit).toHaveBeenNthCalledWith(1, "/loans/create");
        expect(visit).toHaveBeenNthCalledWith(2, "/loans/existing/create");
    });
});
