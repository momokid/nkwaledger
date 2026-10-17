// @vitest-environment jsdom
import { act, ComponentProps } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import Show from "./Show";

vi.mock("@/Layouts/AuthenticatedLayout", () => ({
    default: ({ children }: { children: unknown }) => children,
    useTheme: () => ({ dark: false }),
}));
vi.mock("@inertiajs/react", async () => {
    const React = await import("react");

    return {
        Link: ({ href, children }: { href: string; children: React.ReactNode }) => React.createElement("a", { href }, children),
        router: { visit: vi.fn() },
    };
});

const base = {
    loan: { uuid: "l-1", lender: "Sika Microfinance", amount: 100000, balance: 60000, status: "active", check: { status: "confirmed", note: "Spoke to the manager", date: "2026-10-16" } },
    farmer: "Mensah Kofi",
    repayments: [{ uuid: "r1", date: "2026-10-10", amount: 60000, principal: 50000, interest: 10000, cancelled: false }],
    rejected: [],
    checks: [{ uuid: "c1", status: "confirmed", note: "Spoke to the manager", date: "2026-10-16", by: "Admin One" }],
};

let root: Root;
let container: HTMLElement;

beforeEach(async () => {
    (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    container = document.createElement("div");
    document.body.appendChild(container);
    root = createRoot(container);
    await act(async () => root.render(<Show {...(base as unknown as ComponentProps<typeof Show>)} />));
});

afterEach(async () => {
    await act(async () => root.unmount());
    container.remove();
});

describe("The agent's loan page", () => {
    it("shows the lender, the farmer, the money, the check with its note, and the repayments", () => {
        const text = container.textContent ?? "";

        expect(text).toContain("Sika Microfinance");
        expect(text).toContain("Mensah Kofi");
        expect(text).toContain("Loan Balance GHS 600.00");
        expect(text).toContain("Spoke to the manager");
        expect(text).toContain("Admin One");
        expect(Array.from(container.querySelectorAll("table")).at(-1)!.querySelectorAll("tbody tr")).toHaveLength(1);
    });

    it("has nothing to save: no form, no field, no action button", () => {
        expect(container.querySelectorAll("textarea, input, select, form")).toHaveLength(0);
        expect(Array.from(container.querySelectorAll("button")).map((button) => button.textContent)).not.toContain("Save");
    });
});
