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
        usePage: () => ({ props: { errors: {}, flash: {} } }),
        useForm: (initial: Record<string, unknown>) => ({
            data: initial,
            errors: {},
            processing: false,
            setData: () => {},
            transform: () => {},
            post: () => {},
        }),
    };
});

const props = {
    loan: { uuid: "9d2f0c0e-0000-4000-8000-000000000001", lender: "Sika Microfinance", amount: 100000, balance: 50000, status: "active" },
    rejected: [{ uuid: "x1", date: "2026-10-09", amount: 5000 }],
    repayments: [
        { uuid: "r1", date: "2026-10-10", amount: 60000, principal: 50000, interest: 10000, cancelled: false },
        { uuid: "r2", date: "2026-10-11", amount: 1000, principal: 800, interest: 200, cancelled: false },
    ],
    paidFrom: [{ value: "cash", label: "Cash" }],
    idempotencyKey: "key-1",
    previewUrl: "/p",
    postUrl: "/s",
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

describe("The loan page", () => {
    it("shows the check with the lender and its note in place of the self-reported notice", async () => {
        const checked = { ...props, loan: { ...props.loan, check: { status: "does_not_match", note: "Branch says 800", date: "2026-10-16" } } };
        await act(async () => root.render(<Show {...(checked as unknown as ComponentProps<typeof Show>)} />));

        const text = container.textContent ?? "";

        expect(text).toContain("The lender says this does not match");
        expect(text).toContain("Branch says 800");
        expect(text).not.toContain("It has not been checked with the lender");
    });

    const recordButton = () => Array.from(container.querySelectorAll("button")).find((b) => b.textContent === "Record a repayment");

    it("offers Record a repayment on an active loan only: not on a repaid or a cancelled one", async () => {
        const render = async (status: string) => act(async () => root.render(<Show {...({ ...props, loan: { ...props.loan, status } } as unknown as ComponentProps<typeof Show>)} />));

        await render("active");
        expect(recordButton()).toBeDefined();

        await render("repaid");
        expect(recordButton()).toBeUndefined();

        await render("cancelled");
        expect(recordButton()).toBeUndefined();
    });

    it("has a Back link to the loans list", async () => {
        await act(async () => root.render(<Show {...(props as unknown as ComponentProps<typeof Show>)} />));

        const back = Array.from(container.querySelectorAll("a")).find((link) => link.textContent === "← Back");

        expect(back?.getAttribute("href")).toBe("/loans");
    });

    it("shows the four approved table headers and no raw key", async () => {
        await act(async () => root.render(<Show {...(props as unknown as ComponentProps<typeof Show>)} />));

        expect(Array.from(container.querySelectorAll("th")).map((th) => th.textContent)).toEqual([
            "Repayment Date",
            "Repayment Amount",
            "Principal on Repayment",
            "Interest on Repayment",
        ]);
        expect(container.textContent).not.toMatch(/\b(repayment|lender)\.[a-z_]+/);
    });


    it("shows the notice once, whatever the number of repayments", async () => {
        await act(async () => root.render(<Show {...(props as unknown as ComponentProps<typeof Show>)} />));

        expect(container.querySelectorAll("[role='note']").length).toBe(1);
        expect(container.querySelector("[role='note']")?.textContent).toBe(
            "You entered this loan yourself. It has not been checked with the lender.",
        );
    });

    it("has the Repayments section and the Record a repayment button", async () => {
        await act(async () => root.render(<Show {...(props as unknown as ComponentProps<typeof Show>)} />));

        expect(container.textContent).toContain("Repayments");
        expect(Array.from(container.querySelectorAll("button")).map((b) => b.textContent)).toContain("Record a repayment");
    });

    it("shows a repayment the agent entered that was rejected, with the approved words", async () => {
        await act(async () => root.render(<Show {...(props as unknown as ComponentProps<typeof Show>)} />));

        expect(container.textContent).toContain("Repayment rejected");
        expect(container.textContent).toContain("50.00");
    });
});
