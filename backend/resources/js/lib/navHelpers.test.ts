import { describe, expect, it } from "vitest";
import { splitMobileNav } from "./navHelpers";

type Item = { label: string; href: string; ready: boolean; children?: Item[] };
const item = (label: string, extra: Partial<Item> = {}): Item => ({ label, href: `/${label}`, ready: true, ...extra });

describe("splitMobileNav", () => {
    it("shows four items and puts the rest of main, then tools, under More", () => {
        const main = ["a", "b", "c", "d", "e", "f"].map((l) => item(l));
        const { bar, more } = splitMobileNav(main, [item("t1")]);

        expect(bar.map((i) => i.label)).toEqual(["a", "b", "c", "d"]);
        expect(more.map((i) => i.label)).toEqual(["e", "f", "t1"]);
    });

    it("lists a group's sub-items instead of the group", () => {
        const group = item("g", { href: "#", children: [item("g1"), item("g2")] });
        const { more } = splitMobileNav([...["a", "b", "c", "d"].map((l) => item(l)), group], []);

        expect(more.map((i) => i.label)).toEqual(["g1", "g2"]);
    });

    it("has no More when everything fits", () => {
        const { more } = splitMobileNav([item("a")], []);

        expect(more).toEqual([]);
    });
});
