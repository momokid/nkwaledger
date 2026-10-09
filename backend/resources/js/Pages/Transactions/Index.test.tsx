// @vitest-environment jsdom
import { act, ComponentProps } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import Index from "./Index";

vi.mock("@/Layouts/AuthenticatedLayout", () => ({
    default: ({ children }: { children: unknown }) => children,
    useTheme: () => ({ dark: false }),
}));

vi.mock("@inertiajs/react", () => ({
    usePage: () => ({ props: { errors: {}, flash: {} } }),
    router: { visit: () => {} },
    useForm: (initial: Record<string, string>) => ({
        data: initial,
        errors: {},
        processing: false,
        setData: () => {},
        reset: () => {},
        post: () => {},
    }),
}));

const FARMER = "5b0f9f4e-0c3e-4a67-9a52-1e0f3f2a1111";

const creditRow = (uuid: string, outstanding: number) => ({
    uuid,
    reference: `REF-${uuid}`,
    date: "2026-03-01",
    description: "I sold crops",
    narration: null,
    amount: 50000,
    outstanding,
});

function props(canSettle: boolean, creditRows = [creditRow("owed", 50000)]) {
    return {
        farmer: { id: FARMER, name: "Ama Mensah" },
        statement: {
            rows: [],
            opening_balance: 0,
            closing_balance: 0,
            total_in: 0,
            total_out: 0,
            total_assets: 0,
            total_expenditure: 0,
            total_income: 0,
            total_liability: 0,
            cancelled_in: 0,
            cancelled_out: 0,
            provisional_held_back: 0,
            total: 0,
            page: 1,
            last_page: 1,
        },
        filters: { from: "2026-03-01", to: "2026-03-31", account: null },
        accounts: [],
        creditRows,
        creditSettlementAccounts: [{ id: 9, name: "Cash" }],
        layout: "farmer" as const,
        basePath: "/my-records",
        canSettle,
    };
}

let root: Root;
let container: HTMLElement;

async function showCreditTab(canSettle: boolean, creditRows?: ReturnType<typeof creditRow>[]) {
    await act(async () => root.render(<Index {...(props(canSettle, creditRows) as unknown as ComponentProps<typeof Index>)} />));

    const tab = Array.from(container.querySelectorAll("button")).find((button) => button.textContent === "Credit");
    await act(async () => tab!.click());
}

const recordPaymentButtons = () => Array.from(container.querySelectorAll("button")).filter((button) => button.textContent === "Record payment");

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

describe("Record payment on the credit tab", () => {
    it("shows for a row that is still owed when the user may settle", async () => {
        await showCreditTab(true);

        expect(recordPaymentButtons()).toHaveLength(1);
    });

    it("shows nothing for a row that is still owed when the user may not settle", async () => {
        await showCreditTab(false);

        expect(recordPaymentButtons()).toHaveLength(0);
        expect(container.querySelectorAll("tbody tr td:last-child")[0].textContent).toBe("");
    });

    it("shows Paid and no button for a fully paid row, even for a user who may settle", async () => {
        await showCreditTab(true, [creditRow("paid", 0)]);

        expect(recordPaymentButtons()).toHaveLength(0);
        expect(container.textContent).toContain("Paid");
    });
});

describe("What was cancelled", () => {
    const render = async (cancelledIn: number, cancelledOut: number) => {
        const base = props(true) as unknown as { statement: Record<string, unknown> };
        base.statement = { ...base.statement, cancelled_in: cancelledIn, cancelled_out: cancelledOut };

        await act(async () => root.render(<Index {...(base as unknown as ComponentProps<typeof Index>)} />));
    };

    it("shows cancelled money in and cancelled money out as two amounts", async () => {
        await render(35000, 15000);

        expect(container.textContent).toContain("Cancelled money in");
        expect(container.textContent).toContain("Cancelled money out");
    });

    it("shows only the amount that is not zero", async () => {
        await render(0, 15000);

        expect(container.textContent).not.toContain("Cancelled money in");
        expect(container.textContent).toContain("Cancelled money out");
    });

    it("shows neither when nothing was cancelled", async () => {
        await render(0, 0);

        expect(container.textContent).not.toContain("Cancelled money");
    });
});
