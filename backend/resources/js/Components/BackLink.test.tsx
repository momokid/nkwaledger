import { describe, expect, it, vi } from "vitest";
import { handleBackClick, shouldRenderFallbackLink } from "./BackLink";

describe("shouldRenderFallbackLink", () => {
    it("renders the fallback link when there is no real history to go back to", () => {
        expect(shouldRenderFallbackLink(1)).toBe(true);
        expect(shouldRenderFallbackLink(0)).toBe(true);
    });

    it("does not render the fallback link when history.back() has somewhere to go", () => {
        expect(shouldRenderFallbackLink(2)).toBe(false);
    });
});

describe("handleBackClick", () => {
    it("calls history.back() when history.length > 1", () => {
        const goBack = vi.fn();

        handleBackClick(2, goBack);

        expect(goBack).toHaveBeenCalledOnce();
    });

    it("does not call history.back() when history.length <= 1", () => {
        const goBack = vi.fn();

        handleBackClick(1, goBack);

        expect(goBack).not.toHaveBeenCalled();
    });
});
