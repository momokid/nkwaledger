// @vitest-environment jsdom
import { act, ComponentProps } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import "fake-indexeddb/auto";
import { withQueueLock } from "@/lib/queueLock";
import { enqueue, listPending, listStuck, recordFailedAttempt } from "@/lib/offlineStore";
import { buildHealthReport } from "@/lib/healthReport";
import { QueuedHealthReport } from "@/types/offlineQueue";
import Create from "./Create";

const h = vi.hoisted(() => ({
    storageFull: false,
    posts: [] as Array<{ body: Record<string, unknown>; options: { onSuccess: () => void; onError: (e: Record<string, string>) => void } }>,
}));

vi.mock("@/Layouts/AuthenticatedLayout", () => ({
    default: ({ children }: { children: unknown }) => children,
    useTheme: () => ({ dark: false }),
}));

vi.mock("@/lib/offlineStore", async (importOriginal) => {
    const real = await importOriginal<typeof import("@/lib/offlineStore")>();

    return {
        ...real,
        enqueue: (...args: Parameters<typeof real.enqueue>) =>
            h.storageFull ? Promise.reject(new DOMException("full", "QuotaExceededError")) : real.enqueue(...args),
    };
});

vi.mock("@inertiajs/react", async () => {
    const React = await import("react");

    return {
        usePage: () => ({ props: { auth: { user: { id: 7 } }, errors: {} } }),
        useForm: (initial: Record<string, unknown>) => {
            const [data, setState] = React.useState(initial);
            const transform = React.useRef((d: Record<string, unknown>) => d);

            return {
                data,
                errors: {},
                processing: false,
                setData: (key: string, value: unknown) => setState((prev) => ({ ...prev, [key]: value })),
                reset: (...fields: string[]) =>
                    setState((prev) => ({ ...prev, ...Object.fromEntries((fields.length ? fields : Object.keys(initial)).map((f) => [f, initial[f]])) })),
                transform: (fn: (d: Record<string, unknown>) => Record<string, unknown>) => {
                    transform.current = fn;
                },
                post: (_url: string, options: never) => h.posts.push({ body: transform.current(data), options }),
            };
        },
    };
});

const FARMER = "5b0f9f4e-0c3e-4a67-9a52-1e0f3f2a1111";
const props = { farmUnit: { id: 4, name: "Poultry house" }, farmer: { id: FARMER } };
const GENERIC = "Something went wrong. Please try again.";
const WORDING = "Not sent yet. Your record is saved on this phone.";

class FakeRecorder {
    mimeType = "audio/webm";
    ondataavailable: ((e: { data: Blob }) => void) | null = null;
    onstop: (() => void) | null = null;
    start() {}
    stop() {
        this.ondataavailable?.({ data: new Blob([new Uint8Array([1, 2])]) });
        this.onstop?.();
    }
}

let root: Root;
let container: HTMLElement;
let counter: number;

const online = (value: boolean) => Object.defineProperty(navigator, "onLine", { value, configurable: true });
const text = () => container.textContent ?? "";
const mount = () => act(async () => root.render(<Create {...(props as unknown as ComponentProps<typeof Create>)} />));

const describeIt = (value: string) =>
    act(async () => {
        const area = container.querySelector("textarea")!;
        Object.getOwnPropertyDescriptor(HTMLTextAreaElement.prototype, "value")!.set!.call(area, value);
        area.dispatchEvent(new Event("input", { bubbles: true }));
    });

const choosePhoto = (type = "image/png") =>
    act(async () => {
        const input = container.querySelector<HTMLInputElement>('input[type="file"]')!;
        Object.defineProperty(input, "files", { value: [new File([new Uint8Array([7, 7, 7])], "leaf.png", { type })], configurable: true });
        input.dispatchEvent(new Event("change", { bubbles: true }));
    });

const press = (label: string) =>
    act(async () => {
        Array.from(container.querySelectorAll("button")).find((b) => b.textContent?.includes(label))!.click();
    });

const recordVoiceNote = async () => {
    await press("Record a voice note");
    await press("Stop");
};

const tapSend = () =>
    act(async () => {
        container.querySelector("form")!.dispatchEvent(new Event("submit", { bubbles: true, cancelable: true }));
    });

const queued = async () => listPending<QueuedHealthReport>("7");

