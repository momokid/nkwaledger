// @vitest-environment jsdom
import { beforeEach, describe, expect, it } from "vitest";
import "fake-indexeddb/auto";
import { getPinRecord, isPinAllowed, savePin, verifyPin } from "./pin";

beforeEach(() => {
    indexedDB = new IDBFactory();
});

describe("isPinAllowed", () => {
    it.each(["4826", "0000x", "123", "12345", "abcd", ""])("refuses %j (not 4 digits or not a PIN)", (pin) => {
        if (pin === "4826") {
            expect(isPinAllowed(pin)).toBe(true);

            return;
        }

        expect(isPinAllowed(pin)).toBe(false);
    });

    it.each(["1111", "0000", "9999", "1234", "2345", "0123", "6789", "4321", "9876", "3210"])("refuses the easy PIN %s", (pin) => {
        expect(isPinAllowed(pin)).toBe(false);
    });

    it.each(["1357", "2580", "4826", "1243", "7890", "1212"])("allows %s", (pin) => {
        expect(isPinAllowed(pin)).toBe(true);
    });
});

describe("the stored PIN", () => {
    it("keeps a random salt, a PBKDF2 hash of at least 210000 iterations, a counter and a locked flag, never the PIN", async () => {
        await savePin("7", "4826");
        const record = await getPinRecord("7");

        expect(record).toMatchObject({ userId: "7", attempts: 0, locked: false });
        expect(record!.iterations).toBeGreaterThanOrEqual(210000);
        expect(record!.salt.byteLength).toBeGreaterThanOrEqual(16);
        expect(record!.hash.byteLength).toBe(32);
        expect(Object.values(record!).some((value) => value === "4826" || value === 4826)).toBe(false);
    });

    it("uses a different salt every time", async () => {
        await savePin("7", "4826");
        await savePin("8", "4826");

        const [a, b] = [await getPinRecord("7"), await getPinRecord("8")];

        expect(Array.from(a!.salt)).not.toEqual(Array.from(b!.salt));
        expect(Array.from(a!.hash)).not.toEqual(Array.from(b!.hash));
    });
});

describe("verifyPin", () => {
    beforeEach(async () => {
        await savePin("7", "4826");
    });

    it("accepts the right PIN and resets the counter", async () => {
        expect(await verifyPin("7", "1357")).toBe("wrong");
        expect(await verifyPin("7", "1357")).toBe("wrong");
        expect((await getPinRecord("7"))!.attempts).toBe(2);

        expect(await verifyPin("7", "4826")).toBe("ok");
        expect((await getPinRecord("7"))!.attempts).toBe(0);
    });

    it("locks on the fifth wrong try, and the lock is stored", async () => {
        for (let i = 0; i < 4; i++) {
            expect(await verifyPin("7", "1357")).toBe("wrong");
        }

        expect(await verifyPin("7", "1357")).toBe("locked");
        expect(await getPinRecord("7")).toMatchObject({ attempts: 5, locked: true });
    });

    it("accepts no more tries once locked, even the right PIN", async () => {
        for (let i = 0; i < 5; i++) {
            await verifyPin("7", "1357");
        }

        expect(await verifyPin("7", "4826")).toBe("locked");
        expect((await getPinRecord("7"))!.locked).toBe(true);
    });

    it("does not let another user's PIN unlock this user", async () => {
        await savePin("8", "2580");

        expect(await verifyPin("7", "2580")).toBe("wrong");
        expect(await verifyPin("8", "2580")).toBe("ok");
    });

    it("says there is no PIN for a user who has none", async () => {
        expect(await getPinRecord("9")).toBeUndefined();
        expect(await verifyPin("9", "4826")).toBe("none");
    });
});
