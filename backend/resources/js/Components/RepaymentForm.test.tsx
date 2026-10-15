// @vitest-environment jsdom
import { act } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import RepaymentForm from "./RepaymentForm";

const post = vi.fn();
const transform = vi.fn();
let amount = "";

vi.mock("@/Layouts/AuthenticatedLayout", () => ({
    default: ({ children }: { children: unknown }) => children,
    useTheme: () => ({ dark: false }),
}));

vi.mock("@inertiajs/react", () => ({
    usePage: () => ({ props: { errors: {} } }),
    useForm: (initial: Record<string, unknown>) => ({
        data: { ...initial, amount },
        errors: {},
        processing: false,
        setData: () => {},
        transform: (...args: unknown[]) => transform(...args),
        post: (...args: unknown[]) => post(...args),
    }),
}));

const OVER = "The amount is more than the loan still owes";

let root: Root;
let container: HTMLElement;

async function show(split: { principal: number; interest: number } | { message: string }) {
    vi.stubGlobal("fetch", vi.fn(async () => ({ ok: !("message" in split), json: async () => split })));
    amount = "1300";

    await act(async () => {
        root.render(
            <RepaymentForm
                paidFrom={[{ value: "cash", label: "Cash" }]}
                previewUrl="/preview"
                postUrl="/post"
                today="2026-10-12"
                onCancel={() => {}}
            />,
        );
    });
    await act(async () => {
        await new Promise((resolve) => setTimeout(resolve, 300));
    });
}

const button = (label: string) => Array.from(container.querySelectorAll("button")).find((b) => b.textContent === label);
const save = () => act(async () => container.querySelector("form")!.dispatchEvent(new Event("submit", { bubbles: true, cancelable: true })));

beforeEach(() => {
    (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    post.mockClear();
    transform.mockClear();
    container = document.createElement("div");
    document.body.appendChild(container);
    root = createRoot(container);
});

afterEach(async () => {
    await act(async () => root.unmount());
    container.remove();
    vi.unstubAllGlobals();
});

describe("The repayment form", () => {
    it("shows the split from the server and lets nobody edit it", async () => {
        await show({ principal: 100000, interest: 30000 });

        expect(container.querySelector('[aria-label="split"]')?.textContent).toContain("1,000.00");
        expect(container.querySelector('[aria-label="split"] input')).toBeNull();
    });

    it("shows the approved labels and buttons, and no raw key", async () => {
        await show({ principal: 100000, interest: 30000 });

        const text = container.textContent ?? "";

        for (const words of ["Repayment Amount", "Repayment Date", "Paid From", "Principal on Repayment", "Interest on Repayment", "Save Repayment", "Cancel"]) {
            expect(text).toContain(words);
        }
        expect(text).not.toMatch(/\b(repayment|lender)\.[a-z_]+/);
    });

    it("shows the server's message under the amount when it is more than the loan owes, and asks nothing", async () => {
        await show({ message: OVER });

        expect(container.textContent).toContain(OVER);
        expect(container.querySelector('[aria-label="split"]')).toBeNull();
        expect(container.querySelector('[role="alertdialog"]')).toBeNull();
    });

    it("saves straight away, with no question", async () => {
        await show({ principal: 50000, interest: 10000 });
        await save();

        expect(container.querySelector('[role="alertdialog"]')).toBeNull();
        expect(post).toHaveBeenCalledWith("/post");
    });
});
