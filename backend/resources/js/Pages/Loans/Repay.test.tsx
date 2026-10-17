// @vitest-environment jsdom
import { act, ComponentProps } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import Repay from "./Repay";

vi.mock("@/Layouts/AuthenticatedLayout", () => ({
    default: ({ children }: { children: unknown }) => children,
    useTheme: () => ({ dark: false }),
}));

vi.mock("@/Components/RepaymentForm", () => ({ default: () => null }));

vi.mock("@inertiajs/react", async () => {
    const React = await import("react");

    return {
        Link: ({ href, children }: { href: string; children: React.ReactNode }) => React.createElement("a", { href }, children),
        router: { visit: () => {} },
    };
});

const props = {
    loan: { uuid: "9d2f0c0e-0000-4000-8000-000000000001", lender: "Sika Microfinance", amount: 100000, balance: 50000 },
    paidFrom: [{ value: "cash", label: "Cash" }],
    previewUrl: "/p",
    postUrl: "/s",
    backUrl: "/agent/farmers/f-1",
    today: "2026-10-12",
};

let root: Root;
let container: HTMLElement;

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

describe("The agent's repayment page", () => {
    it("has a Back link to the farmer's page", async () => {
        await act(async () => root.render(<Repay {...(props as unknown as ComponentProps<typeof Repay>)} />));

        const back = Array.from(container.querySelectorAll("a")).find((link) => link.textContent === "← Back");

        expect(back?.getAttribute("href")).toBe("/agent/farmers/f-1");
    });
});
