// @vitest-environment jsdom
import { act, ComponentProps } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import Show from "./Show";

const fetchMock = vi.fn();
const visit = vi.fn();

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
        router: { visit: (...args: unknown[]) => visit(...args) },
    };
});

const base = {
    loan: { uuid: "l-1", lender: "Sika Microfinance", amount: 100000, balance: 60000, status: "active", check: null },
    farmer: "Mensah Kofi",
    repayments: [{ uuid: "r1", date: "2026-10-10", amount: 60000, principal: 50000, interest: 10000, balance: 60000, cancelled: false }],
    rejected: [{ uuid: "x1", date: "2026-10-09", amount: 5000 }],
    checks: [],
    idempotencyKey: "key-1",
};

let root: Root;
let container: HTMLElement;

// a fresh page each time, so nothing from an earlier render carries over
const render = async (over: Record<string, unknown> = {}) => {
    await act(async () => root.unmount());
    root = createRoot(container);
    await act(async () => root.render(<Show {...({ ...base, ...over } as unknown as ComponentProps<typeof Show>)} />));
};

const choose = async (label: string) => {
    const radio = Array.from(container.querySelectorAll<HTMLInputElement>("input[type='radio']")).find((input) => input.parentElement?.textContent === label)!;
    await act(async () => radio.click());
};

const typeNote = async (value: string) => {
    const area = container.querySelector<HTMLTextAreaElement>("textarea")!;
    const setter = Object.getOwnPropertyDescriptor(HTMLTextAreaElement.prototype, "value")!.set!;

    await act(async () => {
        setter.call(area, value);
        area.dispatchEvent(new Event("input", { bubbles: true }));
    });
};

const save = () => Array.from(container.querySelectorAll("button")).find((button) => button.textContent === "Save")!;

const saved = {
    ok: true,
    status: 201,
    json: async () => ({
        check: { status: "confirmed", note: "Spoke to the manager", date: "2026-10-16" },
        checks: [{ uuid: "c1", status: "confirmed", note: "Spoke to the manager", date: "2026-10-16", by: "Admin One" }],
    }),
};

