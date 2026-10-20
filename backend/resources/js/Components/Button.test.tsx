// @vitest-environment jsdom
import { act } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import Button from "./Button";

vi.mock("@/Layouts/AuthenticatedLayout", () => ({
    default: ({ children }: { children: unknown }) => children,
    useTheme: () => ({ dark: false }),
}));

let root: Root;
let container: HTMLElement;

const show = (props: Record<string, unknown> = {}) =>
    act(async () =>
        root.render(
            <Button type="submit" busyLabel="Saving..." {...props}>
                Save
            </Button>,
        ),
    );
const button = () => container.querySelector("button")!;

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

describe("The shared Button while it saves", () => {
    it("shows a spinner beside the busy label, and locks", async () => {
        await show({ busy: true });

        expect(button().querySelector("[data-spinner]")).not.toBeNull();
        expect(button().textContent).toBe("Saving...");
        expect(button().disabled).toBe(true);
        expect(button().getAttribute("aria-busy")).toBe("true");
    });

    it("hides the spinner from screen readers, which read the label", async () => {
        await show({ busy: true });

        expect(button().querySelector("[data-spinner]")!.getAttribute("aria-hidden")).toBe("true");
    });

    it("stands still for people who asked for less motion", async () => {
        await show({ busy: true });

        expect(button().querySelector("[data-spinner]")!.className).toContain("motion-reduce:animate-none");
    });

    it("shows no spinner and the normal label when it is not saving", async () => {
        await show();

        expect(button().querySelector("[data-spinner]")).toBeNull();
        expect(button().textContent).toBe("Save");
        expect(button().disabled).toBe(false);
        expect(button().getAttribute("aria-busy")).toBeNull();
    });
});
