// @vitest-environment jsdom
import { act } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import RepaymentForm from "./RepaymentForm";

const fetchMock = vi.fn();
const visited = vi.fn();
const onSaved = vi.fn();

vi.mock("@/Layouts/AuthenticatedLayout", () => ({
    default: ({ children }: { children: unknown }) => children,
    useTheme: () => ({ dark: false }),
}));
vi.mock("@/lib/syncHttp", () => ({ csrfToken: () => "token" }));
vi.mock("@inertiajs/react", async () => {
    const React = await import("react");

    return {
        usePage: () => ({ props: { errors: {} } }),
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

const saved = { loan: { uuid: "l-1", balance: 50000, status: "active" }, repayments: [], rejected: [], idempotencyKey: "fresh-key" };

let root: Root;
let container: HTMLElement;

const input = (id: string) => container.querySelector<HTMLInputElement | HTMLSelectElement>(`#${id}`)!;
const set = async (id: string, value: string) => {
    const element = input(id);
    const prototype = element instanceof HTMLSelectElement ? HTMLSelectElement.prototype : HTMLInputElement.prototype;

    await act(async () => {
        Object.getOwnPropertyDescriptor(prototype, "value")!.set!.call(element, value);
        element.dispatchEvent(new Event(element instanceof HTMLSelectElement ? "change" : "input", { bubbles: true }));
    });
};
const submit = () => act(async () => container.querySelector("form")!.dispatchEvent(new Event("submit", { bubbles: true, cancelable: true })));
const sent = () => fetchMock.mock.calls.filter((call) => call[0] === "/post");

beforeEach(async () => {
    (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    fetchMock.mockReset();
    visited.mockReset();
    onSaved.mockReset();
    fetchMock.mockImplementation(async (url: string) => (url === "/preview" ? { ok: true, json: async () => ({ principal: 100, interest: 0 }) } : { ok: true, status: 201, json: async () => saved }));
    vi.stubGlobal("fetch", fetchMock);
    container = document.createElement("div");
    document.body.appendChild(container);
    root = createRoot(container);
    await act(async () =>
        root.render(
            <RepaymentForm
                paidFrom={[{ value: "cash", label: "Cash" }, { value: "momo", label: "MoMo" }]}
                previewUrl="/preview"
                postUrl="/post"
                today="2026-10-12"
                idempotencyKey="key-1"
                onSaved={onSaved}
                onCancel={() => {}}
            />,
        ),
    );
});

afterEach(async () => {
    await act(async () => root.unmount());
    container.remove();
    vi.unstubAllGlobals();
});

describe("Saving a repayment in place", () => {
    it("posts as JSON from the page itself, never as a page visit", async () => {
        await set("repay-amount", "100");
        await set("repay-paid-from", "momo");
        await submit();

        const [url, init] = sent()[0];

        expect(url).toBe("/post");
        expect(init.headers.Accept).toBe("application/json");
        expect(JSON.parse(init.body)).toEqual({ amount: "100", date: "2026-10-12", paid_from: "momo", idempotency_key: "key-1" });
        expect(visited).not.toHaveBeenCalled();
    });

    it("clears every field after a save, and hands the result to the page", async () => {
        await set("repay-amount", "100");
        await set("repay-date", "2026-10-01");
        await set("repay-paid-from", "momo");
        await submit();

        expect(input("repay-amount").value).toBe("");
        expect(input("repay-date").value).toBe("2026-10-12");
        expect(input("repay-paid-from").value).toBe("cash");
        expect(onSaved).toHaveBeenCalledWith(saved);
    });

    it("uses the fresh key for the next repayment, so it counts as a new one", async () => {
        await set("repay-amount", "100");
        await submit();
        await set("repay-amount", "50");
        await submit();

        expect(JSON.parse(sent()[0][1].body).idempotency_key).toBe("key-1");
        expect(JSON.parse(sent()[1][1].body).idempotency_key).toBe("fresh-key");
    });

    it("shows the split gone once the form is cleared", async () => {
        await set("repay-amount", "100");
        await act(async () => {
            await new Promise((resolve) => setTimeout(resolve, 300));
        });

        expect(container.querySelector('[aria-label="split"]')).not.toBeNull();

        await submit();

        expect(container.querySelector('[aria-label="split"]')).toBeNull();
    });

    it("keeps what was typed and shows the server's words when the repayment is refused", async () => {
        fetchMock.mockImplementation(async (url: string) =>
            url === "/preview" ? { ok: true, json: async () => ({ principal: 100, interest: 0 }) } : { ok: false, status: 422, json: async () => ({ message: "This loan is fully repaid" }) },
        );
        await set("repay-amount", "100");
        await submit();

        expect(container.textContent).toContain("This loan is fully repaid");
        expect(input("repay-amount").value).toBe("100");
        expect(onSaved).not.toHaveBeenCalled();
    });

    it("shows a field error under its field and keeps what was typed", async () => {
        fetchMock.mockImplementation(async (url: string) =>
            url === "/preview" ? { ok: false, json: async () => ({}) } : { ok: false, status: 422, json: async () => ({ errors: { date: ["Repayment date cannot be before the loan date"] } }) },
        );
        await set("repay-amount", "100");
        await submit();

        expect(container.textContent).toContain("Repayment date cannot be before the loan date");
        expect(input("repay-amount").value).toBe("100");
    });

    it("locks the button and shows Saving... while it saves", async () => {
        let release: (value: unknown) => void = () => {};
        fetchMock.mockImplementation((url: string) => (url === "/preview" ? Promise.resolve({ ok: true, json: async () => ({ principal: 100, interest: 0 }) }) : new Promise((resolve) => (release = resolve))));
        await set("repay-amount", "100");
        await submit();

        const saveButton = container.querySelector<HTMLButtonElement>("button[type='submit']")!;

        expect(saveButton.textContent).toBe("Saving...");
        expect(saveButton.disabled).toBe(true);

        await act(async () => release({ ok: true, status: 201, json: async () => saved }));

        expect(container.querySelector<HTMLButtonElement>("button[type='submit']")!.disabled).toBe(false);
    });
});
