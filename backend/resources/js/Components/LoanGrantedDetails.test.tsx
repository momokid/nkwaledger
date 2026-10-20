// @vitest-environment jsdom
import { act } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import LoanGrantedDetails from "./LoanGrantedDetails";

vi.mock("@/Layouts/AuthenticatedLayout", () => ({
    default: ({ children }: { children: unknown }) => children,
    useTheme: () => ({ dark: false }),
}));

let root: Root;
let container: HTMLElement;

const show = (interest: number | null) => act(async () => root.render(<LoanGrantedDetails grantedOn="2026-09-01" amount={100000} interest={interest} />));

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

describe("Loan Granted Details", () => {
    it("has its own heading and its own columns", async () => {
        await show(20000);

        expect(container.textContent).toContain("Loan Granted Details");
        expect(Array.from(container.querySelectorAll("th")).map((cell) => cell.textContent)).toEqual(["Date Granted", "Amount Granted", "Interest Agreed"]);
    });

    it("shows the day, the amount and the interest agreed", async () => {
        await show(20000);

        const cells = Array.from(container.querySelectorAll("td")).map((cell) => cell.textContent);

        expect(cells[0]).toContain("01/09/2026");
        expect(cells[1]).toBe("GHS 1,000.00");
        expect(cells[2]).toBe("GHS 200.00");
    });

    it("leaves the interest blank when none was agreed", async () => {
        await show(null);

        expect(Array.from(container.querySelectorAll("td"))[2].textContent).toBe("");
    });
});
