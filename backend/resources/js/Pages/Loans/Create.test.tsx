// @vitest-environment jsdom
import { act, ComponentProps } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import Create from "./Create";

let mockErrors: Record<string, string> = {};
let mockDark = false;
let mockOld: Record<string, string> = { lender: "u-1", lender_name: "Sika Microfinance" };
const post = vi.fn();
const visit = vi.fn();

vi.mock("@/Layouts/AuthenticatedLayout", () => ({
    default: ({ children }: { children: unknown }) => children,
    useTheme: () => ({ dark: mockDark }),
}));

vi.mock("@inertiajs/react", async () => {
    const React = await import("react");

    return {
        Link: ({ href, children }: { href: string; children: React.ReactNode }) => React.createElement("a", { href }, children),
        usePage: () => ({ props: { errors: mockErrors, flash: {}, old: mockOld } }),
        router: { visit: (...args: unknown[]) => visit(...args) },
        useForm: (initial: Record<string, string>) => ({
            data: initial,
            errors: {},
            processing: false,
            setData: () => {},
            reset: () => {},
            post: (...args: unknown[]) => post(...args),
        }),
    };
});

const props = (kind: "money" | "existing", over: Record<string, unknown> = {}) => ({
    kind,
    agent: false,
    postUrl: "/loans",
    backUrl: "/loans",
    paidInto: [
        { id: 1, label: "Cash" },
        { id: 2, label: "MoMo" },
        { id: 3, label: "Bank" },
    ],
    idempotencyKey: "key-1",
    ...over,
});

let root: Root;
let container: HTMLElement;

async function show(kind: "money" | "existing", over: Record<string, unknown> = {}) {
    await act(async () => root.render(<Create {...(props(kind, over) as unknown as ComponentProps<typeof Create>)} />));
}

const labels = () => Array.from(container.querySelectorAll("label")).map((label) => label.textContent);

beforeEach(() => {
    (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    mockErrors = {};
    mockDark = false;
    mockOld = { lender: "u-1", lender_name: "Sika Microfinance" };
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

describe("The new loan form", () => {
    it("has a Back link to wherever the form was opened from, for the farmer and for the agent", async () => {
        await show("money");
        const back = () => Array.from(container.querySelectorAll("a")).find((link) => link.textContent === "← Back")?.getAttribute("href");

        expect(back()).toBe("/loans");

        await show("money", { agent: true, backUrl: "/agent/farmers/f-1" });

        expect(back()).toBe("/agent/farmers/f-1");
    });

    it("has exactly the approved fields", async () => {
        await show("money");

        expect(labels()).toEqual([
            "Lender name",
            "Amount received",
            "Date received",
            "Paid into",
            "Total interest agreed (optional)",
            "Due date (optional)",
            "Note (optional)",
        ]);
    });

    it("has no lender type select, and offers the paid-into choices", async () => {
        await show("money");

        const options = Array.from(container.querySelectorAll("select")).map((select) =>
            Array.from(select.querySelectorAll("option"))
                .map((option) => option.textContent)
                .filter((text) => text !== ""),
        );

        expect(container.querySelector("#lender_type")).toBeNull();
        expect(options).toEqual([["Cash", "MoMo", "Bank"]]);
    });

    it("keeps Save blocked, and posts nothing, until a lender is picked", async () => {
        mockOld = {};
        await show("money");

        const save = Array.from(container.querySelectorAll("button")).find((button) => button.textContent === "Save this record")!;
        expect(save.disabled).toBe(true);

        await act(async () => {
            container.querySelector("form")!.dispatchEvent(new Event("submit", { bubbles: true, cancelable: true }));
        });

        expect(post).not.toHaveBeenCalled();
    });
});

describe("The loan I already had form", () => {
    it("has exactly the approved fields, and no date and no paid-into", async () => {
        await show("existing");

        expect(labels()).toEqual([
            "Lender name",
            "Amount you still owe",
            "Interest still to pay (optional)",
            "Due date (optional)",
            "Note (optional)",
        ]);
    });
});

describe("The Cancel button", () => {
    const cancel = () => Array.from(container.querySelectorAll("button")).find((button) => button.textContent === "Cancel");

    it.each(["money", "existing"] as const)("is on the %s form, and goes back to the Loans page without saving", async (kind) => {
        await show(kind);

        expect(cancel()).toBeDefined();
        expect(cancel()?.getAttribute("type")).toBe("button");

        await act(async () => cancel()!.click());

        expect(visit).toHaveBeenCalledWith("/loans");
        expect(post).not.toHaveBeenCalled();
    });

    it("is there in the dark theme too", async () => {
        mockDark = true;
        await show("money");

        expect(cancel()).toBeDefined();
    });
});

describe("The agent's forms", () => {
    const agentOver = { agent: true, postUrl: "/agent/farmers/f-1/loans", backUrl: "/agent/farmers/f-1" };

    it("reuse the farmer's labels, and the existing loan amount reads Loan Amount", async () => {
        await show("existing", agentOver);

        expect(labels()).toEqual([
            "Lender name",
            "Loan Amount",
            "Interest still to pay (optional)",
            "Due date (optional)",
            "Note (optional)",
        ]);
    });

    it("keep Amount received on the new loan form", async () => {
        await show("money", agentOver);

        expect(labels()).toContain("Amount received");
        expect(labels()).not.toContain("Loan Amount");
    });

    it("post to the agent address, and Cancel goes back to the farmer page", async () => {
        await show("money", agentOver);

        const cancel = Array.from(container.querySelectorAll("button")).find((button) => button.textContent === "Cancel");
        await act(async () => cancel!.click());

        expect(visit).toHaveBeenCalledWith("/agent/farmers/f-1");

        const form = container.querySelector("form")!;
        await act(async () => {
            form.dispatchEvent(new Event("submit", { bubbles: true, cancelable: true }));
        });

        expect(post).toHaveBeenCalledWith("/agent/farmers/f-1/loans");
    });

    it("the farmer's own form still posts to /loans", async () => {
        await show("existing");

        const form = container.querySelector("form")!;
        await act(async () => {
            form.dispatchEvent(new Event("submit", { bubbles: true, cancelable: true }));
        });

        expect(post).toHaveBeenCalledWith("/loans");
        expect(labels()).toContain("Amount you still owe");
    });
});

describe("Errors", () => {
    it("shows the error right beside its field", async () => {
        mockErrors = { amount: "Enter an amount above zero.", date: "This date is in the future." };
        await show("money");

        const text = (label: string) => {
            const field = Array.from(container.querySelectorAll("label")).find((item) => item.textContent === label);

            return field?.parentElement?.textContent ?? "";
        };

        expect(text("Amount received")).toContain("Enter an amount above zero.");
        expect(text("Date received")).toContain("This date is in the future.");
        expect(text("Lender name")).not.toContain("Enter an amount above zero.");
    });
});
