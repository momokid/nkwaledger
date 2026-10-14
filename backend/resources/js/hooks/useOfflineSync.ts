import { usePage } from "@inertiajs/react";
import { useEffect, useState } from "react";
import { OFFLINE_SYNC_RAN_EVENT, syncOnce } from "@/lib/offlineSync";
import { PageProps } from "@/types";

const BACKGROUND_SYNC_TAG = "nkwa-offline-sync";

export { OFFLINE_SYNC_RAN_EVENT };

// registering here is a progressive enhancement only — it lets the browser wake
// this tab to flush the queue, but Safari/iOS has no Background Sync API at all,
// so the foreground paths below must (and do) work completely without it
async function registerBackgroundSync(): Promise<void> {
    if (!("serviceWorker" in navigator)) {
        return;
    }

    const registration = await navigator.serviceWorker
        .register("/sw.js")
        .catch(() => null);

    if (registration && "sync" in registration) {
        await (registration as ServiceWorkerRegistration & {
            sync: { register: (tag: string) => Promise<void> };
        }).sync.register(BACKGROUND_SYNC_TAG).catch(() => {});
    }
}

export default function useOfflineSync() {
    const [authExpired, setAuthExpired] = useState(false);
    const { auth } = usePage<PageProps>().props;
    const currentUser = auth?.user ? String(auth.user.id) : null;

    useEffect(() => {
        const attemptSync = () => {
            if (navigator.onLine) {
                void syncOnce(currentUser).then((outcome) => {
                    if (outcome) {
                        setAuthExpired(outcome.authExpired);
                    }
                });
            }
        };

        attemptSync();
        void registerBackgroundSync();

        const onVisible = () => {
            if (document.visibilityState === "visible") {
                attemptSync();
            }
        };

        const onWorkerMessage = (event: MessageEvent) => {
            if (event.data?.type === "NKWA_RUN_OFFLINE_SYNC") {
                attemptSync();
            }
        };

        window.addEventListener("online", attemptSync);
        document.addEventListener("visibilitychange", onVisible);
        navigator.serviceWorker?.addEventListener("message", onWorkerMessage);

        return () => {
            window.removeEventListener("online", attemptSync);
            document.removeEventListener("visibilitychange", onVisible);
            navigator.serviceWorker?.removeEventListener(
                "message",
                onWorkerMessage,
            );
        };
    }, [currentUser]);

    return authExpired;
}
