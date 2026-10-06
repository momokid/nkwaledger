// @vitest-environment jsdom
import { act, ComponentProps } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import "fake-indexeddb/auto";
import { listPending } from "@/lib/offlineStore";
import { QueuedBatchRecord } from "@/types/offlineQueue";
import Create from "./Create";

const h = vi.hoisted(() => ({
    posts: [] as Array<{ body: Record<string, string>; options: { onSuccess: () => void; onError: (e: Record<string, string>) => void } }>,
}));

vi.mock("@/Layouts/AuthenticatedLayout", () => ({
    default: ({ children }: { children: unknown }) => children,
    useTheme: () => ({ dark: false }),
}));

vi.mock("@inertiajs/react", async () => {
    const React = await import("react");

    return {
        usePage: () => ({ props: pageProps() }),
        useForm: (initial: Record<string, string>) => {
            const [data, setState] = React.useState(initial);
            const transform = React.useRef((d: Record<string, string>) => d);

            return {
                data,
                errors: {},
                processing: false,
                setData: (key: string | Record<string, string>, value?: string) =>
                    setState((prev) => (typeof key === "string" ? { ...prev, [key]: value as string } : { ...prev, ...key })),
                reset: (...fields: string[]) =>
                    setState((prev) => ({ ...prev, ...Object.fromEntries(fields.map((f) => [f, initial[f]])) })),
                transform: (fn: (d: Record<string, string>) => Record<string, string>) => {
                    transform.current = fn;
                },
                post: (_url: string, options: never) => h.posts.push({ body: transform.current(data), options }),
            };
        },
    };
});

const FARMER = "5b0f9f4e-0c3e-4a67-9a52-1e0f3f2a1111";

function pageProps() {
    return {
        auth: { user: { id: 7 } },
        errors: {},
        flash: {},
        old: {
            transaction_template_id: "1",
            amount: "100",
            settlement_account_id: "9",
            transaction_date: "2026-03-01",
        },
        farmer: { id: FARMER, name: "Ama Mensah" },
        templates: [
            {
                id: 1,
                name: "Sold",
                transaction_type: "INCOME",
                settlement_side: "debit",
                requires_farm_unit: false,
                is_produce_sale: false,
                is_stock_purchase: false,
                allows_credit: false,
            },
        ],
        settlementAccounts: [{ id: 9, name: "Cash" }],
        farmUnits: [],
        layout: "farmer" as const,
        basePath: "/my-records",
    };
}

let root: Root;
let container: HTMLElement;
let counter: number;

const online = (value: boolean) => Object.defineProperty(navigator, "onLine", { value, configurable: true });

const tapSave = () =>
    act(async () => {
        container.querySelector("form")!.dispatchEvent(new Event("submit", { bubbles: true, cancelable: true }));
    });

const queued = async () => (await listPending<QueuedBatchRecord>("7")).map((item) => item.payload.uuid);

beforeEach(async () => {
    (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    indexedDB = new IDBFactory();
    h.posts.length = 0;
    counter = 0;
    vi.spyOn(crypto, "randomUUID").mockImplementation(() => `00000000-0000-4000-8000-${String(++counter).padStart(12, "0")}`);
    online(true);
    container = document.createElement("div");
    document.body.appendChild(container);
    root = createRoot(container);
    await act(async () => root.render(<Create {...(pageProps() as unknown as ComponentProps<typeof Create>)} />));
});

afterEach(async () => {
    await act(async () => root.unmount());
    container.remove();
    vi.restoreAllMocks();
});

describe("one record keeps one uuid", () => {
    it("queues a lost-reply post under the key that was sent", async () => {
        await tapSave();
        const sent = h.posts[0].body.idempotency_key;

        await act(async () => h.posts[0].options.onError({}));

        await vi.waitFor(async () => expect(await queued()).toEqual([sent]));
    });

    it("queues a record saved offline from the start under the form's uuid", async () => {
        online(false);

        await tapSave();

        await vi.waitFor(async () => expect(await queued()).toEqual(["00000000-0000-4000-8000-000000000001"]));
    });

    it("gives the next record a new uuid after a successful save", async () => {
        await tapSave();
        await act(async () => h.posts[0].options.onSuccess());
        await tapSave();

        expect(h.posts[1].body.idempotency_key).not.toBe(h.posts[0].body.idempotency_key);
    });

    it("gives the next submit a new uuid after a 422 refusal", async () => {
        await tapSave();
        await act(async () => h.posts[0].options.onError({ amount: "refused" }));
        await tapSave();

        expect(h.posts[1].body.idempotency_key).not.toBe(h.posts[0].body.idempotency_key);
    });

    it("keeps the same uuid when the same record is sent again after a network error", async () => {
        await tapSave();
        act(() => {
            h.posts[0].options.onError({});
        });
        await tapSave();

        expect(h.posts[1].body.idempotency_key).toBe(h.posts[0].body.idempotency_key);
    });

    it("makes one uuid for a double tap online", async () => {
        await act(async () => {
            const form = container.querySelector("form")!;
            form.dispatchEvent(new Event("submit", { bubbles: true, cancelable: true }));
            form.dispatchEvent(new Event("submit", { bubbles: true, cancelable: true }));
        });

        expect(h.posts).toHaveLength(2);
        expect(h.posts[1].body.idempotency_key).toBe(h.posts[0].body.idempotency_key);
    });

    it("makes one uuid for a double tap offline", async () => {
        online(false);

        await act(async () => {
            const form = container.querySelector("form")!;
            form.dispatchEvent(new Event("submit", { bubbles: true, cancelable: true }));
            form.dispatchEvent(new Event("submit", { bubbles: true, cancelable: true }));
        });

        await vi.waitFor(async () => {
            const uuids = await queued();
            expect(uuids).toHaveLength(2);
            expect(new Set(uuids).size).toBe(1);
        });
    });
});
