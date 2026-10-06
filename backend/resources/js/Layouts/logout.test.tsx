// @vitest-environment jsdom
import { act } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import "fake-indexeddb/auto";
import {
    deleteDeviceKey,
    enqueue,
    getOrCreateDeviceKey,
    listPending,
    markNeedsAttention,
    queueCounts,
    recordFailedAttempt,
} from "@/lib/offlineStore";
import AdminLayout from "./AdminLayout";
import AuthenticatedLayout from "./AuthenticatedLayout";

const h = vi.hoisted(() => ({
    post: vi.fn(),
    authExpired: false,
}));

vi.mock("@inertiajs/react", async () => {
    const React = await import("react");

    return {
        Head: () => null,
        Link: ({ children, href }: { children: unknown; href: string }) => React.createElement("a", { href }, children as never),
        router: { post: h.post, visit: () => {} },
        usePage: () => ({
            url: "/",
            props: {
                auth: {
                    user: { id: 7, first_name: "Ama", surname: "Mensah", roles: ["farmer"] },
                    nav: { main: [], tools: [] },
                    adminMenu: [],
                },
                features: { marketplace: false },
                flash: {},
                errors: {},
            },
        }),
    };
});

vi.mock("@/hooks/useOfflineSync", () => ({ default: () => h.authExpired }));
vi.mock("@/hooks/useIsVerified", () => ({ default: () => true }));
vi.mock("@/Components/NotificationBell", () => ({ default: () => null }));
vi.mock("@/Components/ConnectivityIndicator", () => ({ default: () => null }));
vi.mock("@/Components/FlashMessages", () => ({ default: () => null }));
vi.mock("@/Components/OfflineNavigationNotice", () => ({ default: () => null }));
vi.mock("@/Components/VerificationGate", () => ({ default: ({ children }: { children: unknown }) => children }));

const MESSAGE =
    "You have records not sent yet. Connect to the internet and wait for them to send before you sign out.";
const FARMER = "5b0f9f4e-0c3e-4a67-9a52-1e0f3f2a1111";

const record = () => ({
    shape: 2 as const,
    uuid: crypto.randomUUID(),
    template: 1,
    farmer: FARMER,
    amount: "100",
    event_date: "2026-03-01",
    device_created_at: "2026-03-01T08:00:00.000Z",
});

async function keyIsStored(): Promise<boolean> {
    const db = await new Promise<IDBDatabase>((resolve, reject) => {
        const request = indexedDB.open("nkwa-offline-store");
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
    const found = await new Promise<unknown>((resolve, reject) => {
        const request = db.transaction("device-key", "readonly").objectStore("device-key").get("device-key");
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
    db.close();

    return found !== undefined;
}

const layouts = [
    ["AuthenticatedLayout", AuthenticatedLayout],
    ["AdminLayout", AdminLayout],
] as const;

let root: Root;
let container: HTMLElement;

beforeEach(async () => {
    (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    (globalThis as { route?: unknown }).route = Object.assign((name: string) => `/${name}`, { current: () => false });
    h.post.mockReset();
    h.post.mockImplementation((_url: string, _data: unknown, options?: { onSuccess?: () => void }) => options?.onSuccess?.());
    h.authExpired = false;

    indexedDB = new IDBFactory();
    await deleteDeviceKey();
    await getOrCreateDeviceKey();

    container = document.createElement("div");
    document.body.appendChild(container);
    root = createRoot(container);
});

afterEach(async () => {
    await act(async () => root.unmount());
    container.remove();
});

async function signOutFrom(Layout: typeof AuthenticatedLayout | typeof AdminLayout) {
    await act(async () => root.render(<Layout title="Test">content</Layout>));

    const button = Array.from(container.querySelectorAll("button")).find((b) => b.textContent?.includes("Sign out"));
    await act(async () => button!.click());
}

describe.each(layouts)("signing out of %s", (_name, Layout) => {
    const blocked = async () => {
        await vi.waitFor(() => expect(container.textContent).toContain(MESSAGE));
        expect(h.post).not.toHaveBeenCalled();
        expect(await keyIsStored()).toBe(true);
    };

    it("is blocked by the user's own pending item", async () => {
        await enqueue(record(), "7");

        await signOutFrom(Layout);

        await blocked();
    });

    const stick = async (owner: string) => {
        const id = await enqueue(record(), owner);
        for (let i = 0; i < 5; i++) {
            await recordFailedAttempt(id);
        }
    };

    it("signs out when the user's only items are stuck, keeping the key and the item", async () => {
        await stick("7");

        await signOutFrom(Layout);

        await vi.waitFor(() => expect(h.post).toHaveBeenCalled());
        expect(await keyIsStored()).toBe(true);
        expect(await queueCounts("7")).toEqual({ own: 0, total: 1 });
        expect(container.textContent).not.toContain(MESSAGE);
    });

    it("is blocked when the user has a stuck item and a pending one", async () => {
        await stick("7");
        await enqueue(record(), "7");

        await signOutFrom(Layout);

        await blocked();
    });

    it("is blocked by the user's own needs-attention item", async () => {
        await markNeedsAttention(await enqueue({ url: "/my-records", data: { amount: "5" } }, "7"), "fix it");

        await signOutFrom(Layout);

        await blocked();
    });

    it("signs out but keeps the key when only another user's item is left", async () => {
        await enqueue(record(), "8");

        await signOutFrom(Layout);

        await vi.waitFor(() => expect(h.post).toHaveBeenCalled());
        expect(await keyIsStored()).toBe(true);
        expect(await listPending("8")).toHaveLength(1);
        expect(container.textContent).not.toContain(MESSAGE);
    });

    it("signs out and deletes the key when the queue is empty", async () => {
        await signOutFrom(Layout);

        await vi.waitFor(() => expect(h.post).toHaveBeenCalled());
        await vi.waitFor(async () => expect(await keyIsStored()).toBe(false));
    });

    it("clears the PIN unlock flag when it signs out", async () => {
        sessionStorage.setItem("nkwa_pin_unlocked", "7");

        await signOutFrom(Layout);

        await vi.waitFor(() => expect(h.post).toHaveBeenCalled());
        expect(sessionStorage.getItem("nkwa_pin_unlocked")).toBeNull();
    });

    it("keeps the PIN unlock flag when sign-out is blocked", async () => {
        sessionStorage.setItem("nkwa_pin_unlocked", "7");
        await enqueue(record(), "7");

        await signOutFrom(Layout);

        await blocked();
        expect(sessionStorage.getItem("nkwa_pin_unlocked")).toBe("7");
    });

    it("never deletes the key when the session ends by itself", async () => {
        h.authExpired = true;
        await enqueue(record(), "7");

        await act(async () => root.render(<Layout title="Test">content</Layout>));

        expect(container.textContent).toContain("Your session has ended");
        expect(h.post).not.toHaveBeenCalled();
        expect(await keyIsStored()).toBe(true);
        expect(await listPending("7")).toHaveLength(1);
    });
});
