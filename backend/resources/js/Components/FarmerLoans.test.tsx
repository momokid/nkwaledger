// @vitest-environment jsdom
import { act } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import FarmerLoans from "./FarmerLoans";

vi.mock("@/Layouts/AuthenticatedLayout", () => ({
    useTheme: () => ({ dark: false }),
}));

let root: Root;
let container: HTMLElement;

const loan = (over: Record<string, unknown> = {}) => ({
    uuid: "9d2f0c0e-0000-4000-8000-000000000001",
    lender: "Sika Microfinance",
    amount: 50000,
    balance: 50000,
    status: "active" as const,
    ...over,
});

beforeEach(() => {
    (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
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
        await act(async () => root.render(<FarmerLoans loans={[loan(), loan({ uuid: "2", lender: "Uncle Kofi", balance: 0, status: "cancelled" })]} />));

        const headers = Array.from(container.querySelectorAll("th")).map((cell) => cell.textContent);

        expect(container.querySelector("h2")?.textContent).toBe("Loans");
        expect(headers).toEqual(["Lender", "Amount", "Loan Balance", "Status"]);
        expect(container.textContent).toContain("Active");
        expect(container.textContent).toContain("Cancelled");
        expect(container.querySelectorAll("button, a, input, form")).toHaveLength(0);
    });

    it("shows nothing when the farmer has no loans", async () => {
        await act(async () => root.render(<FarmerLoans loans={[]} />));

        expect(container.textContent).toBe("");
    });
});
