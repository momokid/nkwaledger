// @vitest-environment jsdom
import { act, ComponentProps } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import Show from "./Show";

vi.mock("@/Layouts/AuthenticatedLayout", () => ({
    default: ({ children }: { children: unknown }) => children,
    useTheme: () => ({ dark: false }),
}));

vi.mock("@inertiajs/react", () => ({
    usePage: () => ({ props: { errors: {}, flash: {} } }),
    useForm: (initial: Record<string, unknown>) => ({
        data: initial,
        errors: {},
        processing: false,
        setData: () => {},
        transform: () => {},
        post: () => {},
    }),
}));

const props = {
    loan: { uuid: "9d2f0c0e-0000-4000-8000-000000000001", lender: "Sika Microfinance", amount: 100000, balance: 50000, status: "active" },
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
});
