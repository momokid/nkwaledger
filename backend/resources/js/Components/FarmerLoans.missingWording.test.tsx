// @vitest-environment jsdom
import { act } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

let mockErrors: Record<string, string> = {};
const post = vi.fn();
const visit = vi.fn();

vi.mock("@/Layouts/AuthenticatedLayout", () => ({
    useTheme: () => ({ dark: false }),
}));

vi.mock("@inertiajs/react", () => ({
    usePage: () => ({ props: { errors: mockErrors } }),
    router: { visit: (...args: unknown[]) => visit(...args), post: (...args: unknown[]) => post(...args) },
}));


let root: Root;
let container: HTMLElement;

beforeEach(() => {
    (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    container = document.createElement("div");
    document.body.appendChild(container);
    root = createRoot(container);
});

afterEach(async () => {
    await act(async () => root.unmount());
    container.remove();
    vi.unstubAllEnvs();
    vi.doUnmock("../../wording.json");
    vi.resetModules();
});

describe("A page whose text map is loaded with a key missing", () => {
    it("still shows the page, with the plain sentence in place of the missing words", async () => {
        const { default: map } = await import("../../wording.json");
        const { "repayment.rejected": _gone, ...rest } = map as Record<string, string>;
        vi.resetModules();
        vi.doMock("../../wording.json", () => ({ default: rest }));
        vi.stubEnv("PROD", true);
        const error = vi.spyOn(console, "error").mockImplementation(() => {});

        const { default: FarmerLoans } = await import("./FarmerLoans");
        const loans = [{ row: "entry", uuid: "e-1", lender: "Sika Microfinance", amount: 50000, balance: null, status: "rejected", kind: "repayment" }];
        await act(async () => root.render(<FarmerLoans loans={loans as never} />));

        expect(container.textContent).toContain("Sika Microfinance");
        expect(container.textContent).toContain("Something went wrong. Please try again.");
        expect(error).toHaveBeenCalled();

        error.mockRestore();
    });
});
