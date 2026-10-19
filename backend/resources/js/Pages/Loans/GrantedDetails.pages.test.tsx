// @vitest-environment jsdom
import { act, ComponentProps, ComponentType } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import FarmerShow from "./Show";
import AgentShow from "../Agent/Loans/Show";
import AdminShow from "../Admin/Loans/Show";

vi.mock("@/Layouts/AdminLayout", () => ({ default: ({ children }: { children: unknown }) => children }));
vi.mock("@/Layouts/AuthenticatedLayout", () => ({
    default: ({ children }: { children: unknown }) => children,
    useTheme: () => ({ dark: false }),
}));
vi.mock("@/lib/syncHttp", () => ({ csrfToken: () => "token" }));
vi.mock("@inertiajs/react", async () => {
    const React = await import("react");

    return {
        Link: ({ href, children }: { href: string; children: React.ReactNode }) => React.createElement("a", { href }, children),
        router: { visit: () => {} },
        usePage: () => ({ props: { errors: {}, flash: {} } }),
        useForm: (initial: Record<string, string>) => ({ data: initial, errors: {}, processing: false, setData: () => {}, post: () => {} }),
    };
});

const loan = { uuid: "l-1", lender: "Sika Microfinance", amount: 100000, agreed_interest: 20000, granted_on: "2026-09-01", balance: 60000, status: "active", check: null };
const base = {
    loan,
    farmer: "Mensah Kofi",
    repayments: [{ uuid: "r1", date: "2026-10-10", amount: 60000, principal: 50000, interest: 10000, balance: 60000, cancelled: false }],
    rejected: [],
    checks: [],
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

describe.each([
    ["the farmer's loan page", FarmerShow],
    ["the agent's loan page", AgentShow],
    ["the admin's loan page", AdminShow],
])("%s", (_name, Page) => {
    it("starts the repayments with the loan itself, in its own table", async () => {
        const Component = Page as ComponentType<Record<string, unknown>>;
        await act(async () => root.render(<Component {...(base as unknown as ComponentProps<typeof Component>)} />));

        const heading = Array.from(container.querySelectorAll("h3")).find((item) => item.textContent === "Loan Granted Details")!;
        const repayments = Array.from(container.querySelectorAll("h3")).find((item) => item.textContent === "Repayments")!;
        const tables = container.querySelectorAll("table");

        expect(heading).toBeDefined();
        expect(heading.compareDocumentPosition(repayments) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
        expect(tables[0].textContent).toContain("GHS 1,000.00");
        expect(tables[0].textContent).toContain("GHS 200.00");
        expect(Array.from(tables[0].querySelectorAll("th")).map((cell) => cell.textContent)).toEqual(["Date Granted", "Amount Granted", "Interest Agreed"]);
        expect(tables[1].textContent).toContain("GHS 600.00");
    });
});
