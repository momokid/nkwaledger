// @vitest-environment jsdom
import { act } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { DATA_COST_TEXT } from "@/lib/dataCostText";
import { dataCostGateAllows, setDataCostAsker } from "@/lib/dataCostGate";
import DataCostDialog from "./DataCostDialog";

vi.mock("@/Layouts/AuthenticatedLayout", async () => {
    const { createContext, useContext } = await import("react");
    const ThemeContext = createContext({ dark: false, toggle: () => {}, textSize: "normal", setTextSize: () => {} });

    return { ThemeContext, useTheme: () => useContext(ThemeContext) };
});

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
    localStorage.clear();
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

describe("the dialog's theme", () => {
    const panel = () => container.querySelector<HTMLElement>('[role="dialog"] > div')!;
    const open = () =>
        act(async () => {
            void dataCostGateAllows();
        });
    // "Wait" holds until the farmer comes back to the app
    const comeBack = () => {
        Object.defineProperty(document, "visibilityState", { value: "visible", configurable: true });
        document.dispatchEvent(new Event("visibilitychange"));
    };

    it("is light when the app is set to light, or was never set", async () => {
        await open();
        expect(panel().style.background).toBe("rgb(255, 255, 255)");
        expect(container.querySelector("p")!.style.color).toBe("rgb(17, 24, 39)");

        await act(async () => button("Wait").click());
        comeBack();
        localStorage.setItem("nkwa_theme", "light");
        await open();
        expect(panel().style.background).toBe("rgb(255, 255, 255)");
    });

    it("is dark when the app is set to dark, with no layout around it", async () => {
        localStorage.setItem("nkwa_theme", "dark");

        await open();

        expect(panel().style.background).toBe("rgb(31, 41, 55)");
        expect(container.querySelector("p")!.style.color).toBe("rgb(249, 250, 251)");
        expect(container.textContent).toBe(`${DATA_COST_TEXT.warning}${DATA_COST_TEXT.send}${DATA_COST_TEXT.wait}`);
    });

    it("follows a change of theme made while the app stays open", async () => {
        await open();
        await act(async () => button("Wait").click());
        comeBack();

        localStorage.setItem("nkwa_theme", "dark");
        await open();

        expect(panel().style.background).toBe("rgb(31, 41, 55)");
    });
});
