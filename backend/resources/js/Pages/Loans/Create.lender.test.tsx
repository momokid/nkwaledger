// @vitest-environment jsdom
import { act, ComponentProps } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import Create from "./Create";

let mockOld: Record<string, string> = {};
const fetchMock = vi.fn();
const posted = vi.fn();

vi.mock("@/Layouts/AuthenticatedLayout", () => ({
    default: ({ children }: { children: unknown }) => children,
    useTheme: () => ({ dark: false }),
}));

vi.mock("@inertiajs/react", async () => {
    const React = await import("react");

    return {
        Link: ({ href, children }: { href: string; children: React.ReactNode }) => React.createElement("a", { href }, children),
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
                post: () => posted(data),
            };
        },
    };
});

const props = {
    kind: "money",
    agent: false,
    postUrl: "/loans",
    backUrl: "/loans",
    paidInto: [{ id: 1, label: "Cash" }],
    idempotencyKey: "key-1",
};

const found = [
    { uuid: "u-1", name: "Sika Microfinance", type: "microfinance" },
    { uuid: "u-2", name: "Sikaman Rural Bank", type: "rural_bank" },
];

let root: Root;
let container: HTMLElement;

const input = () => container.querySelector<HTMLInputElement>("#lender_name")!;
const options = () => Array.from(container.querySelectorAll<HTMLButtonElement>("[role='option']"));
const save = () => Array.from(container.querySelectorAll("button")).find((button) => button.textContent === "Save this record")!;

const typeInto = async (value: string, wait = true) => {
    const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, "value")!.set!;

    await act(async () => {
        setter.call(input(), value);
        input().dispatchEvent(new Event("input", { bubbles: true }));
    });

    if (wait) {
        await act(async () => {
            await new Promise((resolve) => setTimeout(resolve, 300));
        });
    }
};

const key = async (target: HTMLElement, name: string) => {
    await act(async () => {
        target.dispatchEvent(new KeyboardEvent("keydown", { key: name, bubbles: true, cancelable: true }));
    });
};

const show = async (old: Record<string, string> = {}) => {
    mockOld = old;
    await act(async () => root.render(<Create {...(props as unknown as ComponentProps<typeof Create>)} />));
};

beforeEach(() => {
    (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    fetchMock.mockReset();
    posted.mockReset();
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
    it("has no lender type select, no phone and no note", async () => {
        await show();

        expect(container.querySelector("#lender_type")).toBeNull();
        expect(container.querySelector("#lender_phone")).toBeNull();
        expect(container.querySelector("#lender_note")).toBeNull();
        expect(container.textContent).not.toContain("Lender type");
    });

    it("searches with a POST, by name only, after a short wait, and lets the farmer pick one", async () => {
        fetchMock.mockResolvedValue({ ok: true, json: async () => found });
        await show();
        await typeInto("sik", false);

        expect(fetchMock).not.toHaveBeenCalled();

        await act(async () => {
            await new Promise((resolve) => setTimeout(resolve, 300));
        });

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(fetchMock.mock.calls[0][0]).toBe("/lenders/search");
        expect(fetchMock.mock.calls[0][1].method).toBe("POST");
        expect(JSON.parse(fetchMock.mock.calls[0][1].body)).toEqual({ q: "sik" });

        await act(async () => options()[0].click());

        expect(input().value).toBe("Sika Microfinance");
        expect(options()).toHaveLength(0);
    });

    it("shows each result's name with its type next to it", async () => {
        fetchMock.mockResolvedValue({ ok: true, json: async () => found });
        await show();
        await typeInto("sik");

        expect(options().map((option) => option.textContent)).toEqual([
            "Sika MicrofinanceMicrofinance",
            "Sikaman Rural BankRural Bank",
        ]);
    });

    it("does not search for fewer than two characters", async () => {
        await show();
        await typeInto("s");

        expect(fetchMock).not.toHaveBeenCalled();
    });

    it("says it is not in the list when nothing is found, and offers no way to add it", async () => {
        fetchMock.mockResolvedValue({ ok: true, json: async () => [] });
        await show();
        await typeInto("my own bank");

        expect(container.textContent).toContain("Lender is not listed");
        expect(save().disabled).toBe(true);
    });

    it("keeps Save blocked until a result is picked, then sends the lender id and no type", async () => {
        fetchMock.mockResolvedValue({ ok: true, json: async () => found });
        await show();
        await typeInto("sika");

        expect(save().disabled).toBe(true);

        await act(async () => {
            container.querySelector("form")!.dispatchEvent(new Event("submit", { bubbles: true, cancelable: true }));
        });
        expect(posted).not.toHaveBeenCalled();

        await act(async () => options()[0].click());

        expect(save().disabled).toBe(false);

        await act(async () => {
            container.querySelector("form")!.dispatchEvent(new Event("submit", { bubbles: true, cancelable: true }));
        });

        expect(posted).toHaveBeenCalledTimes(1);
        expect(posted.mock.calls[0][0]).toMatchObject({ lender: "u-1" });
        expect(Object.keys(posted.mock.calls[0][0])).not.toContain("lender_type");
    });

    it("clears the chosen lender when the search text is edited", async () => {
        fetchMock.mockResolvedValue({ ok: true, json: async () => found });
        await show();
        await typeInto("sika");
        await act(async () => options()[0].click());

        expect(save().disabled).toBe(false);

        await typeInto("sikam", false);

        expect(save().disabled).toBe(true);
    });

    it("shows the picked lender again after a failed save, and Save stays ready", async () => {
        await show({ lender: "u-1", lender_name: "Sika Microfinance" });

        expect(input().value).toBe("Sika Microfinance");
        expect(save().disabled).toBe(false);
    });

    it("works by keyboard: Arrow Down into the list, up and down between results, Escape closes it", async () => {
        fetchMock.mockResolvedValue({ ok: true, json: async () => found });
        await show();
        await typeInto("sik");

        await key(input(), "ArrowDown");
        expect(document.activeElement).toBe(options()[0]);

        await key(options()[0], "ArrowDown");
        expect(document.activeElement).toBe(options()[1]);

        await key(options()[1], "ArrowUp");
        expect(document.activeElement).toBe(options()[0]);

        await key(options()[0], "ArrowUp");
        expect(document.activeElement).toBe(input());

        await key(input(), "ArrowDown");
        await key(options()[0], "Escape");
        expect(options()).toHaveLength(0);
        expect(document.activeElement).toBe(input());
    });

    it("gives every result a large tap target and is reachable as a real button", async () => {
        fetchMock.mockResolvedValue({ ok: true, json: async () => found });
        await show();
        await typeInto("sik");

        for (const option of options()) {
            expect(option.tagName).toBe("BUTTON");
            expect(option.getAttribute("type")).toBe("button");
            expect(option.style.minHeight).toBe("48px");
        }
    });
});
