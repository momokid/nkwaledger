// @vitest-environment jsdom
import { act } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import Tour from "./Tour";
import { tourFor } from "@/lib/tourSteps";

(globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;

describe("tourFor", () => {
    it("gives the farmer six steps and the agent five", () => {
        expect(tourFor("farmer")).toHaveLength(6);
        expect(tourFor("agent")).toHaveLength(5);
    });

    it("has no tour for a role without one", () => {
        expect(tourFor("vet")).toEqual([]);
    });

    it("never mentions the Marketplace", () => {
        const all = [...tourFor("farmer"), ...tourFor("agent")];
        expect(JSON.stringify(all)).not.toContain("Marketplace");
    });
});

describe("Tour", () => {
    let root: Root;
    let host: HTMLDivElement;
    const steps = tourFor("farmer");

    beforeEach(() => {
        host = document.createElement("div");
        document.body.appendChild(host);
        root = createRoot(host);
    });

    afterEach(() => {
        act(() => root.unmount());
        host.remove();
    });

    const button = (label: string) =>
        [...host.querySelectorAll("button")].find((b) => b.textContent === label);

    it("walks forward and back, and finishes with Done", () => {
        const onClose = vi.fn();
        act(() => root.render(<Tour steps={steps} onClose={onClose} />));

        expect(host.textContent).toContain(steps[0].text);
        expect(button("Back")).toBeUndefined();

        act(() => button("Next")!.click());
        expect(host.textContent).toContain(steps[1].text);

        act(() => button("Back")!.click());
        expect(host.textContent).toContain(steps[0].text);

        for (let i = 0; i < steps.length - 1; i++) act(() => button("Next")!.click());
        expect(button("Next")).toBeUndefined();
        act(() => button("Done")!.click());
        expect(onClose).toHaveBeenCalledOnce();
    });

    it("can be skipped", () => {
        const onClose = vi.fn();
        act(() => root.render(<Tour steps={steps} onClose={onClose} />));
        act(() => button("Skip tour")!.click());
        expect(onClose).toHaveBeenCalledOnce();
    });

    it("outlines the menu item it is talking about", () => {
        const item = document.createElement("div");
        item.setAttribute("data-tour", steps[0].target);
        document.body.appendChild(item);
        act(() => root.render(<Tour steps={steps} onClose={() => {}} />));
        expect(item.style.outline).not.toBe("");
        act(() => root.unmount());
        expect(item.style.outline).toBe("");
        item.remove();
        root = createRoot(host);
    });
});
