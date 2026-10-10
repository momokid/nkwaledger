// @vitest-environment jsdom
import { act, ComponentProps } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import Index from "./Index";

const visit = vi.fn();

vi.mock("@/Layouts/AuthenticatedLayout", () => ({
    default: ({ children }: { children: unknown }) => children,
    useTheme: () => ({ dark: false }),
}));

vi.mock("@inertiajs/react", () => ({
    usePage: () => ({ props: { errors: {}, flash: {} } }),
    router: { visit: (...args: unknown[]) => visit(...args) },
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

async function show(loans: unknown[]) {
    await act(async () => root.render(<Index {...({ loans } as unknown as ComponentProps<typeof Index>)} />));
}

const buttons = () => Array.from(container.querySelectorAll("button")).map((button) => button.textContent);

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
