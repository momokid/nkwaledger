// @vitest-environment jsdom
import { act, ComponentProps } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import Create from "./Create";

let mockOld: Record<string, string> = {};
const fetchMock = vi.fn();

vi.mock("@/Layouts/AuthenticatedLayout", () => ({
    default: ({ children }: { children: unknown }) => children,
    useTheme: () => ({ dark: false }),
}));

vi.mock("@inertiajs/react", async () => {
    const React = await import("react");

    return {
        usePage: () => ({ props: { errors: {}, flash: {}, old: mockOld } }),
        router: { visit: () => {} },
        useForm: (initial: Record<string, string>) => {
            const [data, setData] = React.useState(initial);

            return {
                data,
                errors: {},
                processing: false,
                setData: (key: string, value: string) => setData((all) => ({ ...all, [key]: value })),
                reset: () => {},
                post: () => {},
            };
        },
    };
});

const props = {
    kind: "money",
    agent: false,
    postUrl: "/loans",
    backUrl: "/loans",
    lenderTypes: ["bank", "microfinance", "credit_union", "other"],
    paidInto: [{ id: 1, label: "Cash" }],
    idempotencyKey: "key-1",
};

let root: Root;
let container: HTMLElement;

const type = async (id: string, value: string) => {
    const input = container.querySelector<HTMLInputElement>(`#${id}`)!;
    const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, "value")!.set!;

    await act(async () => {
        setter.call(input, value);
        input.dispatchEvent(new Event("input", { bubbles: true }));
    });
    await act(async () => {
        await new Promise((resolve) => setTimeout(resolve, 300));
    });
};

const show = async (lenderType: string) => {
    mockOld = { lender_type: lenderType };
    await act(async () => root.render(<Create {...(props as unknown as ComponentProps<typeof Create>)} />));
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

describe("The lender on the loan form", () => {
    it("searches institutions with a POST, so nothing typed goes in an address, and lets the farmer pick one", async () => {
        fetchMock.mockResolvedValue({ ok: true, json: async () => [{ uuid: "u-1", name: "Sika Microfinance", type: "microfinance" }] });
        await show("microfinance");
        await type("lender_name", "sik");

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(fetchMock.mock.calls[0][0]).toBe("/lenders/search");
        expect(fetchMock.mock.calls[0][1].method).toBe("POST");
        expect(JSON.parse(fetchMock.mock.calls[0][1].body)).toEqual({ q: "sik", type: "microfinance" });

        const option = container.querySelector<HTMLButtonElement>("[role='option']")!;
        expect(option.textContent).toBe("Sika Microfinance");

        await act(async () => option.click());

        expect(container.querySelector<HTMLInputElement>("#lender_name")!.value).toBe("Sika Microfinance");
        expect(container.querySelector("[role='option']")).toBeNull();
    });

    it("does not search for fewer than two characters", async () => {
        await show("bank");
        await type("lender_name", "s");

        expect(fetchMock).not.toHaveBeenCalled();
    });

    it("says it is not in the list when nothing is found, and offers no way to add it", async () => {
        fetchMock.mockResolvedValue({ ok: true, json: async () => [] });
        await show("bank");
        await type("lender_name", "my own bank");

        expect(container.textContent).toContain("lender.not_in_list");
        expect(container.querySelector("#lender_phone")).toBeNull();
    });

    it("has no phone or note field for any lender type, and offers the credit union type", async () => {
        for (const lenderType of ["bank", "microfinance", "credit_union", "other", ""]) {
            await show(lenderType);

            expect(container.querySelector("#lender_phone")).toBeNull();
            expect(container.querySelector("#lender_note")).toBeNull();
        }

        const options = Array.from(container.querySelectorAll("option")).map((option) => option.textContent);
        expect(options).toContain("lender.type.credit_union");
    });

    it("searches for a credit union too", async () => {
        fetchMock.mockResolvedValue({ ok: true, json: async () => [] });
        await show("credit_union");
        await type("lender_name", "teach");

        expect(JSON.parse(fetchMock.mock.calls[0][1].body)).toEqual({ q: "teach", type: "credit_union" });
    });
});
