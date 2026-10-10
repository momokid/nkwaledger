// @vitest-environment jsdom
import { act, ComponentProps } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import Create from "./Create";

let mockErrors: Record<string, string> = {};
let mockDark = false;
const post = vi.fn();
const visit = vi.fn();

vi.mock("@/Layouts/AuthenticatedLayout", () => ({
    default: ({ children }: { children: unknown }) => children,
    useTheme: () => ({ dark: mockDark }),
}));

vi.mock("@inertiajs/react", () => ({
    usePage: () => ({ props: { errors: mockErrors, flash: {}, old: {} } }),
    router: { visit: (...args: unknown[]) => visit(...args) },
    useForm: (initial: Record<string, string>) => ({
        data: initial,
        errors: {},
        processing: false,
        setData: () => {},
        reset: () => {},
        post: (...args: unknown[]) => post(...args),
    }),
}));

const props = (kind: "money" | "existing") => ({
    kind,
    lenderTypes: ["bank", "microfinance", "group", "individual", "other"],
    paidInto: [
        { id: 1, label: "Cash" },
        { id: 2, label: "MoMo" },
        { id: 3, label: "Bank" },
    ],
    idempotencyKey: "key-1",
});

let root: Root;
let container: HTMLElement;

async function show(kind: "money" | "existing") {
    await act(async () => root.render(<Create {...(props(kind) as unknown as ComponentProps<typeof Create>)} />));
}

const labels = () => Array.from(container.querySelectorAll("label")).map((label) => label.textContent);

beforeEach(() => {
    (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    mockErrors = {};
    mockDark = false;
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
    it("has exactly the approved fields", async () => {
        await show("money");

        expect(labels()).toEqual([
            "Lender name",
            "Lender type",
            "Amount received",
            "Date received",
            "Paid into",
            "Total interest agreed (optional)",
            "Due date (optional)",
            "Note (optional)",
        ]);
    });

    it("offers the approved lender types and paid-into choices", async () => {
        await show("money");

        const options = Array.from(container.querySelectorAll("select")).map((select) =>
            Array.from(select.querySelectorAll("option"))
                .map((option) => option.textContent)
                .filter((text) => text !== ""),
        );

        expect(options[0]).toEqual(["Bank", "Microfinance", "Group", "Individual", "Other"]);
        expect(options[1]).toEqual(["Cash", "MoMo", "Bank"]);
    });
});

describe("The loan I already had form", () => {
    it("has exactly the approved fields, and no date and no paid-into", async () => {
        await show("existing");

        expect(labels()).toEqual([
            "Lender name",
            "Lender type",
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
