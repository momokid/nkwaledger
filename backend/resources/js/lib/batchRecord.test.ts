import { describe, expect, it } from "vitest";
import { buildBatchRecord } from "./batchRecord";

const FARMER = "5b0f9f4e-0c3e-4a67-9a52-1e0f3f2a1111";

const data = {
    transaction_template_id: "12",
    amount: "150.50",
    transaction_date: "2026-03-01",
    farm_unit_id: "4",
    settlement_account_id: "9",
    is_credit: "0",
    narration: "feed",
};

describe("buildBatchRecord", () => {
    it("holds exactly what the server needs, marked with the shape version", () => {
        const record = buildBatchRecord(FARMER, data, "3");

        expect(record).toMatchObject({
            shape: 2,
            template: 12,
            farmer: FARMER,
            amount: "150.50",
            event_date: "2026-03-01",
            farm_unit_id: 4,
            settlement_account_id: 9,
            is_credit: false,
            quantity: "3",
            narration: "feed",
        });
        expect(record.uuid).toMatch(/^[0-9a-f-]{36}$/);
        expect(Number.isNaN(Date.parse(record.device_created_at))).toBe(false);
    });

    it("leaves out what was not entered and marks credit", () => {
        const record = buildBatchRecord(
            FARMER,
            { ...data, farm_unit_id: "", settlement_account_id: "", is_credit: "1", narration: "" },
            "",
        );

        expect(record).not.toHaveProperty("farm_unit_id");
        expect(record).not.toHaveProperty("settlement_account_id");
        expect(record).not.toHaveProperty("quantity");
        expect(record).not.toHaveProperty("narration");
        expect(record.is_credit).toBe(true);
    });

    it("gives every record its own uuid", () => {
        expect(buildBatchRecord(FARMER, data, "").uuid).not.toBe(buildBatchRecord(FARMER, data, "").uuid);
    });
});
