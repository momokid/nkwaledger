// @vitest-environment jsdom
import { act, ComponentProps } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import Index from "./Index";

vi.mock("@/Layouts/AdminLayout", () => ({ default: ({ children }: { children: unknown }) => children }));
vi.mock("@/Layouts/AuthenticatedLayout", () => ({ useTheme: () => ({ dark: false }) }));

const LABEL = "Health reports waiting for a photo";

let root: Root;
let container: HTMLElement;

const show = (waitingCount: number, reports: unknown[] = []) =>
    act(async () => root.render(<Index {...({ reports, waitingCount } as unknown as ComponentProps<typeof Index>)} />));

beforeEach(() => {
    (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    container = document.createElement("div");
    document.body.appendChild(container);
    root = createRoot(container);
});

afterEach(async () => {
    await act(async () => root.unmount());
    container.remove();
});

describe("the waiting-for-a-photo count on the admin health report queue", () => {
    it("shows the label with the count", async () => {
        await show(3);

        expect(container.textContent).toContain(LABEL);
        expect(container.querySelector('[data-testid="waiting-count"]')?.textContent).toBe("3");
    });

    it("shows the label with 0 too", async () => {
        await show(0);

        expect(container.textContent).toContain(LABEL);
        expect(container.querySelector('[data-testid="waiting-count"]')?.textContent).toBe("0");
    });

    it("shows it beside a queue that has reports in it", async () => {
        await show(2, [{ uuid: "u", farm_unit_name: "Poultry", farmer_name: "Ama Mensah", category: "Livestock", routed_role: "vet", created_at: "2026-03-01" }]);

        expect(container.textContent).toContain(LABEL);
        expect(container.textContent).toContain("Ama Mensah");
    });
});
