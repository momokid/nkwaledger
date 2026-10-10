import { describe, expect, it } from "vitest";
import { LOCK_AFTER_HIDDEN_MS, shouldLock } from "./idleLock";

const at = (wall: number, mono: number, origin = 1) => ({ wall, mono, origin });

describe("shouldLock", () => {
    it("is named once, at 60 seconds", () => {
        expect(LOCK_AFTER_HIDDEN_MS).toBe(60_000);
    });

    it("keeps the app open just under the limit and locks at it", () => {
        expect(shouldLock(at(1_000, 0), at(60_999, 59_999))).toBe(false);
        expect(shouldLock(at(1_000, 0), at(61_000, 60_000))).toBe(true);
    });

    it("locks when the wall clock moved backward, however little time passed", () => {
        expect(shouldLock(at(1_000, 0), at(999, 5))).toBe(true);
    });

    it("locks when the wall clock stood still but the monotonic one ran on", () => {
        expect(shouldLock(at(1_000, 0), at(1_000, 60_000))).toBe(true);
    });

    it("locks when the wall clock jumped forward", () => {
        expect(shouldLock(at(1_000, 0), at(1_000_000, 10))).toBe(true);
    });

    it("trusts only the wall clock once the page has been reloaded, since the monotonic clock restarted", () => {
        expect(shouldLock(at(1_000, 500_000, 1), at(30_000, 100, 2))).toBe(false);
        expect(shouldLock(at(1_000, 500_000, 1), at(61_000, 100, 2))).toBe(true);
    });
});
