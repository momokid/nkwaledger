// @vitest-environment jsdom
import { act, ComponentProps } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import Create from "./Create";

const fetchMock = vi.fn();

vi.mock("@/Layouts/AuthenticatedLayout", () => ({
    default: ({ children }: { children: unknown }) => children,
    useTheme: () => ({ dark: false }),
}));
vi.mock("@/lib/syncHttp", () => ({ csrfToken: () => "token" }));

vi.mock("@inertiajs/react", async () => {
    const React = await import("react");

    return {
        Link: ({ href, children }: { href: string; children: React.ReactNode }) => React.createElement("a", { href }, children),
        usePage: () => ({ props: { errors: {}, flash: {}, old: {} } }),
        router: { visit: () => {} },
        useForm: (initial: Record<string, string>) => {
            const [data, setData] = React.useState(initial);

            return { data, errors: {}, processing: false, setData: (key: string, value: string) => setData((all) => ({ ...all, [key]: value })), reset: () => {}, post: () => {} };
        },
    };
});

const base = { kind: "money", agent: false, requestFor: null, postUrl: "/loans", backUrl: "/loans", paidInto: [{ id: 1, label: "Cash" }], idempotencyKey: "key-1" };

let root: Root;
let container: HTMLElement;

const button = (label: string) => Array.from(container.querySelectorAll("button")).find((item) => item.textContent === label);
const set = async (element: HTMLElement, value: string) => {
    const prototype = element instanceof HTMLSelectElement ? HTMLSelectElement.prototype : element instanceof HTMLTextAreaElement ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype;

    await act(async () => {
        Object.getOwnPropertyDescriptor(prototype, "value")!.set!.call(element, value);
        element.dispatchEvent(new Event(element instanceof HTMLSelectElement ? "change" : "input", { bubbles: true }));
    });
};

// the search finds nothing for what was typed, so the lender is not listed
const typeUnknown = async (name: string) => {
    await set(container.querySelector("#lender_name")!, name);
    await act(async () => {
        await new Promise((resolve) => setTimeout(resolve, 300));
    });
};

const open = async (over: Record<string, unknown> = {}) => {
    fetchMock.mockResolvedValue({ ok: true, json: async () => [] });
    await act(async () => root.render(<Create key={JSON.stringify(over)} {...({ ...base, ...over } as unknown as ComponentProps<typeof Create>)} />));
    await typeUnknown("Nsawam");
};

beforeEach(() => {
    (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    fetchMock.mockReset();
    vi.stubGlobal("fetch", fetchMock);
    container = document.createElement("div");
    document.body.appendChild(container);
    root = createRoot(container);
});

afterEach(async () => {
    await act(async () => root.unmount());
    container.remove();
    vi.unstubAllGlobals();
});

describe("Requesting a lender from the loan form", () => {
    it("offers the request only when the search finds nothing", async () => {
        await open();

        expect(button("Request this lender")).toBeDefined();

        fetchMock.mockResolvedValue({ ok: true, json: async () => [{ uuid: "u-1", name: "Nsawam Rural Bank", type: "rural_bank" }] });
        await typeUnknown("Nsawa");

        expect(button("Request this lender")).toBeUndefined();
    });

    it("opens a small form with the typed name filled in, the types, a note and the two buttons", async () => {
        await open();
        await act(async () => button("Request this lender")!.click());

        const options = Array.from(container.querySelectorAll("#request_type option")).map((option) => option.textContent);

        expect(container.textContent).toContain("Request a lender");
        expect((container.querySelector("#request_name") as HTMLInputElement).value).toBe("Nsawam");
        expect(options).toEqual(["", "Bank", "Rural Bank", "Credit Financial Institution", "Microfinance", "Other"]);
        expect(container.querySelector("#request_note")).not.toBeNull();
        expect(button("Send request")).toBeDefined();
        expect(button("Cancel")).toBeDefined();
    });

    it("waits for a name and a type before it can be sent", async () => {
        await open();
        await act(async () => button("Request this lender")!.click());

        expect(button("Send request")!.disabled).toBe(true);

        await set(container.querySelector("#request_type")!, "rural_bank");

        expect(button("Send request")!.disabled).toBe(false);

        await set(container.querySelector("#request_name")!, "  ");

        expect(button("Send request")!.disabled).toBe(true);
    });

    it("sends a farmer's request, with no farmer named, and says it was sent", async () => {
        await open();
        await act(async () => button("Request this lender")!.click());
        await set(container.querySelector("#request_type")!, "rural_bank");
        await set(container.querySelector("#request_note")!, "Near the market");
        fetchMock.mockResolvedValue({ ok: true, status: 201, json: async () => ({ ok: true }) });
        await act(async () => button("Send request")!.click());

        const call = fetchMock.mock.calls.at(-1)!;

        expect(call[0]).toBe("/lender-requests");
        expect(JSON.parse(call[1].body)).toEqual({ name: "Nsawam", type: "rural_bank", note: "Near the market" });
        expect(container.textContent).toContain("Request sent. You will be notified with the response");
        expect(container.querySelector("#request_name")).toBeNull();
    });

    it("names the farmer when an agent sends it", async () => {
        await open({ agent: true, requestFor: "farmer-uuid-1" });
        await act(async () => button("Request this lender")!.click());
        await set(container.querySelector("#request_type")!, "microfinance");
        fetchMock.mockResolvedValue({ ok: true, status: 201, json: async () => ({ ok: true }) });
        await act(async () => button("Send request")!.click());

        expect(JSON.parse(fetchMock.mock.calls.at(-1)![1].body)).toEqual({ name: "Nsawam", type: "microfinance", farmer: "farmer-uuid-1" });
    });

    it("shows what the server refused under the name and keeps the form", async () => {
        await open();
        await act(async () => button("Request this lender")!.click());
        await set(container.querySelector("#request_type")!, "rural_bank");
        fetchMock.mockResolvedValue({ ok: false, status: 422, json: async () => ({ errors: { name: ["This lender has already been contacted waiting for response"] } }) });
        await act(async () => button("Send request")!.click());

        expect(container.textContent).toContain("This lender has already been contacted waiting for response");
        expect(container.querySelector("#request_name")).not.toBeNull();
    });

    it("closes the form on Cancel without sending anything", async () => {
        await open();
        await act(async () => button("Request this lender")!.click());
        const before = fetchMock.mock.calls.length;
        await act(async () => button("Cancel")!.click());

        expect(container.querySelector("#request_name")).toBeNull();
        expect(fetchMock.mock.calls.length).toBe(before);
    });
});
