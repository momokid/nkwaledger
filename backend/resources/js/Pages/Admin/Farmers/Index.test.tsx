// @vitest-environment jsdom
import { act, ComponentProps } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import Index from "./Index";

const h = vi.hoisted(() => ({
    router: { visit: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn(), reload: vi.fn(), on: vi.fn(() => () => {}) },
    post: vi.fn(),
}));

vi.mock("@/Layouts/AdminLayout", () => ({ default: ({ children }: { children: unknown }) => children }));
vi.mock("@/Layouts/AuthenticatedLayout", () => ({
    default: ({ children }: { children: unknown }) => children,
    useTheme: () => ({ dark: false }),
}));
vi.mock("@inertiajs/react", () => ({
    router: h.router,
    usePage: () => ({ props: { errors: {}, auth: { user: { id: 1 } } } }),
    useForm: (initial: Record<string, unknown>) => ({
        data: initial,
        errors: {},
        processing: false,
        setData: () => {},
        setError: () => {},
        clearErrors: () => {},
        reset: () => {},
        post: () => {},
        transform: () => {},
    }),
}));

const farmer = (uuid: string, name: string, over: Record<string, unknown> = {}) => ({
    id: uuid,
    name,
    phone: "0244000701",
    phone_verified: true,
    has_login: true,
    locked: false,
    community: "Kuapa",
    agent: null,
    identity_verified: false,
    is_active: true,
    ...over,
});

const props = (permissions: Record<string, boolean>, rows = [farmer("uuid-kofi", "Mensah Kofi"), farmer("uuid-ama", "Owusu Ama")]) => ({
    farmers: { data: rows, links: [] },
    pending: [],
    communities: [],
    farmerGroups: [],
    farmTypes: [],
    agents: [],
    layout: "admin" as const,
    basePath: "/admin/farmers",
    permissions: { create: false, update: true, verify: false, assign: true, force_logout: true, lock: true, unlock: true, ...permissions },
});

let root: Root;
let container: HTMLElement;

const text = () => container.textContent ?? "";
const buttons = (label: string) => Array.from(container.querySelectorAll("button")).filter((b) => b.textContent === label);
const rowOf = (name: string) => Array.from(container.querySelectorAll("tbody tr")).find((tr) => tr.textContent?.includes(name));
const render = (p: ReturnType<typeof props>) => act(async () => root.render(<Index {...(p as unknown as ComponentProps<typeof Index>)} />));

const CONFIRM = "Sign this user out of all devices now?";

