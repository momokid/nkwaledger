// @vitest-environment jsdom
import { act } from "react";
import { createRoot, Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import AuthenticatedLayout from "./AuthenticatedLayout";

vi.mock("@inertiajs/react", async () => {
    const React = await import("react");

    return {
        Head: () => null,
        router: { visit: vi.fn() },
        Link: ({ href, children }: { href: string; children: React.ReactNode }) => React.createElement("a", { href }, children),
        usePage: () => ({ props: { auth: { user: { roles: ["agent"], first_name: "Ama", surname: "Boateng" } }, features: {} } }),
    };
});
vi.mock("@/hooks/useIsVerified", () => ({ default: () => true }));
vi.mock("@/hooks/useOfflineSync", () => ({ default: () => false }));
vi.mock("@/hooks/useSafeLogout", () => ({ default: () => ({ logout: vi.fn(), blocked: false }) }));
vi.mock("@/Components/NotificationBell", () => ({ default: () => null }));
vi.mock("@/Components/ConnectivityIndicator", () => ({ default: () => null }));
vi.mock("@/Components/LogoutBlockedBanner", () => ({ default: () => null }));
vi.mock("@/Components/FlashMessages", () => ({ default: () => null }));
vi.mock("@/Components/SessionEndedBanner", () => ({ default: () => null }));
vi.mock("@/Components/OfflineNavigationNotice", () => ({ default: () => null }));
vi.mock("@/Components/VerificationGate", () => ({ default: ({ children }: { children: unknown }) => children }));

let root: Root;
let container: HTMLElement;

beforeEach(async () => {
    (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    container = document.createElement("div");
    document.body.appendChild(container);
    root = createRoot(container);
    await act(async () => root.render(<AuthenticatedLayout title="x">body</AuthenticatedLayout>));
});

afterEach(async () => {
    await act(async () => root.unmount());
    container.remove();
});

describe("agent menu", () => {
    it("has a Credit group holding View Loans and New Loan, like the admin menu", async () => {
        const credit = [...container.querySelectorAll("button")].find((b) => b.textContent === "Credit");
        expect(credit).toBeTruthy();

        await act(async () => credit!.click());

        expect(container.querySelector('a[href="/agent/loans"]')?.textContent).toBe("View Loans");
        expect(container.querySelector('a[href="/agent/loans/new"]')?.textContent).toBe("New Loan");
    });
});
