// @vitest-environment jsdom
import { act, ComponentProps } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import Statement from "./Statement";

vi.mock("@/Layouts/AuthenticatedLayout", () => ({
    default: ({ children }: { children: unknown }) => children,
    useTheme: () => ({ dark: false }),
}));
vi.mock("@/Components/BackLink", () => ({ default: ({ fallbackHref }: { fallbackHref: string }) => <a href={fallbackHref}>Back</a> }));

const statement = {
    loan: { uuid: "l-1", lender: "Sika Microfinance", amount: 100000, granted_on: "2026-10-01", paid_into: "Cash", interest: 20000, due_date: "2026-11-01", status: "active", balance: 100000 },
    repayments: [{ uuid: "r1", date: "2026-10-10", amount: 20000, principal: 16000, interest: 4000, balance: 100000, cancelled: false }],
    farms: [{ uuid: "f1", name: "Alpha", purpose: "I bought seed", planned: 60000, accounted: 42000, not_yet_accounted: 18000, over: 0 }],
    purchases: [{ date: "2026-10-12", what: "I bought seed", farm: "Alpha", amount: 12000 }],
    kinds: [{ name: "I bought seed", amount: 12000 }],
    other_uses: [{ uuid: "o1", note: "School fees", amount: 5000 }],
    totals: { on_farms: 12000, other: 5000, not_yet: 83000 },
    prepared_on: "2026-10-25",
    notice: "You entered this loan yourself. It has not been checked with the lender.",
};

let root: Root;
let container: HTMLElement;

const render = async (over: Record<string, unknown> = {}) => {
    await act(async () => root.unmount());
    root = createRoot(container);
    await act(async () =>
        root.render(<Statement {...({ statement, backUrl: "/loans/l-1", printUrl: "/loans/l-1/statement/print", pdfUrl: "/loans/l-1/statement/pdf", ...over } as unknown as ComponentProps<typeof Statement>)} />),
    );
};

beforeEach(async () => {
    container = document.createElement("div");
    document.body.appendChild(container);
    root = createRoot(container);
    await render();
});

afterEach(async () => {
    await act(async () => root.unmount());
    container.remove();
});

describe("The loan statement page", () => {
    it("shows the loan, the repayments and what the money was used for, in the approved words", () => {
        const text = container.textContent ?? "";

        for (const expected of [
            "Loan statement", "The loan", "Repayments", "What the money was used for",
            "Amount received: GHS 1,000.00", "Date received: 01/10/2026", "Total interest agreed: GHS 200.00", "Due date: 01/11/2026", "Loan balance: GHS 1,000.00",
            "Repayment Date", "10/10/2026",
            "Alpha: I bought seed", "Alpha: planned GHS 600.00, accounted GHS 420.00, not yet accounted GHS 180.00",
            "What it was", "I bought seed – Alpha", "School fees: GHS 50.00",
            "Used on the farms: GHS 120.00", "Used for something else: GHS 50.00", "Not yet accounted for: GHS 830.00",
            "Prepared on 25/10/2026", "You entered this loan yourself. It has not been checked with the lender.",
        ]) {
            expect(text).toContain(expected);
        }
    });

    it("offers Print and Download PDF, and a way back", () => {
        const links = Array.from(container.querySelectorAll("a")).map((link) => [link.textContent?.trim(), link.getAttribute("href")]);

        expect(links).toContainEqual(["Print", "/loans/l-1/statement/print"]);
        expect(links).toContainEqual(["Download PDF", "/loans/l-1/statement/pdf"]);
        expect(links).toContainEqual(["Back", "/loans/l-1"]);
    });

    it("leaves out the use of the money for a loan that has none", async () => {
        await render({ statement: { ...statement, farms: [], purchases: [], kinds: [], other_uses: [] } });

        expect(container.textContent).not.toContain("What the money was used for");
        expect(container.textContent).toContain("Loan statement");
    });
});
