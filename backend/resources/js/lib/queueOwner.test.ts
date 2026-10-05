import { describe, expect, it } from "vitest";
import { buildQueueRow, belongsTo, ownedBy } from "./queueOwner";

describe("buildQueueRow", () => {
    it("stamps the owner as a plain field beside the others", () => {
        const row = buildQueueRow({ id: "a", createdAt: "t" }, "7");

        expect(row).toEqual({ id: "a", createdAt: "t", synced: false, owner: "7" });
    });

    it("leaves the owner off when there is none", () => {
        expect(buildQueueRow({ id: "a" }, null)).not.toHaveProperty("owner");
    });
});

describe("belongsTo", () => {
    it("is true for the same owner", () => {
        expect(belongsTo({ owner: "7" }, "7")).toBe(true);
    });

    it("is false for a different owner", () => {
        expect(belongsTo({ owner: "7" }, "8")).toBe(false);
    });

    it("is true for an item with no owner", () => {
        expect(belongsTo({}, "8")).toBe(true);
    });

    it("is false for everything when the current user is unknown", () => {
        expect(belongsTo({ owner: "7" }, null)).toBe(false);
        expect(belongsTo({}, null)).toBe(false);
    });
});

describe("ownedBy", () => {
    it("keeps only the current user's and ownerless items, and leaves the list itself alone", () => {
        const list = [{ id: 1, owner: "7" }, { id: 2, owner: "8" }, { id: 3 }];
        const copy = structuredClone(list);

        expect(ownedBy(list, "7").map((item) => item.id)).toEqual([1, 3]);
        expect(list).toEqual(copy);
    });
});