beforeEach(async () => {
    (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    indexedDB = new IDBFactory();
    h.posts.length = 0;
    h.storageFull = false;
    counter = 0;
    vi.spyOn(crypto, "randomUUID").mockImplementation(() => `00000000-0000-4000-8000-${String(++counter).padStart(12, "0")}`);
    vi.stubGlobal("MediaRecorder", FakeRecorder);
    Object.defineProperty(navigator, "mediaDevices", {
        value: { getUserMedia: async () => ({ getTracks: () => [] }) },
        configurable: true,
    });
    URL.createObjectURL = vi.fn(() => "blob:preview");
    URL.revokeObjectURL = vi.fn();
    online(false);
    container = document.createElement("div");
    document.body.appendChild(container);
    root = createRoot(container);
    await mount();
});

afterEach(async () => {
    await withQueueLock(async () => {});
    await act(async () => root.unmount());
    container.remove();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

describe("a report made with no internet", () => {
    it("is saved on the phone with its photo and voice note, under the form's uuid", async () => {
        await describeIt("Some birds look weak");
        await choosePhoto();
        await recordVoiceNote();
        await tapSend();

        await vi.waitFor(async () => expect(await queued()).toHaveLength(1));
        const [item] = await queued();

        expect(item.payload).toMatchObject({
            type: "health_report",
            uuid: "00000000-0000-4000-8000-000000000001",
            farmer: FARMER,
            farm_unit_id: 4,
            description: "Some birds look weak",
        });
        expect(item.payload.media.photo.type).toBe("image/png");
        expect(item.payload.media.audio?.type).toBe("audio/webm");
        expect(text()).toContain("Saved on your phone.");
    });

    it("is saved without a voice note when none was recorded", async () => {
        await describeIt("Some birds look weak");
        await choosePhoto();
        await tapSend();

        await vi.waitFor(async () => expect(await queued()).toHaveLength(1));
        expect((await queued())[0].payload.media.audio).toBeUndefined();
    });

    it("survives a page reload", async () => {
        await describeIt("Some birds look weak");
        await choosePhoto();
        await tapSend();
        await vi.waitFor(async () => expect(await queued()).toHaveLength(1));

        await act(async () => root.unmount());
        root = createRoot(container);
        await mount();

        expect(await queued()).toHaveLength(1);
    });

    it("is not saved, and says what is missing in the online wording", async () => {
        await tapSend();

        expect(text()).toContain("Please describe what you are seeing.");
        expect(text()).toContain("Please add one photo.");
        expect(await queued()).toEqual([]);
    });

    it("is not saved when the phone is full, and shows the generic error", async () => {
        h.storageFull = true;
        await describeIt("Some birds look weak");
        await choosePhoto();
        await tapSend();

        await vi.waitFor(() => expect(text()).toContain(GENERIC));
        expect(await queued()).toEqual([]);
        expect(text()).not.toContain("Saved on your phone.");
    });

    it("can be saved again after the phone had no room, under the same uuid", async () => {
        h.storageFull = true;
        await describeIt("Some birds look weak");
        await choosePhoto();
        await tapSend();
        await vi.waitFor(() => expect(text()).toContain(GENERIC));

        h.storageFull = false;
        await tapSend();

        await vi.waitFor(async () => expect(await queued()).toHaveLength(1));
        expect((await queued())[0].payload.uuid).toBe("00000000-0000-4000-8000-000000000001");
        expect(text()).not.toContain(GENERIC);
    });
});

describe("a report made online", () => {
    it("sends its uuid so a retry can be told from a new report", async () => {
        online(true);
        await describeIt("Some birds look weak");
        await choosePhoto();
        await tapSend();

        expect(h.posts[0].body.idempotency_key).toBe("00000000-0000-4000-8000-000000000001");
    });

    it("is queued under the key that was sent when the reply is lost", async () => {
        online(true);
        await describeIt("Some birds look weak");
        await choosePhoto();
        await tapSend();

        await act(async () => h.posts[0].options.onError({}));

        await vi.waitFor(async () => expect((await queued()).map((item) => item.payload.uuid)).toEqual([h.posts[0].body.idempotency_key]));
    });
});

describe("a report stuck on the phone", () => {
    const stuck = async (owner: string) => {
        const id = await enqueue(
            await buildHealthReport({
                farmer: FARMER,
                farmUnitId: 4,
                description: "Some birds look weak",
                photo: new File([new Uint8Array([7])], "leaf.png", { type: "image/png" }),
                audio: null,
                uuid: crypto.randomUUID(),
            }),
            owner,
        );

        for (let i = 0; i < 5; i++) {
            await recordFailedAttempt(id);
        }

        return id;
    };

    const refresh = () => act(async () => window.dispatchEvent(new Event("nkwa:offline-sync-ran")));

    it("shows the approved wording and is never deleted", async () => {
        await stuck("7");
        await refresh();

        await vi.waitFor(() => expect(text()).toContain(WORDING));
        expect(text()).toContain("Some birds look weak");
        expect(Array.from(container.querySelectorAll("button")).some((b) => b.textContent?.includes("Discard"))).toBe(false);
        expect(await listStuck("7")).toHaveLength(1);
    });

    it("is not shown to another user", async () => {
        await stuck("8");
        await refresh();

        expect(text()).not.toContain(WORDING);
    });
});
