// @vitest-environment jsdom
import { act, ComponentProps } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import Index from "./Index";

const fetchMock = vi.fn();

vi.mock("@/Layouts/AdminLayout", () => ({ default: ({ children }: { children: unknown }) => children }));
vi.mock("@/Layouts/AuthenticatedLayout", () => ({
    default: ({ children }: { children: unknown }) => children,
    useTheme: () => ({ dark: false }),
}));
vi.mock("@/lib/syncHttp", () => ({ csrfToken: () => "token" }));

const props = {
    rows: [
        { uuid: "q-1", requested_by: "Mensah Kofi", name: "Nsawam Community Bank", type: "rural_bank", note: "Near the market", date: "2026-10-17" },
        { uuid: "q-2", requested_by: "Boateng Ama", name: "Tamale Savings", type: "microfinance", note: null, date: "2026-10-16" },
    ],
    types: ["bank", "rural_bank", "credit_financial_institution", "microfinance", "other"],
};

let root: Root;
let container: HTMLElement;

const button = (label: string, within: ParentNode = container) => Array.from(within.querySelectorAll("button")).find((item) => item.textContent === label)!;
const rowOf = (uuid: string) => container.querySelector<HTMLElement>(`[data-request='${uuid}']`)!;
const type = async (element: HTMLInputElement | HTMLTextAreaElement, value: string) => {
    const prototype = element instanceof HTMLTextAreaElement ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype;

    await act(async () => {
        Object.getOwnPropertyDescriptor(prototype, "value")!.set!.call(element, value);
        element.dispatchEvent(new Event("input", { bubbles: true }));
    });
};
const ok = () => ({ ok: true, status: 200, json: async () => ({ ok: true }) });

beforeEach(async () => {
    (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    fetchMock.mockReset();
    vi.stubGlobal("fetch", fetchMock);
    container = document.createElement("div");
    document.body.appendChild(container);
    root = createRoot(container);
    await act(async () => root.render(<Index {...(props as unknown as ComponentProps<typeof Index>)} />));
});

afterEach(async () => {
    await act(async () => root.unmount());
    container.remove();
    vi.unstubAllGlobals();
});

describe("The admin's Lender Requests", () => {
    it("lists each waiting request under the approved columns", () => {
        const headers = Array.from(container.querySelectorAll("th")).map((cell) => cell.textContent);

        expect(headers).toEqual(["Requested By", "Lender Name", "Lender Type", "Note", "Date", ""]);
        expect(rowOf("q-1").textContent).toContain("Mensah Kofi");
        expect(rowOf("q-1").textContent).toContain("Nsawam Community Bank");
        expect(rowOf("q-1").textContent).toContain("Rural Bank");
        expect(rowOf("q-1").textContent).toContain("Near the market");
    });

    it("adds the lender in place: it posts, then the request leaves the queue without a reload", async () => {
        fetchMock.mockResolvedValue(ok());
        await act(async () => button("Add Lender", rowOf("q-1")).click());

        expect(fetchMock.mock.calls[0][0]).toBe("/admin/lender-requests/q-1/add");
        expect(rowOf("q-1")).toBeNull();
        expect(rowOf("q-2")).not.toBeNull();
    });

    it("uses an existing lender: it searches the list, and sends only the lender picked", async () => {
        fetchMock.mockImplementation(async (url: string) =>
            url === "/lenders/search" ? { ok: true, json: async () => [{ uuid: "len-1", name: "Sika Microfinance", type: "microfinance" }] } : ok(),
        );
        await act(async () => button("Use existing lender", rowOf("q-2")).click());
        await type(container.querySelector<HTMLInputElement>("#existing-q-2")!, "sika");
        await act(async () => {
            await new Promise((resolve) => setTimeout(resolve, 300));
        });
        await act(async () => container.querySelector<HTMLButtonElement>("[role='option']")!.click());

        const post = fetchMock.mock.calls.find((call) => String(call[0]).includes("use-existing"))!;

        expect(post[0]).toBe("/admin/lender-requests/q-2/use-existing");
        expect(JSON.parse(post[1].body)).toEqual({ lender: "len-1" });
        expect(rowOf("q-2")).toBeNull();
    });

    it("rejects with a reason: the button waits for the reason, and the reason is sent", async () => {
        fetchMock.mockResolvedValue(ok());
        await act(async () => button("Reject", rowOf("q-1")).click());

        const panelReject = () => button("Reject", container.querySelector("#reason-q-1")!.parentElement!);

        expect(panelReject().disabled).toBe(true);

        await type(container.querySelector<HTMLTextAreaElement>("#reason-q-1")!, "Not a real bank");
        await act(async () => panelReject().click());

        expect(fetchMock.mock.calls[0][0]).toBe("/admin/lender-requests/q-1/reject");
        expect(JSON.parse(fetchMock.mock.calls[0][1].body)).toEqual({ reason: "Not a real bank" });
        expect(rowOf("q-1")).toBeNull();
    });

    it("shows what the server refused under the request and keeps the request", async () => {
        fetchMock.mockResolvedValue({ ok: false, status: 422, json: async () => ({ errors: { name: ["Lender already exists"] } }) });
        await act(async () => button("Add Lender", rowOf("q-1")).click());

        expect(container.querySelector("[role='alert']")!.textContent).toBe("Lender already exists");
        expect(rowOf("q-1")).not.toBeNull();
    });

    it("cancels an open panel", async () => {
        await act(async () => button("Reject", rowOf("q-1")).click());
        await act(async () => button("Cancel").click());

        expect(container.querySelector("#reason-q-1")).toBeNull();
    });

    it("says so when nothing is waiting, and links nowhere", async () => {
        await act(async () => root.render(<Index key="empty" {...({ ...props, rows: [] } as unknown as ComponentProps<typeof Index>)} />));

        expect(container.textContent).toContain("No requests yet");
        expect(container.querySelectorAll("a")).toHaveLength(0);
    });
});
