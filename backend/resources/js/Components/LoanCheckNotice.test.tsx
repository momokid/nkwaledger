// @vitest-environment jsdom
import { act } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import LoanCheckNotice, { LoanCheck } from "./LoanCheckNotice";

vi.mock("@/Layouts/AuthenticatedLayout", () => ({
    default: ({ children }: { children: unknown }) => children,
    useTheme: () => ({ dark: false }),
}));

let root: Root;
let container: HTMLElement;

const show = async (check: LoanCheck | null | undefined, whenNone?: "notice" | "nothing") =>
    act(async () => root.render(<LoanCheckNotice check={check} whenNone={whenNone} />));

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

describe("The check with the lender, as the farmer, the agent and the admin read it", () => {
    it("shows the self-reported notice when nobody has checked the loan", async () => {
        await show(null);

        expect(container.textContent).toBe("You entered this loan yourself. It has not been checked with the lender.");
    });

    it("shows nothing at all in a staff list when there is no check", async () => {
        await show(undefined, "nothing");

        expect(container.textContent).toBe("");
    });

    it("says Confirmed with the lender on the date, and shows the note under it", async () => {
        await show({ status: "confirmed", note: "Spoke to the manager", date: "2026-10-16" });

        expect(container.textContent).toContain("Confirmed with the lender on");
        expect(container.textContent).toContain("2026");
        expect(container.textContent).toContain("Spoke to the manager");
        expect(container.textContent).not.toContain("not been checked");
    });

    it.each([
        ["not_confirmed", "Not Confirmed"],
        ["does_not_match", "The lender says this does not match"],
        ["unreachable", "Lender could not be reached"],
    ] as const)("says the approved words for %s", async (status, words) => {
        await show({ status, note: null, date: "2026-10-16" });

        expect(container.textContent).toBe(words);
    });

    it("shows a note as plain text, never as markup", async () => {
        await show({ status: "confirmed", note: "<b>bold</b>", date: "2026-10-16" });

        expect(container.querySelector("b")).toBeNull();
        expect(container.textContent).toContain("<b>bold</b>");
    });
});
