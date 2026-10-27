// @vitest-environment jsdom
import { act } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

(globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;

const h = vi.hoisted(() => ({ post: vi.fn() }));

vi.mock("axios", () => ({ default: { post: h.post } }));

import AssistantChat from "./AssistantChat";

describe("AssistantChat", () => {
    let root: Root;
    let host: HTMLDivElement;

    beforeEach(() => {
        h.post.mockReset();
        host = document.createElement("div");
        document.body.appendChild(host);
        root = createRoot(host);
        act(() => root.render(<AssistantChat />));
    });

    afterEach(() => {
        act(() => root.unmount());
        host.remove();
    });

    const button = (label: string) => [...host.querySelectorAll("button")].find((b) => b.textContent === label);
    const box = () => host.querySelector("textarea") as HTMLTextAreaElement;

    const type = (value: string) => {
        const setter = Object.getOwnPropertyDescriptor(HTMLTextAreaElement.prototype, "value")!.set!;
        act(() => {
            setter.call(box(), value);
            box().dispatchEvent(new Event("input", { bubbles: true }));
        });
    };

    const send = async () => {
        await act(async () => {
            button("Send")!.click();
        });
    };

    it("starts closed and opens with the approved texts", () => {
        expect(host.textContent).toContain("Ask for help");
        expect(host.textContent).not.toContain("Help assistant");

        act(() => button("Ask for help")!.click());

        expect(host.textContent).toContain("Help assistant");
        expect(host.textContent).toContain("Hello! Ask me how to use NkwaLedger. For example: How do I add a farm?");
        expect(host.textContent).toContain(
            "Answers come from an AI. Your question is sent to an AI provider. Never type your PIN or password.",
        );
        expect(box().placeholder).toBe("Type your question");
    });

    it("closes", () => {
        act(() => button("Ask for help")!.click());
        act(() => button("Close")!.click());
        expect(host.textContent).not.toContain("Help assistant");
    });

    it("keeps Send off for an empty or too long question", () => {
        act(() => button("Ask for help")!.click());
        expect(button("Send")!.disabled).toBe(true);
        type("How do I add a farm?");
        expect(button("Send")!.disabled).toBe(false);
        type("a".repeat(501));
        expect(button("Send")!.disabled).toBe(true);
    });

    it("shows the question and the reply", async () => {
        h.post.mockResolvedValue({ data: { reply: "Tap My Farm." } });
        act(() => button("Ask for help")!.click());
        type("How do I add a farm?");
        await send();

        expect(h.post).toHaveBeenCalledWith("/assistant", { message: "How do I add a farm?" });
        expect(host.textContent).toContain("How do I add a farm?");
        expect(host.textContent).toContain("Tap My Farm.");
        expect(box().value).toBe("");
    });

    it("turns a reply link into a link to the page", async () => {
        h.post.mockResolvedValue({ data: { reply: "Open /my-farm to add one." } });
        act(() => button("Ask for help")!.click());
        type("Where?");
        await send();

        expect(host.querySelector('a[href="/my-farm"]')).not.toBeNull();
    });

    it("tells the user to call when the daily limit is reached", async () => {
        h.post.mockRejectedValue({ response: { status: 429, data: { support_phone: "0577915555" } } });
        act(() => button("Ask for help")!.click());
        type("Hi");
        await send();

        expect(host.textContent).toContain(
            "You have used your 50 questions for today. Please call or WhatsApp 0577915555, 24 hours.",
        );
    });

    it("tells the user to call when the AI cannot answer", async () => {
        h.post.mockRejectedValue({ response: { status: 503, data: { support_phone: "0577915555" } } });
        act(() => button("Ask for help")!.click());
        type("Hi");
        await send();

        expect(host.textContent).toContain(
            "Sorry, I cannot answer right now. Please call or WhatsApp 0577915555, 24 hours.",
        );
    });
});
