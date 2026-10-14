import { describe, expect, it } from "vitest";
import { createRecordKey } from "./recordKey";

describe("createRecordKey", () => {
    it("keeps the same uuid until it is renewed", () => {
        const key = createRecordKey();

        expect(key.current()).toMatch(/^[0-9a-f-]{36}$/);
        expect(key.current()).toBe(key.current());
    });

    it("gives a new uuid after renew", () => {
        const key = createRecordKey();
        const first = key.current();

        key.renew();

        expect(key.current()).not.toBe(first);
    });
});
