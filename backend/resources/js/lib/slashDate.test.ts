import { describe, expect, it } from "vitest";
import { slashDate } from "./format";

describe("slashDate", () => {
    it("writes a date as DD/MM/YYYY", () => {
        expect(slashDate("2026-10-18")).toBe("18/10/2026");
    });

    it("ignores a time after the date, with no timezone shift", () => {
        expect(slashDate("2026-10-18T23:30:00.000000Z")).toBe("18/10/2026");
    });

    it("shows a dash when there is no date", () => {
        expect(slashDate(null)).toBe("—");
        expect(slashDate("nonsense")).toBe("—");
    });
});
