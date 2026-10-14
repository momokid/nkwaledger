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

const PROMPT = "This is more than you still owe on this loan. Is the extra amount interest or fees?";

let root: Root;
let container: HTMLElement;

async function show(agent: boolean, split: { principal: number; interest: number; excess: number }) {
    vi.stubGlobal("fetch", vi.fn(async () => ({ ok: true, json: async () => split })));
    amount = "1300";

    await act(async () => {
        root.render(
            <RepaymentForm
                paidFrom={[{ value: "cash", label: "Cash" }]}
                previewUrl="/preview"
                postUrl="/post"
                today="2026-10-12"
                agent={agent}
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
        await show(false, { principal: 100000, interest: 30000, excess: 10000 });

        expect(container.querySelector('[aria-label="split"]')?.textContent).toContain("1,000.00");
        expect(container.querySelector('[aria-label="split"] input')).toBeNull();
    });

    it("shows the approved labels and buttons, and no raw key", async () => {
        await show(false, { principal: 100000, interest: 30000, excess: 0 });

        const text = container.textContent ?? "";

        for (const words of ["Repayment Amount", "Repayment Date", "Paid From", "Principal on Repayment", "Interest on Repayment", "Save Repayment", "Cancel"]) {
            expect(text).toContain(words);
        }
        expect(text).not.toMatch(/\b(repayment|lender)\.[a-z_]+/);
    });

    it("asks the exact question before saving more than is owed, and Yes saves with the confirmation", async () => {
        await show(false, { principal: 100000, interest: 30000, excess: 10000 });
        await save();

        expect(container.textContent).toContain(PROMPT);
        expect(post).not.toHaveBeenCalled();

        await act(async () => button("Yes")!.click());

        const send = transform.mock.calls[0][0] as (data: Record<string, unknown>) => Record<string, unknown>;
        expect(send({ amount: "1300" }).confirm_excess).toBe(true);
        expect(post).toHaveBeenCalledWith("/post");
    });

    it("No, change the amount goes back to the form without saving", async () => {
        await show(false, { principal: 100000, interest: 30000, excess: 10000 });
        await save();
        await act(async () => button("No, change the amount")!.click());

        expect(container.textContent).not.toContain(PROMPT);
        expect(post).not.toHaveBeenCalled();
    });

    it("saves straight away when nothing is above what is owed", async () => {
        await show(false, { principal: 50000, interest: 10000, excess: 0 });
        await save();

        expect(container.textContent).not.toContain(PROMPT);
        expect(post).toHaveBeenCalledWith("/post");
    });

    it("never asks an agent: only the farmer can confirm an extra amount", async () => {
        await show(true, { principal: 100000, interest: 30000, excess: 10000 });
        await save();

        expect(container.textContent).not.toContain(PROMPT);
        expect(post).toHaveBeenCalledWith("/post");
    });
});
