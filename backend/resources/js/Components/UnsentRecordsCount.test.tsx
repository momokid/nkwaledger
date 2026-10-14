// @vitest-environment jsdom
import { act } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import "fake-indexeddb/auto";
import { enqueue, recordFailedAttempt } from "@/lib/offlineStore";
import { buildBatchRecord } from "@/lib/batchRecord";
import { withQueueLock } from "@/lib/queueLock";
import UnsentRecordsCount from "./UnsentRecordsCount";

vi.mock("@/Layouts/AuthenticatedLayout", () => ({ useTheme: () => ({ dark: false }) }));
vi.mock("@inertiajs/react", () => ({
    usePage: () => ({ props: { auth: { user: { id: 7 } } } }),
    Link: ({ href, children }: { href: string; children: unknown }) => <a href={href}>{children as never}</a>,
}));

const FARMER = "5b0f9f4e-0c3e-4a67-9a52-1e0f3f2a1111";
let root: Root;
let container: HTMLElement;

const record = () => buildBatchRecord(FARMER, { transaction_template_id: "1", amount: "10", transaction_date: "2026-03-01" }, "", crypto.randomUUID());

async function queue(owner: string, stuck: boolean) {
    const id = await enqueue(record(), owner);

    if (stuck) {
        for (let i = 0; i < 5; i++) await recordFailedAttempt(id);
    }
}

const show = async () => {
    await act(async () => root.render(<UnsentRecordsCount />));
    // the phone's storage answers a moment after the page draws
    await act(async () => new Promise((resolve) => setTimeout(resolve, 80)));
};

beforeEach(() => {
    (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    indexedDB = new IDBFactory();
    container = document.createElement("div");
    document.body.appendChild(container);
    root = createRoot(container);
});

afterEach(async () => {
    await withQueueLock(async () => {});
    await act(async () => root.unmount());
    container.remove();
});

describe("Records not sent yet", () => {
    it("is hidden when nothing is stuck", async () => {
        await show();

        expect(container.textContent).toBe("");
    });

    it("is hidden when records are only waiting to send, not stuck", async () => {
        await queue("7", false);
        await show();

        expect(container.textContent).toBe("");
    });

    it("counts only this farmer's stuck records, labelled, and links to My Records", async () => {
        await queue("7", true);
        await queue("7", true);
        await queue("7", false);
        await queue("8", true);
        await show();

        expect(container.textContent).toContain("Records not sent yet");
        expect(container.querySelector('[data-testid="unsent-count"]')?.textContent).toBe("2");
        expect(container.querySelector("a")?.getAttribute("href")).toBe("/my-records");
    });

    it("follows a sync that parks or clears records", async () => {
        await show();
        expect(container.textContent).toBe("");

        await queue("7", true);
        await act(async () => window.dispatchEvent(new Event("nkwa:offline-sync-ran")));
        await act(async () => {});

        expect(container.querySelector('[data-testid="unsent-count"]')?.textContent).toBe("1");
    });
});