beforeEach(() => {
    (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    (globalThis as { route?: unknown }).route = (name: string, param?: string) => `/${name}/${param ?? ""}`;
    (window as unknown as { axios: unknown }).axios = { post: h.post };
    Object.values(h.router).forEach((fn) => fn.mockClear());
    h.post.mockReset();
    container = document.createElement("div");
    document.body.appendChild(container);
    root = createRoot(container);
});

afterEach(async () => {
    await act(async () => root.unmount());
    container.remove();
});

describe("Force logout on the farmer list", () => {
    it("shows for a farmer who has a login, to someone who may use it", async () => {
        await render(props({}));

        expect(rowOf("Kofi")!.textContent).toContain("Force logout");
        expect(rowOf("Ama")!.textContent).toContain("Force logout");
    });

    it("does not show without the permission, nor for a farmer with no login", async () => {
        await render(props({ force_logout: false }));
        expect(buttons("Force logout")).toHaveLength(0);

        await render(props({}, [farmer("uuid-kofi", "Mensah Kofi", { has_login: false })]));
        expect(buttons("Force logout")).toHaveLength(0);
    });

    it("shows even where the person cannot edit farmers, and keeps Open for those who can", async () => {
        await render(props({ update: false }));
        expect(buttons("Force logout")).toHaveLength(2);
        expect(buttons("Open")).toHaveLength(0);

        await render(props({}));
        expect(buttons("Open")).toHaveLength(2);
    });

    it("asks first, in the approved words, and Cancel does nothing", async () => {
        await render(props({}));

        await act(async () => buttons("Force logout")[0].click());

        expect(text()).toContain(CONFIRM);
        expect(buttons("Sign out")).toHaveLength(1);

        await act(async () => buttons("Cancel")[0].click());

        expect(text()).not.toContain(CONFIRM);
        expect(h.post).not.toHaveBeenCalled();
    });

    it("signs the farmer out with one background request, shows the row as loading meanwhile, then says so in the row", async () => {
        let finish!: () => void;
        h.post.mockReturnValue(new Promise<void>((resolve) => (finish = resolve)));
        await render(props({}));

        await act(async () => buttons("Force logout")[0].click());
        await act(async () => buttons("Sign out")[0].click());

        expect(h.post).toHaveBeenCalledTimes(1);
        expect(h.post.mock.calls[0][0]).toContain("uuid-kofi");
        expect(text()).not.toContain(CONFIRM);
        const loadingRow = container.querySelectorAll("tbody tr")[0];
        expect(loadingRow.textContent).toBe("");
        expect(loadingRow.querySelectorAll("div").length).toBeGreaterThan(0);
        expect(text()).toContain("Owusu Ama");

        await act(async () => finish());

        expect(rowOf("Kofi")!.textContent).toContain("User signed out.");
        expect(rowOf("Ama")!.textContent).not.toContain("User signed out.");
        expect(rowOf("Kofi")!.textContent).not.toContain("Force logout");
    });

    it("never reloads the page or visits another one", async () => {
        h.post.mockResolvedValue({});
        await render(props({}));

        await act(async () => buttons("Force logout")[0].click());
        await act(async () => buttons("Sign out")[0].click());

        expect(rowOf("Kofi")!.textContent).toContain("User signed out.");
        ["visit", "post", "patch", "delete", "reload"].forEach((method) => expect(h.router[method as keyof typeof h.router]).not.toHaveBeenCalled());
    });

    it("puts the row back and shows what the server said when it is refused", async () => {
        h.post.mockRejectedValue({ response: { data: { message: "Conflict" } } });
        await render(props({}));

        await act(async () => buttons("Force logout")[0].click());
        await act(async () => buttons("Sign out")[0].click());

        expect(rowOf("Kofi")!.textContent).toContain("Conflict");
        expect(rowOf("Kofi")!.textContent).toContain("Force logout");
        expect(text()).not.toContain("User signed out.");
    });
});

const LOCK_CONFIRM = "Lock this account? The farmer will be signed out and cannot sign in until you unlock it.";
const lockedRows = () => [farmer("uuid-kofi", "Mensah Kofi", { locked: true }), farmer("uuid-ama", "Owusu Ama")];

describe("Lock and unlock on the farmer list", () => {
    it("shows a Locked badge to anyone who sees the list, and the buttons only with the permissions", async () => {
        await render(props({ lock: false, unlock: false, force_logout: false, update: false }, lockedRows()));

        expect(rowOf("Kofi")!.textContent).toContain("Locked");
        expect(rowOf("Ama")!.textContent).not.toContain("Locked");
        expect(buttons("Lock account")).toHaveLength(0);
        expect(buttons("Unlock account")).toHaveLength(0);
    });

    it("offers Lock to an unlocked farmer and Unlock to a locked one, and no Force logout for a locked one", async () => {
        await render(props({}, lockedRows()));

        expect(rowOf("Ama")!.textContent).toContain("Lock account");
        expect(rowOf("Kofi")!.textContent).toContain("Unlock account");
        expect(rowOf("Kofi")!.textContent).not.toContain("Force logout");
    });

    it("hides each button when only the other permission is held, and for a farmer with no login", async () => {
        await render(props({ lock: false }, lockedRows()));
        expect(buttons("Lock account")).toHaveLength(0);
        expect(buttons("Unlock account")).toHaveLength(1);

        await render(props({ unlock: false }, lockedRows()));
        expect(buttons("Lock account")).toHaveLength(1);
        expect(buttons("Unlock account")).toHaveLength(0);

        await render(props({}, [farmer("uuid-kofi", "Mensah Kofi", { has_login: false })]));
        expect(buttons("Lock account")).toHaveLength(0);
    });

    it("asks first in the approved words, and Cancel does nothing", async () => {
        await render(props({}));

        await act(async () => buttons("Lock account")[0].click());
        expect(text()).toContain(LOCK_CONFIRM);
        expect(buttons("Lock")).toHaveLength(1);

        await act(async () => buttons("Cancel")[0].click());
        expect(text()).not.toContain(LOCK_CONFIRM);
        expect(h.post).not.toHaveBeenCalled();
    });

    it("locks with one background request, shows the row as loading, then updates the same row in place", async () => {
        let finish!: () => void;
        h.post.mockReturnValue(new Promise<void>((resolve) => (finish = resolve)));
        await render(props({}));

        await act(async () => buttons("Lock account")[0].click());
        await act(async () => buttons("Lock")[0].click());

        expect(h.post).toHaveBeenCalledTimes(1);
        expect(h.post.mock.calls[0][0]).toContain("admin.farmers.lock");
        expect(h.post.mock.calls[0][0]).toContain("uuid-kofi");
        expect(container.querySelectorAll("tbody tr")[0].textContent).toBe("");
        expect(text()).toContain("Owusu Ama");

        await act(async () => finish());

        const row = rowOf("Kofi")!.textContent!;
        expect(row).toContain("Account locked.");
        expect(row).toContain("Locked");
        expect(row).toContain("Unlock account");
        expect(row).not.toContain("Lock account");
        expect(rowOf("Ama")!.textContent).not.toContain("Locked");
        ["visit", "post", "patch", "delete", "reload"].forEach((method) => expect(h.router[method as keyof typeof h.router]).not.toHaveBeenCalled());
    });

    it("unlocks in the approved words and updates the row in place", async () => {
        h.post.mockResolvedValue({});
        await render(props({}, lockedRows()));

        await act(async () => buttons("Unlock account")[0].click());
        expect(text()).toContain("Unlock this account?");
        await act(async () => buttons("Unlock")[0].click());

        expect(h.post.mock.calls[0][0]).toContain("admin.farmers.unlock");
        const row = rowOf("Kofi")!.textContent!;
        expect(row).toContain("Account unlocked.");
        expect(row).not.toContain("Locked");
        expect(row).toContain("Lock account");
    });

    it("puts the row back and shows what the server said when it is refused", async () => {
        h.post.mockRejectedValue({ response: { data: { message: "Conflict" } } });
        await render(props({}));

        await act(async () => buttons("Lock account")[0].click());
        await act(async () => buttons("Lock")[0].click());

        const row = rowOf("Kofi")!.textContent!;
        expect(row).toContain("Conflict");
        expect(row).toContain("Lock account");
        expect(row).not.toContain("Account locked.");
    });
});
