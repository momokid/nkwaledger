// @vitest-environment jsdom
import { act, ComponentProps } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import Show from "./Show";

const fetchMock = vi.fn();
const visited = vi.fn();

vi.mock("@/Layouts/AuthenticatedLayout", () => ({
    default: ({ children }: { children: unknown }) => children,
    useTheme: () => ({ dark: false }),
}));
vi.mock("@/lib/syncHttp", () => ({ csrfToken: () => "token" }));
vi.mock("@inertiajs/react", async () => {
    const React = await import("react");

    return {
        Link: ({ href, children }: { href: string; children: React.ReactNode }) => React.createElement("a", { href }, children),
        router: { visit: (...args: unknown[]) => visited(...args) },
        usePage: () => ({ props: { errors: {}, flash: {} } }),
        useForm: (initial: Record<string, string>) => {
            const [data, setAll] = React.useState(initial);

            return {
                data,
                errors: {},
                processing: false,
                setData: (key: string | Record<string, string>, value?: string) => setAll((all) => (typeof key === "string" ? { ...all, [key]: value as string } : { ...all, ...key })),
                post: (...args: unknown[]) => visited(...args),
            };
        },
    };
});

const props = {
    loan: { uuid: "l-1", lender: "Sika Microfinance", amount: 100000, agreed_interest: 20000, granted_on: "2026-09-01", balance: 120000, status: "active" },
    rejected: [],
    repayments: [],
    paidFrom: [{ value: "cash", label: "Cash" }],
    unaccounted: 0,
    otherUses: [],
    farms: [],
    idempotencyKey: "key-1",
    previewUrl: "/p",
    postUrl: "/s",
    today: "2026-10-12",
};

const after = (balance: number, status = "active") => ({
    loan: { uuid: "l-1", lender: "Sika Microfinance", amount: 100000, balance, status },
    repayments: [{ uuid: "r1", date: "2026-10-12", amount: 60000, principal: 50000, interest: 10000, balance: 60000, cancelled: false }],
    rejected: [],
    idempotencyKey: "fresh-key",
});

let root: Root;
let container: HTMLElement;

const button = (label: string) => Array.from(container.querySelectorAll("button")).find((item) => item.textContent === label)!;
const type = async (value: string) => {
    const element = container.querySelector<HTMLInputElement>("#repay-amount")!;

    await act(async () => {
        Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, "value")!.set!.call(element, value);
        element.dispatchEvent(new Event("input", { bubbles: true }));
    });
};
const save = () => act(async () => container.querySelector("form")!.dispatchEvent(new Event("submit", { bubbles: true, cancelable: true })));

beforeEach(async () => {
    (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    fetchMock.mockReset();
    visited.mockReset();
    fetchMock.mockImplementation(async (url: string) => (url === "/p" ? { ok: true, json: async () => ({ principal: 100, interest: 0 }) } : { ok: true, status: 201, json: async () => after(60000) }));
    vi.stubGlobal("fetch", fetchMock);
    container = document.createElement("div");
    document.body.appendChild(container);
    root = createRoot(container);
    await act(async () => root.render(<Show {...(props as unknown as ComponentProps<typeof Show>)} />));
    await act(async () => button("Record a repayment").click());
});

afterEach(async () => {
    await act(async () => root.unmount());
    container.remove();
    vi.unstubAllGlobals();
});

describe("The loan page after a repayment is saved", () => {
    it("shows the new repayment and the new balance in place, and clears the form", async () => {
        // the loan's own line is the only row until a repayment is saved
        expect(container.querySelectorAll("tbody tr")).toHaveLength(1);

        await type("600");
        await save();

        expect(container.querySelectorAll("tbody tr")).toHaveLength(2);
        expect(container.textContent).toContain("Loan Balance GHS 600.00");
        expect(container.querySelector<HTMLInputElement>("#repay-amount")!.value).toBe("");
        expect(visited).not.toHaveBeenCalled();
    });

    it("closes the form when the last repayment clears the loan", async () => {
        fetchMock.mockImplementation(async (url: string) => (url === "/p" ? { ok: true, json: async () => ({ principal: 100, interest: 0 }) } : { ok: true, status: 201, json: async () => after(0, "repaid") }));
        await type("1200");
        await save();

        expect(container.querySelector("#repay-amount")).toBeNull();
        expect(Array.from(container.querySelectorAll("button")).map((item) => item.textContent)).not.toContain("Record a repayment");
    });
});
