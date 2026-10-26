// @vitest-environment jsdom
import { act } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

(globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;

const h = vi.hoisted(() => ({ roles: ["farmer"] }));

vi.mock("@inertiajs/react", () => ({
    usePage: () => ({ props: { auth: { user: { roles: h.roles } } } }),
}));

vi.mock("@/Layouts/AuthenticatedLayout", async () => {
    const React = await import("react");
    return {
        default: ({ children }: { children: unknown }) => React.createElement("div", null, children as never),
        useTheme: () => ({ dark: false }),
    };
});

import Index from "./Index";

describe("Help page tour button", () => {
    let root: Root;
    let host: HTMLDivElement;

    beforeEach(() => {
        host = document.createElement("div");
        document.body.appendChild(host);
        root = createRoot(host);
    });

    afterEach(() => {
        act(() => root.unmount());
        host.remove();
    });

    const tourButton = () => [...host.querySelectorAll("button")].find((b) => b.textContent === "Take the tour");

    it("shows Take the tour to a farmer and starts it", () => {
        h.roles = ["farmer"];
        act(() => root.render(<Index supportPhone="0577915555" />));
        expect(tourButton()).toBeDefined();
        act(() => tourButton()!.click());
        expect(host.textContent).toContain("This is your home.");
        expect(host.textContent).toContain("0577915555");
    });

    it("hides it from a role with no tour", () => {
        h.roles = ["vet"];
        act(() => root.render(<Index supportPhone={null} />));
        expect(tourButton()).toBeUndefined();
    });
});
