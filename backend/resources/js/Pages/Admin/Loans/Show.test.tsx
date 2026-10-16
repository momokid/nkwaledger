// @vitest-environment jsdom
import { act, ComponentProps } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import Show from "./Show";

vi.mock("@/Layouts/AdminLayout", () => ({ default: ({ children }: { children: unknown }) => children }));
vi.mock("@/Layouts/AuthenticatedLayout", () => ({
    default: ({ children }: { children: unknown }) => children,
    useTheme: () => ({ dark: false }),
}));
vi.mock("@inertiajs/react", async () => {
    const React = await import("react");

    return {
        Link: ({ href, children }: { href: string; children: React.ReactNode }) => React.createElement("a", { href }, children),
    };
});

const props = {
    loan: { uuid: "l-1", lender: "Sika Microfinance", amount: 100000, balance: 60000, status: "active" },
    farmer: "Mensah Kofi",
    repayments: [{ uuid: "r1", date: "2026-10-10", amount: 60000, principal: 50000, interest: 10000, cancelled: false }],
    rejected: [{ uuid: "x1", date: "2026-10-09", amount: 5000 }],
};

let root: Root;
let container: HTMLElement;

beforeEach(async () => {
    (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    container = document.createElement("div");
    document.body.appendChild(container);
    root = createRoot(container);
    await act(async () => root.render(<Show {...(props as unknown as ComponentProps<typeof Show>)} />));
});

afterEach(async () => {
    await act(async () => root.unmount());
    container.remove();
});

describe("The admin's loan page", () => {
    it("shows the farmer, the lender, the balance, the status and the notice", () => {
        const text = container.textContent ?? "";

        expect(text).toContain("Sika Microfinance");
        expect(text).toContain("Mensah Kofi");
        expect(text).toContain("Loan Balance GHS 600.00");
        expect(text).toContain("Active");
        expect(text).toContain("You entered this loan yourself. It has not been checked with the lender.");
    });

    it("has the four approved repayment headers, the repayment and the rejected one, and no raw key", () => {
        const headers = Array.from(container.querySelectorAll("th")).map((th) => th.textContent);
        const text = container.textContent ?? "";

        expect(headers).toEqual(["Repayment Date", "Repayment Amount", "Principal on Repayment", "Interest on Repayment"]);
        expect(text).toContain("GHS 500.00");
        expect(text).toContain("Repayment rejected");
        expect(text).not.toMatch(/\b(repayment|lender)\.[a-z_]+/);
    });

    it("is read-only: no buttons, no form, and its only link goes back to the admin list", () => {
        expect(container.querySelectorAll("button, form, input")).toHaveLength(0);
        expect(Array.from(container.querySelectorAll("a")).map((link) => link.getAttribute("href"))).toEqual(["/admin/loans"]);
    });
});
