import { afterEach, describe, expect, it, vi } from "vitest";
import { wording, fill } from "./wording";

describe("wording", () => {
    it("gives the approved text for a key", () => {
        expect(wording("repayment.date")).toBe("Repayment Date");
        expect(wording("lender.type.credit_financial_institution")).toBe("Credit Financial Institution");
    });

    it("fills placeholders without reading a placeholder inside a filled value", () => {
        expect(fill("repayment.sms", { amount: "250.00", lender: "A {code}", code: "483920" })).toBe(
            "Your agent recorded a repayment of GHS 250.00 to A {code}. Code: 483920",
        );
    });

    it("refuses a key that has no wording", () => {
        expect(() => wording("repayment.nope")).toThrow();
    });
});

describe("wording in production", () => {
    afterEach(() => vi.unstubAllEnvs());

    it("gives the plain sentence for a missing key and reports it once in the console", () => {
        vi.stubEnv("PROD", true);
        const error = vi.spyOn(console, "error").mockImplementation(() => {});

        expect(wording("repayment.nope")).toBe("Something went wrong. Please try again.");
        expect(fill("repayment.nope", { amount: "1" })).toBe("Something went wrong. Please try again.");
        expect(error).toHaveBeenCalledTimes(1);

        error.mockRestore();
    });
});
