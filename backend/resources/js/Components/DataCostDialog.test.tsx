// @vitest-environment jsdom
import { act } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { DATA_COST_TEXT } from "@/lib/dataCostText";
import { dataCostGateAllows, setDataCostAsker } from "@/lib/dataCostGate";
import DataCostDialog from "./DataCostDialog";

vi.mock("@/Layouts/AuthenticatedLayout", () => ({ useTheme: () => ({ dark: false }) }));

let root: Root;
let container: HTMLElement;

beforeEach(async () => {
    (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    container = document.createElement("div");
    document.body.appendChild(container);
    root = createRoot(container);
    await act(async () => root.render(<DataCostDialog />));
});

afterEach(async () => {
    await act(async () => root.unmount());
    container.remove();
    setDataCostAsker(null);
});

const button = (label: string) => Array.from(container.querySelectorAll("button")).find((b) => b.textContent === label)!;

describe("the data-cost dialog", () => {
    it("shows nothing until a report is about to upload", () => {
        expect(container.querySelector('[role="dialog"]')).toBeNull();
    });

    it("asks with the approved words and nothing else, with two buttons", async () => {
        let answer!: Promise<boolean>;
        await act(async () => {
            answer = dataCostGateAllows();
        });

        expect(container.querySelector('[role="dialog"]')).not.toBeNull();
        expect(container.textContent).toBe(`${DATA_COST_TEXT.warning}${DATA_COST_TEXT.send}${DATA_COST_TEXT.wait}`);
        expect(Array.from(container.querySelectorAll("button")).map((b) => b.textContent)).toEqual(["Send now", "Wait"]);

        await act(async () => button("Wait").click());
        expect(await answer).toBe(false);
    });

    it("lets the upload start on Send now, and closes", async () => {
        let answer!: Promise<boolean>;
        await act(async () => {
            answer = dataCostGateAllows();
        });

        await act(async () => button("Send now").click());

        expect(await answer).toBe(true);
        expect(container.querySelector('[role="dialog"]')).toBeNull();
    });

    it("counts as Wait, without the Wait rule, when the farmer leaves the page it stood on", async () => {
        let answer!: Promise<boolean>;
        await act(async () => {
            answer = dataCostGateAllows();
        });

        await act(async () => root.unmount());

        expect(await answer).toBe(false);

        // the next page asks again straight away
        root = createRoot(container);
        await act(async () => root.render(<DataCostDialog />));
        await act(async () => {
            void dataCostGateAllows();
        });
        expect(container.querySelector('[role="dialog"]')).not.toBeNull();
    });
});