beforeEach(async () => {
    (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    fetchMock.mockReset();
    visit.mockClear();
    vi.stubGlobal("fetch", fetchMock);
    container = document.createElement("div");
    document.body.appendChild(container);
    root = createRoot(container);
    await render();
});

afterEach(async () => {
    await act(async () => root.unmount());
    container.remove();
    vi.unstubAllGlobals();
});

describe("The admin's loan page", () => {
    it("shows the farmer, the lender, the balance, the status and the self-reported notice while nobody has checked the loan", () => {
        const text = container.textContent ?? "";

        expect(text).toContain("Sika Microfinance");
        expect(text).toContain("Mensah Kofi");
        expect(text).toContain("Loan Balance GHS 600.00");
        expect(text).toContain("Active");
        expect(text).toContain("You entered this loan yourself. It has not been checked with the lender.");
    });

    it("has the four approved repayment headers, the repayment and the rejected one, and no raw key", () => {
        const headers = Array.from(Array.from(container.querySelectorAll("table")).at(-1)!.querySelectorAll("th")).map((th) => th.textContent);
        const text = container.textContent ?? "";

        expect(headers).toEqual(["Repayment Date", "Repayment Amount", "Principal on Repayment", "Interest on Repayment", "Loan Balance"]);
        expect(text).toContain("GHS 500.00");
        expect(text).toContain("Repayment rejected");
        expect(text).not.toMatch(/\b(repayment|lender)\.[a-z_]+/);
    });

    it("records no repayment: its only links go back to the admin list and to the statement, and the only form is the check", () => {
        expect(Array.from(container.querySelectorAll("a")).map((link) => link.getAttribute("href"))).toEqual(["/admin/loans", "/loans/l-1/statement"]);
        expect(container.querySelectorAll("form")).toHaveLength(1);
        expect(container.querySelectorAll("input[type='text']")).toHaveLength(0);
        expect(container.textContent).not.toContain("Record a repayment");
    });
});

describe("Confirm with the lender", () => {
    it("offers the four statuses and a note, and Save waits until a status is chosen", () => {
        const options = Array.from(container.querySelectorAll("input[type='radio']")).map((input) => input.parentElement?.textContent);

        expect(container.textContent).toContain("Confirm with the lender");
        expect(options).toEqual(["Confirmed", "Not Confirmed", "Does not match", "Lender could not be reached"]);
        expect(container.querySelector("textarea")).not.toBeNull();
        expect(save().disabled).toBe(true);
    });

    it("saves in place: it posts the status, the note and the key, then shows the result and the history with no page visit", async () => {
        fetchMock.mockResolvedValue(saved);
        await choose("Confirmed");
        await typeNote("Spoke to the manager");

        expect(save().disabled).toBe(false);

        await act(async () => save().click());

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(fetchMock.mock.calls[0][0]).toBe("/admin/loans/l-1/checks");
        expect(JSON.parse(fetchMock.mock.calls[0][1].body)).toEqual({ status: "confirmed", note: "Spoke to the manager", idempotency_key: "key-1" });

        const text = container.textContent ?? "";

        expect(text).toContain("Confirmed with the lender on");
        expect(text).toContain("Checks with the lender");
        expect(text).toContain("Admin One");
        expect(text).not.toContain("You entered this loan yourself");
        expect(visit).not.toHaveBeenCalled();
        expect(save().disabled).toBe(true);
        expect(container.querySelector<HTMLTextAreaElement>("textarea")!.value).toBe("");
    });

    it("shows what the server refused under its own field and keeps what was typed", async () => {
        fetchMock.mockResolvedValue({ ok: false, status: 422, json: async () => ({ errors: { note: ["Something went wrong. Please try again."] } }) });
        await choose("Not Confirmed");
        await typeNote("x");
        await act(async () => container.querySelector("form")!.dispatchEvent(new Event("submit", { bubbles: true, cancelable: true })));

        expect(container.textContent).toContain("Something went wrong. Please try again.");
        expect(container.querySelector<HTMLTextAreaElement>("textarea")!.value).toBe("x");
    });

    it("is not offered on a cancelled loan", async () => {
        await render({ loan: { ...base.loan, status: "cancelled" } });

        expect(container.querySelector("form")).toBeNull();
    });

    it("lists earlier checks, newest first, with who made them and their notes", async () => {
        await render({
            checks: [
                { uuid: "c2", status: "confirmed", note: "Spoke to the manager", date: "2026-10-16", by: "Admin One" },
                { uuid: "c1", status: "unreachable", note: "Phone off", date: "2026-10-15", by: "Admin Two" },
            ],
        });

        const text = container.textContent ?? "";

        expect(text.indexOf("Spoke to the manager")).toBeLessThan(text.indexOf("Phone off"));
        expect(text).toContain("Admin Two");
        expect(text).toContain("Lender could not be reached");
    });
});

describe("Withdraw this check", () => {
    const checked = {
        loan: { ...base.loan, check: { status: "confirmed", note: "Spoke to the manager", date: "2026-10-16" } },
        checks: [{ uuid: "c1", status: "confirmed", note: "Spoke to the manager", date: "2026-10-16", by: "Admin One" }],
    };
    const button = (label: string) => Array.from(container.querySelectorAll("button")).find((item) => item.textContent === label);

    it("is not offered while there is no check to take back", () => {
        expect(button("Withdraw this check")).toBeUndefined();
    });

    it("opens a reason box, and the withdraw button waits for a reason", async () => {
        await render(checked);
        await act(async () => button("Withdraw this check")!.click());

        expect(container.querySelector("#withdraw-note")).not.toBeNull();
        expect(button("Withdraw this check")!.disabled).toBe(true);

        await act(async () => button("Cancel")!.click());

        expect(container.querySelector("#withdraw-note")).toBeNull();
        expect(button("Withdraw this check")).toBeDefined();
    });

    it("withdraws in place: it posts the reason, then shows the self-reported notice again and the withdrawn check in the history", async () => {
        fetchMock.mockResolvedValue({
            ok: true,
            status: 201,
            json: async () => ({
                check: null,
                checks: [
                    { uuid: "c2", status: "withdrawn", note: "Wrong branch", date: "2026-10-17", by: "Admin One" },
                    { uuid: "c1", status: "confirmed", note: "Spoke to the manager", date: "2026-10-16", by: "Admin One" },
                ],
            }),
        });
        await render(checked);
        await act(async () => button("Withdraw this check")!.click());

        const area = container.querySelector<HTMLTextAreaElement>("#withdraw-note")!;
        const setter = Object.getOwnPropertyDescriptor(HTMLTextAreaElement.prototype, "value")!.set!;
        await act(async () => {
            setter.call(area, "Wrong branch");
            area.dispatchEvent(new Event("input", { bubbles: true }));
        });
        await act(async () => button("Withdraw this check")!.click());

        expect(fetchMock.mock.calls[0][0]).toBe("/admin/loans/l-1/checks/withdraw");
        expect(JSON.parse(fetchMock.mock.calls[0][1].body).note).toBe("Wrong branch");

        const text = container.textContent ?? "";

        expect(text).toContain("You entered this loan yourself. It has not been checked with the lender.");
        expect(text).toContain("Withdrawn");
        expect(text).toContain("Wrong branch");
        expect(button("Withdraw this check")).toBeUndefined();
        expect(visit).not.toHaveBeenCalled();
    });

    it("shows what the server refused under the reason box and keeps the reason", async () => {
        fetchMock.mockResolvedValue({ ok: false, status: 422, json: async () => ({ errors: { note: ["Something went wrong. Please try again."] } }) });
        await render(checked);
        await act(async () => button("Withdraw this check")!.click());

        const area = container.querySelector<HTMLTextAreaElement>("#withdraw-note")!;
        const setter = Object.getOwnPropertyDescriptor(HTMLTextAreaElement.prototype, "value")!.set!;
        await act(async () => {
            setter.call(area, "x");
            area.dispatchEvent(new Event("input", { bubbles: true }));
        });
        await act(async () => button("Withdraw this check")!.click());

        expect(container.textContent).toContain("Something went wrong. Please try again.");
        expect(container.querySelector<HTMLTextAreaElement>("#withdraw-note")!.value).toBe("x");
    });
});
