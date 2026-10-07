import "../css/app.css";
import "./bootstrap";

import { createInertiaApp, router } from "@inertiajs/react";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { createRoot, hydrateRoot } from "react-dom/client";
import DataCostDialog from "@/Components/DataCostDialog";
import PinGate from "@/Components/PinGate";
import { installContactTracking } from "@/lib/serverContact";

const appName = import.meta.env.VITE_APP_NAME || "NkwaLedger";

// every successful answer from our own server counts as contact, for the offline lock
installContactTracking();

// the worker keeps the static shell and the offline page; page loads and data always go to the network
if (typeof window !== "undefined" && "serviceWorker" in navigator) {
    window.addEventListener("load", () => {
        void navigator.serviceWorker.register("/sw.js").catch(() => {});
    });
}

window.addEventListener("pageshow", (e: PageTransitionEvent) => {
    if (e.persisted) {
        const state = window.history.state;
        if (state?.loggedOut) {
            window.location.replace("/login");
        } else {
            window.location.reload();
        }
    }
});

// most pages still need a live connection; without this, a failed navigation while
// offline throws an uncaught console error and leaves the farmer with nothing
router.on("exception", (event) => {
    if (!navigator.onLine) {
        event.preventDefault();
        window.dispatchEvent(new CustomEvent("inertia-offline-blocked"));
    }
});

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob("./Pages/**/*.tsx"),
        ),
    setup({ el, App, props }) {
        if (import.meta.env.SSR) {
            hydrateRoot(el, <App {...props} />);
            return;
        }
        createRoot(el).render(
            <App {...props}>
                {({ Component, props: page, key }) => (
                    <PinGate user={(page as { auth?: { user?: { id: number } | null } }).auth?.user}>
                        <DataCostDialog />
                        <Component key={key} {...page} />
                    </PinGate>
                )}
            </App>,
        );
    },
    progress: {
        color: "#1D9E75",
    },
});
