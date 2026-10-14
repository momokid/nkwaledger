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
vi.mock("@inertiajs/react", () => ({ router: { get: vi.fn() } }));

const types = ["bank", "rural_bank", "credit_financial_institution", "microfinance", "other"];

const props = {
    lenders: [
        { uuid: "u-1", type: "bank", name: "First Bank" },
        { uuid: "u-2", type: "microfinance", name: "Second Micro" },
    ],
    pagination: { page: 1, last: 1 },
    types,
};

let root: Root;
let container: HTMLElement;

const rowSelect = (index: number) => container.querySelectorAll<HTMLSelectElement>("tbody select")[index];

const pick = async (select: HTMLSelectElement, value: string) => {
    const setter = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, "value")!.set!;

    await act(async () => {
        setter.call(select, value);
        select.dispatchEvent(new Event("change", { bubbles: true }));
    });
};

const reply = (ok: boolean, json: unknown) => ({ ok, json: async () => json });

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

describe("The type on each row of the Lenders page", () => {
    it("is a select showing the five keys, on the row's own type", () => {
        expect(Array.from(rowSelect(0).options).map((option) => option.value)).toEqual(types);
        expect(Array.from(rowSelect(0).options).map((option) => option.textContent)).toEqual(types.map((type) => `lender.type.${type}`));
        expect([rowSelect(0).value, rowSelect(1).value]).toEqual(["bank", "microfinance"]);
    });

    it("saves at once when one is picked, sending only the type, and shows it saved", async () => {
        fetchMock.mockResolvedValue(reply(true, { uuid: "u-1", type: "rural_bank", name: "First Bank" }));

        await pick(rowSelect(0), "rural_bank");

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(fetchMock.mock.calls[0][0]).toBe("/admin/lenders/u-1");
        expect(fetchMock.mock.calls[0][1].method).toBe("PATCH");
        expect(JSON.parse(fetchMock.mock.calls[0][1].body)).toEqual({ type: "rural_bank" });
        expect(rowSelect(0).value).toBe("rural_bank");
        expect(container.querySelector("tbody tr")!.textContent).toContain("lender.saved");
    });

    it("puts the old type back and shows an error when the save fails", async () => {
        fetchMock.mockResolvedValue(reply(false, { errors: { type: ["lender.type_invalid"] } }));

        await pick(rowSelect(0), "other");

        expect(rowSelect(0).value).toBe("bank");
        expect(container.querySelector("tbody tr")!.textContent).toContain("lender.type_invalid");
        expect(container.querySelector("tbody tr")!.textContent).not.toContain("lender.saved");
    });

    it("puts the old type back, with the fallback error, when the request itself fails", async () => {
        fetchMock.mockRejectedValue(new Error("offline"));

        await pick(rowSelect(1), "bank");

        expect(rowSelect(1).value).toBe("microfinance");
        expect(container.querySelectorAll("tbody tr")[1].textContent).toContain("lender.save_failed");
    });

    it("leaves the other rows alone", async () => {
        fetchMock.mockResolvedValue(reply(true, { uuid: "u-1", type: "other", name: "First Bank" }));

        await pick(rowSelect(0), "other");

        expect(rowSelect(1).value).toBe("microfinance");
        expect(container.querySelectorAll("tbody tr")[1].textContent).not.toContain("lender.saved");
    });
});

describe("Renaming", () => {
    it("keeps its own edit and sends only the name", async () => {
        fetchMock.mockResolvedValue(reply(true, { uuid: "u-1", type: "bank", name: "First Bank Renamed" }));

        const edit = Array.from(container.querySelectorAll("button")).find((button) => button.textContent === "lender.edit")!;
        await act(async () => edit.click());

        const input = container.querySelector<HTMLInputElement>("tbody input")!;
        const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, "value")!.set!;
        await act(async () => {
            setter.call(input, "First Bank Renamed");
            input.dispatchEvent(new Event("input", { bubbles: true }));
        });

        const save = Array.from(container.querySelectorAll("button")).find((button) => button.textContent === "lender.save")!;
        await act(async () => save.click());

        expect(JSON.parse(fetchMock.mock.calls[0][1].body)).toEqual({ name: "First Bank Renamed" });
        expect(container.querySelector("tbody tr")!.textContent).toContain("First Bank Renamed");
    });
});
