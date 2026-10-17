// @vitest-environment jsdom
import { act } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import FarmerLoans from "./FarmerLoans";

let mockErrors: Record<string, string> = {};
const post = vi.fn();
const visit = vi.fn();

vi.mock("@/Layouts/AuthenticatedLayout", () => ({
    useTheme: () => ({ dark: false }),
}));

vi.mock("@inertiajs/react", () => ({
    usePage: () => ({ props: { errors: mockErrors } }),
    router: { visit: (...args: unknown[]) => visit(...args), post: (...args: unknown[]) => post(...args) },
}));

let root: Root;
let container: HTMLElement;

const row = (over: Record<string, unknown> = {}) => ({
    row: "loan" as const,
    uuid: "9d2f0c0e-0000-4000-8000-000000000001",
    lender: "Sika Microfinance",
    amount: 50000,
    balance: 50000,
    status: "active" as const,
    ...over,
});

const entry = (over: Record<string, unknown> = {}) => row({ row: "entry", balance: null, status: "pending", uuid: "e-1", ...over });

const buttons = () => Array.from(container.querySelectorAll("button")).map((button) => button.textContent);
const click = async (label: string) => {
    const button = Array.from(container.querySelectorAll("button")).find((item) => item.textContent === label);
    await act(async () => button!.click());
};

async function show(loans: unknown[], farmerUuid?: string) {
    await act(async () => root.render(<FarmerLoans loans={loans as never} farmerUuid={farmerUuid} />));
}

beforeEach(() => {
    (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    mockErrors = {};
    post.mockClear();
    visit.mockClear();
    container = document.createElement("div");
    document.body.appendChild(container);
    root = createRoot(container);
});

afterEach(async () => {
    await act(async () => root.unmount());
    container.remove();
});

describe("The admin's read-only loans section", () => {
    it("lists the loans under the approved names, and has no button at all", async () => {
        await show([row(), row({ uuid: "2", lender: "Uncle Kofi", balance: 0, status: "cancelled" })]);

        const headers = Array.from(container.querySelectorAll("th")).map((cell) => cell.textContent);

        expect(container.querySelector("h2")?.textContent).toBe("Loans");
        expect(headers).toEqual(["Lender", "Amount", "Loan Balance", "Status"]);
        expect(container.textContent).toContain("Active");
        expect(container.textContent).toContain("Cancelled");
        expect(container.querySelectorAll("button, a, input, form")).toHaveLength(0);
    });

    it("shows the entry words and no buttons", async () => {
        await show([entry(), entry({ uuid: "e-2", status: "declined" }), entry({ uuid: "e-3", status: "withdrawn" })]);

        const text = container.textContent ?? "";

        expect(text).toContain("Waiting for the farmer to accept");
        expect(text).toContain("Declined by the farmer");
        expect(text).toContain("Withdrawn");
        expect(container.querySelectorAll("button, input")).toHaveLength(0);
    });

    it("marks a repayment entry and a rejected repayment in the approved words, with no raw key", async () => {
        await show([entry({ kind: "repayment" }), entry({ uuid: "e-4", kind: "repayment", status: "rejected" })]);

        const text = container.textContent ?? "";

        expect(text).toContain("Sika Microfinance - Repayment");
        expect(text).toContain("Repayment rejected");
        expect(text).not.toMatch(/\b(repayment|lender)\.[a-z_]+/);
    });

    it("shows a loan's check with the lender and its note, and nothing about it for a loan nobody has checked", async () => {
        await show([row({ check: { status: "unreachable", note: "Phone off all day", date: "2026-10-16" } }), row({ uuid: "2", lender: "Uncle Kofi" })]);

        const text = container.textContent ?? "";

        expect(text).toContain("Lender could not be reached");
        expect(text).toContain("Phone off all day");
        expect(text).not.toContain("not been checked with the lender");
    });

    it("shows nothing when the farmer has no loans", async () => {
        await show([]);

        expect(container.textContent).toBe("");
    });
});

describe("The agent's loans section", () => {
    const FARMER = "f-uuid-1";

    it("has the two buttons, and says so when there are no loans", async () => {
        await show([], FARMER);

        expect(buttons()).toContain("Add a loan");
        expect(buttons()).toContain("Add an existing loan for the farmer");
        expect(container.textContent).toContain("No loans recorded for this farmer.");
    });

    it("opens the agent forms", async () => {
        await show([], FARMER);
        await click("Add a loan");
        await click("Add an existing loan for the farmer");

        expect(visit).toHaveBeenNthCalledWith(1, "/agent/farmers/f-uuid-1/loans/create");
        expect(visit).toHaveBeenNthCalledWith(2, "/agent/farmers/f-uuid-1/loans/existing/create");
    });

    it("gives a waiting entry Send code, Withdraw, and the code box with Confirm", async () => {
        await show([entry()], FARMER);

        expect(container.textContent).toContain("Waiting for the farmer to accept");
        expect(buttons()).toContain("Send code to the farmer");
        expect(buttons()).toContain("Withdraw");
        expect(buttons()).toContain("Confirm");
        expect(container.textContent).toContain("Enter the 6-digit code the farmer received.");
    });

    it("sends the code, and sends the typed code with Confirm", async () => {
        await show([entry()], FARMER);

        await click("Send code to the farmer");
        expect(post).toHaveBeenCalledWith("/agent/loan-entries/e-1/send-code");

        const input = container.querySelector("input[inputmode='numeric']") as HTMLInputElement;
        const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, "value")!.set!;
        await act(async () => {
            setter.call(input, "123456");
            input.dispatchEvent(new Event("input", { bubbles: true }));
        });
        await click("Confirm");

        expect(post).toHaveBeenCalledWith("/agent/loan-entries/e-1/code", { code: "123456" });
    });

    it("shows a code error beside the code box", async () => {
        mockErrors = { code: "The code is not correct." };
        await show([entry()], FARMER);

        expect(container.textContent).toContain("The code is not correct.");
    });

    it("asks before withdrawing, and Keep saves nothing", async () => {
        await show([entry()], FARMER);
        await click("Withdraw");

        expect(container.textContent).toContain("Withdraw this loan? The farmer will no longer be able to accept it.");
        expect(post).not.toHaveBeenCalled();

        await click("Keep");
        expect(container.textContent).not.toContain("Withdraw this loan?");

        await click("Withdraw");
        const answers = Array.from(container.querySelectorAll("button")).filter((item) => item.textContent === "Withdraw");
        await act(async () => answers[answers.length - 1].click());

        expect(post).toHaveBeenCalledWith("/agent/loan-entries/e-1/withdraw");
    });

    it("gives a declined, withdrawn or accepted row no actions", async () => {
        await show([entry({ status: "declined" }), entry({ uuid: "e-2", status: "withdrawn" }), row()], FARMER);

        expect(buttons()).not.toContain("Send code to the farmer");
        expect(buttons()).not.toContain("Withdraw");
        expect(buttons()).not.toContain("Confirm");
    });
});
