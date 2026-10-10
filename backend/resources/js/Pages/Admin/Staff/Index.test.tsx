// @vitest-environment jsdom
import { act, ComponentProps } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import Index from "./Index";

const h = vi.hoisted(() => ({
    router: { visit: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn(), reload: vi.fn() },
    post: vi.fn(),
}));

vi.mock("@/Layouts/AdminLayout", () => ({ default: ({ children }: { children: unknown }) => children }));
vi.mock("@/Layouts/AuthenticatedLayout", () => ({ useTheme: () => ({ dark: false }) }));
vi.mock("@inertiajs/react", () => ({
    router: h.router,
    usePage: () => ({ props: { auth: { user: { id: 1 } } } }),
    useForm: (initial: Record<string, unknown>) => ({
        data: initial,
        errors: {},
        processing: false,
        setData: () => {},
        setError: () => {},
        clearErrors: () => {},
        reset: () => {},
        post: () => {},
    }),
}));

const member = (id: number, uuid: string, first: string, over: Record<string, unknown> = {}) => ({
    id,
    uuid,
    surname: "Owusu",
    first_name: first,
    other_name: null,
    phone: `024400060${id}`,
    email: null,
    role: "agent",
    is_active: true,
    is_activated: true,
    invited_at: "2026-03-01",
    ...over,
});

const props = (permissions: Record<string, boolean>, rows = [member(1, "uuid-self", "Self"), member(2, "uuid-kofi", "Kofi"), member(3, "uuid-ama", "Ama")]) => ({
    staff: { data: rows, links: [] },
    roles: ["agent"],
    permissions: { create: true, update: true, delete: true, force_logout: true, ...permissions },
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
    Object.values(h.router).forEach((fn) => fn.mockReset());
    h.post.mockReset();
    container = document.createElement("div");
    document.body.appendChild(container);
    root = createRoot(container);
});

afterEach(async () => {
    await act(async () => root.unmount());
    container.remove();
});

describe("Force logout on the staff list", () => {
    it("shows for an activated member who is not the signed-in admin", async () => {
        await render(props({}));

        expect(rowOf("Kofi")!.textContent).toContain("Force logout");
        expect(rowOf("Ama")!.textContent).toContain("Force logout");
        expect(rowOf("Self")!.textContent).not.toContain("Force logout");
    });

    it("does not show without the permission, nor for someone who has not activated", async () => {
        await render(props({ force_logout: false }));
        expect(buttons("Force logout")).toHaveLength(0);

        await render(props({}, [member(2, "uuid-kofi", "Kofi", { is_activated: false })]));
        expect(buttons("Force logout")).toHaveLength(0);
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

    it("signs the user out with one background request, shows the row as loading meanwhile, then says so in the row", async () => {
        let finish!: () => void;
        h.post.mockReturnValue(new Promise<void>((resolve) => (finish = resolve)));
        await render(props({}));

        await act(async () => buttons("Force logout")[0].click());
        await act(async () => buttons("Sign out")[0].click());

        expect(h.post).toHaveBeenCalledTimes(1);
        expect(h.post.mock.calls[0][0]).toContain("uuid-kofi");
        expect(text()).not.toContain(CONFIRM);
        const loadingRow = container.querySelectorAll("tbody tr")[1];
        expect(loadingRow.textContent).toBe("");
        expect(loadingRow.querySelectorAll("div").length).toBeGreaterThan(0);
        expect(text()).toContain("Ama");

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
        Object.values(h.router).forEach((fn) => expect(fn).not.toHaveBeenCalled());
    });

    it("puts the row back and shows what the server said when it is refused", async () => {
        h.post.mockRejectedValue({ response: { data: { message: "Too Many Attempts." } } });
        await render(props({}));

        await act(async () => buttons("Force logout")[0].click());
        await act(async () => buttons("Sign out")[0].click());

        expect(rowOf("Kofi")!.textContent).toContain("Too Many Attempts.");
        expect(rowOf("Kofi")!.textContent).toContain("Force logout");
        expect(text()).not.toContain("User signed out.");
    });
});
