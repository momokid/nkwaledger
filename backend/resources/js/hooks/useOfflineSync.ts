import { useEffect } from "react";
import { runSync } from "@/lib/offlineSync";

const BACKGROUND_SYNC_TAG = "nkwa-offline-sync";

// any page that shows queued/needs-attention entries listens for this,
// regardless of what actually triggered the sync attempt
export const OFFLINE_SYNC_RAN_EVENT = "nkwa:offline-sync-ran";

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
    useEffect(() => {
        const attemptSync = () => {
            if (navigator.onLine) {
                void runSync().finally(() =>
                    window.dispatchEvent(new Event(OFFLINE_SYNC_RAN_EVENT)),
                );
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
    }, []);
}
